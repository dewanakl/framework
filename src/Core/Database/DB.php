<?php

namespace Core\Database;

use Closure;
use Core\Facades\App;
use Core\Model\Model;
use Core\Model\Query;
use Exception;
use Throwable;

/**
 * Helper class DB untuk customizable nama table.
 *
 * @class DB
 * @package \Core\Database
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
        return (new static)->setTable($name)->clearForDB();
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
     * Apakah masih ada transaction?.
     *
     * @return bool
     */
    public static function inTransaction(): bool
    {
        return App::get()->singleton(DataBase::class)->inTransaction();
    }

    /**
     * Get information driver pdo.
     *
     * @return array<string, mixed>
     */
    public static function getInfoDriver(): array
    {
        return App::get()->singleton(DataBase::class)->getInfoDriver();
    }

    /**
     * Dapatkan log dari semua query.
     *
     * @return array<string, mixed>
     */
    public static function getRecordQueryLog(): array
    {
        return App::get()->singleton(Query::class)->getRecordQueryLog();
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
     * @return mixed
     */
    public static function transaction(Closure $fn): mixed
    {
        $result = null;

        try {
            static::beginTransaction();
            $result = App::get()->resolve($fn);
            static::commit();
        } catch (Exception $e) {
            $result = null;
            static::rollBack();
            static::exception($e);
        }

        return $result;
    }
}
