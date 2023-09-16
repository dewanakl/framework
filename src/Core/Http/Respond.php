<?php

namespace Core\Http;

use DateTimeInterface;
use Exception;
use JsonSerializable;
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
     * Respond code.
     *
     * @var int $code
     */
    private $code;

    /**
     * Respond header.
     *
     * @var Header $header
     */
    private $header;

    /**
     * Parameter query string.
     *
     * @var array<string, string> $parameter
     */
    private $parameter;

    /**
     * Version header.
     *
     * @var string $version
     */
    private $version;

    /**
     * Http message.
     *
     * @var string|null $message
     */
    private $message;

    /**
     * Init object.
     *
     * @param string|null $content
     * @param int $code
     * @param array $header
     * @param string $version
     * @return void
     */
    public function __construct(string|null $content = null, int $code = 200, array $header = [], string $version = '1.1')
    {
        $this->code = $code;
        $this->header = new Header($header);
        $this->header->set('Content-Type', 'text/html; charset=utf-8');
        $this->header->set('Date', gmdate(DateTimeInterface::RFC7231));
        $this->content = $content;
        $this->version = $version;
        $this->message = $this->codeHttpMessage($code);
        $this->parameter = [];
    }

    /**
     * Alihkan halaman ke url.
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
     * Isi dengan pesan di session.
     *
     * @param string $key
     * @param mixed $value
     * @return Respond
     */
    public function with(string $key, mixed $value): Respond
    {
        session()->set($key, $value);
        return $this;
    }

    /**
     * Kembali ke halaman yang dulu.
     *
     * @return Respond
     */
    public function back(): Respond
    {
        return $this->to(session()->get(Session::ROUTE, '/'));
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
     * Dengan query parameter.
     *
     * @param string $key
     * @param string $value
     * @return Respond
     */
    public function param(string $key, string $value): Respond
    {
        $this->parameter[$key] = $value;
        return $this;
    }

    /**
     * Alihkan halaman sesuai url.
     *
     * @param string $uri
     * @param bool $force
     * @return void
     */
    public function redirect(string $uri, bool $force = false): void
    {
        session()->unset(Session::TOKEN);

        $uri = str_contains($uri, base_url()) ? $uri : base_url($uri);

        if (!empty($this->parameter)) {
            $uri = $uri . '?' . http_build_query($this->parameter);
        }

        $this->setCode($force ? 301 : 302);
        $this->header->set('Location', $uri);
    }

    /**
     * Force redirect to url.
     *
     * @param string $uri
     * @return Respond
     */
    public function forceRedirect(string $uri): Respond
    {
        $this->redirect($uri, true);
        return $this;
    }

    /**
     * Set a HTTP version.
     *
     * @param string $ver
     * @return Respond
     */
    public function setVersionHeader(string $ver): Respond
    {
        $this->version = $ver;
        return $this;
    }

    /**
     * Get version header.
     *
     * @return string
     */
    public function getVersionHeader(): string
    {
        return $this->version;
    }

    /**
     * Set a HTTP message.
     *
     * @param string $message
     * @return Respond
     */
    public function setHttpMessage(string $message): Respond
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Ubah code menjadi http message.
     *
     * @param int $code
     * @return string|null
     *
     * @throws Exception
     */
    public function codeHttpMessage(int $code): string|null
    {
        switch ($code) {
            case 100:
                return 'Continue';
            case 101:
                return 'Switching Protocols';
            case 200:
                return 'OK';
            case 201:
                return 'Created';
            case 202:
                return 'Accepted';
            case 203:
                return 'Non-Authoritative Information';
            case 204:
                return 'No Content';
            case 205:
                return 'Reset Content';
            case 206:
                return 'Partial Content';
            case 300:
                return 'Multiple Choices';
            case 301:
                return 'Moved Permanently';
            case 302:
                return 'Moved Temporarily';
            case 303:
                return 'See Other';
            case 304:
                return 'Not Modified';
            case 305:
                return 'Use Proxy';
            case 400:
                return 'Bad Request';
            case 401:
                return 'Unauthorized';
            case 402:
                return 'Payment Required';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not Found';
            case 405:
                return 'Method Not Allowed';
            case 406:
                return 'Not Acceptable';
            case 407:
                return 'Proxy Authentication Required';
            case 408:
                return 'Request Time-out';
            case 409:
                return 'Conflict';
            case 410:
                return 'Gone';
            case 411:
                return 'Length Required';
            case 412:
                return 'Precondition Failed';
            case 413:
                return 'Request Entity Too Large';
            case 414:
                return 'Request-URI Too Large';
            case 415:
                return 'Unsupported Media Type';
            case 416:
                return 'Range Not Satisfiable';
            case 500:
                return 'Internal Server Error';
            case 501:
                return 'Not Implemented';
            case 502:
                return 'Bad Gateway';
            case 503:
                return 'Service Unavailable';
            case 504:
                return 'Gateway Time-out';
            case 505:
                return 'HTTP Version not supported';
            default:
                if ($this->message === null) {
                    throw new Exception('This code: ' . $code . ' is no defined in http');
                }
                return null;
        }
    }

    /**
     * Get http header.
     *
     * @return Header
     */
    public function getHeader(): Header
    {
        return $this->header;
    }

    /**
     * Set http status code.
     *
     * @param int $code
     * @return Respond
     */
    public function setCode(int $code): Respond
    {
        $this->code = $code;
        $this->message = $this->codeHttpMessage($code);
        return $this;
    }

    /**
     * Set content.
     *
     * @param string|int|bool|null $prm
     * @return void
     */
    public function setContent(string|int|bool|null $prm): void
    {
        $prm = strval($prm);
        if (!empty($prm)) {
            if ($this->content !== null) {
                $this->content = $this->content . $prm;
            } else {
                $this->content = $prm;
            }
        }
    }

    /**
     * Dapatkan content.
     *
     * @return string|null
     */
    public function getContent(): string|null
    {
        return $this->content;
    }

    /**
     * Clean all temporary respond.
     *
     * @return void
     */
    public function clean(): void
    {
        @clear_ob();
        $this->code = 200;
        $this->header = new Header();
        $this->header->set('Content-Type', 'text/html; charset=utf-8');
        $this->header->set('Date', gmdate(DateTimeInterface::RFC7231));
        $this->content = null;
        $this->message = $this->codeHttpMessage($this->code);
        $this->parameter = [];
    }

    /**
     * Format json default.
     *
     * @param array|object|null $data
     * @param array|object|null $error
     * @param int $code
     * @return string
     */
    public function formatJson(array|object|null $data = null, array|object|null $error = null, int $code = 200): string
    {
        return json([
            'code' => $code,
            'data' => $data,
            'error' => $error
        ], $code);
    }

    /**
     * Send all header queue.
     *
     * @return void
     */
    public function prepare()
    {
        session()->send();

        http_response_code($this->code);
        if ($this->version && $this->code && $this->message) {
            header(sprintf('HTTP/%s %s %s', $this->version, $this->code, $this->message), true, $this->code);
        }

        foreach ($this->header->all() as $key => $value) {
            if (!$value) {
                header($key, true, $this->code);
                continue;
            }

            header($key . ': ' . strval($value), true, $this->code);
        }

        foreach (cookie()->send() as $value) {
            header('Set-Cookie: ' . strval($value), false, $this->code);
        }
    }

    /**
     * Tampilkan responnya.
     *
     * @param mixed $respond
     * @return void
     */
    public function send(mixed $respond): void
    {
        $content = null;

        if (is_string($respond) || is_numeric($respond) || $respond instanceof Stringable) {
            if ($respond instanceof Stringable) {
                session()->set(Session::ROUTE, request()->server->get('REQUEST_URL'));
                session()->unset(Session::OLD);
                session()->unset(Session::ERROR);
            }

            $content = $respond;
        } else if (is_array($respond) || $respond instanceof JsonSerializable) {
            $content = json($respond, $this->code);
        }

        if ($respond instanceof Respond) {
            if ($this->redirect !== null) {
                $this->redirect($this->redirect);
            } else if ($respond->getContent() !== null) {
                $content = $respond->getContent();
            }
        }

        $this->prepare();

        if ($respond instanceof Stream) {
            $respond->push();
            $respond->terminate();
        }

        if ($content) {
            echo $content;
        }

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        @flush();
    }
}
