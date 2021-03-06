<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle;

use Linkin\Bundle\SwaggerResolverBundle\DependencyInjection\Compiler\SwaggerNormalizerCompilerPass;
use Linkin\Bundle\SwaggerResolverBundle\DependencyInjection\Compiler\SwaggerValidatorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class LinkinSwaggerResolverBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container
            ->addCompilerPass(new SwaggerValidatorCompilerPass())
            ->addCompilerPass(new SwaggerNormalizerCompilerPass())
        ;
    }
}
