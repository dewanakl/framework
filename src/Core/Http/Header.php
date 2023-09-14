<?php

namespace Core\Http;

/**
 * Header http foundation.
 *
 * @class Header
 * @package \Core\Http
 */
class Header
{
    /**
     * Data from constructor.
     *
     * @var array $data
     */
    private $data;

    /**
     * Init object.
     *
     * @param array $data
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get all data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get data by key.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->__get($name) ?? $default;
    }

    /**
     * Check apakah ada?.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->__isset($key);
    }

    /**
     * Set data.
     *
     * @param string $key
     * @param mixed $value
     * @return Header
     */
    public function set(string $key, mixed $value): Header
    {
        $this->__set($key, $value);
        return $this;
    }

    /**
     * Hapus dari list ini.
     *
     * @param string $key
     * @return Header
     */
    public function unset(string $key): Header
    {
        if ($this->__isset($key)) {
            $this->data[$key] = null;
            unset($this->data[$key]);
        }

        return $this;
    }

    /**
     * Ambil sebagian dari ini.
     *
     * @param array $only
     * @return array
     */
    public function only(array $only): array
    {
        $temp = [];
        foreach ($only as $ol) {
            $temp[$ol] = $this->__get($ol);
        }

        return $temp;
    }

    /**
     * Ambil kecuali dari ini.
     *
     * @param array $except
     * @return array
     */
    public function except(array $except): array
    {
        $temp = [];
        foreach ($this->all() as $key => $value) {
            if (!in_array($key, $except)) {
                $temp[$key] = $value;
            }
        }

        return $temp;
    }

    /**
     * Set data.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /**
     * Get data by key.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->__isset($name) ? $this->data[$name] : null;
    }

    /**
     * Check if exist.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return !empty($this->data[$name]);
    }
}
