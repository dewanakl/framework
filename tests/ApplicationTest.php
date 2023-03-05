<?php

namespace Test;

use Core\Facades\App;
use Core\Facades\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function __construct()
    {
        parent::__construct();
        App::new(new Application);
    }
}
