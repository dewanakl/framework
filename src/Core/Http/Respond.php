<?php

namespace Core\Http;

use Core\Facades\App;
use Core\Model\BaseModel;
use Core\View\Render;
use Core\View\View;

/**
 * Respond dari request yang masuk.
 *
 * @class Respond
 * @package \Core\Http
 */
class Respond
{
    /**
     * Session object.
     * 
     * @var Session $session
     */
    private $session;

    /**
     * Url redirect.
     * 
     * @var string|null $redirect
     */
    private $redirect;

    /**
     * Init object.
     * 
     * @param Session $session
     * @return void
     */
    function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Alihkan halaman.
     * 
     * @param string $prm
     * @return Respond
     */
    public function to(string $prm): Respond
    {
        $this->redirect = $prm;
        return $this;
    }

    /**
     * Isi dengan pesan.
     * 
     * @param string $key
     * @param mixed $value
     * @return Respond
     */
    public function with(string $key, mixed $value): Respond
    {
        $this->session->set($key, $value);
        return $this;
    }

    /**
     * Kembali ke halaman yang dulu.
     * 
     * @return Respond
     */
    public function back(): Respond
    {
        return $this->to($this->session->get('_oldroute', '/'));
    }

    /**
     * Alihkan halaman sesuai url.
     * 
     * @param string $uri
     * @return void
     */
    public function redirect(string $uri): void
    {
        $this->session->unset('_token');
        $this->session->send();

        $uri = str_contains($uri, baseurl()) ? $uri : baseurl() . $uri;

        http_response_code(302);
        header('HTTP/1.1 302 Found', true, 302);
        header('Location: ' . $uri, true, 302);
        exit(0);
    }

    /**
     * Tampilkan responnya.
     * 
     * @param mixed $respond
     * @return void
     */
    public function send(mixed $respond): void
    {
        if (is_string($respond) || is_numeric($respond) || $respond instanceof Render || $respond instanceof View) {
            if ($respond instanceof Render || $respond instanceof View) {
                $this->session->set('_oldroute', App::get()->singleton(Request::class)->server('REQUEST_URI'));
                $this->session->unset('old');
                $this->session->unset('error');
            }

            $this->session->send();
            $this->echo($respond);
        }

        if (is_array($respond) || $respond instanceof BaseModel) {
            $this->session->send();
            $this->echo(json($respond));
        }

        if ($respond instanceof Respond) {
            if (!is_null($this->redirect)) {
                $this->redirect($this->redirect);
            }
        }

        if ($respond instanceof Stream) {
            $respond->process();
        }
    }

    /**
     * Echo responnya.
     * 
     * @param mixed $prm
     * @return void
     */
    public function echo(mixed $prm): void
    {
        if ($prm) {
            @clear_ob();
            echo $prm;
            @ob_flush();
            @flush();
        }
    }
}
