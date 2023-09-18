<?php

namespace Core\Database;

use Closure;
use Core\Facades\App;

/**
 * Helper class untuk skema tabel.
 *
 * @class Schema
 * @package \Core\Database
 */
final class Schema
{
    /**
     * Dump to sql file.
     *
     * @var bool $dump
     */
    public static $dump = false;

    /**
     * Data dari dump.
     *
     * @var array|null $dumpSql
     */
    public static $dumpSql;

    /**
     * Indicate is dump file.
     *
     * @param bool $dump
     * @return void
     */
    public static function setDump(bool $dump): void
    {
        static::$dump = $dump;
    }

    /**
     * Get dump file sql.
     *
     * @return array
     */
    public static function getDump(): array
    {
        return static::$dumpSql ?? [];
    }

    /**
     * Bikin tabel baru.
     *
     * @param string $name
     * @param Closure $attribute
     * @return void
     */
    public static function create(string $name, Closure $attribute): void
    {
        $table = App::get()->singleton(Table::class)->table($name);
        App::get()->resolve($attribute);

        if (static::$dump) {
            static::$dumpSql[] = $table->create();
        } else {
            App::get()->singleton(DataBase::class)->exec($table->create());
        }
    }

    /**
     * Ubah attribute tabelnya.
     *
     * @param string $name
     * @param Closure $attribute
     * @return void
     */
    public static function table(string $name, Closure $attribute): void
    {
        $table = App::get()->singleton(Table::class)->table($name);
        App::get()->resolve($attribute);

        $export = $table->export();
        if ($export) {
            if (static::$dump) {
                static::$dumpSql[] = $export;
            } else {
                App::get()->singleton(DataBase::class)->exec($export);
            }
        }
    }

    /**
     * Hapus tabel.
     *
     * @param string $name
     * @return void
     */
    public static function drop(string $name): void
    {
        if (static::$dump) {
            static::$dumpSql[] = sprintf('DROP TABLE IF EXISTS %s;', $name);
        } else {
            App::get()->singleton(DataBase::class)->exec(sprintf('DROP TABLE IF EXISTS %s;', $name));
        }
    }

    /**
     * Rename tabelnya.
     *
     * @param string $from
     * @param string $to
     * @return void
     */
    public static function rename(string $from, string $to): void
    {
        if (static::$dump) {
            static::$dumpSql[] = sprintf('ALTER TABLE %s RENAME TO %s;', $from, $to);
        } else {
            App::get()->singleton(DataBase::class)->exec(sprintf('ALTER TABLE %s RENAME TO %s;', $from, $to));
        }
    }
}
