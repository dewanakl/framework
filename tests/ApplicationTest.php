<?php

namespace Test;

use Core\Facades\App;
use Core\Facades\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    protected $application;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->application = App::new(new Application);
    }
}
