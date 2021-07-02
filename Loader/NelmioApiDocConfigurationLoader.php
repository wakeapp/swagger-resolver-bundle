<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Loader;

use Linkin\Bundle\SwaggerResolverBundle\Merger\OperationParameterMerger;
use Nelmio\ApiDocBundle\ApiDocGenerator;
use OpenApi\Annotations\OpenApi;
use Symfony\Component\Routing\RouterInterface;

class NelmioApiDocConfigurationLoader extends AbstractAnnotationConfigurationLoader
{
    /**
     * Instance of nelmio Api configuration generator
     *
     * @var ApiDocGenerator
     */
    private $apiDocGenerator;

    /**
     * @param OperationParameterMerger $merger
     * @param RouterInterface $router
     * @param ApiDocGenerator $generator
     */
    public function __construct(OperationParameterMerger $merger, RouterInterface $router, ApiDocGenerator $generator)
    {
        parent::__construct($merger, $router);

        $this->apiDocGenerator = $generator;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadConfiguration(): OpenApi
    {
        return $this->apiDocGenerator->generate();
    }
}
