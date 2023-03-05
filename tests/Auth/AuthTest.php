<?php

namespace Test\Auth;

use Core\Auth\Auth;
use Test\ApplicationTest as TestCase;

class AuthTest extends TestCase
{
    public function testAuthCheck()
    {
        $this->assertFalse(Auth::check());
        $this->assertFalse((new Auth)->check());
    }

    public function testAuthId()
    {
        $this->assertNull(Auth::id());
        $this->assertNull((new Auth)->id());
    }

    public function testAuthUser()
    {
        $this->assertNull(Auth::user());
        $this->assertNull((new Auth)->user());
    }
}
