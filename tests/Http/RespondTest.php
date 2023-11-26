<?php

namespace Test\Http;

use Core\Http\Respond;
use Test\ApplicationTest;

class RespondTest extends ApplicationTest
{
    public function testIsSame()
    {
        $a = $this->application->make(Respond::class);
        $b = $this->application->singleton(Respond::class);

        $this->assertSame($a, $b);
    }

    public function testIsNotSame()
    {
        $b = $this->application->singleton(Respond::class);
        $a = $this->application->make(Respond::class);

        $this->assertNotSame($a, $b);
    }

    public function testIsNotSameIfBuildOwn()
    {
        $b = $this->application->singleton(Respond::class);
        $a = new Respond();

        $this->assertNotSame($a, $b);
    }
}
