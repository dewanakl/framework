<?php

namespace Core\Auth;

use Core\Facades\App;

/**
 * Helper class autentikasi.
 *
 * @method static bool check()
 * @method static int|string|null id()
 * @method static \Core\Model\Model|null user()
 * @method static void logout()
 * @method static void login(\Core\Model\Model $user)
 * @method static bool attempt(array $credential, string $model = 'App\Models\User')
 *
 * @see \Core\Auth\AuthManager
 *
 * @class Auth
 * @package \Core\Auth
 */
final class Auth
{
    /**
     * Panggil method secara static.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->__call($method, $parameters);
    }

    /**
     * Panggil method secara object.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return App::get()->singleton(AuthManager::class)->{$method}(...$parameters);
    }
}
