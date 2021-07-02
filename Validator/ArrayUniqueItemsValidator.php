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
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function array_unique;
use function count;
use function sprintf;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
class ArrayUniqueItemsValidator extends AbstractArrayValidator
{
    /**
     * {@inheritdoc}
     */
    public function supports(object $property, array $context = []): bool
    {
        $propertyUniqueItems = $property instanceof Parameter ? $property->schema->uniqueItems : $property->uniqueItems;

        return parent::supports($property, $context) && true === $propertyUniqueItems;
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

        $itemsUnique = array_unique($value);

        if (count($itemsUnique) !== count($value)) {
            throw new InvalidOptionsException(sprintf('Property "%s" should contains unique items', $propertyName));
        }
    }
}
