<?php

declare(strict_types=1);

/*
 * This file is part of the SwaggerResolverBundle package.
 *
 * (c) Viktor Linkin <adrenalinkin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Linkin\Bundle\SwaggerResolverBundle\Loader;

use Exception;
use Linkin\Bundle\SwaggerResolverBundle\Collection\SchemaDefinitionCollection;
use Linkin\Bundle\SwaggerResolverBundle\Collection\SchemaOperationCollection;
use Linkin\Bundle\SwaggerResolverBundle\Exception\PathNotFoundException;
use Linkin\Bundle\SwaggerResolverBundle\Merger\OperationParameterMerger;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\PathItem;
use OpenApi\Annotations\RequestBody;
use OpenApi\Annotations\Schema as OpenApiSchema;
use OpenApi\Generator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\RouterInterface;

use function end;
use function explode;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
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
     * @throws Exception
     */
    private function registerCollections(): void
    {
        $openApiConfiguration = $this->loadConfiguration();

        $definitionCollection = new SchemaDefinitionCollection();
        $operationCollection = new SchemaOperationCollection();

        $methodList = ['get', 'post', 'put', 'delete', 'options', 'patch'];

        /** @var OpenApiSchema $schema */
        foreach ($openApiConfiguration->components->schemas as $schema) {
            $definitionCollection->addSchema($schema->schema, $schema);
        }

        /** @var PathItem $pathItem */
        foreach ($openApiConfiguration->paths as $pathItem) {
            $path = $pathItem->path;

            foreach ($methodList as $method) {
                /** @var Operation $openApiOperation */
                $openApiOperation = $pathItem->$method;

                if ($openApiOperation instanceof Operation) {
                    $routeName = $this->getRouteNameByPath(sprintf('%s %s', strtolower($method), $path));
                    $schema = $this->parameterMerger->merge($openApiOperation, $definitionCollection->getIterator());
                    $operationCollection->addSchema($routeName, $method, $schema);

                    /** @var RequestBody $requestBody */
                    $requestBody = $openApiOperation->requestBody === Generator::UNDEFINED ?
                        null : $openApiOperation->requestBody
                    ;

                    if ($requestBody) {
                        $contentList = $requestBody->content === Generator::UNDEFINED ? [] : $requestBody->content;

                        /** @var MediaType $mediaType */
                        foreach ($contentList as $mediaType) {
                            $schema = $mediaType->schema;
                            $ref = $schema->ref === Generator::UNDEFINED ? null : $schema->ref;

                            if ($ref) {
                                $explodedName = explode('/', $ref);
                                $definitionName = end($explodedName);

                                foreach ($definitionCollection->getSchemaResources($definitionName) as $fileResource) {
                                    $operationCollection->addSchemaResource($routeName, $fileResource);
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->registerDefinitionResources($definitionCollection);

        $operationCollection->addSchemaResource('/', new FileResource(''));
        $this->registerOperationResources($operationCollection);

        $this->definitionCollection = $definitionCollection;
        $this->operationCollection = $operationCollection;
    }
}
