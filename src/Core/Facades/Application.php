<?php

namespace Core\Facades;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;

/**
 * Aplikasi untuk menampung kumpulan objek yang bisa digunakan kembali serta
 * inject sebuah object kedalam fungsi.
 *
 * @class Application
 * @package \Core\Facades
 */
class Application
{
    /**
     * Kumpulan objek ada disini.
     * 
     * @var array $objectPool
     */
    private $objectPool;

    /**
     * Buat objek application.
     *
     * @return void
     */
    public function __construct()
    {
        if ($this->objectPool === null) {
            $this->objectPool = [];
        }
    }

    /**
     * Inject pada constructor yang akan di buat objek.
     *
     * @param string $name
     * @param array $default
     * @return object
     * 
     * @throws Exception
     */
    private function build(string $name, array $default = []): object
    {
        try {
            $reflector = new ReflectionClass($name);

            $constructor = $reflector->getConstructor();
            $args = is_null($constructor) ? [] : $constructor->getParameters();

            return new $name(...$this->getDependencies($args, $default));
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Cek apa aja yang dibutuhkan untuk injek objek atau parameter.
     *
     * @param array $parameters
     * @param array $default
     * @return array
     */
    private function getDependencies(array $parameters, array $default = []): array
    {
        $args = [];
        $id = 0;

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $args[] = $this->singleton($type->getName());
                continue;
            }

            $args[] = $default[$id] ?? $parameter->getDefaultValue();
            $id++;
        }

        return $args;
    }

    /**
     * Bikin objek dari sebuah class lalu menyimpannya agar bisa dipake lagi.
     *
     * @param string $name
     * @param array $default
     * @return object
     */
    public function &singleton(string $name, array $default = []): object
    {
        if (empty($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->build($name, $default);
        }

        if (!is_object($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->build($this->objectPool[$name]);
        }

        return $this->objectPool[$name];
    }

    /**
     * Bikin objek dari sebuah class lalu gantikan dengan yang baru.
     *
     * @param string $name
     * @param array $default
     * @return object
     */
    public function &make(string $name, array $default = []): object
    {
        $this->clean($name);
        return $this->singleton($name, $default);
    }

    /**
     * Inject objek pada suatu fungsi yang akan di eksekusi.
     *
     * @param string|object $name
     * @param string $method
     * @param array $default
     * @return mixed
     * 
     * @throws Exception
     */
    public function invoke(string|object $name, string $method, array $default = []): mixed
    {
        if (!is_object($name)) {
            $name = $this->singleton($name);
        }

        try {
            $reflector = new ReflectionClass($name);
            $params = $this->getDependencies($reflector->getMethod($method)->getParameters(), $default);

            return $name->{$method}(...$params);
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Hapus dan dapatkan object itu terlebih dahulu.
     * 
     * @param string $name
     * @return object|null
     */
    public function clean(string $name): object|null
    {
        $object = $this->objectPool[$name] ?? null;
        $this->objectPool[$name] = null;
        unset($this->objectPool[$name]);
        return $object;
    }

    /**
     * Inject objek pada suatu closure fungsi.
     *
     * @param Closure $name
     * @param array $default
     * @return mixed
     * 
     * @throws Exception
     */
    public function resolve(Closure $name, array $default = []): mixed
    {
        try {
            $reflector = new ReflectionFunction($name);
            $arg = $reflector->getParameters();

            return $name(...$this->getDependencies($arg, $default));
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Binding class/interface dengan class object.
     *
     * @param string $abstract
     * @param Closure|string $class
     * @return void
     * 
     * @throws Exception
     */
    public function bind(string $abstract, Closure|string $class): void
    {
        if ($class instanceof Closure) {
            $result = $this->resolve($class, array($this));

            if (!is_object($result)) {
                throw new Exception('Return value harus sebuah object !');
            }

            $class = $result;
        }

        $this->objectPool[$abstract] = $class;
    }
}
