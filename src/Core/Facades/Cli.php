<?php

namespace Core\Facades;

use Core\Support\Console;
use Throwable;

class Cli extends Service
{
    public function run(): int
    {
        $cli = $this->app->make(Console::class);

        try {
            $this->bootingProviders();
            $this->registerProvider();
            return $cli->run();
        } catch (Throwable $th) {
            return $cli->catchException($th);
        }
    }
}
