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

use function mb_strlen;
use function sprintf;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
class StringMaxLengthValidator implements SwaggerValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $propertyMaxLength = $property instanceof Parameter ? $property->schema->maxLength : $property->maxLength;

        return ParameterTypeEnum::STRING === $propertyType && Generator::UNDEFINED !== $propertyMaxLength;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $propertyMaxLength = $property instanceof Parameter ? $property->schema->maxLength : $property->maxLength;

        if (mb_strlen($value) > $propertyMaxLength) {
            throw new InvalidOptionsException(sprintf(
                'Property "%s" should have %s character or less',
                $propertyName,
                $propertyMaxLength
            ));
        }
    }
}
