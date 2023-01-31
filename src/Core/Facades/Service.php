<?php

namespace Core\Facades;

use App\Kernel;
use Core\Http\Request;
use Core\Http\Respond;
use Core\Middleware\Middleware;
use Core\Routing\Route;

/**
 * Class untuk menjalankan middleware dan controller.
 *
 * @class Service
 * @package \Core\Facades
 */
class Service
{
    /**
     * Objek request disini.
     * 
     * @var Request $request
     */
    private $request;

    /**
     * Objek respond disini.
     * 
     * @var Respond $respond
     */
    private $respond;

    /**
     * Buat objek service.
     *
     * @param Request $request
     * @param Respond $respond
     * @return void
     */
    function __construct(Request $request, Respond $respond)
    {
        $this->request = $request;
        $this->respond = $respond;
        $this->bootingProviders();
    }

    /**
     * Eksekusi booting provider.
     *
     * @return void
     */
    private function bootingProviders(): void
    {
        $services = App::get()->singleton(Kernel::class)->services();

        foreach ($services as $service) {
            App::get()->make($service)->booting();
        }
    }

    /**
     * Eksekusi register provider.
     *
     * @return void
     */
    private function registerProvider(): void
    {
        $services = App::get()->singleton(Kernel::class)->services();

        foreach ($services as $service) {
            App::get()->clean($service)->registrasi();
        }
    }

    /**
     * Eksekusi middlewarenya.
     *
     * @param array $middlewares
     * @return void
     */
    private function invokeMiddleware(array $middlewares): void
    {
        $middlewarePool = array_map(fn ($middleware) => new $middleware, $middlewares);

        $middleware = new Middleware($middlewarePool);
        $middleware->handle($this->request);

        unset($middleware);
        unset($middlewarePool);
    }

    /**
     * Eksekusi controllernya.
     *
     * @param array $route
     * @param array $variables
     * @return void
     */
    private function invokeController(array $route, array $variables): void
    {
        $controller = $route['controller'];
        $method = $route['function'];
        array_shift($variables);

        if (is_null($controller)) {
            $controller = $method;
            $method = '__invoke';
        }

        $this->respond->send(App::get()->invoke($controller, $method, $variables));
    }

    /**
     * Jalankan servicenya.
     *
     * @return int
     */
    public function run(): int
    {
        $path = parse_url($this->request->server('REQUEST_URI'), PHP_URL_PATH);
        $method = strtoupper($this->request->method() == 'POST'
            ? $this->request->get('_method', 'POST')
            : $this->request->method());

        $routeMatch = false;
        $methodMatch = false;

        $this->invokeMiddleware(App::get()->singleton(Kernel::class)->middlewares());

        if ($method === 'OPTIONS') {
            http_response_code(200);
            header('HTTP/1.1 200 OK', true, 200);
            return 0;
        }

        foreach (Route::router()->routes() as $route) {
            $pattern = '#^' . $route['path'] . '$#';
            $variables = [];

            if (preg_match($pattern, $path, $variables)) {
                $routeMatch = true;
                if ($method === $route['method']) {
                    $methodMatch = true;

                    $this->invokeMiddleware($route['middleware']);
                    $this->registerProvider();
                    $this->invokeController($route, $variables);

                    return 0;
                }
            }
        }

        if ($routeMatch && !$methodMatch) {
            if ($this->request->ajax()) {
                $this->respond->send(json([
                    'error' => 'Method Not Allowed 405'
                ], 405));
            }

            notAllowed();
        } else if (!$routeMatch) {
            if ($this->request->ajax()) {
                $this->respond->send(json([
                    'error' => 'Not Found 404'
                ], 404));
            }

            notFound();
        }

        return 0;
    }
}
