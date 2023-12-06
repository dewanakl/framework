<?php

namespace Core\Middleware;

use Closure;
use Core\Http\Request;

/**
 * Middleware - cek dahulu sebelum ke controller.
 *
 * @class Middleware
 * @package \Core\Middleware
 * @see https://github.com/esbenp/onion
 */
class Middleware
{
    /**
     * Kumpulan objek middleware ada disini.
     *
     * @var array<int, MiddlewareInterface> $layers
     */
    private $layers;

    /**
     * Buat objek middleware.
     *
     * @param array<int, class-string<MiddlewareInterface>|MiddlewareInterface> $layers
     * @return void
     */
    public function __construct(array $layers = [])
    {
        for ($i = (count($layers) - 1); $i >= 0; $i--) {
            $this->layers[] = is_object($layers[$i]) ? $layers[$i] : new $layers[$i];
        }
    }

    /**
     * Buat lapisan perlayer untuk eksekusi.
     *
     * @param Closure $nextLayer
     * @param MiddlewareInterface $layer
     * @return Closure
     */
    private function createLayer(Closure $nextLayer, MiddlewareInterface $layer): Closure
    {
        return function (Request $request) use ($nextLayer, $layer): mixed {
            return $layer->handle($request, $nextLayer);
        };
    }

    /**
     * Handle semua dari layer middleware.
     *
     * @param Request $request
     * @param Closure $core
     * @return mixed
     */
    public function handle(Request $request, Closure $core): mixed
    {
        $next = array_reduce(
            $this->layers,
            function (Closure $nextLayer, MiddlewareInterface $layer): Closure {
                return $this->createLayer($nextLayer, $layer);
            },
            $core
        );

        return $next($request);
    }
}
