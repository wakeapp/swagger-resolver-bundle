<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Normalizer;

use Closure;
use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use Linkin\Bundle\SwaggerResolverBundle\Exception\NormalizationFailedException;
use OpenApi\Annotations\Parameter;
use Symfony\Component\OptionsResolver\Options;

use function is_numeric;

class IntegerNormalizer implements SwaggerNormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(
        object $propertySchema,
        string $propertyName,
        bool $isRequired,
        array $context = []
    ): bool {
        $attributeType = $propertySchema instanceof Parameter ? $propertySchema->schema->type : $propertySchema->type;
        
        return $attributeType === ParameterTypeEnum::INTEGER;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormalizer(object $propertySchema, string $propertyName, bool $isRequired): Closure
    {
        return function (Options $options, $value) use ($isRequired, $propertyName) {
            if (is_numeric($value)) {
                return (int) $value;
            }

            if (!$isRequired && $value === null) {
                return null;
            }

            throw new NormalizationFailedException($propertyName, (string) $value);
        };
    }
}
