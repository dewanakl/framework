<?php

namespace Core\Valid;

use Exception;

/**
 * Translate bahasa.
 *
 * @class Trans
 * @package \Core\Valid
 */
class Trans
{
    /**
     * Data dari file lang.
     *
     * @var array $data
     */
    private $data;

    /**
     * Bahasa yang dipakai sekarang.
     *
     * @var string $language
     */
    private static $language;

    /**
     * Init object.
     *
     * @return void
     */
    public function __construct()
    {
        $this->data = (array) require_once base_path('/resources/lang/' . static::getLanguage() . '.php');
    }

    /**
     * Tetapkan bahasanya.
     *
     * @param string $lang
     * @return void
     */
    public static function setLanguage(string $lang): void
    {
        static::$language = $lang;
    }

    /**
     * Dapatkan bahasa sekarang.
     *
     * @return string
     */
    public static function getLanguage(): string
    {
        return static::$language;
    }

    /**
     * Dapatkan valuenya.
     *
     * @param string $key
     * @return string
     *
     * @throws Exception
     */
    public function get(string $key): string
    {
        $result = $this->data;
        foreach (explode('.', $key) as $value) {
            $result = $result[$value];
        }

        if ($result) {
            return $result;
        }

        throw new Exception(sprintf('Key trans "%s" not found.', $key));
    }

    /**
     * Lakukan translatenya.
     *
     * @param string $name
     * @param array<string, string> $params
     * @return string
     */
    public function trans(string $name, array $params = []): string
    {
        $data = [];
        foreach ($params as $key => $value) {
            $data[':' . $key] = $value;
        }

        return strtr($this->get($name), $data);
    }
}
