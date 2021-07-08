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

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
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
