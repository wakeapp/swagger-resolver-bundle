<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\Property;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function count;
use function sprintf;

class ArrayMinItemsValidator extends AbstractArrayValidator
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyMinItems = $property instanceof Parameter ? $property->schema->minItems : $property->minItems;

        return parent::supports($property, $context) && Generator::UNDEFINED !== $propertyMinItems;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(object $property, string $propertyName, $value): void
    {
        $propertyCollectionFormat = $property instanceof Parameter ?
            $property->schema->collectionFormat :
            $property->collectionFormat
        ;

        $value = $this->convertValueToArray($propertyName, $value, $propertyCollectionFormat);
        $propertyMinItems = $property instanceof Parameter ? $property->schema->minItems : $property->minItems;

        if (count($value) < $propertyMinItems) {
            throw new InvalidOptionsException(sprintf(
                'Property "%s" should have %s items or more',
                $propertyName,
                $propertyMinItems
            ));
        }
    }
}
