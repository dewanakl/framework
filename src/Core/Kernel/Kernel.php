<?php

namespace Core\Kernel;

use Core\Facades\App;
use Core\Facades\Application;
use Core\Facades\Cli;
use Core\Facades\Web;
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
     * @return Web
     */
    public static function web(KernelContract $kernel): Web
    {
        return new Web(static::build($kernel));
    }

    /**
     * Kernel for cli.
     *
     * @param KernelContract $kernel
     * @return Cli
     */
    public static function cli(KernelContract $kernel): Cli
    {
        return new Cli(static::build($kernel));
    }
}
