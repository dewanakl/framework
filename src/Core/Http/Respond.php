<?php

namespace Core\Http;

use Core\Facades\App;
use Core\Model\Model;
use Stringable;

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
     * Content to respond.
     * 
     * @var string|null $content
     */
    private $content;

    /**
     * Init object.
     * 
     * @param Session $session
     * @return void
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Alihkan halaman.
     * 
     * @param string $url
     * @return Respond
     */
    public function to(string $url): Respond
    {
        $this->redirect = $url;
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
        return $this->to($this->session->get('__oldroute', '/'));
    }

    /**
     * Respond sebagai json.
     * 
     * @param mixed $data
     * @param int $code
     * @return Respond
     */
    public function json(mixed $data, int $code = 200): Respond
    {
        $this->content = strval(json($data, $code));
        return $this;
    }

    /**
     * Redirect with route name.
     * 
     * @param string $route
     * @param mixed ...$key
     * @return Respond
     */
    public function route(string $route, mixed ...$key): Respond
    {
        return $this->to(route($route, ...$key));
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
        if (is_string($respond) || is_numeric($respond) || $respond instanceof Stringable) {
            if ($respond instanceof Stringable) {
                $this->session->set('__oldroute', App::get()->singleton(Request::class)->get('REQUEST_URL'));
                $this->session->unset('old');
                $this->session->unset('error');
            }

            $this->session->send();
            $this->echo($respond);
        }

        if (is_array($respond) || $respond instanceof Model) {
            $this->session->send();
            $this->echo(json($respond));
        }

        if ($respond instanceof Respond) {
            if ($this->redirect !== null) {
                $this->redirect($this->redirect);
            }

            if ($this->content !== null) {
                $this->session->send();
                $this->echo($this->content);
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
