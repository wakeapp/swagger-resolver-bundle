<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function in_array;
use function sprintf;

class NumberMaximumValidator implements SwaggerValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $propertyMaximum = $property instanceof Parameter ? $property->schema->maximum : $property->maximum;

        return in_array($propertyType, [ParameterTypeEnum::NUMBER, ParameterTypeEnum::INTEGER], true)
            && Generator::UNDEFINED !== $propertyMaximum
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $message = sprintf('Property "%s" value should be', $propertyName);
        $maximum = $property instanceof Parameter ? $property->schema->maximum : $property->maximum;

        if ($property->exclusiveMaximum && $value >= $maximum) {
            throw new InvalidOptionsException(sprintf('%s strictly lower than %s', $message, $maximum));
        }

        if (!$property->exclusiveMaximum && $value > $maximum) {
            throw new InvalidOptionsException(sprintf('%s lower than or equal to %s', $message, $maximum));
        }
    }
}
