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

namespace Linkin\Bundle\SwaggerResolverBundle\Merger;

use JsonException;
use Linkin\Bundle\SwaggerResolverBundle\Enum\ParameterLocationEnum;
use OpenApi\Annotations\MediaType;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Parameter;
use OpenApi\Annotations\RequestBody;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;

use function array_flip;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
class OperationParameterMerger
{
    /**
     * @var MergeStrategyInterface
     */
    private $mergeStrategy;

    /**
     * @param MergeStrategyInterface $defaultMergeStrategy
     */
    public function __construct(MergeStrategyInterface $defaultMergeStrategy)
    {
        $this->mergeStrategy = $defaultMergeStrategy;
    }

    /**
     * @param Operation $openApiOperation
     * @param iterable $definitions
     *
     * @return Schema
     * @throws JsonException
     */
    public function merge(Operation $openApiOperation, iterable $definitions): Schema
    {
        /** @var RequestBody $requestBody */
        $requestBody = $openApiOperation->requestBody === Generator::UNDEFINED ? null : $openApiOperation->requestBody;

        /** @var Parameter[] $parameterList */
        $parameterList = $openApiOperation->parameters === Generator::UNDEFINED ? null : $openApiOperation->parameters;

        if ($parameterList) {
            foreach ($parameterList as $parameter) {
                $required = $parameter->required === Generator::UNDEFINED ? false : $parameter->required;

                $this->mergeStrategy->addParameter(
                    $parameter->in,
                    $parameter->name,
                    $parameter,
                    $required === true
                );
            }
        }

        if ($requestBody) {
            $contentList = $requestBody->content === Generator::UNDEFINED ? [] : $requestBody->content;

            /** @var MediaType $mediaType */
            foreach ($contentList as $mediaType) {
                $schema = $mediaType->schema;
                $ref = $schema->ref === Generator::UNDEFINED ? null : $schema->ref;

                // body as reference
                if ($ref) {
                    $explodedName = explode('/', $ref);
                    $definitionName = end($explodedName);

                    if (!isset($definitions[$definitionName])) {
                        $definitions[$definitionName] = new Schema([]);
                    }

                    $refDefinition = $definitions[$definitionName];
                    $requiredList = $refDefinition->required === Generator::UNDEFINED ? [] : $refDefinition->required;
                    $requiredList = array_flip($requiredList);

                    foreach ($refDefinition->properties as $property) {
                        $property->title = ParameterLocationEnum::IN_BODY;
                        $this->mergeStrategy->addParameter(
                            ParameterLocationEnum::IN_BODY,
                            $property->property,
                            $property,
                            isset($requiredList[$property->property])
                        );
                    }
                }

                // body as any mediaType
                if (!$ref && $mediaType) {
                    $requiredList = [];

                    foreach ($schema->properties as $property) {
                        $required = !($property->required === Generator::UNDEFINED) && $property->required;

                        if ($required) {
                            $requiredList[] = $property->property;
                        }
                    }

                    $requiredList = array_flip($requiredList);

                    foreach ($schema->properties as $property) {
                        $property->title = ParameterLocationEnum::IN_BODY;

                        $this->mergeStrategy->addParameter(
                            ParameterLocationEnum::IN_BODY,
                            $property->property,
                            $property,
                            isset($requiredList[$property->property])
                        );
                    }
                }
            }
        }

        $mergedSchema = new Schema([
            'type' => 'object',
            'properties' => $this->mergeStrategy->getParameters(),
            'required' => $this->mergeStrategy->getRequired(),
        ]);

        $this->mergeStrategy->clean();

        return $mergedSchema;
    }
}
