<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function mb_strlen;
use function sprintf;

class StringMinLengthValidator implements SwaggerValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $propertyMinLength = $property instanceof Parameter ? $property->schema->minLength : $property->minLength;

        return ParameterTypeEnum::STRING === $propertyType && Generator::UNDEFINED !== $propertyMinLength;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $propertyMinLength = $property instanceof Parameter ? $property->schema->minLength : $property->minLength;

        if (mb_strlen($value) < $propertyMinLength) {
            throw new InvalidOptionsException(sprintf(
                'Property "%s" should have %s character or more',
                $propertyName,
                $propertyMinLength
            ));
        }
    }
}
