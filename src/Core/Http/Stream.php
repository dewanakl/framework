<?php

namespace Core\Http;

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
     * @var resource|false $file
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
     * Init objek.
     * 
     * @param Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Init file.
     * 
     * @param string $file
     * @return void
     */
    private function init(string $file): void
    {
        if (!is_file($file)) {
            notFound();
        }

        $hashFile = @md5_file($file);
        $type = $this->ftype($file);

        if ($type != 'application/octet-stream') {
            $timeFile = @filemtime($file);
            $modified = @strtotime($this->request->server('HTTP_IF_MODIFIED_SINCE', ''));
            $match = @trim($this->request->server('HTTP_IF_NONE_MATCH', ''));

            if ($modified == $timeFile || $match == $hashFile) {
                http_response_code(304);
                header('HTTP/1.1 304 Not Modified', true, 304);
                exit;
            }

            header('Last-Modified: ' . @gmdate('D, d M Y H:i:s', $timeFile) . ' GMT');
            header('Etag: ' . $hashFile);
        }

        @set_time_limit(0);
        @clear_ob();

        $this->file = @fopen($file, 'r', true);
        $this->name = @basename($file);
        $this->boundary = $hashFile;
        $this->size = @filesize($file);
        $this->download = false;
        $this->type = $type;
    }

    /**
     * Send single file.
     * 
     * @param string $range
     * @return void
     */
    private function pushSingle(string $range): void
    {
        [$start, $end] = $this->getRange($range);

        header('Content-Length: ' . strval($end - $start + 1));
        header(sprintf('Content-Range: bytes %s-%s/%s', $start, $end, $this->size));

        fseek($this->file, $start);
        $this->readBuffer($end - $start + 1);
    }

    /**
     * Send multi file.
     * 
     * @param array $ranges
     * @return void
     */
    private function pushMulti(array $ranges): void
    {
        $length = 0;
        $tl = 'Content-Type: ' . $this->type . "\r\n";
        $formatRange = "Content-Range: bytes %s-%s/%s\r\n\r\n";
        $tmpRanges = [];

        foreach ($ranges as $id => $range) {
            [$start, $end] = $this->getRange($range);
            $tmpRanges[$id] = [$start, $end];
            $length += strlen("\r\n--" . $this->boundary . "\r\n");
            $length += strlen($tl);
            $length += strlen(sprintf($formatRange, $start, $end, $this->size));
            $length += $end - $start + 1;
        }

        $length += strlen("\r\n--" . $this->boundary . "--\r\n");

        header('Content-Type: multipart/byteranges; boundary=' . $this->boundary);
        header('Content-Length: ' . strval($length));

        foreach ($ranges as $id => $range) {
            [$start, $end] = $tmpRanges[$id];
            echo "\r\n--" . $this->boundary . "\r\n";
            echo $tl;
            echo sprintf($formatRange, $start, $end, $this->size);
            fseek($this->file, $start);
            $this->readBuffer($end - $start + 1);
        }

        echo "\r\n--" . $this->boundary . "--\r\n";
    }

    /**
     * Get range file.
     * 
     * @param string $range
     * @return array
     */
    private function getRange(string $range): array
    {
        [$start, $end] = explode('-', $range);

        $end = intval(empty($end) ? ($this->size - 1) : min(abs(intval($end)), ($this->size - 1)));
        $start = intval((empty($start) || ($end < abs(intval($start)))) ? 0 : max(abs(intval($start)), 0));

        if ($start > $end) {
            header('Status: 416 Requested Range Not Satisfiable');
            header('Content-Range: */' . strval($this->size));
            exit;
        }

        return [$start, $end];
    }

    /**
     * Read file.
     * 
     * @return void
     */
    private function readFile(): void
    {
        while (!feof($this->file)) {
            echo fgets($this->file);
        }
    }

    /**
     * Read buffer file.
     * 
     * @param int $bytes
     * @param int $size
     * @return void
     */
    private function readBuffer(int $bytes, int $size = 10240): void
    {
        $bytesLeft = $bytes;
        while ($bytesLeft > 0 && !feof($this->file)) {
            $bytesRead = ($bytesLeft > $size) ? $size : $bytesLeft;
            echo fread($this->file, $bytesRead);
            $bytesLeft -= $bytesRead;
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
     * @return void
     */
    public function process(): void
    {
        $range = '';
        $ranges = [];
        $t = 0;

        if ($this->request->method() == 'GET' && ($this->request->server('HTTP_RANGE') !== null)) {
            $range = substr(stristr(trim($this->request->server('HTTP_RANGE')), 'bytes='), 6);
            $ranges = explode(',', $range);
            $t = count($ranges);
        }

        header('Accept-Ranges: 0-' . strval($this->size - 1));
        header('Content-Type: ' . $this->type);
        header('Content-Transfer-Encoding: binary');

        if ($this->type == 'application/octet-stream') {
            header(sprintf('Content-Disposition: attachment; filename="%s"', $this->name));
        } else {
            header('Content-Disposition: inline');
        }

        if ($t > 0) {
            header('HTTP/1.1 206 Partial Content', true, 206);
            if ($t === 1) {
                $this->pushSingle($range);
            } else {
                $this->pushMulti($ranges);
            }
        } else {
            header('Content-Length: ' . strval($this->size));
            $this->readFile();
        }

        @ob_flush();
        @flush();
        @fclose($this->file);
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
     */
    public function send(string $filename): Stream
    {
        $this->init($filename);
        return $this;
    }
}
