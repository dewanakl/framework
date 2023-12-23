<?php

namespace Core\Events;

/**
 * Fungsi tambahan yang mempermudah.
 *
 * Trait Dispatchable
 * @package \Core\Events
 */
trait Dispatchable
{
    /**
     * Mengirimkan event dengan argumen yang diberikan.
     *
     * @param mixed ...$arguments
     * @return object
     */
    public static function dispatch(mixed ...$arguments): object
    {
        return event(new static(...$arguments));
    }

    /**
     * Kirimkan event dengan argumen yang diberikan jika betul.
     *
     * @param bool $boolean
     * @param mixed ...$arguments
     * @return object|null
     */
    public static function dispatchIf(bool $boolean, mixed ...$arguments): object|null
    {
        if ($boolean) {
            return static::dispatch(...$arguments);
        }

        return null;
    }

    /**
     * Kirimkan event dengan argumen yang diberikan jika salah.
     *
     * @param bool $boolean
     * @param mixed ...$arguments
     * @return object|null
     */
    public static function dispatchUnless(bool $boolean, mixed ...$arguments): object|null
    {
        return static::dispatchIf(!$boolean, ...$arguments);
    }
}
