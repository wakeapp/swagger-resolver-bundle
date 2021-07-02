<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Normalizer;

use Closure;
use OpenApi\Annotations\Property;

interface SwaggerNormalizerInterface
{
    /**
     * Check is this normalizer supports received property
     *
     * @param Property $propertySchema
     * @param string $propertyName
     * @param bool $isRequired
     * @param array $context
     *
     * @return bool
     */
    public function supports(
        object $propertySchema,
        string $propertyName,
        bool $isRequired,
        array $context = []
    ): bool;

    /**
     * Returns closure for normalizing property
     *
     * @param Property $propertySchema
     * @param string $propertyName
     * @param bool $isRequired
     *
     * @return Closure
     */
    public function getNormalizer(object $propertySchema, string $propertyName, bool $isRequired): Closure;
}
