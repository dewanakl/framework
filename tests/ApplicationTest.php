<?php

namespace Test;

use Core\Facades\App;
use Core\Facades\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        App::new(new Application);
    }
}
