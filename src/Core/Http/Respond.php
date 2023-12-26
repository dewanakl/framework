<?php

namespace Core\Http;

use Core\Facades\App;
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
    public const HTTP_CONTINUE = 100;
    public const HTTP_SWITCHING_PROTOCOLS = 101;
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_ACCEPTED = 202;
    public const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_RESET_CONTENT = 205;
    public const HTTP_PARTIAL_CONTENT = 206;
    public const HTTP_MULTIPLE_CHOICES = 300;
    public const HTTP_MOVED_PERMANENTLY = 301;
    public const HTTP_MOVED_TEMPORARILY = 302;
    public const HTTP_SEE_OTHER = 303;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_USE_PROXY = 305;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_PAYMENT_REQUIRED = 402;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_NOT_ACCEPTABLE = 406;
    public const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    public const HTTP_REQUEST_TIMEOUT = 408;
    public const HTTP_CONFLICT = 409;
    public const HTTP_GONE = 410;
    public const HTTP_LENGTH_REQUIRED = 411;
    public const HTTP_PRECONDITION_FAILED = 412;
    public const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    public const HTTP_REQUEST_URI_TOO_LARGE = 414;
    public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    public const HTTP_RANGE_NOT_SATISFIABLE = 416;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_NOT_IMPLEMENTED = 501;
    public const HTTP_BAD_GATEWAY = 502;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    public const HTTP_GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;

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
     * Respond headers.
     *
     * @var Header $headers
     */
    public $headers;

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
     * @var string $message
     */
    private $message;

    /**
     * Stream object.
     *
     * @var resource|null|false $stream
     */
    private $stream;

    /**
     * Init object.
     *
     * @param string|null $content
     * @param int $code
     * @param array $headers
     * @param string $version
     * @return void
     */
    public function __construct(string|null $content = null, int $code = Respond::HTTP_OK, array $headers = [], string $version = '1.1')
    {
        $this->code = $code;
        $this->headers = new Header([
            'Content-Type' => 'text/html',
            'Date' => gmdate(DateTimeInterface::RFC7231),
            ...$headers
        ]);

        $this->content = $content;
        $this->version = $version;
        $this->message = $this->codeHttpMessage($code);
        $this->parameter = [];

        if (!App::get()->has(Respond::class) && !is_resource($this->stream)) {
            $this->createStream();
        }
    }

    /**
     * Create a new stream.
     *
     * @return void
     */
    private function createStream(): void
    {
        // Ensure stream is null.
        $this->__destruct();
        $this->stream = fopen('php://output', 'wb');
    }

    /**
     * Destroy object.
     *
     * @return void
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    /**
     * Alihkan halaman ke url.
     *
     * @param string $url
     * @param int $code
     * @return Respond
     */
    public function to(string $url, int $code = Respond::HTTP_MOVED_TEMPORARILY): Respond
    {
        $this->content = $url;
        $this->setCode($code);
        return $this;
    }

    /**
     * Get stream.
     *
     * @return mixed
     */
    public function getStream(): mixed
    {
        return $this->stream;
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

        $this->setCode($force ? Respond::HTTP_MOVED_PERMANENTLY : Respond::HTTP_MOVED_TEMPORARILY);
        $this->headers->set('Location', $uri);
        $this->headers->unset('Content-Type');
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
     * @return string
     *
     * @throws Exception
     */
    public function codeHttpMessage(int $code): string
    {
        $httpStatusMessages = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Moved Temporarily',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
        ];

        if (array_key_exists($code, $httpStatusMessages)) {
            return $httpStatusMessages[$code];
        }

        throw new Exception('This code: ' . $code . ' is not defined in HTTP');
    }

    /**
     * Get http header.
     *
     * @return Header
     */
    public function getHeader(): Header
    {
        return $this->headers;
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
     * Get http status code.
     *
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Set content.
     *
     * @param mixed $content
     * @return Respond
     */
    public function setContent(mixed $content = null): Respond
    {
        if (!is_null($content)) {
            $content = strval($content);
        }

        $this->content = $content;

        return $this;
    }

    /**
     * Set query parameter.
     *
     * @param array $param
     * @return Respond
     */
    public function setParameter(array $param = []): Respond
    {
        $this->parameter = $param;
        return $this;
    }

    /**
     * Get query parameter.
     *
     * @return array
     */
    public function getParameter(): array
    {
        return $this->parameter;
    }

    /**
     * Dapatkan content.
     *
     * @param bool $nullable
     * @return string|null
     */
    public function getContent(bool $nullable = true): string|null
    {
        if ($nullable) {
            return $this->content;
        }

        return $this->content ?? '';
    }

    /**
     * Clean all temporary respond.
     *
     * @return void
     */
    public function clean(): void
    {
        $presistenVersion = $this->getVersionHeader();
        @clear_ob();
        $this->createStream();
        $this->__construct(version: $presistenVersion);
    }

    /**
     * Send all header queue.
     *
     * @return Respond
     */
    public function prepare(): Respond
    {
        // Don't send again.
        if (headers_sent()) {
            return $this;
        }

        session()->send();

        http_response_code($this->code);
        header(sprintf('HTTP/%s %s %s', $this->version, $this->code, $this->message), true, $this->code);

        foreach ($this->headers->all() as $key => $value) {
            if (!$value) {
                header($key, true, $this->code);
                continue;
            }

            header($key . ': ' . strval($value), true, $this->code);
        }

        foreach (cookie()->send() as $value) {
            header('Set-Cookie: ' . strval($value), false, $this->code);
        }

        return $this;
    }

    /**
     * Transform respond to response instance.
     *
     * @param mixed $respond
     * @return Respond
     */
    public function transform(mixed $respond): Respond
    {
        if (is_string($respond) || is_numeric($respond) || $respond instanceof Stringable) {
            if ($respond instanceof Stringable) {
                session()->set(Session::ROUTE, request()->server->get('REQUEST_URL'));
                session()->unset(Session::OLD);
                session()->unset(Session::ERROR);
            }

            $this->content = strval($respond);
            return $this;
        }

        if (is_array($respond) || $respond instanceof JsonSerializable) {
            $this->content = json($respond, $this->code);
            return $this;
        }

        if ($respond instanceof Respond) {
            $this->setCode($respond->getCode());
            $this->content = $respond->getContent();
            $this->headers = new Header([...$this->headers->all(), ...$respond->headers->all()]);
            $this->setParameter([...$this->getParameter(), ...$respond->getParameter()]);

            if ($this->code >= 300 && $this->code < 400) {
                $this->redirect($this->content, $this->code == Respond::HTTP_MOVED_PERMANENTLY);
                $this->content = null;
                return $this;
            }

            return $this;
        }

        if ($respond instanceof Stream) {
            $this->setParameter(); // Set empty query parameters.
            $this->content = null;
            $respond->process();
            return $this;
        }

        return $this;
    }

    /**
     * Tampilkan responnya.
     *
     * @param mixed $respond
     * @return void
     */
    public function send(mixed $respond): void
    {
        @ob_end_clean();

        $this->transform($respond)->prepare();

        if ($respond instanceof Stream) {
            $respond->push();
        }

        if ($respond instanceof Respond && $respond->getContent()) {
            fwrite($respond->getStream(), $respond->getContent(false));
        }

        // Send output buffer.
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        // The end.
        @flush();
    }
}
