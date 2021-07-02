<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Validator;

use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function count;
use function sprintf;

class ArrayMaxItemsValidator extends AbstractArrayValidator
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyMaxItems = $property instanceof Parameter ? $property->schema->maxItems : $property->maxItems;

        return parent::supports($property, $context) && Generator::UNDEFINED !== $propertyMaxItems;
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
        $propertyMaxItems = $property instanceof Parameter ? $property->schema->maxItems : $property->maxItems;

        if (count($value) > $propertyMaxItems) {
            throw new InvalidOptionsException(sprintf(
                'Property "%s" should have %s items or less',
                $propertyName,
                $propertyMaxItems
            ));
        }
    }
}
