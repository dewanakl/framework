<?php

namespace Core\Kernel;

use Core\Facades\App;
use Core\Facades\Application;
use Core\Facades\Service;
use Core\Support\Console;
use Core\Support\Env;
use Core\Support\Time;

/**
 * Nyalakan aplikasi ini melalui kernel.
 *
 * @class Kernel
 * @package \Core\Kernel
 */
final class Kernel
{
    /**
     * Build aplikasi ini.
     *
     * @param KernelContract $kernel
     * @return Application
     */
    private static function build(KernelContract $kernel): Application
    {
        $app = App::new(new Application());
        $app->bind(KernelContract::class, function () use ($kernel): KernelContract {
            return $kernel;
        });

        Env::loadFromDotEnv();
        Time::setTimezoneDefault();

        return $app;
    }

    /**
     * Kernel for web.
     *
     * @param KernelContract $kernel
     * @return Service
     */
    public static function web(KernelContract $kernel): Service
    {
        return new Service(static::build($kernel));
    }

    /**
     * Kernel for console.
     *
     * @param KernelContract $kernel
     * @return Console
     */
    public static function console(KernelContract $kernel): Console
    {
        static::build($kernel);
        return new Console();
    }
}
