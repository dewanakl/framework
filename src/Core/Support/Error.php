<?php

namespace Core\Support;

use Core\Database\Exception\DatabaseException;
use Core\Facades\App;
use Core\Http\Request;
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
     * Dapatkan informasi dalam json.
     *
     * @return bool|string
     */
    public function getInformation(): bool|string
    {
        return $this->information;
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
     * @param Throwable $th
     * @return Error
     */
    public function report(Throwable $th): Error
    {
        $absoluteFile = base_path($this->locationFileLog . $this->nameFileLog);

        $this->information = $this->transformToJson($th);

        if (env('LOG', 'true') == 'false') {
            return $this;
        }

        $status = @file_put_contents($absoluteFile, $this->information . PHP_EOL, FILE_USE_INCLUDE_PATH | FILE_APPEND | LOCK_EX);
        if (!$status) {
            throw new Exception('Error could not save log file');
        }

        return $this;
    }

    /**
     * Show error to dev.
     *
     * @param Request $request
     * @param Throwable $th
     * @return mixed
     */
    public function render(Request $request, Throwable $th): mixed
    {
        if (!debug()) {
            return unavailable();
        }

        respond()->clean();
        respond()->setCode(500);

        if ($request->ajax()) {
            respond()->getHeader()->set('Content-Type', 'application/json');
            return $this->information;
        }

        respond()->getHeader()->set('Content-Type', 'text/html');
        return render(helper_path('/errors/trace'), ['error' => $th]);
    }
}
