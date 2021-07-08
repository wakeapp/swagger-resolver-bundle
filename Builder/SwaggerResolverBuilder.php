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

namespace Linkin\Bundle\SwaggerResolverBundle\Builder;

use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterTypeEnum;
use Linkin\Bundle\SwaggerResolverBundle\Exception\UndefinedPropertyTypeException;
use Linkin\Bundle\SwaggerResolverBundle\Normalizer\SwaggerNormalizerInterface;
use Linkin\Bundle\SwaggerResolverBundle\Resolver\SwaggerResolver;
use Linkin\Bundle\SwaggerResolverBundle\Validator\SwaggerValidatorInterface;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;

use function count;
use function in_array;
use function is_array;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
class SwaggerResolverBuilder
{
    /**
     * @var array
     */
    private $normalizationLocations;

    /**
     * @var SwaggerNormalizerInterface[]
     */
    private $swaggerNormalizers;

    /**
     * @var SwaggerValidatorInterface[]
     */
    private $swaggerValidators;

    /**
     * @param SwaggerValidatorInterface[] $swaggerValidators
     * @param SwaggerNormalizerInterface[] $swaggerNormalizers
     * @param array $normalizationLocations
     */
    public function __construct(array $swaggerValidators, array $swaggerNormalizers, array $normalizationLocations)
    {
        $this->normalizationLocations = $normalizationLocations;
        $this->swaggerNormalizers = $swaggerNormalizers;
        $this->swaggerValidators = $swaggerValidators;
    }

    /**
     * @param Schema $definition
     * @param string $definitionName
     *
     * @return SwaggerResolver
     *
     * @throws UndefinedPropertyTypeException
     */
    public function build(Schema $definition, string $definitionName): SwaggerResolver
    {
        $swaggerResolver = new SwaggerResolver($definition);

        $requiredProperties = $definition->required;

        if (is_array($requiredProperties)) {
            $swaggerResolver->setRequired($requiredProperties);
        }

        $properties = $definition->properties === Generator::UNDEFINED ? [] : $definition->properties;
        $propertiesCount = count($properties);

        if (0 === $propertiesCount) {
            return $swaggerResolver;
        }

        /** @var Property | Parameter $property */
        foreach ($properties as $property) {
            $attributeName = $property instanceof Parameter ? $property->name : $property->property;
            $swaggerResolver->setDefined($attributeName);

            $allowedTypes = $this->getAllowedTypes($property);

            if (null === $allowedTypes) {
                $attributeType = $property instanceof Parameter ? $property->schema->type : $property->type;
                $attributeType = $attributeType ?? '';

                throw new UndefinedPropertyTypeException($definitionName, $property->property, $attributeType);
            }

            if (!$swaggerResolver->isRequired($attributeName)) {
                $allowedTypes[] = 'null';
            }

            $swaggerResolver->setAllowedTypes($attributeName, $allowedTypes);
            $swaggerResolver = $this->addNormalization($swaggerResolver, $attributeName, $property);

            $attributeDefault = $property instanceof Parameter ? $property->schema->default : $property->default;
            $default = $attributeDefault === Generator::UNDEFINED ? null : $attributeDefault;

            if (null !== $default) {
                $swaggerResolver->setDefault($attributeName, $default);
            }

            $attributeEnum = $property instanceof Parameter ? $property->schema->enum : $property->enum;
            $enum = $attributeEnum === Generator::UNDEFINED ? [] : $attributeEnum;

            if (!empty($enum)) {
                $swaggerResolver->setAllowedValues($attributeName, (array) $enum);
            }
        }

        foreach ($this->swaggerValidators as $validator) {
            $swaggerResolver->addValidator($validator);
        }

        return $swaggerResolver;
    }

    /**
     * @param SwaggerResolver $resolver
     * @param string $name
     * @param Property $property
     *
     * @return SwaggerResolver
     */
    private function addNormalization(SwaggerResolver $resolver, string $name, object $property): SwaggerResolver
    {
        $attributeLocation = $property instanceof Parameter ? $property->in : $property->title;

        /** @see \Linkin\Bundle\SwaggerResolverBundle\Merger\OperationParameterMerger parameter location in title */
        if (!in_array($attributeLocation, $this->normalizationLocations, true)) {
            return $resolver;
        }

        $isRequired = $resolver->isRequired($name);

        foreach ($this->swaggerNormalizers as $normalizer) {
            if (!$normalizer->supports($property, $name, $isRequired)) {
                continue;
            }

            $closure = $normalizer->getNormalizer($property, $name, $isRequired);

            return $resolver
                ->setNormalizer($name, $closure)
                ->addAllowedTypes($name, 'string')
            ;
        }

        return $resolver;
    }

    /**
     * @param Property $property
     *
     * @return array
     */
    private function getAllowedTypes(object $property): ?array
    {
        $attributeType = $property instanceof Parameter ? $property->schema->type : $property->type;
        $collectionFormat = $property instanceof Parameter ?
            $property->schema->collectionFormat :
            $property->collectionFormat
        ;

        $allowedTypes = [];

        if (ParameterTypeEnum::STRING === $attributeType) {
            $allowedTypes[] = 'string';

            return $allowedTypes;
        }

        if (ParameterTypeEnum::INTEGER === $attributeType) {
            $allowedTypes[] = 'integer';
            $allowedTypes[] = 'int';

            return $allowedTypes;
        }

        if (ParameterTypeEnum::BOOLEAN === $attributeType) {
            $allowedTypes[] = 'boolean';
            $allowedTypes[] = 'bool';

            return $allowedTypes;
        }

        if (ParameterTypeEnum::NUMBER === $attributeType) {
            $allowedTypes[] = 'double';
            $allowedTypes[] = 'float';

            return $allowedTypes;
        }

        if (ParameterTypeEnum::ARRAY === $attributeType) {
            $allowedTypes[] = Generator::UNDEFINED === $collectionFormat ? 'array' : 'string';

            return $allowedTypes;
        }

        if ('object' === $attributeType) {
            $allowedTypes[] = 'object';
            $allowedTypes[] = 'array';

            return $allowedTypes;
        }

        if (Generator::UNDEFINED === $attributeType && $property->ref) {
            $ref = $property->ref;

            $allowedTypes[] = 'object';
            $allowedTypes[] = 'array';
            $allowedTypes[] = $ref;

            return $allowedTypes;
        }

        return null;
    }
}
