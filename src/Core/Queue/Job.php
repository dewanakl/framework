<?php

namespace Core\Queue;

use Throwable;

/**
 * Job in background.
 *
 * @class Job
 * @package \Core\Queue
 */
abstract class Job
{
    public const HANDLE = 'handle';

    /**
     * Dispatch new job.
     *
     * @param mixed[] $param
     * @return void
     */
    public static function dispatch(mixed ...$param): void
    {
        dispatch(new (static::class)(...$param));
    }

    /**
     * Handle job.
     *
     * @return void
     */
    abstract public function handle();

    /**
     * Handle failed job.
     *
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        //
    }
}
