<?php

namespace Core\Routing;

use Core\Facades\App;
use Throwable;

/**
 * Helper class untuk routing url.
 *
 * @class Route
 * @package \Core\Routing
 */
final class Route
{
    /**
     * Indicate current route.
     *
     * @var array $route
     */
    public static $route;

    /**
     * Get current route.
     *
     * @return array
     */
    public static function &route(): array
    {
        return static::$route;
    }

    /**
     * Simpan url route get.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public static function get(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return static::router()->get($path, $action, $middleware);
    }

    /**
     * Simpan url route post.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public static function post(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return static::router()->post($path, $action, $middleware);
    }

    /**
     * Simpan url route put.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public static function put(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return static::router()->put($path, $action, $middleware);
    }

    /**
     * Simpan url route patch.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public static function patch(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return static::router()->patch($path, $action, $middleware);
    }

    /**
     * Simpan url route delete.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public static function delete(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return static::router()->delete($path, $action, $middleware);
    }

    /**
     * Simpan url route options.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public static function options(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return static::router()->options($path, $action, $middleware);
    }

    /**
     * Tambahkan middleware dalam url route.
     *
     * @param array|string $middlewares
     * @return Router
     */
    public static function middleware(array|string $middlewares): Router
    {
        return static::router()->middleware($middlewares);
    }

    /**
     * Tambahkan url lagi dalam route.
     *
     * @param string $prefix
     * @return Router
     */
    public static function prefix(string $prefix): Router
    {
        return static::router()->prefix($prefix);
    }

    /**
     * Tambahkan controller dalam route.
     *
     * @param string $name
     * @return Router
     */
    public static function controller(string $name): Router
    {
        return static::router()->controller($name);
    }

    /**
     * Isi url file route.
     *
     * @return void
     */
    public static function setRouteFromFile(): void
    {
        require_once base_path('/routes/routes.php');
    }

    /**
     * Isi url dari cache atau route.
     *
     * @return bool
     */
    public static function setRouteFromCacheIfExist(): bool
    {
        try {
            $route = (array) @require_once base_path('/cache/routes/routes.php');
            static::router()->setRoutes($route);
            return true;
        } catch (Throwable) {
            error_clear_last();
            return false;
        }
    }

    /**
     * Ambil objek router.
     *
     * @return Router
     */
    public static function router(): Router
    {
        return App::get()->singleton(Router::class);
    }
}
