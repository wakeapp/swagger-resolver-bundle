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

use OpenApi\Annotations\Parameter;
use OpenApi\Generator;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function count;
use function sprintf;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
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
