<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

interface SwaggerValidatorInterface
{
    /**
     * Check is this validator supports received property
     *
     * @param object $propertySchema
     * @param array  $context
     *
     * @return bool
     */
    public function supports(object $propertySchema, array $context = []): bool;

    /**
     * Validate received property value according to property schema configuration
     *
     * @param object $propertySchema
     * @param string $propertyName
     * @param mixed  $value
     *
     * @throws InvalidOptionsException If the option doesn't fulfill the specified validation rules
     */
    public function validate(object $propertySchema, string $propertyName, $value): void;
}
