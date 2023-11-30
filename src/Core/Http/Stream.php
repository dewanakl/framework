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
     * Respond stream.
     *
     * @var resource|false|null $stream
     */
    private $stream;

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

    public function __destruct()
    {
        if (is_resource($this->file)) {
            fclose($this->file);
        }

        $this->file = null;
        $this->callback = null;
    }

    /**
     * Init file.
     *
     * @param bool $etag
     * @return void
     */
    private function init(bool $etag): void
    {
        $hashFile = $etag ? @md5_file($this->path) : Hash::rand(10);
        $type = $this->ftype($this->path);

        if ($etag && $type != $this->ftype()) {
            if (@trim($this->request->server->get('HTTP_IF_NONE_MATCH', '')) == $hashFile) {
                $this->respond->setCode(304);
                throw new StreamTerminate;
            }

            $this->respond->getHeader()->set('Etag', $hashFile);
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->file = @fopen($this->path, 'rb');
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

                fwrite($this->stream, "\r\n--" . $this->boundary . "\r\n");
                fwrite($this->stream, 'Content-Type: ' . $this->type . "\r\n");
                fwrite($this->stream, sprintf("Content-Range: bytes %s-%s/%s\r\n\r\n", $start, $end, $this->size));

                @fseek($this->file, $start);
                $this->readBuffer($end - $start + 1);
            }

            fwrite($this->stream, "\r\n--" . $this->boundary . "--\r\n");
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
            if (@connection_status() != CONNECTION_NORMAL) {
                break;
            }

            $length = @stream_copy_to_stream(
                $this->file,
                $this->stream,
                ($bytesLeft > $size) ? $size : $bytesLeft
            );

            if ($length === false) {
                break;
            }

            $bytesLeft -= $length;

            // Send Now.
            @ob_end_flush();
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

        $fileMimeTypes = [
            'txt' => 'text/plain',
            'text' => 'text/plain',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'rss' => 'application/rss+xml',
            'atom' => 'application/atom+xml',
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mkv' => 'video/mp4',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'eot' => 'application/vnd.ms-fontobject',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'zip' => 'application/zip',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'csv' => 'text/csv',
            'rtf' => 'application/rtf',
        ];

        $typeFile = strtolower(pathinfo($typeFile, PATHINFO_EXTENSION));

        if (empty($fileMimeTypes[$typeFile])) {
            return 'application/octet-stream';
        }

        return $fileMimeTypes[$typeFile];
    }

    /**
     * Process file.
     *
     * @return Stream
     */
    public function process(): Stream
    {
        $this->stream = $this->respond->getStream();
        $range = '';
        $ranges = [];
        $t = 0;

        if ($this->request->method(Request::GET) && $this->request->server->get('HTTP_RANGE') !== null) {
            $range = substr(stristr(trim($this->request->server->get('HTTP_RANGE')), 'bytes='), 6);
            $raw = strpos($range, ',');
            $ranges = $raw !== false ? explode(',', $range) : [$range];
            $t = count($ranges);
        }

        $this->respond->getHeader()
            ->set('Accept-Ranges', 'bytes')
            ->set('Content-Type',  $this->type)
            ->set('Last-Modified', @gmdate(DateTimeInterface::RFC7231, @filemtime($this->path)))
            ->set(
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
