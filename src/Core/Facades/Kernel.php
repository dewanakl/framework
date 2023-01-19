<?php

namespace Core\Facades;

use App\Kernel as AppKernel;
use Core\Support\Console;
use Exception;

/**
 * Nyalakan aplikasi ini melalui kernel ini
 * 
 * @class Kernel
 * @package \Core\Facades
 */
final class Kernel
{
    /**
     * Object application
     * 
     * @var Application $app
     */
    private $app;

    /**
     * Path application
     * 
     * @var string $path
     */
    private $path;

    /**
     * Init object
     * 
     * @return void
     * @throws Exception
     */
    function __construct()
    {
        $_ENV['_STARTTIME'] = microtime(true);
        $this->app = App::new(new Application());
        $this->path = $this->app->singleton(AppKernel::class)->getPath();
        $this->setEnv();

        if (!date_default_timezone_set(@$_ENV['TIMEZONE'] ?? 'Asia/Jakarta')) {
            throw new Exception('Date time invalid !');
        }
    }

    /**
     * Set env from .env file
     * 
     * @return void
     */
    private function setEnv(): void
    {
        $file = $this->path . '/.env';
        $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }

    public function get(): Application
    {
        return $this->app;
    }

    /**
     * Kernel for web
     * 
     * @return Service
     * @throws Exception
     */
    public static function web(): Service
    {
        $app = new self();

        $https = (!empty(@$_SERVER['HTTPS']) && @$_SERVER['HTTPS'] != 'off') || @$_SERVER['SERVER_PORT'] == '443' || @$_ENV['HTTPS'] == 'true';
        $debug = @$_ENV['DEBUG'] == 'true';

        $_ENV['__BASEURL'] = @$_ENV['BASEURL'] ? rtrim($_ENV['BASEURL'], '/') : ($https ? 'https://' : 'http://') . trim($_SERVER['HTTP_HOST']);
        $_ENV['__HTTPS'] = $https;
        $_ENV['__DEBUG'] = $debug;

        error_reporting($debug ? E_ALL : 0);
        set_exception_handler(function (mixed $error) use ($debug) {
            if ($debug) {
                trace($error);
            }

            unavailable();
        });

        $service = $app->get()->make(Service::class);

        if (!env('APP_KEY')) {
            throw new Exception('App Key gk ada !');
        }

        return $service;
    }

    /**
     * Kernel for console
     * 
     * @return Console
     */
    public static function console(): Console
    {
        return (new self())->get()->make(Console::class, array($_SERVER['argv']));
    }
}
