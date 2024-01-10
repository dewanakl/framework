<?php

namespace Core\Support;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use Psr\Clock\ClockInterface;
use ReturnTypeWillChange;
use Stringable;
use Throwable;

/**
 * Time in this application.
 *
 * @class Time
 * @package \Core\Support
 */
class Time extends DateTimeImmutable implements Stringable, JsonSerializable, ClockInterface
{
    /**
     * Nama dari class ini untuk translate.
     *
     * @var string NAME
     */
    public const NAME = 'time';

    /**
     * Benchmark time start.
     *
     * @var float $benchmarkTime
     */
    public static $benchmarkTIme;

    /**
     * Format time.
     *
     * @var string|null
     */
    private $format;

    /**
     * Create new instance with DateTimeZone.
     *
     * @param string $time
     * @return Time
     */
    public static function factory(string $time = 'now'): Time
    {
        try {
            return new Time($time, new DateTimeZone(env('TIMEZONE', 'Asia/Jakarta')));
        } catch (Throwable) {
            return new Time($time, new DateTimeZone(date_default_timezone_get()));
        }
    }

    /**
     * Dapatkan waktu sekarang.
     *
     * @return DateTimeImmutable
     */
    public function now(): DateTimeImmutable
    {
        return $this->factory();
    }

    /**
     * Start the benchmark
     *
     * @return void
     */
    public static function startBenchmark(): void
    {
        static::$benchmarkTIme = hrtime(true) / 1e9;
    }

    /**
     * End the benchmark
     *
     * @return float
     */
    public static function endBenchmark(): float
    {
        return diff_time(static::$benchmarkTIme, hrtime(true) / 1e9);
    }

    /**
     * Tetapkan timezone pada aplikasi ini.
     *
     * @return void
     */
    public static function setTimezoneDefault(): void
    {
        $status = @date_default_timezone_set(Env::get('TIMEZONE', 'Asia/Jakarta'));
        if (!$status) {
            error_clear_last();
            date_default_timezone_set('UTC');
        }
    }

    /**
     * Set format time.
     *
     * @param string|null $format
     * @return Time
     */
    public function setFormat(string|null $format = null): Time
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Ubah objek ke json secara langsung.
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): mixed
    {
        return $this->__toString();
    }

    /**
     * Magic to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        if ($this->format === 'diff') {
            return $this->diffForHumans();
        }

        return $this->format($this->format ? $this->format : 'Y-m-d H:i:s');
    }

    /**
     * Agar bisa dibaca oleh kita.
     *
     * @param int $depth
     * @return string
     */
    public function diffForHumans(int $depth = 1): string
    {
        $translate = translate();
        $interval = $this->diff($this->now());

        $result = [];
        foreach (['y', 'm', 'd', 'h', 'i', 's'] as $short) {
            if ($depth <= 0) {
                break;
            }

            if ($interval->{$short}) {
                $result[] = strval($interval->{$short}) . ' ' . $translate->trans(static::NAME . '.' . $short);
                $depth--;
            }
        }

        if ($result) {
            return join(', ', $result) . ' ' . $translate->trans(static::NAME . ($interval->invert ? '.future' : '.ago'));
        }

        return $translate->trans(static::NAME . '.recently');
    }
}
