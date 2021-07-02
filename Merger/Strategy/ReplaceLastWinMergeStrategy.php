<?php

declare(strict_types=1);

namespace Linkin\Bundle\SwaggerResolverBundle\Merger\Strategy;

class ReplaceLastWinMergeStrategy extends AbstractMergeStrategy
{
    /**
     * {@inheritdoc}
     */
    public function addParameter(string $parameterSource, string $name, object $data, bool $isRequired)
    {
        if ($isRequired) {
            $this->required[$name] = $name;
        }

        $this->parameters[$name] = $data;
    }
}
