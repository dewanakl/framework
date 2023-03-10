<?php

namespace Core\Model;

use Closure;
use Core\Facades\App;
use Exception;
use Throwable;

/**
 * Helper class DB untuk customizable nama table.
 *
 * @class DB
 * @package \Core\Model
 */
final class DB extends Model
{
    /**
     * Nama tabelnya apah?.
     *
     * @param string $name
     * @return Model
     */
    public static function table(string $name): Model
    {
        return (new static)->setTable($name);
    }

    /**
     * Mulai transaksinya.
     *
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return App::get()->singleton(DataBase::class)->beginTransaction();
    }

    /**
     * Commit transaksinya.
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return App::get()->singleton(DataBase::class)->commit();
    }

    /**
     * Kembalikan transaksinya.
     *
     * @return bool
     */
    public static function rollBack(): bool
    {
        return App::get()->singleton(DataBase::class)->rollBack();
    }

    /**
     * Tampilkan errornya.
     *
     * @param Throwable $e
     * @return void
     */
    public static function exception(Throwable $e): void
    {
        App::get()->singleton(DataBase::class)->catchException($e);
    }

    /**
     * DB transaction sederhana.
     *
     * @param Closure $fn
     * @return void
     */
    public static function transaction(Closure $fn): void
    {
        try {
            static::beginTransaction();
            App::get()->resolve($fn);
            static::commit();
        } catch (Exception $e) {
            static::rollBack();
            static::exception($e);
        }
    }
}
