<?php

namespace Core\Facades;

use App\Kernel as AppKernel;
use Core\Support\Console;
use Exception;
use Throwable;

/**
 * Nyalakan aplikasi ini melalui kernel.
 * 
 * @class Kernel
 * @package \Core\Facades
 */
final class Kernel
{
    /**
     * Build aplikasi ini.
     * 
     * @return Application
     * 
     * @throws Exception
     */
    private static function build(): Application
    {
        $_ENV['_STARTTIME'] = microtime(true);
        App::new(new Application());
        static::setEnv();

        if (!date_default_timezone_set(@$_ENV['TIMEZONE'] ?? 'Asia/Jakarta')) {
            throw new Exception('Timezone invalid !');
        }

        return App::get();
    }

    /**
     * Set env from .env file.
     * 
     * @return void
     */
    private static function setEnv(): void
    {
        $path = App::get()->singleton(AppKernel::class)->getPath();
        $lines = is_file($path . '/app/cache/env.php')
            ? require_once $path . '/app/cache/env.php'
            : (is_file($path . '/.env') ? file($path . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : []);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }

    /**
     * Kernel for web.
     * 
     * @return Service
     * 
     * @throws Exception
     */
    public static function web(): Service
    {
        $app = static::build();

        $https = (!empty(@$_SERVER['HTTPS']) && @$_SERVER['HTTPS'] != 'off') || @$_SERVER['SERVER_PORT'] == '443' || @$_ENV['HTTPS'] == 'true';
        $debug = @$_ENV['DEBUG'] == 'true';

        $_ENV['_BASEURL'] = @$_ENV['BASEURL'] ? rtrim($_ENV['BASEURL'], '/') : ($https ? 'https://' : 'http://') . trim($_SERVER['HTTP_HOST']);
        $_ENV['_HTTPS'] = $https;
        $_ENV['_DEBUG'] = $debug;

        error_reporting($debug ? E_ALL : 0);
        set_exception_handler(function (Throwable $error) use ($debug) {
            if ($debug) {
                trace($error);
            }

            unavailable();
        });

        $service = $app->make(Service::class);

        if (!env('APP_KEY')) {
            throw new Exception('App Key gk ada !');
        }

        return $service;
    }

    /**
     * Kernel for console.
     * 
     * @return Console
     */
    public static function console(): Console
    {
        return static::build()->make(Console::class, array($_SERVER['argv']));
    }
}
