<?php

namespace Core\Queue;

use Core\Facades\App;
use Core\Support\Error;
use ErrorException;
use Throwable;

/**
 * Phproutine in background.
 *
 * @class Routine
 * @package \Core\Queue
 */
final class Routine
{
    /**
     * Run a job.
     *
     * @param string $file
     * @return void
     */
    public static function sync(string $file): void
    {
        $file = base_path('/cache/queue/' . $file);
        if (!is_file($file)) {
            return;
        }

        $item = fopen($file, 'r');
        flock($item, LOCK_SH);

        $data = fgets($item);

        flock($item, LOCK_UN);
        fclose($item);

        if ($data) {
            try {
                set_error_handler(function (int $no, string $msg, string $file, int $line): void {
                    throw new ErrorException($msg, $no, E_ERROR, $file, $line);
                });

                $job = unserialize($data);
                App::get()->invoke($job, Job::HANDLE);
            } catch (Throwable $th) {
                try {
                    restore_error_handler();
                    set_error_handler(function (int $no, string $msg, string $file, int $line): void {
                        throw new ErrorException($msg, $no, E_ERROR, $file, $line);
                    });

                    $job->failed($th);
                } catch (Throwable $t) {
                    (new Error($t))->report();
                } finally {
                    (new Error($th))->report();
                }
            }
        }

        @unlink($file);
    }

    /**
     * Run in background.
     *
     * @param string $filename
     * @return bool
     */
    public static function execInBackground(string $filename): bool
    {
        if (PHP_OS == 'WINNT' || PHP_OS == 'WIN32' || PHP_OS == 'Windows') {
            $proc = popen('start "php" /b php "' . base_path('/saya') . '" "queue:sync" "' . $filename . '" "--nooutput"', "r");

            if (!$proc) {
                return false;
            }

            pclose($proc);
            return true;
        }

        return shell_exec("sudo nohup php " . base_path('/saya') . " queue:sync " . $filename . " --nooutput > /dev/null 2>&1 &") === null;
    }
}
