<?php

namespace Core\Auth;

use Core\Model\Model;
use Core\Facades\App;
use Core\Http\Session;
use Core\Valid\Hash;
use Exception;

/**
 * Autentikasi user dengan model.
 *
 * @class AuthManager
 * @package \Core\Auth
 */
class AuthManager
{
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
     * Init obejct.
     * 
     * @param Session $session
     * @return void
     */
    function __construct(Session $session)
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
        $check = empty($this->user()) ? false : !empty($this->user->fail(fn () => false));
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
            $user = $this->session->get('_user');
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
        $this->session->unset('_user');
    }

    /**
     * Loginkan usernya dengan object.
     * 
     * @param object $user
     * @return void
     * 
     * @throws Exception
     */
    public function login(object $user): void
    {
        if (!($user instanceof Model)) {
            throw new Exception('Class ' . get_class($user) . ' bukan Model !');
        }

        $this->logout();
        $this->user = $user;
        $this->session->set('_user', serialize((clone $user)->only([$user->getPrimaryKey()])));
    }

    /**
     * Loginkan usernya.
     * 
     * @param array $credential
     * @param string $model
     * @return bool
     */
    public function attempt(array $credential, string $model = 'App\Models\User'): bool
    {
        list($email, $password) = array_keys($credential);

        $user = App::get()->singleton($model)->find($credential[$email], $email);

        if ($user->fail(fn () => false)) {
            if (Hash::check($credential[$password], $user->{$password})) {
                $this->login($user);
                return true;
            }
        }

        $this->logout();
        $this->session->set('old', [$email => $credential[$email]]);
        $this->session->set('error', [$email => $email . ' atau ' . $password . ' salah !']);
        return false;
    }
}
