<?php

declare(strict_types=1);

namespace Spiral\Core;

use Spiral\Core\Exception\Container\AutowireException;

/**
 * Declares ability to construct classes.
 */
interface FactoryInterface
{
    /**
     * Create instance of requested class using binding class aliases and set of parameters provided
     * by user, rest of constructor parameters must be filled by container. Method might return
     * pre-constructed singleton when no parameters are specified.
     *
     * @template T
     *
     * @param class-string<T> $alias
     * @param array $parameters Parameters to construct new class.
     *
     * @return T
     *
     * @throws AutowireException
     */
    public function make(string $alias, array $parameters = []): mixed;
}
