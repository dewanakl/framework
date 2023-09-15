<?php

namespace Core\Http\Exception;

use Core\View\Compiler;
use ErrorException;
use Stringable;

/**
 * Base http exception class.
 *
 * @class HttpException
 * @package \Core\Http\Exception
 */
abstract class HttpException extends ErrorException implements Stringable
{
    /**
     * Http code.
     *
     * @var int $code
     */
    protected $code;

    /**
     * Path view file.
     *
     * @var string|null $path
     */
    protected static $path;

    /**
     * Http message.
     *
     * @var string|null $pesan
     */
    protected static $pesan;

    /**
     * Json respond.
     *
     * @var array<int|string, mixed>|string|null $json
     */
    protected static $json;

    /**
     * Show view.
     *
     * @param string $path
     * @param string|null $pesan
     * @return void
     */
    public static function view(string $path, string|null $pesan = null): void
    {
        if (empty(static::$path)) {
            static::$path = $path;
            static::$pesan = $pesan;
        }
    }

    /**
     * Transform to json respond.
     *
     * @param array<int|string, mixed>|null $jsonError
     * @return void
     */
    public static function json(array|null $jsonError = null): void
    {
        if (empty(static::$json)) {
            static::$json = $jsonError ?? static::$pesan;
        }
    }

    /**
     * Init respond with http code.
     *
     * @param int $code
     * @return HttpException
     */
    protected function respond(int $code): HttpException
    {
        $this->code = $code;
        respond()->setCode($code);
        respond()->getHeader()->set('Content-Type', isset(static::$json) ? 'application/json' : 'text/html');
        return $this;
    }

    /**
     * Show as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        if (isset(static::$json)) {
            return strval(is_string(static::$json) ? respond()->formatJson(null, [static::$json], $this->code) : json(static::$json, $this->code));
        }

        $path = isset(static::$path)
            ? (new Compiler)->compile(static::$path)->getPathFileCache()
            : helper_path('/errors/error');

        return render($path, isset(static::$pesan) ? ['pesan' =>  static::$pesan] : [])->__toString();
    }
}
