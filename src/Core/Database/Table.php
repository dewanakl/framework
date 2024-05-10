<?php

namespace Core\Database;

use Closure;
use Core\Facades\App;

/**
 * Membuat tabel dengan mudah.
 *
 * @class Table
 * @package \Core\Database
 */
class Table
{
    /**
     * Param query.
     *
     * @var array $query
     */
    private $query;

    /**
     * Tipe dbms.
     *
     * @var string $type
     */
    private $type;

    /**
     * Nama tabelnya.
     *
     * @var string $table
     */
    private $table;

    /**
     * Alter tabelnya.
     *
     * @var string|null $alter
     */
    private $alter;

    /**
     * Colums di tabel ini.
     *
     * @var array $columns
     */
    private $columns;

    /**
     * Init objek.
     *
     * @return void
     */
    public function __construct()
    {
        $this->type = env('DB_DRIV', 'mysql');
        $this->query = [];
        $this->alter = null;
        $this->columns = [];
    }

    /**
     * Set nama table di database.
     *
     * @param string $name
     * @return Table
     */
    public function table(string $name): Table
    {
        $this->table = $name;
        return $this;
    }

    /**
     * Create table sql.
     *
     * @return string
     */
    public function create(): string
    {
        $query = 'CREATE TABLE IF NOT EXISTS ' . $this->table . ' (' . join(', ', $this->query) . ');';
        $this->query = [];
        $this->columns = [];

        return $query;
    }

    /**
     * Export hasilnya ke string sql.
     *
     * @return string|null
     */
    public function export(): string|null
    {
        if ($this->alter == 'ADD' && !Schema::$dump) {
            foreach ($this->columns as $value) {
                if ($this->checkColumn($value)) {
                    $this->query = [];
                    $this->alter = null;
                    $this->columns = [];
                    return null;
                }
            }
        }

        if ($this->alter == 'ALTER COLUMN') {
            foreach ($this->columns as $c) {
                foreach ($this->query as $i => $q) {
                    if (str_contains($q, $c)) {
                        $this->query[$i] = $c . ' TYPE ' . explode(' ', explode($c, $q)[1])[1];
                    }
                }
            }
        }

        $query = array_map(fn ($data) => $this->alter . ' ' . $data, $this->query);
        if (count($query) == 0) {
            return null;
        }

        $query = 'ALTER TABLE ' . $this->table . ' ' . join(', ', $query) . ';';
        $this->query = [];
        $this->alter = null;
        $this->columns = [];

        return $query;
    }

    /**
     * Check column in this table.
     *
     * @param string $value
     * @return bool
     */
    public function checkColumn(string $value): bool
    {
        $db = App::get()->singleton(DataBase::class);

        if ($this->type == 'pgsql') {
            $query = 'SELECT column_name FROM information_schema.columns WHERE table_name=\'' . $this->table . '\' and column_name=\'' . $value . '\';';
        } else {
            $query = 'SHOW COLUMNS FROM ' . $this->table . ' WHERE Field = \'' . $value . '\';';
        }

        $db->query($query);
        $db->execute();
        return $db->rowCount() != 0;
    }

    /**
     * Get index paling akhir.
     *
     * @return int
     */
    private function getLastArray(): int
    {
        return count($this->query) - 1;
    }

    /**
     * Id, unique, primary key.
     *
     * @param string $name
     * @return void
     */
    public function id(string $name = 'id'): void
    {
        if ($this->type == 'pgsql') {
            $this->query[] = $name . ' BIGSERIAL NOT NULL PRIMARY KEY';
        } else {
            $this->query[] = $name . ' BIGINT NOT NULL PRIMARY KEY AUTO_INCREMENT';
        }
        $this->columns[] = $name;
    }

    /**
     * Tipe string atau varchar.
     *
     * @param string $name
     * @param int $len
     * @return Table
     */
    public function string(string $name, int $len = 255): Table
    {
        $this->query[] = $name . ' VARCHAR(' . strval($len) . ') NOT NULL';
        $this->columns[] = $name;
        return $this;
    }

    /**
     * Tipe integer.
     *
     * @param string $name
     * @return Table
     */
    public function integer(string $name): Table
    {
        $this->query[] = $name . ' BIGINT NOT NULL';
        $this->columns[] = $name;
        return $this;
    }

    /**
     * Tipe text.
     *
     * @param string $name
     * @return Table
     */
    public function text(string $name): Table
    {
        $this->query[] = $name . ' TEXT NOT NULL';
        $this->columns[] = $name;
        return $this;
    }

    /**
     * Tipe boolean.
     *
     * @param string $name
     * @return Table
     */
    public function boolean(string $name): Table
    {
        $this->query[] = $name . ' BOOLEAN NOT NULL';
        $this->columns[] = $name;
        return $this;
    }

    /**
     * Tipe timestamp / datetime.
     *
     * @param string $name
     * @return Table
     */
    public function dateTime(string $name): Table
    {
        if ($this->type == 'pgsql') {
            $this->query[] = $name . ' TIMESTAMP WITHOUT TIME ZONE NOT NULL';
        } else {
            $this->query[] = $name . ' DATETIME NOT NULL';
        }
        $this->columns[] = $name;
        return $this;
    }

