<?php

namespace Core\Support;

use Throwable;

/**
 * Env in this application.
 *
 * @class Env
 * @package \Core\Support
 */
final class Env
{
    public const HTTPS = '_HTTPS';
    public const BASEURL = '_BASEURL';
    public const DEBUG = '_DEBUG';

    /**
     * Set value ke env.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_ENV[$key] = $value;
    }

    /**
     * Dapatkan valuenya.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string|null $key = null, mixed $default = null): mixed
    {
        return $key ? ($_ENV[$key] ?? $default) : $_ENV;
    }

    /**
     * Hapus valuenya.
     *
     * @param string $key
     * @return void
     */
    public static function unset(string $key): void
    {
        static::set($key, null);
        unset($_ENV[$key]);
    }

    /**
     * Init from .env file or cache.
     *
     * @return void
     */
    public static function loadFromDotEnv(): void
    {
        try {
            foreach ((array) @require_once base_path('/cache/env/env.php') as $key => $value) {
                static::set($key, $value);
            }
        } catch (Throwable) {
            error_clear_last();

            $lines = is_file(base_path('/.env'))
                ? file(base_path('/.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                : [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0) {
                    continue;
                }

                list($key, $value) = explode('=', $line, 2);
                static::set(trim($key), trim($value));
            }
        }
    }

    /**
     * Set init default value.
     *
     * @return void
     */
    public static function initDefaultValue(): void
    {
        static::set(
            static::HTTPS,
            static::get('HTTPS', 'false') === 'true' || request()->server->get('HTTPS', 'off') !== 'off' || intval(request()->server->get('SERVER_PORT', 80)) == 443
        );

        static::set(
            static::BASEURL,
            static::get('BASEURL') ? rtrim(static::get('BASEURL'), '/') : (static::get('_HTTPS') ? 'https://' : 'http://') . trim(request()->server->get('HTTP_HOST'))
        );

        static::set(
            static::DEBUG,
            static::get('DEBUG', 'false') === 'true'
        );
    }
}
