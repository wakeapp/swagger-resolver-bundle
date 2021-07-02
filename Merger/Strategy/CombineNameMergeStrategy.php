<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Merger\Strategy;

class CombineNameMergeStrategy extends AbstractMergeStrategy
{
    public const DELIMITER = '_';

    /**
     * {@inheritdoc}
     */
    public function addParameter(string $parameterSource, string $name, object $data, bool $isRequired)
    {
        $name = $parameterSource . self::DELIMITER . $name;

        if ($isRequired) {
            $this->required[$name] = $name;
        }

        $this->parameters[$name] = $data;
    }
}
