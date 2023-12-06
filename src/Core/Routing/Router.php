<?php

namespace Core\Routing;

use Closure;
use Core\Http\Request;

/**
 * Class untuk routing dan mengelompokan url.
 *
 * @class Router
 * @package \Core\Routing
 */
class Router
{
    /**
     * Simpan semua routenya disini.
     *
     * @var array $routes
     */
    private $routes;

    /**
     * Jika ada controller group.
     *
     * @var string|null $controller
     */
    private $controller;

    /**
     * Jika ada prefix group.
     *
     * @var string|null $prefix
     */
    private $prefix;

    /**
     * Untuk middleware group.
     *
     * @var array $middleware
     */
    private $middleware;

    /**
     * Init object.
     *
     * @return void
     */
    public function __construct()
    {
        $this->routes = [];
        $this->middleware = [];
    }

    /**
     * Simpan urlnya.
     *
     * @param string $method
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    private function add(string $method, string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        if (is_array($action)) {
            $controller = $action[0];
            $function = $action[1];
        } else {
            $controller = null;
            $function = $action;
        }

        $middleware = is_null($middleware) ? [] : (is_string($middleware) ? array($middleware) : $middleware);

        $idroute = count($this->routes);
        $this->routes[] = [
            'id' => $idroute == 0 ? 0 : $this->routes[$idroute - 1]['id'] + 1,
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'function' => $function,
            'middleware' => $middleware,
            'name' => null
        ];

        return $this;
    }

    /**
     * Simpan url route get.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public function get(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return $this->add(Request::GET, $path, $action, $middleware);
    }

    /**
     * Simpan url route post.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public function post(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return $this->add(Request::POST, $path, $action, $middleware);
    }

    /**
     * Simpan url route put.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public function put(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return $this->add(Request::PUT, $path, $action, $middleware);
    }

    /**
     * Simpan url route patch.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public function patch(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return $this->add(Request::PATCH, $path, $action, $middleware);
    }

    /**
     * Simpan url route delete.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public function delete(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return $this->add(Request::DELETE, $path, $action, $middleware);
    }

    /**
     * Simpan url route options.
     *
     * @param string $path
     * @param array|string|null $action
     * @param array|string|null $middleware
     * @return Router
     */
    public function options(string $path, array|string|null $action = null, array|string|null $middleware = null): Router
    {
        return $this->add(Request::OPTIONS, $path, $action, $middleware);
    }

    /**
     * Tambahkan middleware dalam url route.
     *
     * @param array|string $middlewares
     * @return Router
     */
    public function middleware(array|string $middlewares): Router
    {
        if (is_string($middlewares)) {
            $middlewares = array($middlewares);
        }

        $this->middleware = $middlewares;
        return $this;
    }

    /**
     * Tambahkan url lagi dalam route.
     *
     * @param string $prefix
     * @return Router
     */
    public function prefix(string $prefix): Router
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Tambahkan controller dalam route.
     *
     * @param string $name
     * @return Router
     */
    public function controller(string $name): Router
    {
        $this->controller = $name;
        return $this;
    }

    /**
     * Tambahkan nama url.
     *
     * @param string $name
     * @return void
     */
    public function name(string $name): void
    {
        $id = count($this->routes) - 1;
        $this->routes[$id]['name'] = $name;
    }

    /**
     * Ambil url yang ada.
     *
     * @return array
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Isi semua url yang ada.
     *
     * @param array $route
     * @return void
     */
    public function setRoutes(array $route): void
    {
        $this->routes = $route;
    }

    /**
     * Kelompokan routenya.
     *
     * @param Closure $group
     * @return void
     */
    public function group(Closure $group): void
    {
        $tempController = $this->controller;
        $tempPrefix = $this->prefix;
        $tempMiddleware = $this->middleware;
        $tempRoutes = $this->routes;

        $this->controller = null;
        $this->prefix = null;
        $this->middleware = [];

        $group();

        foreach ($this->routes as $id => $route) {
            if (!in_array($route, $tempRoutes, true)) {

                if (!is_null($tempController)) {
                    $old = $this->routes[$id]['controller'];
                    $this->routes[$id]['controller'] = is_null($old) ? $tempController : $old;
                }

                if (!is_null($tempPrefix)) {
                    $old = $this->routes[$id]['path'];
                    $this->routes[$id]['path'] = ($old != '/') ? $tempPrefix . $old : $tempPrefix;
                }

                if (!empty($tempMiddleware)) {
                    $result = empty($this->middleware) ? $tempMiddleware : $this->middleware;
                    $this->routes[$id]['middleware'] = [...$result, ...$this->routes[$id]['middleware']];
                }
            }
        }

        $this->controller = null;
        $this->prefix = null;
        $this->middleware = [];
    }
}
