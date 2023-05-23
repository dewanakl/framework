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
     * @var array $data
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
     * Buat objek session.
     *
     * @return void
     */
    public function __construct()
    {
        $this->name = (https() ? '__Secure-' : '') . env('APP_NAME', 'kamu') . '_sessid';
        $this->expires = intval(env('COOKIE_LIFETIME', 86400)) + time();
        $this->data = [];

        if (env('COOKIE', 'true') == 'true') {
            if (@$_COOKIE[$this->name]) {
                $this->data = @unserialize(Hash::decrypt(rawurldecode($_COOKIE[$this->name])));
            }

            if (is_null($this->get('_token'))) {
                $this->set('_token', Hash::rand(20));
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
        if (env('COOKIE', 'true') == 'true') {
            $header = 'Set-Cookie: ' . $this->name . '=' . rawurlencode(Hash::encrypt(serialize($this->data)));

            $header .= '; Expires=' . date('D, d-M-Y H:i:s', $this->expires) . ' GMT';
            $header .= '; Max-Age=' . strval($this->expires - time());
            $header .= '; Path=/';
            $header .= '; Domain=' . parse_url(baseurl(), PHP_URL_HOST);

            if (https()) {
                $header .= '; Secure';
            }

            $header .= '; HttpOnly';
            $header .= '; SameSite=Lax';

            header($header);
        } else {
            $this->data = [];
        }
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
            return $this->data;
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
        @$this->data[$name] = $value;
    }

    /**
     * Hapus nilai dari sesi ini.
     *
     * @param string $name
     * @return void
     */
    public function unset(string $name): void
    {
        $this->data[$name] = null;
        unset($this->data[$name]);
    }

    /**
     * Ambil nilai dari sesi ini.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->__isset($name) ? $this->data[$name] : null;
    }

    /**
     * Cek nilai dari sesi ini.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}
