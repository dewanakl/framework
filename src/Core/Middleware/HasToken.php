<?php

namespace Core\Middleware;

use Core\Http\Session;
use Core\Valid\Hash;

/**
 * Check pada request apakah ada csrf?.
 *
 * @trait HasToken
 * @package \Core\Middleware
 */
trait HasToken
{
    /**
     * Cek token dan ajax yang masuk.
     *
     * @param string $token
     * @param bool $ajax
     * @return string|null
     */
    protected function checkToken(string $token, bool $ajax = false): string|null
    {
        if (!hash_equals(session()->get(Session::TOKEN, Hash::rand(10)), $token)) {
            session()->unset(Session::TOKEN);

            if (!$ajax) {
                page_expired();
            }

            return json(
                ['Csrf token not found'],
                400
            );
        }

        if (!$ajax) {
            session()->unset(Session::TOKEN);
        }

        return null;
    }
}
