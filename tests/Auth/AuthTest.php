<?php

namespace Test\Auth;

use Core\Auth\Auth;
use Core\Facades\App;
use Core\Facades\Application;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testAuthCheck()
    {
        App::new(new Application);

        $this->assertFalse(Auth::check());
        $this->assertFalse((new Auth)->check());
    }

    public function testAuthId()
    {
        App::new(new Application);

        $this->assertNull(Auth::id());
        $this->assertNull((new Auth)->id());
    }

    public function testAuthUser()
    {
        App::new(new Application);

        $this->assertNull(Auth::user());
        $this->assertNull((new Auth)->user());
    }
}
