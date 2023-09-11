<?php

namespace Core\Auth;

use Core\Model\Model;
use Core\Http\Session;
use Core\Valid\Hash;

/**
 * Autentikasi user dengan model.
 *
 * @class AuthManager
 * @package \Core\Auth
 */
class AuthManager
{
    /**
     * Nama dari class ini untuk translate.
     *
     * @var string NAME
     */
    public const NAME = 'auth';

    /**
     * Object model.
     *
     * @var Model|null $user
     */
    private $user;

    /**
     * Object session.
     *
     * @var Session $session
     */
    private $session;

    /**
     * Nama dari session user.
     *
     * @var string SESSUSER
     */
    public const SESSUSER = '__sessuser';

    /**
     * Init obejct.
     *
     * @param Session $session
     * @return void
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Check usernya.
     *
     * @return bool
     */
    public function check(): bool
    {
        $check = empty($this->user()) ? false : !empty($this->user->exist());
        if (!$check) {
            $this->logout();
        }

        return $check;
    }

    /**
     * Dapatkan id usernya.
     *
     * @return int|string|null
     */
    public function id(): int|string|null
    {
        return empty($this->user()) ? null : $this->user->{$this->user->getPrimaryKey()};
    }

    /**
     * Dapatkan obejek usernya.
     *
     * @return Model|null
     */
    public function user(): Model|null
    {
        if (!($this->user instanceof Model)) {
            $user = $this->session->get(static::SESSUSER);
            $this->user = empty($user) ? null : unserialize($user)->refresh();
        }

        return $this->user;
    }

    /**
     * Logoutkan usernya.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->user = null;
        $this->session->unset(static::SESSUSER);
    }

    /**
     * Loginkan usernya dengan Model.
     *
     * @param Model $user
     * @return void
     */
    public function login(Model $user): void
    {
        $this->logout();
        $this->user = clone $user;
        $this->session->set(static::SESSUSER, serialize($user->only($user->getPrimaryKey())));
    }

    /**
     * Loginkan usernya.
     *
     * @param array<string, string> $credential
     * @param string $model
     * @return bool
     */
    public function attempt(array $credential, string $model = '\App\Models\User'): bool
    {
        list($email, $password) = array_keys($credential);
        $user = (new $model)->find($credential[$email], $email);

        if ($user->exist()) {
            if (Hash::check($credential[$password], $user->{$password})) {
                $this->login($user);
                return true;
            }
        }

        $this->logout();
        $this->session->set(Session::OLD, [$email => $credential[$email]]);
        $this->session->set(Session::ERROR, [
            $email => translate()->trans(static::NAME . '.failed', [
                $email => $email,
                $password => $password
            ])
        ]);

        return false;
    }
}
