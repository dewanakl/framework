<?php

namespace Core\Facades;

use Core\Http\Request;
use Core\Http\Respond;
use Core\Kernel\KernelContract;
use Core\Support\Env;
use Exception;

/**
 * Class untuk menjalankan servic web atau cli.
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
    protected $request;

    /**
     * Objek respond disini.
     *
     * @var Respond $respond
     */
    protected $respond;

    /**
     * Objek kernel disini.
     *
     * @var KernelContract $kernel
     */
    protected $kernel;

    /**
     * Objek application disini.
     *
     * @var Application $app
     */
    protected $app;

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

        Env::initDefaultValue();
    }

    /**
     * Eksekusi booting provider.
     *
     * @return void
     */
    protected function bootingProviders(): void
    {
        foreach ($this->kernel->services() as $service) {
            $servis = $this->app->make($service);

            if (!($servis instanceof Provider)) {
                throw new Exception(sprintf('Class "%s" is not part of the provider class', get_class($servis)));
            }

            $this->app->invoke($servis, Provider::BOOTING);
        }
    }

    /**
     * Eksekusi register provider.
     *
     * @return void
     */
    protected function registerProvider(): void
    {
        foreach ($this->kernel->services() as $service) {
            $this->app->invoke($service, Provider::REGISTRASI);
            $this->app->clean($service);
        }
    }

    /**
     * Jalankan servicenya.
     *
     * @return int
     */
    public function run(): int
    {
        return 0;
    }
}
