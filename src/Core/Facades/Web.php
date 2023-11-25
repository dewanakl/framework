<?php

namespace Core\Facades;

use Closure;
use Core\Http\Cookie;
use Core\Http\Exception\HttpException;
use Core\Http\Exception\NotAllowedException;
use Core\Http\Exception\NotFoundException;
use Core\Http\Exception\StreamTerminate;
use Core\Http\Respond;
use Core\Http\Session;
use Core\Http\Stream;
use Core\Middleware\Middleware;
use Core\Routing\Controller;
use Core\Routing\Route;
use Core\Support\Error;
use Core\Valid\Exception\ValidationException;
use ErrorException;
use Exception;
use Throwable;

class Web extends Service
{
    /**
     * Init object.
     *
     * @param Application $application
     * @return void
     */
    public function __construct(Application $application)
    {
        parent::__construct($application);
        $application->singleton(Cookie::class);
        $application->singleton(Session::class);
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
     * @return Respond|Stream
     *
     * @throws ErrorException
     */
    private function process(array $route, array $variables): Respond|Stream
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
     * Run route list.
     *
     * @return Respond|Stream
     *
     * @throws HttpException
     */
    private function runRoute(): Respond|Stream
    {
        $path = $this->request->getValidUrl();
        $method = $this->request->getValidMethod();

        $routeMatch = false;

        foreach (Route::router()->routes() as $route) {
            $pattern = '#^' . $route['path'] . '$#';
            $variables = [];

            if (preg_match($pattern, $path, $variables)) {
                $routeMatch = true;

                if ($route['method'] == $method) {
                    return $this->process($route, $variables);
                }
            }
        }

        if ($routeMatch) {
            if ($this->request->ajax()) {
                NotAllowedException::json();
            }

            throw new NotAllowedException();
        }

        if ($this->request->ajax()) {
            NotFoundException::json();
        }

        throw new NotFoundException();
    }

    /**
     * If throw HttpException handle it.
     *
     * @param HttpException $th
     * @return int
     */
    protected function handleHttpException(HttpException $th): int
    {
        try {
            $this->respond->send($this->respond->transform($th->__toString()));
        } catch (Throwable $th) {
            $this->respond->clean();
            $this->respond->send($this->respond->transform($this->handleError($th)));
        } finally {
            return 1;
        }
    }

    /**
     * Handle error app.
     *
     * @param Throwable $th
     * @return mixed
     */
    protected function handleError(Throwable $th): mixed
    {
        $error = $this->app->make($this->kernel->error());

        try {
            return $error->report($th)->render($this->request, $th);
        } catch (Throwable $th) {
            return (new Error())->setInformation($error->getInformation())->render($this->request, $th);
        }
    }

    /**
     * Jalankan servicenya.
     *
     * @return int
     */
    public function run(): int
    {
        try {
            if (!env('APP_KEY')) {
                throw new Exception('App Key gk ada !');
            }

            $this->bootingProviders();
            $this->respond->send($this->runRoute());

            return 0;
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

            $this->respond->send($this->respond->transform($this->handleError($th)));
            return 1;
        }
    }
}
