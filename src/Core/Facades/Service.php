<?php

namespace Core\Facades;

use Closure;
use Core\Http\Exception\HttpException;
use Core\Http\Exception\NotAllowedException;
use Core\Http\Exception\NotFoundException;
use Core\Http\Exception\StreamTerminate;
use Core\Http\Cookie;
use Core\Http\Request;
use Core\Http\Respond;
use Core\Http\Session;
use Core\Kernel\KernelContract;
use Core\Middleware\Middleware;
use Core\Routing\Controller;
use Core\Routing\Route;
use Core\Support\Env;
use Core\Support\Error;
use Core\Valid\Exception\ValidationException;
use ErrorException;
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
     * Objek kernel disini.
     *
     * @var KernelContract $kernel
     */
    private $kernel;

    /**
     * Objek application disini.
     *
     * @var Application $app
     */
    private $app;

    /**
     * Buat objek service.
     *
     * @param Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->kernel = $this->app->singleton(KernelContract::class);
        $this->request = $this->app->singleton(Request::class);
        $this->respond = $this->app->singleton(Respond::class);

        $this->app->singleton(Cookie::class);
        $this->app->singleton(Session::class);

        Env::initDefaultValue();
    }

    /**
     * Eksekusi booting provider.
     *
     * @return void
     */
    private function bootingProviders(): void
    {
        foreach ($this->kernel->services() as $service) {
            $servis = $this->app->make($service);

            if (!($servis instanceof Provider)) {
                throw new Exception(sprintf('Class "%s" is not part of the provider class', get_class($servis)));
            }

            $servis->booting();
        }
    }

    /**
     * Eksekusi register provider.
     *
     * @return void
     */
    private function registerProvider(): void
    {
        foreach ($this->kernel->services() as $service) {
            $this->app->singleton($service)->registrasi();
            $this->app->clean($service);
        }
    }

    /**
     * Eksekusi core middleware.
     *
     * @param array<string, mixed> $route
     * @param array<int, mixed> $variables
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
     * @param array<string, mixed> $route
     * @param array<int, mixed> $variables
     * @return mixed
     *
     * @throws ErrorException
     */
    private function process(array $route, array $variables): mixed
    {
        $middleware = new Middleware([
            ...$this->kernel->middlewares(),
            ...$route['middleware']
        ]);

        $result = $middleware->handle($this->request, $this->coreMiddleware($route, $variables));

        $error = error_get_last();
        if ($error !== null) {
            error_clear_last();
            throw new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        }

        return $result;
    }

    /**
     * Eksekusi controllernya.
     *
     * @param array<string, mixed> $route
     * @param array<int, mixed> $variables
     * @return mixed
     *
     * @throws Exception
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

        $controller = $this->app->singleton($controller);
        if (!($controller instanceof Controller)) {
            throw new Exception(sprintf('Class "%s" is not extends BaseController.', get_class($controller)));
        }

        $parameters = [];
        for ($i = 1; $i < count($variables); $i++) {
            $parameters[] = $variables[$i];
        }

        return $this->app->invoke($controller, $function, $parameters);
    }

    /**
     * Handle error app.
     *
     * @param Throwable $th
     * @return mixed
     */
    private function handleError(Throwable $th): mixed
    {
        $error = $this->app->make($this->kernel->error());

        try {
            return $error->report($th)->render($this->request, $th);
        } catch (Throwable $th) {
            return (new Error())->setInformation($error->getInformation())->render($this->request, $th);
        }
    }

    /**
     * If throw HttpException handle it.
     *
     * @param HttpException $th
     * @return int
     */
    private function handleHttpException(HttpException $th): int
    {
        $result = null;

        try {
            $result = $th->__toString();
        } catch (Throwable $th) {
            $this->respond->clean();

            $result = $this->handleError($th);

            $this->respond->setCode(500);
        } finally {
            $this->respond->send($result);
            return 1;
        }
    }

    /**
     * Run route list.
     *
     * @param string $path
     * @param string $method
     * @return mixed
     *
     * @throws HttpException
     */
    private function runRoute(string $path, string $method): mixed
    {
        $result = null;
        $routeMatch = false;
        $methodMatch = false;

        foreach (Route::router()->routes() as $route) {
            $pattern = '#^' . $route['path'] . '$#';
            $variables = [];

            if (preg_match($pattern, $path, $variables)) {
                $routeMatch = true;

                if ($route['method'] == $method) {
                    $methodMatch = true;
                    $result = $this->process($route, $variables);
                    break;
                }
            }
        }

        if ($routeMatch && !$methodMatch) {
            if ($this->request->ajax()) {
                NotAllowedException::json();
            }

            throw new NotAllowedException();
        }

        if (!$routeMatch) {
            if ($this->request->ajax()) {
                NotFoundException::json();
            }

            throw new NotFoundException();
        }

        return $result;
    }

    /**
     * Get valid url based on baseurl.
     *
     * @return string
     */
    private function getValidUrl(): string
    {
        $url = '/';
        $host = $this->request->server->get('HTTP_HOST');
        $uri = $this->request->server->get('REQUEST_URI');
        $sep = strpos(base_url(), $host);

        if ($sep === false) {
            $url = $uri;
        } else {
            $sep = substr(base_url(), strlen($host) + $sep);
            $raw = strpos($uri, $sep);
            if ($raw !== false) {
                $url = substr($uri, strlen($sep) + $raw);
            }
        }

        $this->request->server->set('REQUEST_URL', $url);
        return parse_url($url, PHP_URL_PATH);
    }

    /**
     * Pastikan methodnya betul.
     *
     * @return string
     */
    private function getValidMethod(): string
    {
        return !$this->request->ajax() && $this->request->method(Request::POST)
            ? strtoupper($this->request->get(Request::METHOD, $this->request->method()))
            : $this->request->method();
    }

    /**
     * Jalankan servicenya.
     *
     * @return int
     */
    public function run(): int
    {
        $result = null;

        try {
            if (!env('APP_KEY')) {
                throw new Exception('App Key gk ada !');
            }

            $this->bootingProviders();
            $result = $this->runRoute($this->getValidUrl(), $this->getValidMethod());
        } catch (Throwable $th) {
            // Force respond exit.
            if ($th instanceof StreamTerminate || $th instanceof ValidationException) {
                $this->respond->prepare();
                return 0;
            }

            // Ensure clean all output before send error message.
            $this->respond->clean();

            if ($th instanceof HttpException) {
                return $this->handleHttpException($th);
            }

            $result = $this->handleError($th);

            $this->respond->setCode(500);
            $this->respond->send($result);
            return 1;
        }

        $this->respond->send($result);
        return 0;
    }
}
