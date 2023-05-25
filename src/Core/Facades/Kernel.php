<?php

namespace Core\Facades;

use App\Kernel as AppKernel;
use Core\Support\Console;
use Exception;

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
     * Tetapkan timezone pada aplikasi ini.
     * 
     * @return void
     * 
     * @throws Exception
     */
    public static function setTimezone(): void
    {
        if (!@date_default_timezone_set(env('TIMEZONE', 'Asia/Jakarta'))) {
            throw new Exception('Timezone invalid !');
        }
    }

    /**
     * Kernel for web.
     * 
     * @return Service
     */
    public static function web(): Service
    {
        return static::build()->make(Service::class);
    }

    /**
     * Kernel for console.
     * 
     * @return Console
     */
    public static function console(): Console
    {
        return static::build()->make(Console::class);
    }
}
