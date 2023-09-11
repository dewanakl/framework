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
     * @var array<int, object|string> $objectPool
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
     * @param array<int, mixed> $default
     * @return object
     *
     * @throws Exception
     */
    private function build(string $name, array $default = []): object
    {
        try {
            return new $name(...$this->getDependencies(
                (new ReflectionClass($name))->getConstructor()?->getParameters() ?? [],
                $default
            ));
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Cek apa aja yang dibutuhkan untuk injek objek atau parameter.
     *
     * @param array<int, \ReflectionParameter> $parameters
     * @param array<int, mixed> $default
     * @return array<int, mixed>
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
     * @param array<int, mixed> $default
     * @return object
     */
    public function &singleton(string $name, array $default = []): object
    {
        if (empty($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->build($name, $default);
        }

        if (is_string($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->build($this->objectPool[$name]);
        }

        return $this->objectPool[$name];
    }

    /**
     * Bikin objek dari sebuah class lalu gantikan dengan yang baru.
     *
     * @param string $name
     * @param array<int, mixed> $default
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
     * @param array<int, mixed> $default
     * @return mixed
     *
     * @throws Exception
     */
    public function invoke(string|object $name, string $method, array $default = []): mixed
    {
        if (is_string($name)) {
            $name = $this->singleton($name);
        }

        try {
            return $name->{$method}(...$this->getDependencies(
                (new ReflectionClass($name))->getMethod($method)->getParameters(),
                $default
            ));
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Hapus dan dapatkan object itu terlebih dahulu.
     *
     * @param string $name
     * @return void
     */
    public function clean(string $name): void
    {
        $this->objectPool[$name] = null;
        unset($this->objectPool[$name]);
    }

    /**
     * Check if object exist.
     *
     * @param string $abstract
     * @return bool
     */
    public function isset(string $abstract): bool
    {
        return !empty($this->objectPool[$abstract]);
    }

    /**
     * Inject objek pada suatu closure fungsi.
     *
     * @param Closure:mixed $name
     * @param array<int, mixed> $default
     * @return mixed
     *
     * @throws Exception
     */
    public function resolve(Closure $name, array $default = []): mixed
    {
        try {
            return $name(...$this->getDependencies(
                (new ReflectionFunction($name))->getParameters(),
                $default
            ));
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Binding class/interface dengan class object.
     *
     * @param string $abstract
     * @param Closure|string $bind
     * @return void
     *
     * @throws Exception
     */
    public function bind(string $abstract, Closure|string $bind): void
    {
        if ($bind instanceof Closure) {
            $result = $this->resolve($bind, [$this]);

            if (!is_object($result)) {
                throw new Exception('Return value must be object!, returned:' . gettype($result));
            }

            $bind = $result;
        }

        $this->objectPool[$abstract] = $bind;
    }
}
