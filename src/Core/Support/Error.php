<?php

namespace Core\Support;

use Core\Database\Exception\DatabaseException;
use Core\Facades\App;
use Core\Http\Respond;
use Core\View\View;
use DateTimeImmutable;
use Exception;
use Throwable;

/**
 * Error reporting.
 *
 * @class Error
 * @package \Core\Support
 */
class Error
{
    /**
     * Nama file dari log nya.
     *
     * @var string $nameFileLog
     */
    protected $nameFileLog = '/kamu.log';

    /**
     * Nama folder dari log nya.
     *
     * @var string $locationFileLog
     */
    protected $locationFileLog = '/cache/log';

    /**
     * Informasi dalam json.
     *
     * @var string $information
     */
    private $information;

    /**
     * Stream stderr.
     *
     * @var resource|null
     */
    private $stream;

    /**
     * Throwable object.
     *
     * @var Throwable
     */
    private $throwable;

    /**
     * Init object.
     *
     * @param Throwable $throwable
     * @return void
     */
    public function __construct(Throwable $throwable)
    {
        $this->throwable = $throwable;
        $this->stream = fopen('php://stderr', 'wb');
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
     * Ubah ke JSON.
     *
     * @param Throwable $th
     * @return string
     */
    private function transformToJson(Throwable $th): string
    {
        return json_encode([
            'message' => $th->getMessage(),
            'sql' => ($th instanceof DatabaseException) ? $th->getQueryString() : null,
            'database' => ($th instanceof DatabaseException) ? $th->getInfoDriver() : null,
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'code' => $th->getCode(),
            'date' => now(DateTimeImmutable::RFC3339_EXTENDED),
            'duration' => execute_time(),
            'trace' => array_map(function (array $data): array {
                unset($data['args']);
                return $data;
            },  $th->getTrace())
        ], 0, 1024);
    }

    /**
     * View template html.
     *
     * @param string $path
     * @param array $data
     * @return View
     */
    protected function view(string $path, array $data = []): View
    {
        $view = App::get()->singleton(View::class);
        $view->variables($data);
        $view->show($path);

        return $view;
    }

    /**
     * Get Throwable.
     *
     * @return Throwable
     */
    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }

    /**
     * Dapatkan informasi dalam json.
     *
     * @return string
     */
    public function getInformation(): string
    {
        return strval($this->information);
    }

    /**
     * Set infomasi dengan format json.
     *
     * @param string|null $information
     * @return Error
     */
    public function setInformation(string|null $information): Error
    {
        if ($information) {
            $this->information = $information;
        }

        return $this;
    }

    /**
     * Laporkan errornya.
     *
     * @return Error
     */
    public function report(): Error
    {
        if (!$this->information) {
            $this->setInformation($this->transformToJson($this->throwable));
        }

        if (env('LOG', 'true') == 'false') {
            return $this;
        }

        if (is_resource($this->stream)) {
            fwrite($this->stream, sprintf(
                '[%s] (%s) %s::%s %s',
                now(DateTimeImmutable::RFC3339_EXTENDED),
                execute_time(),
                $this->throwable->getFile(),
                $this->throwable->getLine(),
                $this->throwable->getMessage()
            ) . PHP_EOL);
        }

        $status = @file_put_contents(
            base_path($this->locationFileLog . $this->nameFileLog),
            $this->getInformation() . PHP_EOL,
            FILE_USE_INCLUDE_PATH | FILE_APPEND | LOCK_EX
        );

        if (!$status) {
            throw new Exception('Error could not save log file');
        }

        return $this;
    }

    /**
     * Show error to dev.
     *
     * @param Throwable $th
     * @return mixed
     */
    public function render(Throwable $th): mixed
    {
        if (!debug()) {
            return unavailable();
        }

        respond()->clean();
        respond()->setCode(Respond::HTTP_INTERNAL_SERVER_ERROR);

        if (!request()->ajax()) {
            return render(helper_path('/errors/trace'), ['error' => $th]);
        }

        respond()->getHeader()->set('Content-Type', 'application/json');
        return $this->getInformation();
    }
}
