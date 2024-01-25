<?php

declare(strict_types=1);

namespace Spiral\Tests\Core;

use Spiral\Core\Exception\InterceptorException;
use Spiral\Core\InterceptableCore;
use Spiral\Core\InterceptorPipeline;
use Spiral\Framework\ScopeName;
use Spiral\Testing\Attribute\TestScope;
use Spiral\Testing\TestCase;
use Spiral\Tests\Core\Fixtures\DummyController;
use Spiral\Tests\Core\Fixtures\SampleCore;

final class InterceptableCoreTest extends TestCase
{
    #[TestScope(ScopeName::Http)]
    public function testNoInterceptors(): void
    {
        $int = new InterceptableCore(new SampleCore($this->getContainer()));

        $this->assertSame('Hello, Antony.', $int->callAction(
            DummyController::class,
            'index',
            ['name' => 'Antony']
        ));
    }

    #[TestScope(ScopeName::Http)]
    public function testNoInterceptors2(): void
    {
        $int = new InterceptableCore(new SampleCore($this->getContainer()));
        $int->addInterceptor(new DemoInterceptor());

        $this->assertSame('?Hello, Antony.!', $int->callAction(
            DummyController::class,
            'index',
            ['name' => 'Antony']
        ));
    }

    #[TestScope(ScopeName::Http)]
    public function testNoInterceptors22(): void
    {
        $int = new InterceptableCore(new SampleCore($this->getContainer()));
        $int->addInterceptor(new DemoInterceptor());
        $int->addInterceptor(new DemoInterceptor());

        $this->assertSame('??Hello, Antony.!!', $int->callAction(
            DummyController::class,
            'index',
            ['name' => 'Antony']
        ));
    }

    #[TestScope(ScopeName::Http)]
    public function testInvalidPipeline(): void
    {
        $this->expectException(InterceptorException::class);

        $pipeline = new InterceptorPipeline();
        $pipeline->callAction(
            DummyController::class,
            'index',
            ['name' => 'Antony']
        );
    }
}
