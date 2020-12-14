<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Prototype\Command;

use Spiral\Console\Command;
use Spiral\Prototype\Dependency;
use Spiral\Prototype\NodeExtractor;
use Spiral\Prototype\PropertyExtractor;
use Spiral\Prototype\PrototypeLocator;
use Spiral\Prototype\PrototypeRegistry;

abstract class AbstractCommand extends Command
{
    /** @var PrototypeLocator */
    protected $locator;

    /** @var NodeExtractor */
    protected $extractor;

    /** @var PrototypeRegistry */
    protected $registry;

    /** @var array */
    private $cache = [];

    /**
     * @param PrototypeLocator  $locator
     * @param NodeExtractor     $extractor
     * @param PrototypeRegistry $registry
     */
    public function __construct(PrototypeLocator $locator, NodeExtractor $extractor, PrototypeRegistry $registry)
    {
        parent::__construct(null);

        $this->extractor = $extractor;
        $this->locator = $locator;
        $this->registry = $registry;
    }

    /**
     * Fetch class dependencies.
     *
     * @param \ReflectionClass $class
     * @param array            $all
     * @return null[]|Dependency[]|\Throwable[]
     */
    protected function getPrototypeProperties(\ReflectionClass $class, array $all = []): array
    {
        $results = [$this->readProperties($class)];

        $parent = $class->getParentClass();
        while ($parent instanceof \ReflectionClass && isset($all[$parent->getName()])) {
            $results[] = $this->readProperties($parent);
            $parent = $parent->getParentClass();
        }

        return iterator_to_array($this->reverse($results));
    }

    /**
     * @return PropertyExtractor
     */
    protected function getExtractor(): PropertyExtractor
    {
        return $this->container->get(PropertyExtractor::class);
    }

    /**
     * @param Dependency[] $properties
     * @return string
     */
    protected function mergeNames(array $properties): string
    {
        return implode("\n", array_keys($properties));
    }

    /**
     * @param Dependency[] $properties
     * @return string
     */
    protected function mergeTargets(array $properties): string
    {
        $result = [];

        foreach ($properties as $target) {
            if ($target instanceof \Throwable) {
                $result[] = sprintf(
                    '<fg=red>%s [f: %s, l: %s]</fg=red>',
                    $target->getMessage(),
                    $target->getFile(),
                    $target->getLine()
                );
                continue;
            }

            if ($target === null) {
                $result[] = '<fg=yellow>undefined</fg=yellow>';
                continue;
            }

            $result[] = $target->type->fullName;
        }

        return implode("\n", $result);
    }

    private function readProperties(\ReflectionClass $class): array
    {
        if (isset($this->cache[$class->getFileName()])) {
            $proto = $this->cache[$class->getFileName()];
        } else {
            $proto = $this->getExtractor()->getPrototypeProperties(file_get_contents($class->getFilename()));
            $this->cache[$class->getFileName()] = $proto;
        }

        $result = [];
        foreach ($proto as $name) {
            if (!isset($result[$name])) {
                $result[$name] = $this->registry->resolveProperty($name);
            }
        }

        return $result;
    }

    private function reverse(array $results): ?\Generator
    {
        foreach (array_reverse($results) as $result) {
            yield from $result;
        }
    }
}
