<?php

namespace Core\Facades;

use App\Kernel as AppKernel;
use Closure;
use Core\Http\Request;
use Core\Http\Respond;
use Core\Middleware\Middleware;
use Core\Routing\Route;
use Exception;
use Throwable;

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
     * @return void
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->request = App::get()->singleton(Request::class);
        $this->respond = App::get()->singleton(Respond::class);

        $this->setDefaultEnv();
        $this->setExceptionHandler();

        Kernel::setTimezone();
        if (!env('APP_KEY')) {
            throw new Exception('App Key gk ada !');
        }

        $this->bootingProviders();
    }

    /**
     * Handle this app exception.
     *
     * @return void
     */
    private function setExceptionHandler(): void
    {
        error_reporting(debug() ? E_ALL : 0);
        set_exception_handler(function (Throwable $error): void {
            if (debug()) {
                trace($error);
            }

            unavailable();
        });
    }

    /**
     * Set default env to function.
     *
     * @return void
     */
    private function setDefaultEnv(): void
    {
        $_ENV['_HTTPS'] = env('HTTPS') == 'true' || $this->request->server('HTTPS', 'off') !== 'off' || $this->request->server('SERVER_PORT') == '443';
        $_ENV['_BASEURL'] = env('BASEURL') ? rtrim(env('BASEURL'), '/') : (https() ? 'https://' : 'http://') . trim($this->request->server('HTTP_HOST'));
        $_ENV['_DEBUG'] = env('DEBUG') == 'true';
    }

    /**
     * Eksekusi booting provider.
     *
     * @return void
     */
    private function bootingProviders(): void
    {
        $services = App::get()->singleton(AppKernel::class)->services();

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
        $services = App::get()->singleton(AppKernel::class)->services();

        foreach ($services as $service) {
            App::get()->clean($service)->registrasi();
        }
    }

    /**
     * Eksekusi core middleware.
     *
     * @param array $route
     * @param array $variables
     * @return Closure
     */
    private function coreMiddleware(array $route, array $variables): Closure
    {
        return function () use ($route, $variables): mixed {
            $this->registerProvider();
            return $this->invokeController($route, $variables);
        };
    }

    /**
     * Process middleware, provider, and controller.
     *
     * @param array $route
     * @param array $variables
     * @return int
     */
    private function process(array $route, array $variables): int
    {
        $middleware = new Middleware([
            ...App::get()->singleton(AppKernel::class)->middlewares(),
            ...$route['middleware']
        ]);

        $this->respond->send($middleware->handle($this->request, $this->coreMiddleware($route, $variables)));

        return 0;
    }

    /**
     * Eksekusi controllernya.
     *
     * @param array $route
     * @param array $variables
     * @return mixed
     */
    private function invokeController(array $route, array $variables): mixed
    {
        $controller = $route['controller'];
        $function = $route['function'];

        if ($function === null) {
            return null;
        }

        if ($controller === null) {
            $controller = $function;
            $function = '__invoke';
        }

        array_shift($variables);
        return App::get()->invoke($controller, $function, $variables);
    }

    /**
     * Tangani permintaan yang diluar dari route.
     *
     * @return int
     */
    private function handleOutOfRoute(bool $routeMatch): int
    {
        if ($routeMatch) {
            if (!$this->request->ajax()) {
                notAllowed();
            }

            $this->respond->send(json([
                'error' => 'Method Not Allowed 405'
            ], 405));

            return 0;
        }

        if (!$this->request->ajax()) {
            notFound();
        }

        $this->respond->send(json([
            'error' => 'Not Found 404'
        ], 404));

        return 0;
    }

    /**
     * Get valid url based on baseurl.
     *
     * @return string
     */
    private function getValidUrl(): string
    {
        $sep = explode($this->request->server('HTTP_HOST'), baseurl(), 2)[1];
        if (empty($sep)) {
            return $this->request->server('REQUEST_URI');
        }

        $raw = explode($sep, $this->request->server('REQUEST_URI'), 2)[1];
        if (!empty($raw)) {
            return $raw;
        }

        return '/';
    }

    /**
     * Jalankan servicenya.
     *
     * @return int
     */
    public function run(): int
    {
        $url = $this->getValidUrl();
        $path = parse_url($url, PHP_URL_PATH);
        $this->request->__set('REQUEST_URL', $url);

        $method = $this->request->method() === 'POST'
            ? strtoupper($this->request->get('_method', 'POST'))
            : $this->request->method();

        $routeMatch = false;

        foreach (Route::router()->routes() as $route) {
            $pattern = '#^' . $route['path'] . '$#';
            $variables = [];

            if (preg_match($pattern, $path, $variables)) {
                $routeMatch = true;

                if ($route['method'] === $method) {
                    return $this->process($route, $variables);
                }
            }
        }

        return $this->handleOutOfRoute($routeMatch);
    }
}
