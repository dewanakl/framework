<?php

namespace Core\Model;

use Core\Database\DataBase;
use Core\Facades\App;
use DateTime;
use Exception;
use JsonSerializable;
use ReturnTypeWillChange;
use Stringable;

/**
 * Create raw query sql.
 *
 * @class Query
 * @package \Core\Model
 */
class Query
{
    /**
     * String query sql.
     * 
     * @var string|null $query
     */
    private $query;

    /**
     * Nilai yang akan dimasukan.
     * 
     * @var array $param
     */
    private $param;

    /**
     * Nama tabelnya.
     * 
     * @var string $table
     */
    private $table;

    /**
     * Waktu bikin dan update.
     * 
     * @var array $dates
     */
    private $dates;

    /**
     * Castsing a attribute.
     * 
     * @var array $casts
     */
    protected $casts = [];

    /**
     * Primary key tabelnya.
     * 
     * @var string|null $primaryKey
     */
    private $primaryKey;

    /**
     * Set Target Object.
     * 
     * @var string $targetObject
     */
    private $targetObject;

    /**
     * Set Target relasinya.
     * 
     * @var array $relational
     */
    private $relational;

    /**
     * Object database.
     * 
     * @var DataBase $db
     */
    private $db;

    /**
     * Log query.
     * 
     * @var array $queryLog
     */
    private $queryLog;

    /**
     * Waktu query.
     * 
     * @var float $queryDuration
     */
    private $queryDuration;

    /**
     * Data tunggal.
     * 
     * @var int Fetch
     */
    private const Fetch = 1;

    /**
     * Data banyak.
     * 
     * @var int FetchAll
     */
    private const FetchAll = 2;

    /**
     * Status dari fetch.
     * 
     * @var int|null $status
     */
    private $status;

    /**
     * Buat objek model.
     *
     * @return void
     */
    public function __construct()
    {
        if (!($this->db instanceof DataBase)) {
            $this->db = App::get()->singleton(DataBase::class);
        }
    }

    /**
     * Record query yang terlah dimuat.
     * 
     * @return void
     */
    private function recordQueryLog(): void
    {
        $this->queryLog[] = [
            $this->query,
            round((microtime(true) - $this->queryDuration) * 1000, 2),
            $this->targetObject
        ];

        $this->db->close();
        $this->query = null;
        $this->param = [];
    }

    /**
     * Bind antara query dengan param.
     * 
     * @param string $query
     * @param array $data
     * @return void
     */
    private function bind(string $query, array $data = []): void
    {
        $this->queryDuration = microtime(true);
        $this->query = $query;

        $this->db->query($query);

        foreach ($data as $key => $value) {
            $this->db->bind(is_string($key) ? ':' . $key : $key + 1, $value);
        }
    }

    /**
     * Cek select syntax query.
     * 
     * @return void
     */
    private function checkSelect(): void
    {
        if (!str_contains($this->query ?? '', 'SELECT')) {
            $this->query = 'SELECT * FROM ' . $this->table . $this->query;
        }
    }

    /**
     * Check query if empty.
     * 
     * @return void
     */
    private function checkQuery(): void
    {
        if (!$this->query && !$this->param) {
            $this->query = 'SELECT * FROM ' . $this->table;
        }
    }

