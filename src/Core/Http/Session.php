<?php

namespace Core\Http;

use Core\Valid\Hash;

/**
 * Handle session.
 *
 * @class Session
 * @package \Core\Http
 */
class Session
{
    /**
     * Data session.
     *
     * @var Header $data
     */
    private $data;

    /**
     * Name session.
     *
     * @var string $name
     */
    private $name;

    /**
     * Expires session.
     *
     * @var int $expires
     */
    private $expires;

    /**
     * Object cookie.
     *
     * @var Cookie $cookie
     */
    private $cookie;

    /**
     * Csrf token key.
     *
     * @var string TOKEN
     */
    public const TOKEN = '__token';

    /**
     * Old route.
     *
     * @var string TOKEN
     */
    public const ROUTE = '__route';

    /**
     * Old session key.
     *
     * @var string OLD
     */
    public const OLD = '__old';

    /**
     * Error session key.
     *
     * @var string ERROR
     */
    public const ERROR = '__error';

    /**
     * Session id.
     *
     * @var string SESSID
     */
    public const SESSID = '__sessid';

    /**
     * Buat objek session.
     *
     * @param Cookie $cookie
     * @return void
     */
    public function __construct(Cookie $cookie)
    {
        $this->cookie = $cookie;
        $this->data = new Header();

        $this->name = env('APP_NAME', 'kamu') . static::SESSID;
        $this->expires = intval(env('COOKIE_LIFETIME', 120));

        if (env('COOKIE', 'true') == 'true') {
            $rawCookie = $cookie->get($this->name);
            if ($rawCookie) {
                $data = Hash::decrypt(rawurldecode($rawCookie));
                if ($data) {
                    $this->data = new Header((array) @unserialize($data));
                }
            }

            if (is_null($this->get(static::TOKEN))) {
                $this->set(static::TOKEN, Hash::rand(20));
            }
        }
    }

    /**
     * Get name cookie.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Send cookie header.
     *
     * @return void
     */
    public function send(): void
    {
        $this->cookie->set(
            $this->name,
            rawurlencode(Hash::encrypt(serialize($this->data->all()))),
            $this->expires
        );
    }

    /**
     * Ambil nilai dari sesi ini.
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

        return $this->__get($name) ?? $defaultValue;
    }

    /**
     * Isi nilai ke sesi ini.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, mixed $value): void
    {
        $this->data->set($name, $value);
    }

    /**
     * Hapus nilai dari sesi ini.
     *
     * @param string $name
     * @return void
     */
    public function unset(string $name): void
    {
        $this->data->unset($name);
    }

    /**
     * Ambil nilai dari sesi ini.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->data->get($name);
    }
}
