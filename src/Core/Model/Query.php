<?php

namespace Core\Model;

use Closure;
use Core\Database\DataBase;
use Core\Facades\App;
use Core\Support\Time;
use Exception;

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
     * @var array<int|string, mixed>|null $param
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
     * @var array<int, string> $dates
     */
    private $dates = [];

    /**
     * Castsing a attribute.
     *
     * @var array<string, string> $casts
     */
    protected $casts = [];

    /**
     * Primary key tabelnya.
     *
     * @var string|null $primaryKey
     */
    private $primaryKey;

    /**
     * Primary key tabelnya.
     *
     * @var string|null $typeKey
     */
    private $typeKey;

    /**
     * Set Target Object.
     *
     * @var string $targetObject
     */
    private $targetObject;

    /**
     * Set Target relasinya.
     *
     * @var array<int, mixed> $relational
     */
    private $relational;

    /**
     * Is subquery.
     *
     * @var string|null $subQuery
     */
    private $subQuery;

    /**
     * Object database.
     *
     * @var DataBase $db
     */
    private $db;

    /**
     * Log query.
     *
     * @var array<int, array<string, mixed>>|null $queryLog
     */
    private $queryLog;

    /**
     * Waktu query.
     *
     * @var float $queryDuration
     */
    private $queryDuration;

    /**
     * Fillable di database.
     *
     * @var array<int, string> $fillable
     */
    private $fillable;

    /**
     * Date format default.
     *
     * @var string|null
     */
    private $dateFormat;

    /**
     * Data tunggal.
     *
     * @var int Fetch
     */
    public const Fetch = 1;

    /**
     * Data banyak.
     *
     * @var int FetchAll
     */
    public const FetchAll = 2;

    /**
     * Status dari fetch.
     *
     * @var int|null $status
     */
    private $status;

    /**
     * Timezone to database.
     *
     * @var string|null
     */
    public static $tz;

    /**
     * Buat objek model.
     *
     * @return void
     */
    public function __construct()
    {
        if (!($this->db instanceof DataBase)) {
            /** @var DataBase $db */
            $db = App::get()->singleton(DataBase::class);
            $this->db = $db;
        }
    }

    /**
     * Set timezone to database.
     *
     * @return void
     */
    public static function setTimezoneBeforeQuery(): void
    {
        static::$tz = date_default_timezone_get();
    }

    /**
     * Record query yang terlah dimuat.
     *
     * @return void
     */
    private function recordQueryLog(): void
    {
        if (debug()) {
            $this->queryLog[] = [
                'query' => $this->query,
                'time' => round((microtime(true) - $this->queryDuration) * 1000, 2),
                'model' => $this->targetObject
            ];
        }
    }

    /**
     * Execute this query.
     *
     * @param Closure $callback
     * @return mixed
     */
    private function execute(Closure $callback): mixed
    {
        $this->queryDuration = microtime(true);

        if (static::$tz) {
            $this->db->exec(sprintf('SET TIMEZONE TO \'%s\';', static::$tz));
        }

        if ($this->query) {
            $this->db->query($this->query);
        }

        if ($this->param) {
            foreach ($this->param as $key => $value) {
                $this->db->bind(is_string($key) ? ':' . $key : intval($key) + 1, $value);
            }
        }

        $this->db->execute();
        $result = $callback($this->db);
        $this->db->close();

        $this->recordQueryLog();
        $this->query = null;
        $this->param = [];

        return $result;
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
     * Build ke target object.
     *
     * @param array<int|string, mixed> $data
     * @return Model<int|string, mixed>
     *
     * @throws Exception
     */
    private function build(array $data): Model
    {
        /** @var Model<int|string, mixed> $model */
        $model = new $this->targetObject;
        list($methods, $parameters) = $this->relational ? $this->relational : [[], []];
        $status = $this->status;

        foreach ($methods as $method) {
            if (!method_exists($model, $method)) {
                throw new Exception('Method ' . $method . ' tidak ada !');
            }

            $relational = App::get()->invoke($model, $method, $parameters);
            $with = $relational->getWith();

            if ($status == static::Fetch) {
                $data[$relational->getAlias($method)] = $relational->setLocalKey($data[$relational->getLocalKey()])->relational();

                if ($with) {
                    foreach ($with as $loop) {
                        $data[$loop->getAlias($method)] = $loop->setLocalKey($data[$loop->getLocalKey()])->relational();
                    }
                }

                continue;
            }

            if ($status == static::FetchAll) {
                foreach ($data as $key => $value) {
                    $value->{$relational->getAlias($method)} = $relational->setLocalKey($value->{$relational->getLocalKey()})->relational();

                    if ($with) {
                        foreach ($with as $loop) {
                            $value->{$loop->getAlias($method)} = $loop->setLocalKey($value->{$loop->getLocalKey()})->relational();
                        }
                    }

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
            'string' => fn (mixed $data, string|null $arg): string => strval($data),
            'int' => fn (mixed $data, string|null $arg): int => intval($data),
            'float' => fn (mixed $data, string|null $arg): float => floatval($data),
            'bool' => fn (mixed $data, string|null $arg): bool => boolval($data),
            'datetime' => fn (Time $data, string|null $arg): Time => $data->setFormat(empty($arg) ? null : $arg)
        ];

        foreach ($grammar as $key => $value) {
            if (str_contains($type, $key)) {
                return $value(
                    $data,
                    $key == 'datetime' ? substr($type, strlen($key) + 1) : null
                );
            }
        }

        throw new Exception(sprintf('Undefined cast type: %s available [%s]', $type, implode(', ', array_keys($grammar))));
    }

    /**
     * Debug querynya.
     *
     * @return void
     */
    public function dd(): void
    {
        $this->checkSelect();
        dd(
            [
                'query' => $this->query,
                'param' => $this->param,
                'casts' => $this->casts,
                'dateFormat' => $this->dateFormat,
                'dates' => $this->dates,
                'fillable' => $this->fillable,
                'primaryKey' => $this->primaryKey,
                'queryLog' => $this->queryLog,
                'table' => $this->table,
                'targetObject' => $this->targetObject,
                'typeKey' => $this->typeKey,
            ]
        );
    }

    /**
     * Cast to object Time.
     *
     * @param array<string, mixed>|object $attribute
     * @return array<string, mixed>|object
     */
    private function parseDate(array|object $attribute): array|object
    {
        if (!$this->dates) {
            return $attribute;
        }

        foreach ($this->dates as $value) {
            if (is_object($attribute)) {
                if (!empty($attribute->{$value})) {
                    $attribute->{$value} = Time::factory($attribute->{$value})->setFormat($this->dateFormat);
                }

                continue;
            }

            if (!empty($attribute[$value])) {
                $attribute[$value] = Time::factory($attribute[$value])->setFormat($this->dateFormat);
            }
        }

        return $attribute;
    }

    /**
     * Cast to object.
     *
     * @param array<string, mixed>|object $attribute
     * @return array<string, mixed>|object
     */
    private function parseCast(array|object $attribute): array|object
    {
        if (!$this->casts) {
            return $attribute;
        }

        foreach ($this->casts as $att => $type) {
            if (is_object($attribute)) {
                if (!empty($attribute->{$att})) {
                    $attribute->{$att} = $this->casts($type, $attribute->{$att});
                }

                continue;
            }

            if (!empty($attribute[$att])) {
                $attribute[$att] = $this->casts($type, $attribute[$att]);
            }
        }

        return $attribute;
    }

    /**
     * Get query now.
     *
     * @return string
     */
    public function getQuery(): string
    {
        $this->checkQuery();

        $replace = $this->query;
        foreach ($this->param as $key => $value) {
            if (is_int($key)) {
                $pos = strpos($replace, '?');
                if ($pos !== false) {
                    $replace = substr_replace($replace, $value, $pos, 1);
                }

                continue;
            }

            $replace = str_replace(':' . $key, $value, $replace);
        }

        return $replace;
    }

    /**
     * Set fillable.
     *
     * @param array<int, string> $fillable
     * @return Query
     */
    public function setFillable(array $fillable): Query
    {
        $this->fillable = $fillable;
        return $this;
    }

    /**
     * Set date format.
     *
     * @param string|null $dateFormat
     * @return Query
     */
    public function setDateFormat(string|null $dateFormat = null): Query
    {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    /**
     * Dapatkan log dari semua query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecordQueryLog(): array
    {
        return $this->queryLog ?? [];
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
     * @param array<int, string> $date
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
     * @param array<string, string> $casts
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
    public function setPrimaryKey(string|null $primaryKey = null): Query
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * Set typeKey.
     *
     * @param string|null $typeKey
     * @return Query
     */
    public function setTypeKey(string|null $typeKey): Query
    {
        $this->typeKey = $typeKey;
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
     * @param string|array<int, string> $relational
     * @param array<int, mixed> $optional
     * @return Query
     */
    public function with(string|array $relational, array $optional = []): Query
    {
        if (!is_array($relational)) {
            $relational = array($relational);
        }

        $this->relational = [$relational, $optional];
        return $this;
    }

    /**
     * Where syntax sql.
     *
     * @param string|Closure $column
     * @param mixed $value
     * @param string $statment
     * @param string $agr
     * @return Query
     */
    public function where(string|Closure $column, mixed $value = null, string $statment = '=', string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        if ($column instanceof Closure) {
            $this->subQuery = '(';
            $column($this);
            $this->subQuery = null;
            $this->query .= ')';
            return $this;
        }

        $this->query = $this->query . sprintf(' %s %s %s %s ?', $agr, $this->subQuery, $column, $statment);
        $this->param[] = $value;

        return $this;
    }

    /**
     * Insert raw query.
     *
     * @param string $statment
     * @param string $agr
     * @return Query
     */
    public function whereRaw(string $statment, string $agr = 'AND'): Query
    {
        $this->checkQuery();

        if (!str_contains($this->query ?? '', 'WHERE')) {
            $agr = 'WHERE';
        }

        $this->query = $this->query . sprintf(' %s %s', $agr, $statment);
        return $this;
    }

    /**
     * Where IN syntax sql.
     *
     * @param string $column
     * @param array<int, mixed>|Model<int|string, mixed> $value
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

        $value = array_map(function (string $arr): string {
            return sprintf('\'%s\'', $arr);
        }, count($value) == 0 ? ['\'\''] : $value);

        $this->query = $this->query . sprintf(' %s %s IN (?)', $agr, $column);
        $this->param[] = implode(', ', $value);

        return $this;
    }

    /**
     * Where Not IN syntax sql.
     *
     * @param string $column
     * @param array<int, mixed>|Model<int|string, mixed> $value
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

        $value = array_map(function (string $arr) {
            return sprintf('\'%s\'', $arr);
        }, count($value) == 0 ? ['\'\''] : $value);

        $this->query = $this->query . sprintf(' %s %s NOT IN (?)', $agr, $column);
        $this->param[] = implode(', ', $value);

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
        $this->checkQuery();
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
        $this->checkQuery();

        $agr = str_contains($this->query, 'ORDER BY') ? ', ' : ' ORDER BY ';
        $this->query = $this->query . $agr . $name . ' ' . ($order === 'ASC' || $order === 'asc' ? 'ASC' : 'DESC');

        return $this;
    }

    /**
     * Group By syntax sql.
     *
     * @param string|array<int, string> $param
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
        $this->checkSelect();
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
     * @param string|array<int, string> $param
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
     * @param string|null $as
     * @return Query
     */
    public function count(string $name = '*', string|null $as = null): Query
    {
        return $this->select('COUNT(' . $name . ')' . ($name == '*' ? '' : ' AS ' . ($as ? $as : $name)));
    }

    /**
     * Max sql aggregate.
     *
     * @param string $name
     * @param string|null $as
     * @return Query
     */
    public function max(string $name, string|null $as = null): Query
    {
        return $this->select('MAX(' . $name . ') AS ' . ($as ? $as : $name));
    }

    /**
     * Min sql aggregate.
     *
     * @param string $name
     * @param string|null $as
     * @return Query
     */
    public function min(string $name, string|null $as = null): Query
    {
        return $this->select('MIN(' . $name . ') AS ' . ($as ? $as : $name));
    }

    /**
     * Avg sql aggregate.
     *
     * @param string $name
     * @param string|null $as
     * @return Query
     */
    public function avg(string $name, string|null $as = null): Query
    {
        return $this->select('AVG(' . $name . ') AS ' . ($as ? $as : $name));
    }

    /**
     * Sum sql aggregate.
     *
     * @param string $name
     * @param string|null $as
     * @return Query
     */
    public function sum(string $name, string|null $as = null): Query
    {
        return $this->select('SUM(' . $name . ') AS ' . ($as ? $as : $name));
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
        if (empty($this->primaryKey) && $where === null) {
            throw new Exception('Primary key is\'n defined !');
        }

        return $this->where($where ? $where : $this->primaryKey, $id);
    }

    /**
     * Delete by id primary key.
     *
     * @param int|string|array<int, int|string> $id
     * @return int
     *
     * @throws Exception
     */
    public function destroy(int|string|array $id): int
    {
        if (is_array($id)) {
            if (empty($this->primaryKey)) {
                throw new Exception('Primary key is\'n defined !');
            }

            return $this->whereIn($this->primaryKey, $id)->delete();
        }

        return $this->id($id)->delete();
    }

    /**
     * Cari berdasarkan id.
     *
     * @param mixed $id
     * @param string|null $where
     * @return Model<int|string, mixed>
     */
    public function find(mixed $id, string|null $where = null): Model
    {
        return $this->id($id, $where)->limit(1)->first();
    }

    /**
     * Ambil semua data.
     *
     * @return Model<int|string, mixed>
     */
    public function get(): Model
    {
        $this->checkSelect();
        $this->status = static::FetchAll;

        return $this->build($this->execute(function (DataBase $db): array {
            $sets = array();

            do {
                $record = $db->fetch();
                if (!$record) {
                    break;
                }

                $sets[] = $this->parseCast($this->parseDate($record));
            } while (true);

            return $sets;
        }));
    }

    /**
     * Ambil satu data aja paling atas.
     *
     * @return Model<int|string, mixed>
     */
    public function first(): Model
    {
        $this->checkSelect();
        $this->status = static::Fetch;

        return $this->build(
            $this->parseCast(
                $this->parseDate($this->execute(function (DataBase $db): array {
                    $record = $db->fetch();
                    return $record === false ? [] : get_object_vars($record);
                }))
            )
        );
    }

    /**
     * Isi datanya.
     *
     * @param array<string, mixed> $data
     * @return Model<int|string, mixed>
     */
    public function create(array $data): Model
    {
        if ($this->fillable) {
            $temp = [];
            foreach ($data as $key => $value) {
                if (in_array($key, $this->fillable, true)) {
                    $temp[$key] = $value;
                }
            }
            $data = $temp;
        }

        $now = now('Y-m-d H:i:s.u');
        $data = [...$data, ...array_combine($this->dates, array($now, $now))];

        $this->param = array_values($data);
        $this->query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_keys($data)),
            implode(', ', array_fill(0, count($this->param), '?'))
        );

        return $this->build($this->parseCast($this->parseDate($this->execute(function (DataBase $db) use ($data): array {
            if ($this->primaryKey && $this->typeKey) {
                $id = $db->lastInsertId(sprintf('%s_%s_seq', $this->table, $this->primaryKey));
                if ($id) {
                    $data[$this->primaryKey] = $this->casts($this->typeKey, $id);
                }
            }

            return $data;
        }))));
    }

    /**
     * Update datanya.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function update(array $data): int
    {
        if (count($this->dates) > 0) {
            $data = [...$data, ...[$this->dates[1] => now('Y-m-d H:i:s.u')]];
        }

        $query = is_null($this->query) ? 'UPDATE ' . $this->table . ' WHERE' : str_replace('SELECT * FROM', 'UPDATE', $this->query);
        $setQuery = 'SET ' . implode(', ', array_map(fn (string $field): string => $field . ' = ?', array_keys($data))) . ($this->query ? ' WHERE' : '');

        $this->query = str_replace('WHERE', $setQuery, $query);
        $this->param = array_values([...$data, ...$this->param ?? []]);

        return $this->execute(function (DataBase $db): int {
            return $db->rowCount();
        });
    }

    /**
     * Hapus datanya.
     *
     * @return int
     */
    public function delete(): int
    {
        $this->query = is_null($this->query) ? 'DELETE FROM ' . $this->table : str_replace('SELECT *', 'DELETE', $this->query);

        return $this->execute(function (DataBase $db): int {
            return $db->rowCount();
        });
    }

    /**
     * Call this method.
     *
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->{$name}(...$arguments);
    }
}
