<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Loader;

use EXSyst\Component\Swagger\Operation;
use EXSyst\Component\Swagger\Parameter;
use EXSyst\Component\Swagger\Path;
use EXSyst\Component\Swagger\Schema as EXSystSchema;
use OpenApi\Annotations\Schema as OpenApiSchema;
use EXSyst\Component\Swagger\Swagger;
use Linkin\Bundle\SwaggerResolverBundle\Collection\SchemaDefinitionCollection;
use Linkin\Bundle\SwaggerResolverBundle\Collection\SchemaOperationCollection;
use Linkin\Bundle\SwaggerResolverBundle\Exception\PathNotFoundException;
use Linkin\Bundle\SwaggerResolverBundle\Merger\OperationParameterMerger;
use OpenApi\Analysis;
use OpenApi\Annotations\Components;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\PathItem;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\RouterInterface;
use function end;
use function explode;

abstract class AbstractSwaggerConfigurationLoader implements SwaggerConfigurationLoaderInterface
{
    /**
     * @var SchemaDefinitionCollection
     */
    private $definitionCollection;

    /**
     * @var SchemaOperationCollection
     */
    private $operationCollection;

    /**
     * @var array
     */
    private $mapPathToRouteName;

    /**
     * @var OperationParameterMerger
     */
    private $parameterMerger;

    /**
     * @var RouterInterface $router
     */
    private $router;

    /**
     * @param OperationParameterMerger $parameterMerger
     * @param RouterInterface $router
     */
    public function __construct(OperationParameterMerger $parameterMerger, RouterInterface $router)
    {
        $this->parameterMerger = $parameterMerger;
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaDefinitionCollection(): SchemaDefinitionCollection
    {
        if (!$this->definitionCollection) {
            $this->registerCollections();
        }

        return $this->definitionCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchemaOperationCollection(): SchemaOperationCollection
    {
        if (!$this->operationCollection) {
            $this->registerCollections();
        }

        return $this->operationCollection;
    }

    /**
     * Load full configuration and returns Swagger object
     *
     * @return OpenApi
     */
    abstract protected function loadConfiguration(): OpenApi;

    /**
     * Add file resources for swagger definitions
     *
     * @param SchemaDefinitionCollection $definitionCollection
     */
    abstract protected function registerDefinitionResources(SchemaDefinitionCollection $definitionCollection): void;

    /**
     * Add file resources for swagger operations
     *
     * @param SchemaOperationCollection $operationCollection
     */
    abstract protected function registerOperationResources(SchemaOperationCollection $operationCollection): void;

    /**
     * @return RouterInterface
     */
    protected function getRouter(): RouterInterface
    {
        return $this->router;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getRouteNameByPath(string $path): string
    {
        if (empty($this->mapPathToRouteName)) {
            foreach ($this->router->getRouteCollection() as $routeName => $route) {
                foreach ($route->getMethods() as $method) {
                    $this->mapPathToRouteName[sprintf('%s %s', strtolower($method), $route->getPath())]
                        = $routeName
                    ;
                }
            }
        }

        $route = $this->mapPathToRouteName[$path] ?? null;

        if (!$route) {
            throw new PathNotFoundException($path);
        }

        return $this->mapPathToRouteName[$path];
    }

    /**
     * Register collection according to loaded Swagger object
     */
    private function registerCollections(): void
    {
        $swaggerConfiguration = $this->loadConfiguration();

        $definitionCollection = new SchemaDefinitionCollection();
        $operationCollection = new SchemaOperationCollection();

        /** @var \OpenApi\Annotations\Schema $schema */
        foreach ($swaggerConfiguration->components->schemas as $schema) {
            $definitionCollection->addSchema($schema->schema, $this->serializeOpenApiSchemaToEXSystSchema($schema));
        }

//        foreach ($swaggerConfiguration->get`Definitions()->getIterator() as $definitionName => $definition) {
//            $definitionCollection->addSchema($definitionName, $definition);
//        }
//
//        $this->registerDefinitionResources($definitionCollection);
//
//        /** @var Path $pathObject */
//        foreach ($swaggerConfiguration->getPaths()->getIterator() as $path => $pathObject) {
//            /** @var Operation $operation */
//            foreach ($pathObject->getOperations() as $method => $operation) {
//                $routeName = $this->getRouteNameByPath(sprintf('%s %s', strtolower($method), $path));
//                $schema = $this->parameterMerger->merge($operation, $swaggerConfiguration->getDefinitions());
//                $operationCollection->addSchema($routeName, $method, $schema);
//
//                /** @var Parameter $parameter */
//                foreach ($operation->getParameters()->getIterator() as $name => $parameter) {
//                    $ref = $parameter->getSchema()->getRef();
//
//                    if (!$ref) {
//                        continue;
//                    }
//
//                    $explodedName = explode('/', $ref);
//                    $definitionName = end($explodedName);
//
//                    foreach ($definitionCollection->getSchemaResources($definitionName) as $fileResource) {
//                        $operationCollection->addSchemaResource($routeName, $fileResource);
//                    }
//                }
//            }
//        }
//
//        $this->registerOperationResources($operationCollection);
//
//        $this->definitionCollection = $definitionCollection;
//        $this->operationCollection = $operationCollection;

        $this->registerDefinitionResources($definitionCollection);

//        $operationCollection->addSchema('/', 'get', new Schema([]));
        $operationCollection->addSchemaResource('/', new FileResource(''));
        $this->registerOperationResources($operationCollection);

        $this->definitionCollection = $definitionCollection;
        $this->operationCollection = $operationCollection;
    }

    private function serializeOpenApiSchemaToEXSystSchema(OpenApiSchema $schema): EXSystSchema
    {
        $eXSystSchema = new EXSystSchema();

        $eXSystSchema->setDiscriminator($schema->discriminator);
        $eXSystSchema->setReadOnly($schema->readOnly);
        $eXSystSchema->setTitle($schema->title);
        $eXSystSchema->setExample($schema->example);
        $eXSystSchema->setRequired($schema->required);

        return $eXSystSchema;
    }
}
