<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Loader;

use EXSyst\Component\Swagger\Collections\Definitions;
use EXSyst\Component\Swagger\Operation as EXSystOperation;
use OpenApi\Annotations\Operation as OpenApiOperation;
use EXSyst\Component\Swagger\Parameter;
use EXSyst\Component\Swagger\Path;
use EXSyst\Component\Swagger\Schema as EXSystSchema;
use OpenApi\Annotations\Schema;
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
    private const SCHEMA_UNDEFINED = '@OA\Generator::UNDEFINED🙈';

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

        $methods = ['get', 'post', 'put', 'delete', 'options', 'patch'];

        /** @var \OpenApi\Annotations\Schema $schema */
        foreach ($swaggerConfiguration->components->schemas as $schema) {
            $definitionCollection->addSchema($schema->schema, $this->serializeOpenApiSchemaToEXSystSchema($schema));
        }

        /** @var PathItem $pathObject */
        foreach ($swaggerConfiguration->paths as $pathObject) {
            $path = $pathObject->path;

            /** @var OpenApiOperation $operation */
            foreach ($pathObject as $method => $operation) {
                if (array_search($method, $methods) && $operation !== self::SCHEMA_UNDEFINED) {
                    $eXSystOperation = $this->serializeOpenApiOperationToEXSystOperation($operation);
                    $routeName = $this->getRouteNameByPath(sprintf('%s %s', strtolower($method), $path));
                    $schema = $this->parameterMerger->merge($eXSystOperation, new Definitions());
                    $operationCollection->addSchema($routeName, $method, $schema);

                    /** @var Parameter $parameter */
                    foreach ($eXSystOperation->getParameters()->getIterator() as $name => $parameter) {
                        $ref = $parameter->getSchema()->getRef();

                        if (!$ref) {
                            continue;
                        }

                        $explodedName = explode('/', $ref);
                        $definitionName = end($explodedName);

                        foreach ($definitionCollection->getSchemaResources($definitionName) as $fileResource) {
                            $operationCollection->addSchemaResource($routeName, $fileResource);
                        }
                    }
                }
            }
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

    /**
     * @param OpenApiSchema $schema
     * @return EXSystSchema
     */
    private function serializeOpenApiSchemaToEXSystSchema(OpenApiSchema $schema): EXSystSchema
    {
        $eXSystSchema = new EXSystSchema();

        $eXSystSchema->setDiscriminator($this->setNullOpenApiSchemeProperty($schema->discriminator));
        $eXSystSchema->setReadOnly($this->setNullOpenApiSchemeProperty($schema->readOnly));
        $eXSystSchema->setTitle($this->setNullOpenApiSchemeProperty($schema->title));
        $eXSystSchema->setExample($this->setNullOpenApiSchemeProperty($schema->example));
        $eXSystSchema->setRequired($this->setNullOpenApiSchemeProperty($schema->required));

        return $eXSystSchema;
    }

    /**
     * @param OpenApiOperation $operation
     * @return EXSystOperation
     */
    private function serializeOpenApiOperationToEXSystOperation(OpenApiOperation $operation): EXSystOperation
    {
        $eXSystOperation = new EXSystOperation();

        $eXSystOperation->setDeprecated($this->setNullOpenApiSchemeProperty($operation->deprecated));
        $eXSystOperation->setOperationId($this->setNullOpenApiSchemeProperty($operation->operationId));
        $eXSystOperation->setDescription($this->setNullOpenApiSchemeProperty($operation->description));
        $eXSystOperation->setSummary($this->setNullOpenApiSchemeProperty($operation->summary));

        return $eXSystOperation;
    }

    /**
     * @param mixed $property
     * @return mixed
     */
    private function setNullOpenApiSchemeProperty($property)
    {
        return $property === self::SCHEMA_UNDEFINED ? null : $property;
    }
}
