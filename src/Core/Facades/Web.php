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
use Core\Middleware\MiddlewareInterface;
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
     * Eksekusi controller.
     *
     * @param object|null $controller
     * @param string|null $function
     * @return Closure
     */
    private function coreMiddleware(object|null $controller = null, string|null $function = null): Closure
    {
        return function () use ($controller, $function): mixed {
            if ($function === null || $controller === null) {
                return null;
            }

            return $this->app->invoke(
                $controller,
                $function,
                array_values($this->request->route())
            );
        };
    }

    /**
     * Process middleware and controller.
     *
     * @param array<string, mixed> $route
     * @return Respond|Stream
     *
     * @throws ErrorException
     */
    private function process(array $route): Respond|Stream
    {
        $controller = $route['controller'];
        $function = $route['function'];

        if ($controller === null) {
            $controller = $function;
            $function = '__invoke';
        }

        if ($controller) {
            $controller = $this->app->singleton($controller);
            if (!($controller instanceof Controller)) {
                throw new Exception(sprintf('Class "%s" is not extends BaseController.', get_class($controller)));
            }
        }

        $attributeMiddleware = [];
        if ($controller && $function) {
            foreach ($this->app->getAttribute($controller, $function) as $value) {
                $name = $value->getName();
                $object = new $name();

                if ($object instanceof MiddlewareInterface) {
                    $attributeMiddleware[] = $object;
                }
            }
        }

        $middleware = new Middleware([
            ...$this->kernel->middlewares(),
            ...$route['middleware'],
            ...$attributeMiddleware
        ]);

        $result = $middleware->handle(
            $this->request,
            $this->coreMiddleware($controller, $function)
        );

        $error = error_get_last();
        if ($error !== null) {
            error_clear_last();
            throw new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
        }

        return $result;
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

            $params = [];
            $variables = [];

            preg_match_all('/{(\w+)}/', $route['path'], $params);
            $pattern = '#^' . preg_replace('/{(\w+)}/', '([\w-]*)', $route['path']) . '$#';

            if (preg_match($pattern, $path, $variables)) {
                $routeMatch = true;

                if ($route['method'] == $method) {
                    array_shift($variables);
                    $route['params'] = array_combine($params[1], $variables);

                    Route::$route = $route;
                    $this->registerProvider();
                    return $this->process($route);
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
        try {
            $kernel = $this->kernel->error();
            $kernelError = new $kernel($th);
            $kernelError->report();

            // Force close stream.
            $kernelError->__destruct();
            return $kernelError->render($th);
        } catch (Throwable $th) {
            $error = new Error($th);
            return $error->report()->render($th);
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
