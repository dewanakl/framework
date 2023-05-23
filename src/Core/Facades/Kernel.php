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
     */
    private static function build(): Application
    {
        App::new(new Application());
        static::setEnv();
        return App::get();
    }

    /**
     * Tetapkan timezone pada aplikasi ini.
     * 
     * @return void
     * 
     * @throws Exception
     */
    private static function setTimezone(): void
    {
        if (!@date_default_timezone_set(env('TIMEZONE', 'Asia/Jakarta'))) {
            throw new Exception('Timezone invalid !');
        }
    }

    /**
     * Set env from .env file.
     * 
     * @return void
     */
    private static function setEnv(): void
    {
        $path = App::get()->singleton(AppKernel::class)->getPath();

        if (is_file($path . '/cache/env.php')) {
            foreach ((array) require_once $path . '/cache/env.php' as $key => $value) {
                $_ENV[$key] = $value;
            }
        } else {
            $lines = is_file($path . '/.env')
                ? file($path . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                : [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $_ENV[trim($name)] = trim($value);
            }
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

        $_ENV['_HTTPS'] = @$_SERVER['HTTPS'] !== 'off' || @$_SERVER['SERVER_PORT'] == '443' || @$_ENV['HTTPS'] == 'true';
        $_ENV['_BASEURL'] = @$_ENV['BASEURL'] ? rtrim($_ENV['BASEURL'], '/') : (https() ? 'https://' : 'http://') . trim($_SERVER['HTTP_HOST']);
        $_ENV['_DEBUG'] = @$_ENV['DEBUG'] == 'true';

        error_reporting(debug() ? E_ALL : 0);
        set_exception_handler(function (Throwable $error): void {
            if (debug()) {
                trace($error);
            }

            unavailable();
        });

        $service = $app->make(Service::class);

        static::setTimezone();

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
        $app = static::build();
        static::setTimezone();
        return $app->make(Console::class, array($_SERVER['argv']));
    }
}
