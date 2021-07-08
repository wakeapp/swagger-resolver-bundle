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

namespace Linkin\Bundle\SwaggerResolverBundle\Merger\Strategy;

use Linkin\Bundle\SwaggerResolverBundle\Merger\MergeStrategyInterface;

/**
 * @author Viktor Linkin <adrenalinkin@gmail.com>
 */
abstract class AbstractMergeStrategy implements MergeStrategyInterface
{
    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var array
     */
    protected $required = [];

    /**
     * {@inheritdoc}
     */
    abstract public function addParameter(string $parameterSource, string $name, object $data, bool $isRequired);

    /**
     * {@inheritdoc}
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * {@inheritdoc}
     */
    public function clean()
    {
        $this->parameters = [];
        $this->required = [];
    }
}
