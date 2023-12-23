<?php

namespace Core\Events;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class dari semua event.
 *
 * @class Event
 * @package \Core\Events
 */
class Event implements StoppableEventInterface
{
    /**
     * Apakah tidak ada pemroses event lebih lanjut?.
     *
     * @var bool $propagationStopped
     */
    private $propagationStopped = false;

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Menghentikan penyebaran event ke listener event selanjutnya.
     *
     * Jika beberapa listener event terhubung ke event yang sama
     * Tidak ada pemroses event selanjutnya akan terjadi setelah meggunakan perintah stop ini
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