    /**
     * created_at and updated_at.
     *
     * @return void
     */
    public function timeStamp(): void
    {
        if ($this->type == 'pgsql') {
            $this->query[] = 'created_at TIMESTAMP WITHOUT TIME ZONE';
            $this->query[] = 'updated_at TIMESTAMP WITHOUT TIME ZONE';
        } else {
            $this->query[] = 'created_at DATETIME';
            $this->query[] = 'updated_at DATETIME';
        }
        $this->columns[] = 'created_at';
        $this->columns[] = 'updated_at';
    }

    /**
     * Boleh kosong.
     *
     * @return Table
     */
    public function nullable(): Table
    {
        $this->query[$this->getLastArray()] = str_replace('NOT NULL', '', end($this->query));
        return $this;
    }

    /**
     * Default value pada dbms.
     *
     * @param string|int|bool $name
     * @return void
     */
    public function default(string|int|bool $name): void
    {
        $data = is_string($name) ? ' DEFAULT \'' . $name . '\'' : ' DEFAULT ' . (is_bool($name) ? ($name ? 'true' : 'false') : strval($name));
        $this->query[$this->getLastArray()] = end($this->query) . $data;
    }

    /**
     * Harus berbeda.
     *
     * @return void
     */
    public function unique(): void
    {
        $this->query[$this->getLastArray()] = end($this->query) . ' UNIQUE';
    }

    /**
     * Bikin indexnya.
     *
     * @param string|array $idx
     * @return void
     */
    public function index(string|array $idx): void
    {
        $this->query[] = sprintf('KEY %s_%s_index (%s)', $this->table, join('_', is_string($idx) ? [$idx] : $idx), join(', ', is_string($idx) ? [$idx] : $idx));
    }

    /**
     * Drop indexnya.
     *
     * @param string|array $idx
     * @return void
     */
    public function dropIndex(string|array $idx): void
    {
        if ($this->type == 'pgsql') {
            $this->query[] = sprintf('DROP INDEX IDX_%s_%s;', $this->table, join('_', is_string($idx) ? [$idx] : $idx));
        } else {
            $this->alter = 'DROP';
            $this->query[$this->getLastArray()] = sprintf('INDEX IDX_%s_%s;', $this->table, join('_', is_string($idx) ? [$idx] : $idx));
        }
    }

    /**
     * Bikin relasi antara nama attribute.
     *
     * @param string $name
     * @return Table
     */
    public function foreign(string $name): Table
    {
        $this->query[] = 'CONSTRAINT FK_' . $this->table . '_' . $name . ' FOREIGN KEY(' . $name . ')';
        return $this;
    }

    /**
     * Dengan nama attribute tabel targetnya.
     *
     * @param string $name
     * @return Table
     */
    public function references(string $name): Table
    {
        $this->query[$this->getLastArray()] = end($this->query) . ' REFERENCES TABLE-TARGET(' . $name . ')';
        return $this;
    }

    /**
     * Nama tabel targetnya.
     *
     * @param string $name
     * @return Table
     */
    public function on(string $name): Table
    {
        $this->query[$this->getLastArray()] = str_replace('TABLE-TARGET', $name, end($this->query));
        return $this;
    }

    /**
     * Hapus nilai pada foreign key juga jika menghapus.
     *
     * @return void
     */
    public function cascadeOnDelete(): void
    {
        $this->query[$this->getLastArray()] = end($this->query) . ' ON DELETE CASCADE';
    }

    /**
     * Tambahkan kolom baru.
     *
     * @param Closure $fn
     * @return void
     */
    public function addColumn(Closure $fn): void
    {
        $this->alter = 'ADD';
        $fn($this);
    }

    /**
     * Hapus kolom.
     *
     * @param string $name
     * @return void
     */
    public function dropColumn(string $name): void
    {
        $this->alter = 'DROP';
        $this->query[$this->getLastArray()] = 'COLUMN ' . $name;
    }

    /**
     * Hapus foreignkey.
     *
     * @param string $name
     * @return void
     */
    public function dropForeign(string $name): void
    {
        $this->alter = 'DROP';
        $this->query[$this->getLastArray()] = ($this->type == 'mysql' ? 'FOREIGN KEY' : 'CONSTRAINT') . ' FK_' . $this->table . '_' . $name;
    }

    /**
     * Rename kolom.
     *
     * @param string $from
     * @param string $to
     * @return void
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->alter = 'RENAME COLUMN';
        $this->query[$this->getLastArray()] = $from . ' TO ' . $to;
        $this->columns[] = $from;
    }

    /**
     * Edit kolomnya.
     *
     * @param Closure $fn
     * @return void
     */
    public function changeColumn(Closure $fn): void
    {
        if ($this->type == 'mysql') {
            $this->alter = 'MODIFY COLUMN';
        } else {
            $this->alter = 'ALTER COLUMN';
        }

        $fn($this);
    }
}
