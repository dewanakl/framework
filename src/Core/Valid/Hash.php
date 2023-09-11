<?php

namespace Core\Valid;

/**
 * Encrypt decrypt string.
 *
 * @class Hash
 * @package \Core\Valid
 */
final class Hash
{
    /**
     * Algo Ciphering.
     *
     * @var string CIPHERING
     */
    public const CIPHERING = 'aes-256-cbc';

    /**
     * Algo Hash.
     *
     * @var string HASH
     */
    public const HASH = 'sha3-512';

    /**
     * Seperator key.
     *
     * @var string SPTR
     */
    public const SPTR = '::';

    /**
     * Encrypt dengan app key.
     *
     * @param string $str
     * @return string
     */
    public static function encrypt(string $str): string
    {
        $key = explode(static::SPTR, env('APP_KEY', static::SPTR), 2);
        $iv = openssl_random_pseudo_bytes(intval(openssl_cipher_iv_length(static::CIPHERING)));
        $encrypted = openssl_encrypt($str, static::CIPHERING, base64_decode($key[1]), OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . hash_hmac(static::HASH, $encrypted, base64_decode($key[0]), true) . $encrypted);
    }

    /**
     * Decrypt dengan app key.
     *
     * @param string $str
     * @return string|null
     */
    public static function decrypt(string $str): string|null
    {
        $key = explode(static::SPTR, env('APP_KEY', static::SPTR), 2);
        $raw = base64_decode($str, true);

        if ($raw === false) {
            return null;
        }

        $iv = intval(openssl_cipher_iv_length(static::CIPHERING));
        $encrypted = substr($raw, $iv + 64);

        if (!hash_equals(
            substr($raw, $iv, 64),
            hash_hmac(static::HASH, $encrypted, base64_decode($key[0]), true)
        )) {
            return null;
        }

        $result = openssl_decrypt($encrypted, static::CIPHERING, base64_decode($key[1]), OPENSSL_RAW_DATA, substr($raw, 0, $iv));
        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * Make hash password.
     *
     * @param string $value
     * @return string
     */
    public static function make(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * Check hash password.
     *
     * @param string $value
     * @param string $hashedValue
     * @return bool
     */
    public static function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }

    /**
     * Random string.
     *
     * @param int $len
     * @return string
     */
    public static function rand(int $len): string
    {
        return bin2hex(random_bytes($len));
    }
}
