<?php

namespace Test\Facades;

use Core\Facades\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testObjectSame()
    {
        $app = new Application;

        $A = $app->make(Application::class);
        $B = $app->singleton(Application::class);

        $this->assertSame($A, $B);
    }

    public function testObjectNotSame()
    {
        $app = new Application;

        $B = $app->singleton(Application::class);
        $A = $app->make(Application::class);

        $this->assertNotSame($A, $B);
    }

    public function testObjectClosureNotSame()
    {
        $app = new Application;

        $app->bind(Application::class, function () {
            return new Application;
        });

        $B = $app->singleton(Application::class);
        $A = $app->make(Application::class);

        $this->assertNotSame($A, $B);
    }
}
