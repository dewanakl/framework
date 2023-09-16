<?php

namespace Core\Http;

use Closure;
use Core\Http\Exception\NotFoundException;
use Core\Http\Exception\StreamTerminate;
use Core\Valid\Hash;
use DateTimeInterface;

/**
 * Stream sebuah file.
 *
 * @class Stream
 * @package \Core\Http
 * @see https://gist.github.com/kosinix/4cf0d432638817888149
 */
class Stream
{
    /**
     * Open file.
     *
     * @var resource|false|null $file
     */
    private $file;

    /**
     * Basename file.
     *
     * @var string $name
     */
    private $name;

    /**
     * Hash file.
     *
     * @var string $boundary
     */
    private $boundary;

    /**
     * Size file.
     *
     * @var int|false $size
     */
    private $size;

    /**
     * Type file.
     *
     * @var string $type
     */
    private $type;

    /**
     * Download file.
     *
     * @var bool $download
     */
    private $download;

    /**
     * Request object.
     *
     * @var Request $request
     */
    private $request;

    /**
     * Respond object.
     *
     * @var Respond $respond
     */
    private $respond;

    /**
     * Callback to execute this stream.
     *
     * @var Closure|null $callback
     */
    private $callback;

    /**
     * Absolute path file.
     *
     * @var string $path
     */
    private $path;

    /**
     * Init objek.
     *
     * @param Request $request
     * @param Respond $respond
     * @return void
     */
    public function __construct(Request $request, Respond $respond)
    {
        $this->request = $request;
        $this->respond = $respond;
    }