    /**
     * Datetime created_at and updated_at.
     * 
     * @param string $datetime
     * @return DateTime
     */
    private function dateTime(string $datetime = 'now'): DateTime
    {
        return new class($datetime) extends DateTime implements Stringable, JsonSerializable
        {
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
                return $this->format('Y-m-d H:i:s');
            }

            /**
             * Agar bisa dibaca oleh kita.
             * 
             * @param int $depth
             * @return string
             */
            public function diffForHumans(int $depth = 1): string
            {
                $interval = $this->diff(new DateTime);
                $grammar = [
                    'y' => 'tahun',
                    'm' => 'bulan',
                    'd' => 'hari',
                    'h' => 'jam',
                    'i' => 'menit',
                    's' => 'detik'
                ];

                $result = [];
                $isEmpty = true;

                foreach ($grammar as $short => $long) {
                    if ($depth <= 0) {
                        break;
                    }

                    if ($interval->{$short}) {
                        $result[] = strval($interval->{$short}) . ' ' . $long;
                        $depth--;
                        $isEmpty = false;
                    }
                }

                if ($isEmpty) {
                    return 'baru saja';
                }

                return join(', ', $result) . ' yang lalu';
            }
        };
    }

    /**
     * Build ke target object.
     * 
     * @param array $data
     * @return Model
     * 
     * @throws Exception
     */
    private function build(array $data): Model
    {
        $model = new $this->targetObject;
        $methods = $this->relational;
        $status = $this->status;

        foreach ($methods as $method) {
            if (!method_exists($model, $method)) {
                throw new Exception('Method ' . $method . ' tidak ada !');
            }

            $relational = $model->{$method}();

            if ($status == static::Fetch) {
                $data[$method] = $relational->setLocalKey($data[$relational->getLocalKey()])->relational();
                continue;
            }

            if ($status == static::FetchAll) {
                foreach ($data as $key => $value) {
                    $value->{$method} = $relational->setLocalKey($value->{$relational->getLocalKey()})->relational();
                    $data[$key] = $value;
                }
            }
        }

        $this->status = null;
        return $model->setAttribute($data);
    }

    /**
     * Casts attribute.
     * 
     * @param string $type
     * @param mixed $data
     * @return mixed
     * 
     * @throws Exception
     */
    private function casts(string $type, mixed $data): mixed
    {
        $grammar = [
            'string' => fn (mixed $data): string => strval($data),
            'int' => fn (mixed $data): int => intval($data),
            'float' => fn (mixed $data): float => floatval($data),
            'bool' => fn (mixed $data): bool => boolval($data)
        ];

        foreach ($grammar as $key => $value) {
            if ($key == $type) {
                return $value($data);
            }
        }

        throw new Exception('Undefined cast type: ' . $type);
    }

    /**
     * Debug querynya.
     *
     * @return void
     */
    public function dd(): void
    {
        $this->checkSelect();
        dd($this->query, $this->param);
    }

    /**
     * Dapatkan log dari semua query.
     * 
     * @return array
     */
    public function getRecordQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Set nama tabelnya.
     *
     * @param string $name
     * @return Query
     */
    public function setTable(string $name): Query
    {
        $this->table = $name;
        return $this;
    }

    /**
     * Set tanggal updatenya.
     *
     * @param array $date
     * @return Query
     */
    public function setDates(array $date): Query
    {
        $this->dates = $date;
        return $this;
    }

    /**
     * Set casts attribute.
     *
     * @param array $casts
     * @return Query
     */
    public function setCasts(array $casts): Query
    {
        $this->casts = $casts;
        return $this;
    }

    /**
     * Set primaryKey.
     *
     * @param string|null $primaryKey
     * @return Query
     */
    public function setPrimaryKey(string|null $primaryKey): Query
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * Set target object.
     *
     * @param string $targetObject
     * @return Query
     */
    public function setObject(string $targetObject): Query
    {
        $this->targetObject = $targetObject;
        $this->relational = [];
        $this->status = null;
        return $this;
    }

    /**
     * Tambahkan relasi dengan fungsi yang ada di model.
     *
     * @param string|array $relational
     * @return Query
     */
    public function with(string|array $relational): Query
    {
        if (!is_array($relational)) {
            $relational = array($relational);
        }

        $this->relational = $relational;
        return $this;
    }

    /**
     * Where syntax sql.
     *
     * @param string $column
     * @param mixed $value
     * @param string $statment
     * @param string $agr
     * @return Query
     */
    public function where(string $column, mixed $value, string $statment = '=', string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        $replaceColumn = str_replace(['.', '-'], '_', $column);

        $this->query = $this->query . sprintf(' %s %s %s :', $agr, $column, $statment) . $replaceColumn;
        $this->param[$replaceColumn] = $value;

        return $this;
    }

    /**
     * Where IN syntax sql.
     *
     * @param string $column
     * @param array|Model $value
     * @param string $agr
     * @return Query
     */
    public function whereIn(string $column, array|Model $value, string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        if ($value instanceof Model) {
            $data = [];
            foreach ($value->toArray() as $val) {
                $data[] = array_values($val)[0];
            }
            $value = $data;
        }

        $value = count($value) == 0 ? ['\'\''] : $value;

        $this->query = $this->query . sprintf(' %s %s IN (%s)', $agr, $column, implode(', ', $value));

        return $this;
    }

    /**
     * Where Not IN syntax sql.
     *
     * @param string $column
     * @param array|Model $value
     * @param string $agr
     * @return Query
     */
    public function whereNotIn(string $column, array|Model $value, string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        if ($value instanceof Model) {
            $data = [];
            foreach ($value->toArray() as $val) {
                $data[] = array_values($val)[0];
            }
            $value = $data;
        }

        $value = count($value) == 0 ? ['\'\''] : $value;

        $this->query = $this->query . sprintf(' %s %s NOT IN (%s)', $agr, $column, implode(', ', $value));

        return $this;
    }

    /**
     * Where NULL syntax sql.
     *
     * @param string $column
     * @param string $agr
     * @return Query
     */
    public function whereNull(string $column, string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        $this->query = $this->query . sprintf(' %s %s IS NULL', $agr, $column);

        return $this;
    }

    /**
     * Where Not NULL syntax sql.
     *
     * @param string $column
     * @param string $agr
     * @return Query
     */
    public function whereNotNull(string $column, string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        $this->query = $this->query . sprintf(' %s %s IS NOT NULL', $agr, $column);

        return $this;
    }

    /**
     * Join syntax sql.
     *
     * @param string $table
     * @param string $column
     * @param string $refers
     * @param string $param
     * @param string $type
     * @return Query
     */
    public function join(string $table, string $column, string $refers, string $param = '=', string $type = 'INNER'): Query
    {
        $this->query = $this->query . sprintf(' %s JOIN %s ON %s %s %s', $type, $table, $column, $param, $refers);
        return $this;
    }

    /**
     * Left join syntax sql.
     *
     * @param string $table
     * @param string $column
     * @param string $refers
     * @param string $param
     * @return Query
     */
    public function leftJoin(string $table, string $column, string $refers, string $param = '='): Query
    {
        return $this->join($table, $column, $refers, $param, 'LEFT');
    }

    /**
     * Right join syntax sql.
     *
     * @param string $table
     * @param string $column
     * @param string $refers
     * @param string $param
     * @return Query
     */
    public function rightJoin(string $table, string $column, string $refers, string $param = '='): Query
    {
        return $this->join($table, $column, $refers, $param, 'RIGHT');
    }

    /**
     * Full join syntax sql.
     *
     * @param string $table
     * @param string $column
     * @param string $refers
     * @param string $param
     * @return Query
     */
    public function fullJoin(string $table, string $column, string $refers, string $param = '='): Query
    {
        return $this->join($table, $column, $refers, $param, 'FULL OUTER');
    }

    /**
     * Order By syntax sql.
     *
     * @param string $name
     * @param string $order
     * @return Query
     */
    public function orderBy(string $name, string $order = 'ASC'): Query
    {
        $agr = str_contains($this->query, 'ORDER BY') ? ', ' : ' ORDER BY ';
        $this->query = $this->query . $agr . $name . ' ' . ($order === 'ASC' || $order === 'asc' ? 'ASC' : 'DESC');

        return $this;
    }

    /**
     * Group By syntax sql.
     *
     * @param string|array $param
     * @return Query
     */
    public function groupBy(string|array $param): Query
    {
        if (is_array($param)) {
            $param = implode(', ', $param);
        }

        $this->query = $this->query . ' GROUP BY ' . $param;
        return $this;
    }

    /**
     * Having syntax sql.
     *
     * @param string $param
     * @return Query
     */
    public function having(string $param): Query
    {
        $this->query = $this->query . ' HAVING ' . $param;
        return $this;
    }

    /**
     * Limit syntax sql.
     *
     * @param int $param
     * @return Query
     */
    public function limit(int $param): Query
    {
        $this->query = $this->query . ' LIMIT ' . strval(intval($param));
        return $this;
    }

    /**
     * Offset syntax sql.
     *
     * @param int $param
     * @return Query
     */
    public function offset(int $param): Query
    {
        $this->query = $this->query . ' OFFSET ' . strval(intval($param));
        return $this;
    }

    /**
     * Select raw syntax sql.
     *
     * @param string|array $param
     * @return Query
     */
    public function select(string|array $param): Query
    {
        if (is_array($param)) {
            $param = implode(', ', $param);
        }

        $this->checkSelect();
        $data = explode(' FROM', $this->query, 2);

        $this->query = $data[0] . (str_contains($this->query, 'SELECT *') ? ' ' : ', ') . $param . ' FROM' . $data[1];
        $this->query = str_replace('SELECT *', 'SELECT', $this->query);

        return $this;
    }

    /**
     * Count sql aggregate.
     *
     * @param string $name
     * @return Query
     */
    public function count(string $name = '*'): Query
    {
        return $this->select('COUNT(' . $name . ')' . ($name == '*' ? '' : ' AS ' . $name));
    }

    /**
     * Max sql aggregate.
     *
     * @param string $name
     * @return Query
     */
    public function max(string $name): Query
    {
        return $this->select('MAX(' . $name . ') AS ' . $name);
    }

    /**
     * Min sql aggregate.
     *
     * @param string $name
     * @return Query
     */
    public function min(string $name): Query
    {
        return $this->select('MIN(' . $name . ') AS ' . $name);
    }

    /**
     * Avg sql aggregate.
     *
     * @param string $name
     * @return Query
     */
    public function avg(string $name): Query
    {
        return $this->select('AVG(' . $name . ') AS ' . $name);
    }

    /**
     * Sum sql aggregate.
     *
     * @param string $name
     * @return Query
     */
    public function sum(string $name): Query
    {
        return $this->select('SUM(' . $name . ') AS ' . $name);
    }

    /**
     * Hitung jumlah rownya.
     *
     * @return int
     */
    public function rowCount(): int
    {
        return $this->db->rowCount();
    }

    /**
     * Cari model id.
     *
     * @param mixed $id
     * @param string|null $where
     * @return Query
     * 
     * @throws Exception
     */
    public function id(mixed $id, string|null $where = null): Query
    {
        if (empty($this->primaryKey)) {
            throw new Exception('Primary key tidak terdefinisi !');
        }

        return $this->where($where ? $where : $this->primaryKey, $id);
    }

    /**
     * Delete by id primary key.
     *
     * @param int $id
     * @return int
     */
    public function destroy(int $id): int
    {
        return $this->id($id)->delete();
    }

    /**
     * Cari berdasarkan id.
     *
     * @param mixed $id
     * @param string|null $where
     * @return Model
     */
    public function find(mixed $id, string|null $where = null): Model
    {
        return $this->id($id, $where)->limit(1)->first();
    }

    /**
     * Ambil semua data.
     *
     * @return Model
     */
    public function get(): Model
    {
        $this->checkSelect();
        $this->status = static::FetchAll;

        $this->bind($this->query, $this->param ?? []);
        $this->db->execute();

        $isDates = false;
        if (count($this->dates) > 0) {
            $isDates = true;
        }

        $sets = array();
        do {
            $record = $this->db->fetch();
            if (!$record) {
                break;
            }

            if ($isDates) {
                foreach ($this->dates as $value) {
                    if (!empty($record->{$value})) {
                        $record->{$value} = $this->dateTime($record->{$value});
                    }
                }
            }

            foreach ($this->casts as $attribute => $type) {
                if (!empty($record->{$attribute})) {
                    $record->{$attribute} = $this->casts($type, $record->{$attribute});
                }
            }

            $sets[] = $record;
        } while (true);

        $this->recordQueryLog();
        return $this->build($sets);
    }

    /**
     * Ambil satu data aja paling atas.
     *
     * @return Model
     */
    public function first(): Model
    {
        $this->checkSelect();
        $this->status = static::Fetch;

        $this->bind($this->query, $this->param ?? []);
        $this->db->execute();

        $record = $this->db->fetch();
        $set = $record === false ? [] : get_object_vars($record);

        if (count($this->dates) > 0) {
            foreach ($this->dates as $value) {
                if (!empty($set[$value])) {
                    $set[$value] = $this->dateTime($set[$value]);
                }
            }
        }

        foreach ($this->casts as $attribute => $type) {
            if (!empty($set[$attribute])) {
                $set[$attribute] = $this->casts($type, $set[$attribute]);
            }
        }

        $this->recordQueryLog();
        return $this->build($set);
    }

    /**
     * Isi datanya.
     * 
     * @param array $data
     * @return Model
     */
    public function create(array $data): Model
    {
        $isDates = false;
        if (count($this->dates) > 0) {
            $isDates = true;
        }

        if ($isDates) {
            $now = now('Y-m-d H:i:s.u');
            $data = [...$data, ...array_combine($this->dates, array($now, $now))];
        }

        $keys = array_keys($data);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $keys),
            implode(', ', array_map(fn ($field) => ':' . $field, $keys))
        );

        $this->bind($query, $data);
        $this->db->execute();

        if ($this->primaryKey) {
            $id = $this->db->lastInsertId();
            if ($id) {
                $data[$this->primaryKey] = $id;
            }
        }

        if ($isDates) {
            foreach ($this->dates as $value) {
                $data[$value] = $this->dateTime($data[$value]);
            }
        }

        $this->recordQueryLog();
        return $this->build($data);
    }

    /**
     * Update datanya.
     * 
     * @param array $data
     * @return int
     */
    public function update(array $data): int
    {
        if (count($this->dates) > 0) {
            $data = [...$data, ...[$this->dates[1] => now('Y-m-d H:i:s.u')]];
        }

        $query = is_null($this->query) ? 'UPDATE ' . $this->table . ' WHERE' : str_replace('SELECT * FROM', 'UPDATE', $this->query);
        $setQuery = 'SET ' . implode(', ', array_map(fn ($field) => $field . ' = :' . $field, array_keys($data))) . ($this->query ? ' WHERE' : '');

        $this->bind(str_replace('WHERE', $setQuery, $query), [...$data, ...$this->param ?? []]);
        $this->db->execute();
        $this->recordQueryLog();
        return $this->db->rowCount();
    }

    /**
     * Hapus datanya.
     * 
     * @return int
     */
    public function delete(): int
    {
        $query = is_null($this->query) ? 'DELETE FROM ' . $this->table : str_replace('SELECT *', 'DELETE', $this->query);
        $this->bind($query, $this->param ?? []);

        $this->db->execute();
        $this->recordQueryLog();
        return $this->db->rowCount();
    }
}
