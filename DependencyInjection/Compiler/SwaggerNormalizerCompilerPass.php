<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\DependencyInjection\Compiler;

use Linkin\Bundle\SwaggerResolverBundle\Builder\SwaggerResolverBuilder;
use SplPriorityQueue;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use function iterator_to_array;

class SwaggerNormalizerCompilerPass implements CompilerPassInterface
{
    public const TAG = 'linkin_swagger_resolver.normalizer';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SwaggerResolverBuilder::class)) {
            return;
        }

        $normalizerQueue = new SplPriorityQueue();

        foreach ($container->findTaggedServiceIds(self::TAG) as $id => $tags) {
            $validatorReference = new Reference($id);
            $priority = 0;

            foreach ($tags as $attributes) {
                if (isset($attributes['priority'])) {
                    $priority = $attributes['priority'];
                }
            }

            $normalizerQueue->insert($validatorReference, $priority);
        }

        $normalizers = iterator_to_array($normalizerQueue);

        $container
            ->getDefinition(SwaggerResolverBuilder::class)
            ->replaceArgument(1, $normalizers)
        ;
    }
}
