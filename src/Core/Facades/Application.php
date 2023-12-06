<?php

namespace Core\Facades;

use Closure;
use Core\Facades\Exception\NotFoundException;
use Core\Facades\Exception\ContainerException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Throwable;

/**
 * Aplikasi untuk menampung kumpulan objek yang bisa digunakan kembali serta
 * inject sebuah object kedalam fungsi.
 *
 * @class Application
 * @package \Core\Facades
 */
class Application implements ContainerInterface
{
    /**
     * Kumpulan objek ada disini.
     *
     * @var array<string, object|string> $objectPool
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
     * @throws ContainerException
     */
    private function build(string $name, array $default = []): object
    {
        try {
            $ref = new ReflectionClass($name);

            return new $name(...$this->getDependencies(
                $ref->getConstructor()?->getParameters() ?? [],
                $default
            ));
        } catch (ReflectionException $e) {
            throw new ContainerException($e->getMessage());
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
            /** @var \ReflectionNamedType|null */
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
     * @throws ContainerException
     */
    public function invoke(string|object $name, string $method, array $default = []): mixed
    {
        if (is_string($name)) {
            $name = $this->singleton($name);
        }

        try {
            $ref = new ReflectionClass($name);

            return $name->{$method}(...$this->getDependencies(
                $ref->getMethod($method)->getParameters(),
                $default
            ));
        } catch (ReflectionException $e) {
            throw new ContainerException($e->getMessage());
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
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException;
        }

        try {
            return $this->singleton($id);
        } catch (Throwable $th) {
            throw new ContainerException($th->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $abstract): bool
    {
        return !empty($this->objectPool[$abstract]);
    }

    /**
     * Inject objek pada suatu closure fungsi.
     *
     * @param Closure $name
     * @param array<int, mixed> $default
     * @return mixed
     *
     * @throws ContainerException
     */
    public function resolve(Closure $name, array $default = []): mixed
    {
        try {
            $ref = new ReflectionFunction($name);

            return $name(...$this->getDependencies(
                $ref->getParameters(),
                $default
            ));
        } catch (ReflectionException $e) {
            throw new ContainerException($e->getMessage());
        }
    }

    /**
     * Binding class/interface dengan class object.
     *
     * @param string $abstract
     * @param Closure|string $bind
     * @return void
     *
     * @throws ContainerException
     */
    public function bind(string $abstract, Closure|string $bind): void
    {
        if ($bind instanceof Closure) {
            $result = $this->resolve($bind, [$this]);

            if (!is_object($result)) {
                throw new ContainerException('Return value must be object!, returned:' . gettype($result));
            }

            $bind = $result;
        }

        $this->objectPool[$abstract] = $bind;
    }

    /**
     * Get attribute on function of object.
     *
     * @param object $abstract
     * @param string $function
     * @return array<int, \ReflectionAttribute>
     */
    public function getAttribute(object $abstract, string $function): array
    {
        $ref = new ReflectionClass($abstract);
        return $ref->getMethod($function)->getAttributes();
    }
}
