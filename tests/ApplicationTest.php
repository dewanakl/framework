<?php

use Core\Facades\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function testApplication()
    {
        $app = new Application;

        $A = $app->make(Application::class);
        $B = $app->singleton(Application::class);

        $this->assertSame($A, $B);
    }
}
