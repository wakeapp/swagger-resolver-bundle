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

use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function in_array;
use function sprintf;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
class NumberMinimumValidator implements SwaggerValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $propertyMinimum = $property instanceof Parameter ? $property->schema->minimum : $property->minimum;

        return in_array($propertyType, [ParameterTypeEnum::NUMBER, ParameterTypeEnum::INTEGER], true)
            && Generator::UNDEFINED !== $propertyMinimum
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $message = sprintf('Property "%s" value should be', $propertyName);
        $minimum = $property instanceof Parameter ? $property->schema->minimum : $property->minimum;
        $exclusiveMinimum = $property instanceof Parameter ?
            $property->schema->exclusiveMinimum :
            $property->exclusiveMinimum
        ;

        $exclusiveMinimum = $exclusiveMinimum === Generator::UNDEFINED ? false : $exclusiveMinimum;


        if ($exclusiveMinimum && $value <= $minimum) {
            throw new InvalidOptionsException(sprintf('%s strictly greater than %s', $message, $minimum));
        }

        if (!$exclusiveMinimum && $value < $minimum) {
            throw new InvalidOptionsException(sprintf('%s greater than or equal to %s', $message, $minimum));
        }
    }
}
