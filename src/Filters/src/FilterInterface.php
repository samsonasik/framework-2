<?php

declare(strict_types=1);

namespace Spiral\Filters;

interface FilterInterface
{
    public function filterDefinition(): FilterDefinitionInterface;
}
