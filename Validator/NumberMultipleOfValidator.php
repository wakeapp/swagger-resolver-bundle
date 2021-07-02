<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Property;
use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function in_array;
use function is_int;
use function sprintf;

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
