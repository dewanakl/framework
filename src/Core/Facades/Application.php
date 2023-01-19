<?php

namespace Core\Facades;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

/**
 * Aplikasi untuk menampung kumpulan objek yang bisa digunakan kembali serta
 * inject sebuah object kedalam fungsi
 *
 * @class Application
 * @package \Core\Facades
 */
class Application
{
    /**
     * Kumpulan objek ada disini gaes
     * 
     * @var array $objectPool
     */
    private $objectPool;

    /**
     * Buat objek application
     *
     * @return void
     */
    function __construct()
    {
        if (is_null($this->objectPool)) {
            $this->objectPool = [];
        }
    }

    /**
     * Inject pada constructor yang akan di buat objek
     *
     * @param string $name
     * @param array $param
     * @return object|null
     * 
     * @throws Exception
     */
    private function getConstructor(string $name, array $param = []): object|null
    {
        $result = null;

        try {
            $reflector = new ReflectionClass($name);

            $constructor = $reflector->getConstructor();
            $args = is_null($constructor) ? null : $constructor->getParameters();

            $result = new $name(...$this->getDependencies($args, $param));
            unset($reflector);
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * Cek apa aja yang dibutuhkan untuk injek objek atau parameter
     *
     * @param mixed $parameters
     * @param array $value
     * @return array
     */
    private function getDependencies(mixed $parameters = null, array $value = []): array
    {
        $args = [];
        $id = 0;

        if (!$parameters) {
            return $args;
        }

        foreach ($parameters as $parameter) {
            if ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
                $args[] = $this->singleton($parameter->getType()->getName());
            } else {
                $args[] = $value[$id] ?? $parameter->getDefaultValue();
                $id++;
            }
        }

        return $args;
    }

    /**
     * Bikin objek dari sebuah class lalu menyimpannya
     *
     * @param string $name
     * @param array $param
     * @return object
     */
    public function singleton(string $name, array $param = []): object
    {
        if (empty($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->getConstructor($name, $param);
        }

        if (!is_object($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->getConstructor($this->objectPool[$name]);
        }

        return $this->objectPool[$name];
    }

    /**
     * Bikin objek dari sebuah class lalu gantikan dengan yang lama
     *
     * @param string $name
     * @param array $param
     * @return object
     */
    public function make(string $name, array $param = []): object
    {
        $this->objectPool[$name] = $this->getConstructor($name, $param);

        return $this->objectPool[$name];
    }

    /**
     * Inject objek pada suatu fungsi yang akan di eksekusi
     *
     * @param string|object $name
     * @param string $method
     * @param array $value
     * @return mixed
     * 
     * @throws Exception
     */
    public function invoke(string|object $name, string $method, array $value = []): mixed
    {
        if (!is_object($name)) {
            $name = $this->singleton($name);
        }

        $result = null;

        try {
            $reflector = new ReflectionClass($name);
            $params = $this->getDependencies($reflector->getMethod($method)->getParameters(), $value);
            $result = $name->{$method}(...$params);
            unset($reflector);
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * Hapus dan dapatkan object itu terlebih dahulu
     * 
     * @param string $name
     * @return mixed
     */
    public function clean(string $name): mixed
    {
        $object = $this->objectPool[$name] ?? null;
        unset($this->objectPool[$name]);
        return $object;
    }

    /**
     * Inject objek pada suatu closure fungsi
     *
     * @param Closure $name
     * @param array $param
     * @return mixed
     * 
     * @throws Exception
     */
    public function resolve(Closure $name, array $param = []): mixed
    {
        $result = null;

        try {
            $reflector = new ReflectionFunction($name);
            $result = $name(...$this->getDependencies($reflector->getParameters(), $param));
            unset($reflector);
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), 0, $e);
        }

        return $result;
    }

    /**
     * Binding interface dengan class object
     *
     * @param string $interface
     * @param Closure|string $class
     * @return void
     * 
     * @throws Exception
     */
    public function bind(string $interface, Closure|string $class): void
    {
        if ($class instanceof Closure) {
            $result = $this->resolve($class, array($this));

            if (!is_object($result)) {
                throw new Exception('Return value harus sebuah object !');
            }

            $class = $result;
        }

        $this->objectPool[$interface] = $class;
    }
}
