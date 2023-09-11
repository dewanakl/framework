<?php

namespace Core\Kernel;

/**
 * KernelContract dari applikasi ini.
 *
 * @interface KernelContract
 * @package \Core\KernelContract
 */
interface KernelContract
{
    /**
     * Dapatkan lokasi dari app.
     *
     * @return string
     */
    public function path(): string;

    /**
     * Kirim errornya lewat class.
     *
     * @return string
     */
    public function error(): string;

    /**
     * Kumpulan middleware yang dijalankan lebih awal.
     *
     * @return array<int, string>
     */
    public function middlewares(): array;

    /**
     * Registrasi service agar bisa dijalankan.
     *
     * @return array<int, string>
     */
    public function services(): array;
}