    /**
     * Init file.
     *
     * @param bool $etag
     * @return void
     */
    private function init(bool $etag): void
    {
        $hashFile = $etag ? @md5_file($this->path) : Hash::rand(5);
        $type = $this->ftype($this->path);

        if ($type != $this->ftype() && $etag) {
            if (@trim($this->request->server->get('HTTP_IF_NONE_MATCH', '')) == $hashFile) {
                $this->respond->setCode(304);
                throw new StreamTerminate;
            }

            $this->respond->getHeader()->set('Etag', $hashFile);
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->file = @fopen($this->path, 'r');
        $this->name = @basename($this->path);
        $this->boundary = $hashFile;
        $this->size = @filesize($this->path);
        $this->download = false;
        $this->type = $type;
    }

    /**
     * Send single file.
     *
     * @param string $range
     * @return Closure
     */
    private function pushSingle(string $range): Closure
    {
        list($start, $end) = $this->getRange($range);

        if ($start > 0 || $end < ($this->size - 1)) {
            $this->respond->setCode(206);
            $this->respond->getHeader()->set('Content-Length', strval($end - $start + 1));
            $this->respond->getHeader()->set('Content-Range', sprintf('bytes %s-%s/%s', $start, $end, $this->size));

            return function () use ($start, $end): void {
                @fseek($this->file, $start);
                $this->readBuffer($end - $start + 1);
            };
        }

        return $this->readFile();
    }

    /**
     * Send multi file.
     *
     * @param array $ranges
     * @return Closure
     */
    private function pushMulti(array $ranges): Closure
    {
        $length = 0;
        $tmpRanges = [];

        foreach ($ranges as $range) {
            list($start, $end) = $this->getRange($range);
            $tmpRanges[] = [$start, $end];
            $length += strlen("\r\n--" . $this->boundary . "\r\n");
            $length += strlen('Content-Type: ' . $this->type . "\r\n");
            $length += strlen(sprintf("Content-Range: bytes %s-%s/%s\r\n\r\n", $start, $end, $this->size));
            $length += $end - $start + 1;
        }

        $length += strlen("\r\n--" . $this->boundary . "--\r\n");

        $this->respond->setCode(206);
        $this->respond->getHeader()->set('Content-Type', 'multipart/byteranges; boundary=' . $this->boundary);
        $this->respond->getHeader()->set('Content-Length', strval($length));

        return function () use ($tmpRanges): void {
            foreach ($tmpRanges as $range) {
                list($start, $end) = $range;

                echo "\r\n--" . $this->boundary . "\r\n";
                echo 'Content-Type: ' . $this->type . "\r\n";
                echo sprintf("Content-Range: bytes %s-%s/%s\r\n\r\n", $start, $end, $this->size);

                @fseek($this->file, $start);
                $this->readBuffer($end - $start + 1);
            }

            echo "\r\n--" . $this->boundary . "--\r\n";
        };
    }

    /**
     * Get range file.
     *
     * @param string $range
     * @return array
     *
     * @throws StreamTerminate
     */
    private function getRange(string $range): array
    {
        $raw = strpos($range, '-');

        $start = substr($range, 0, $raw);
        $end = substr($range, $raw + 1); // number 1 of separator length '-';

        $end = intval(empty($end) ? ($this->size - 1) : min(abs(intval($end)), ($this->size - 1)));
        $start = intval((empty($start) || ($end < abs(intval($start)))) ? 0 : max(abs(intval($start)), 0));

        // @phpstan-ignore-next-line
        if ($start < 0 || $start > $end) {
            $this->respond->setCode(416);
            $this->respond->getHeader()->set('Content-Range', 'bytes */' . strval($this->size));
            throw new StreamTerminate;
        }

        return [$start, $end];
    }

    /**
     * Read file.
     *
     * @return Closure
     */
    private function readFile(): Closure
    {
        $this->respond->getHeader()->set('Content-Length', strval($this->size));
        return function (): void {
            $this->readBuffer($this->size);
        };
    }

    /**
     * Read buffer file.
     *
     * @param int $bytes
     * @param int $size
     * @return void
     */
    private function readBuffer(int $bytes, int $size = 1024): void
    {
        $bytesLeft = $bytes;
        while ($bytesLeft > 0 && !feof($this->file)) {
            if (@connection_aborted()) {
                break;
            }

            $length = ($bytesLeft > $size) ? $size : $bytesLeft;

            $read = @fread($this->file, $length);
            if ($read === false) {
                break;
            }

            echo $read;

            $bytesLeft -= $length;
            @flush();
        }
    }

    /**
     * Get type file.
     *
     * @param string|null $typeFile
     * @return string
     */
    private function ftype(string|null $typeFile = null): string
    {
        if ($this->download || $typeFile === null) {
            return 'application/octet-stream';
        }

        $mimeTypes = [
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'html' => 'text/plain',
            'php' => 'text/plain',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/ico',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mkv' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'json' => 'application/json',
            'pdf' => 'application/pdf'
        ];

        $typeFile = strtolower(pathinfo($typeFile, PATHINFO_EXTENSION));

        if (empty($mimeTypes[$typeFile])) {
            return 'application/octet-stream';
        }

        return $mimeTypes[$typeFile];
    }

    /**
     * Process file.
     *
     * @return Stream
     */
    public function process(): Stream
    {
        $range = '';
        $ranges = [];
        $t = 0;

        if ($this->request->method(Request::GET) && $this->request->server->get('HTTP_RANGE') !== null) {
            $range = substr(stristr(trim($this->request->server->get('HTTP_RANGE')), 'bytes='), 6);
            $raw = strpos($range, ',');
            $ranges = $raw !== false ? explode(',', $range) : [$range];
            $t = count($ranges);
        }

        $this->respond->getHeader()->set('Accept-Ranges', 'bytes');
        $this->respond->getHeader()->set('Content-Type',  $this->type);
        $this->respond->getHeader()->set('Last-Modified', @gmdate(DateTimeInterface::RFC7231, @filemtime($this->path)));

        $this->respond->getHeader()->set(
            'Content-Disposition',
            $this->type == $this->ftype()
                ? sprintf('attachment; filename="%s"', $this->name)
                : 'inline'
        );

        if ($t > 0) {
            if ($t === 1) {
                $this->callback = $this->pushSingle($range);
            } else {
                $this->callback = $this->pushMulti($ranges);
            }
        } else {
            $this->callback = $this->readFile();
        }

        return $this;
    }

    /**
     * Push to echo.
     *
     * @return void
     */
    public function push(): void
    {
        ($this->callback)();
    }

    /**
     * End of stream.
     *
     * @return void
     */
    public function terminate(): void
    {
        if (is_resource($this->file)) {
            @fclose($this->file);
        }

        $this->file = null;
        $this->callback = null;
    }

    /**
     * Download file.
     *
     * @return Stream
     */
    public function download(): Stream
    {
        $this->download = true;
        $this->type = $this->ftype();
        return $this;
    }

    /**
     * Send file.
     *
     * @param string $filename
     * @return Stream
     *
     * @throws NotFoundException
     */
    public function send(string $filename): Stream
    {
        if (!is_file($filename)) {
            throw new NotFoundException;
        }

        $this->path = $filename;
        $this->respond->clean();
        $this->init(false);

        return $this;
    }
}
