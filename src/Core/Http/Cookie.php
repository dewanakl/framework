<?php

namespace Core\Http;

use DateTimeInterface;

/**
 * Handle http cookie.
 *
 * @class Cookie
 * @package \Core\Http
 */
class Cookie
{
    /**
     * Data cookie.
     *
     * @var Header $data
     */
    private $data;

    /**
     * Send cookie
     *
     * @var array<int, string> $queue
     */
    private $queue;

    /**
     * Buat objek cookie.
     *
     * @return void
     */
    public function __construct()
    {
        $this->data = new Header($_COOKIE);
        $this->queue = [];
    }

    /**
     * Send cookie header.
     *
     * @return array
     */
    public function send(): array
    {
        return env('COOKIE', 'true') == 'true' ? $this->queue : [];
    }

    /**
     * Ambil nilai dari cookie ini.
     *
     * @param string|null $name
     * @param mixed $defaultValue
     * @return mixed
     */
    public function get(string|null $name = null, mixed $defaultValue = null): mixed
    {
        if ($name === null) {
            return $this->data->all();
        }

        return $this->data->get($name, $defaultValue);
    }

    /**
     * Isi nilai ke cookie ini.
     *
     * @param string $name
     * @param string $value
     * @param int $minutes
     * @param string|null $path
     * @param string|null $domain
     * @param bool|null $secure
     * @param bool $httpOnly
     * @param string $sameSite
     * @return void
     */
    public function set(string $name, string $value = '', int $minutes = 0, string|null $path = null, string|null $domain = null, bool|null $secure = null, bool $httpOnly = true, string $sameSite = 'Lax'): void
    {
        $header = sprintf('%s=%s', $name, $value);

        $expires = $minutes * 60;
        $header .= '; Expires=' . gmdate(DateTimeInterface::RFC7231, $expires + time());
        $header .= '; Max-Age=' . strval($expires);
        $header .= '; Path=' . ($path ? $path : '/');
        $header .= '; Domain=' . ($domain ? $domain : parse_url(base_url(), PHP_URL_HOST));

        if (($secure === null && https()) || $secure) {
            $header .= '; Secure';
        }

        if ($httpOnly) {
            $header .= '; HttpOnly';
        }

        $header .= '; SameSite=' . $sameSite;
        $this->queue[] = $header;
    }

    /**
     * Hapus nilai dari cookie ini.
     *
     * @param string $name
     * @return void
     */
    public function unset(string $name): void
    {
        $this->set($name);
    }
}
