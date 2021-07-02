<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function preg_match;
use function sprintf;
use function trim;

class StringPatternValidator implements SwaggerValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $propertyPattern = $property instanceof Parameter ? $property->schema->pattern : $property->pattern;

        return ParameterTypeEnum::STRING === $propertyType && Generator::UNDEFINED !== $propertyPattern;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $propertyPattern = $property instanceof Parameter ? $property->schema->pattern : $property->pattern;
        $pattern = sprintf('/%s/', trim($propertyPattern, '/'));

        if (!preg_match($pattern, $value)) {
            throw new InvalidOptionsException(sprintf(
                'Property "%s" should match the pattern "%s"',
                $propertyName,
                $pattern
            ));
        }
    }
}
