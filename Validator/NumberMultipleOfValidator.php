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
use function is_int;
use function sprintf;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
class NumberMultipleOfValidator implements SwaggerValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $propertyMultipleOf = $property instanceof Parameter ? $property->schema->multipleOf : $property->multipleOf;

        return in_array($propertyType, [ParameterTypeEnum::NUMBER, ParameterTypeEnum::INTEGER], true)
            && Generator::UNDEFINED !== $propertyMultipleOf
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $propertyMultipleOf = $property instanceof Parameter ? $property->schema->multipleOf : $property->multipleOf;
        $divisionResult = $value / $propertyMultipleOf;

        if (!is_int($divisionResult)) {
            throw new InvalidOptionsException(sprintf(
                'Property "%s" should be an integer after division by %s',
                $propertyName,
                $propertyMultipleOf
            ));
        }
    }
}
