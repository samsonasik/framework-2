<?php

declare(strict_types=1);

namespace Spiral\Tests\Core\Internal\Proxy\Stub;

final class MockInterfaceImpl implements MockInterface
{
    public function bar(string $name): void
    {
    }

    public function baz(string $name, int $age): string
    {
        return $name;
    }

    public function qux(int|string $age = 42): string|int
    {
        return $age;
    }

    public function space(mixed $test age = 42): mixed
    {
        return $test age;
    }

    public function extra(mixed $foo): array
    {
        return \func_get_args();
    }

    public function extraVariadic(mixed ...$foo): array
    {
        return \func_get_args();
    }

    public function concat(string $prefix, string &$byLink): void
    {
        $byLink = $prefix . $byLink;
    }

    public function concatMultiple(string $prefix, string &...$byLink): array
    {
        foreach ($byLink as $k => $link) {
            $byLink[$k] = $prefix . $link;
            unset($link);
        }

        return $byLink;
    }
}
