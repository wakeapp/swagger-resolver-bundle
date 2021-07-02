<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Configuration;

use JsonException;
use Linkin\Bundle\SwaggerResolverBundle\Loader\SwaggerConfigurationLoaderInterface;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function json_decode;
use function md5;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const PHP_SAPI;

class SwaggerCachedConfiguration extends SwaggerConfiguration implements WarmableInterface
{
    private const CACHE_KEY = 'linkin_swagger_resolver';

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var ConfigCacheFactoryInterface
     */
    private $configCacheFactory;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var SwaggerConfigurationLoaderInterface
     */
    private $loader;

    /**
     * @var FilesystemAdapter
     */
    private $cacheSchema;

    /**
     * @param SwaggerConfigurationLoaderInterface $loader
     * @param string $cacheDir
     * @param bool $debug
     */
    public function __construct(SwaggerConfigurationLoaderInterface $loader, string $cacheDir, bool $debug)
    {
        parent::__construct($loader);

        $this->cacheDir = $cacheDir . '/' . self::CACHE_KEY;
        $this->debug = $debug;
        $this->loader = $loader;
        $this->cacheSchema = new FilesystemAdapter(self::CACHE_KEY, 0, $this->cacheDir);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function getDefinition(string $definitionName): Schema
    {
        $key = sprintf('%s/definitions/%s_%s.php', $this->cacheDir, $definitionName, md5($definitionName));

        $beta = 1.0;
        $schema = $this->cacheSchema->get(md5($key), function (ItemInterface $item) use ($key, $definitionName) {
            $item->expiresAfter(3600);

            return $this->getDumpDefinition($key, $definitionName);
        }, $beta);

        return $schema;
    }

    private function getDumpDefinition(string $key, string $definitionName): Schema
    {
        $cache = $this->getConfigCacheFactory()->cache(
            $key,
            function (ConfigCacheInterface $cache) use ($definitionName) {
                $this->dumpDefinition($definitionName, $cache);
            }
        );

        return include $cache->getPath();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function getPathDefinition(string $routeName, string $method): Schema
    {
        $key = sprintf('%s/paths/%s/%s_%s.php', $this->cacheDir, $routeName, $method, md5($routeName . $method));

        $beta = 1.0;
        $schema = $this->cacheSchema->get(md5($key), function (ItemInterface $item) use ($key, $routeName, $method) {
            $item->expiresAfter(3600);

            return $this->getDumpPathDefinition($key, $routeName, $method);
        }, $beta);

        return $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function getDumpPathDefinition(string $key, string $routeName, string $method): Schema
    {
        $cache = $this->getConfigCacheFactory()->cache(
            $key,
            function (ConfigCacheInterface $cache) use ($routeName, $method) {
                $this->dumpOperation($routeName, $method, $cache);
            }
        );

        return include $cache->getPath();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function warmUp($cacheDir): void
    {
        $definitionWithoutResources = [];
        $definitionCollection = $this->loader->getSchemaDefinitionCollection();

        foreach ($definitionCollection->getIterator() as $definitionName => $definition) {
            $this->getDefinition($definitionName);

            if (empty($definitionCollection->getSchemaResources($definitionName))) {
                $definitionWithoutResources[$definitionName] = $definitionName;
            }
        }

        $operationCollection = $this->loader->getSchemaOperationCollection();

        foreach ($operationCollection as $routeName => $methodList) {
            foreach ($methodList as $method => $operation) {
                $this->getPathDefinition($routeName, $method);
            }

            if (empty($operationCollection->getSchemaResources($routeName))) {
                $definitionWithoutResources[$routeName] = $routeName;
            }
        }

        if ($definitionWithoutResources && PHP_SAPI === 'cli') {
            $this->displayConsoleNote(
                'LinkinSwaggerResolverBundle can\'t find source files for next definitions to auto-warm up cache:',
                true
            );

            foreach ($definitionWithoutResources as $definitionName) {
                $this->displayConsoleNote($definitionName, false);
            }

            echo "\n";
        }
    }

    /**
     * @param string $message
     * @param bool $firstLine
     */
    private function displayConsoleNote(string $message, bool $firstLine): void
    {
        $message = $firstLine ? sprintf('[NOTE] %s', $message) : sprintf('       %s', $message);
        $message = sprintf("\e[33m ! %s \e[39m\n", $message);

        echo $firstLine ? "\n" . $message : $message;
    }

    /**
     * @param string $definitionName
     * @param ConfigCacheInterface $cache
     * @throws JsonException
     */
    private function dumpDefinition(string $definitionName, ConfigCacheInterface $cache): void
    {
        $definition = parent::getDefinition($definitionName);

        $resources = $this->loader->getSchemaDefinitionCollection()->getSchemaResources($definitionName);

        $this->dumpSchema($definition, $resources, $cache);
    }

    /**
     * @param string $routeName
     * @param string $method
     * @param ConfigCacheInterface $cache
     * @throws JsonException
     */
    private function dumpOperation(string $routeName, string $method, ConfigCacheInterface $cache): void
    {
        $definition = parent::getPathDefinition($routeName, $method);

        $resources = $this->loader->getSchemaOperationCollection()->getSchemaResources($routeName);

        $this->dumpSchema($definition, $resources, $cache);
    }

    /**
     * @param Schema $schema
     * @param FileResource[] $resources
     * @param ConfigCacheInterface $cache
     * @throws JsonException
     */
    private function dumpSchema(Schema $schema, array $resources, ConfigCacheInterface $cache): void
    {
        $template = <<<EOF
<?php

declare(strict_types=1);

use OpenApi\Annotations\Schema;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Parameter;

\$propertyObjectList = [];
\$propertyList = %s;
\$parameterList = %s;

foreach (\$propertyList as \$property) {
\$propertyObjectList['properties'][] = new Property(\$property);
}

foreach (\$parameterList as \$name => \$parameter) {
\$parameterSchema = new Schema(\$parameter['schema']);
unset(\$parameter['schema']);

\$propertyObjectList['properties'][\$name] = new Parameter(\$parameter + ['schema' => \$parameterSchema]);
}

\$arguments = %s + \$propertyObjectList;
return new Schema(\$arguments);

EOF;

        $propertyList = [];
        $parameterList = [];

        foreach ($schema->properties as $property) {
            if ($property instanceof Property) {
                $propertyList[$property->property] = json_decode($property->toJson(), true, 512, JSON_THROW_ON_ERROR);
            }

            if ($property instanceof Parameter) {
                $parameterList[$property->name] = json_decode($property->toJson(), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        $schema->properties = Generator::UNDEFINED;
        $actualPropertyList = [];

        foreach ($propertyList as $name => $property) {
            if (isset($property['$ref'])) {
                $actualPropertyList[] = ['ref' => $property['$ref'], 'property' => $name];

                continue;
            }

            $actualPropertyList[] = $property;
        }

        // to avoid problem with unexpected stdClass
        $definitionAsArray = json_decode($schema->toJson(), true, 512, JSON_THROW_ON_ERROR);

        $definitionExport = var_export($definitionAsArray, true);
        $propertiesExport = var_export($actualPropertyList, true);
        $parametersExport = var_export($parameterList, true);

        $cache->write(sprintf($template, $propertiesExport, $parametersExport, $definitionExport), $resources);
    }

    /**
     * @return ConfigCacheFactoryInterface
     */
    private function getConfigCacheFactory(): ConfigCacheFactoryInterface
    {
        if (!$this->configCacheFactory) {
            $this->configCacheFactory = new ConfigCacheFactory($this->debug);
        }

        return $this->configCacheFactory;
    }
}