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
     * @var array<int|string, mixed> $param
     */
    private array $param = [];

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
    public const FETCH = 1;

    /**
     * Data banyak.
     *
     * @var int FetchAll
     */
    public const FETCH_ALL = 2;

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
     * List of selected columns for SELECT statement.
     * Example: ['users.id', 'users.name', 'COUNT(posts.id) AS post_count']
     *
     * @var array
     */
    private array $selects = [];

    /**
     * WHERE conditions.
     * Contains structured data for each condition (basic, nested, raw).
     *
     * @var array
     */
    private array $wheres = [];

    /**
     * JOIN clauses.
     * Each item is a string like "LEFT JOIN posts ON posts.user_id = users.id"
     *
     * @var array
     */
    private array $joins = [];

    /**
     * GROUP BY columns.
     * Example: ['users.id', 'users.role']
     *
     * @var array
     */
    private array $groupByQuery = [];

    /**
     * ORDER BY clauses.
     * Example: ['created_at DESC', 'name ASC']
     *
     * @var array
     */
    private array $orderByQuery = [];

    /**
     * HAVING conditions (after GROUP BY).
     * Structured like $wheres: supports nested, basic, and raw.
     *
     * @var array
     */
    private array $havings = [];

    /**
     * LIMIT value for result set.
     * Null if not set.
     *
     * @var int|null
     */
    private ?int $limitQuery = null;

    /**
     * OFFSET value for result set.
     * Null if not set.
     *
     * @var int|null
     */
    private ?int $offsetQuery = null;

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
            switch ($this->db->getInfoDriver()['DRIVER_NAME']) {
                case 'pgsql':
                    $this->db->exec(sprintf("SET TIME ZONE '%s'", static::$tz));
                    break;

                case 'mysql':
                    $this->db->exec(sprintf("SET time_zone = '%s'", static::$tz));
                    break;
            }
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

            if ($status == static::FETCH) {
                $data[$relational->getAlias($method)] = $relational->setLocalKey($data[$relational->getLocalKey()])->relational();

                if ($with) {
                    foreach ($with as $loop) {
                        $data[$loop->getAlias($method)] = $loop->setLocalKey($data[$loop->getLocalKey()])->relational();
                    }
                }

                continue;
            }

            if ($status == static::FETCH_ALL) {
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
            'string' => fn(mixed $data, string|null $arg): string => strval($data),
            'int' => fn(mixed $data, string|null $arg): int => intval($data),
            'float' => fn(mixed $data, string|null $arg): float => floatval($data),
            'bool' => fn(mixed $data, string|null $arg): bool => boolval($data),
            'datetime' => fn(Time $data, string|null $arg): Time => $data->setFormat(empty($arg) ? null : $arg)
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
     * Start build sql query.
     *
     * @return string
     */
    private function building(): string
    {
        $sql = 'SELECT ';

        if (count($this->selects) === 0) {
            $sql .= '*';
        }

        $selects = [];
        foreach ($this->selects as $value) {
            $selects[] = $value['sql'];
            $this->param = array_merge($this->param, $value['param']);
        }

        $this->selects = [];

        $sql .= implode(', ', $selects);
        $sql .= ' FROM ' . $this->table;

        if (count($this->joins) > 0) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $this->joins = [];

        if (count($this->wheres) > 0) {
            list($s, $p) = $this->buildWhere();
            $sql .= ' WHERE ' . $s;
            $this->param = array_merge($this->param, $p);
        }

        $this->wheres = [];

        if (count($this->groupByQuery) > 0) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupByQuery);
        }

        $this->groupByQuery = [];

        if (count($this->havings) > 0) {
            list($s, $p) = $this->buildHaving();
            $sql .= ' HAVING ' . $s;
            $this->param = array_merge($this->param, $p);
        }

        $this->havings = [];

        if (count($this->orderByQuery) > 0) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderByQuery);
        }

        $this->orderByQuery = [];

        if ($this->limitQuery !== null) {
            $sql .= ' LIMIT ' . strval($this->limitQuery);
        }

        $this->limitQuery = null;

        if ($this->offsetQuery !== null) {
            $sql .= ' OFFSET ' . strval($this->offsetQuery);
        }

        $this->offsetQuery = null;

        return $sql .= ';';
    }

    /**
     * Build where query
     *
     * @return array<array|string>
     */
    private function buildWhere(): array
    {
        $sql = '';
        $param = [];

        foreach ($this->wheres as $index => $where) {
            $prefix = $index === 0 ? '' : ' ' . $where['boolean'] . ' ';

            if ($where['type'] === 'basic') {
                $sql .= $prefix . sprintf('%s %s ?', $where['column'], $where['operator']);
                $param[] = $where['value'];
            } elseif ($where['type'] === 'nested') {
                list($s, $p) = $where['query']->buildWhere();
                $sql .= $prefix . '(' . $s . ')';
                $param = array_merge($param, $p);
            } elseif ($where['type'] === 'raw') {
                $sql .= $prefix . $where['sql'];
                $param = array_merge($param, $where['param']);
            }
        }

        return [$sql, $param];
    }

    /**
     * Build having query
     *
     * @return array<array|string>
     */
    private function buildHaving(): array
    {
        $sql = '';
        $param = [];

        foreach ($this->havings as $index => $having) {
            $prefix = $index === 0 ? '' : ' ' . $having['boolean'] . ' ';

            if ($having['type'] === 'basic') {
                $sql .= $prefix . sprintf('%s %s ?', $having['column'], $having['operator']);
                $param[] = $having['value'];
            } elseif ($having['type'] === 'nested') {
                list($s, $p) = $having['query']->buildHaving();
                $sql .= $prefix . '(' . $s  . ')';
                $param = array_merge($param, $p);
            }
        }

        return [$sql, $param];
    }

    /**
     * Debug querynya.
     *
     * @return void
     */
    public function dd(): void
    {
        $this->query = $this->building();

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
        $this->query = $this->building();

        $replace = $this->query;
        foreach ($this->param as $key => $value) {
            if (is_int($key)) {
                $pos = strpos($replace, '?');
                if ($pos !== false) {
                    $replace = substr_replace($replace, is_null($value) ? 'NULL' : $value, $pos, 1);
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
     * @param string|callable $column
     * @param mixed $value
     * @param string $operator
     * @param string $boolean
     * @return Query
     */
    public function where(string|callable $column, mixed $value = null, string $operator = '=', string $boolean = 'AND'): Query
    {
        if (is_callable($column)) {
            $nested = new static;
            $column($nested);
            $this->wheres[] = [
                'type' => 'nested',
                'boolean' => strtoupper($boolean),
                'query' => $nested,
            ];
        } else {
            $this->wheres[] = [
                'type' => 'basic',
                'boolean' => strtoupper($boolean),
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        return $this;
    }

    /**
     * Where IN syntax sql.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @param bool $not
     * @return Query
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): Query
    {
        if (count($values) === 0) {
            return $this->where('0', '1');
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'type' => 'raw',
            'boolean' => strtoupper($boolean),
            'sql' => sprintf('%s %s (%s)', $column, $not ? 'NOT IN' : 'IN', $placeholders),
            'param' => $values
        ];

        return $this;
    }

    /**
     * Where Not IN syntax sql.
     *
     * @param string $column
     * @param array $values
     * @param string $boolean
     * @return Query
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): Query
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Where NULL syntax sql.
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     * @return Query
     */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): Query
    {
        $this->wheres[] = [
            'type' => 'raw',
            'boolean' => strtoupper($boolean),
            'sql' => sprintf('%s %s', $column, $not ? 'IS NOT NULL' : 'IS NULL'),
            'param' => [],
        ];

        return $this;
    }

    /**
     * Where Not NULL syntax sql.
     *
     * @param string $column
     * @param string $boolean
     * @return Query
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): Query
    {
        return $this->whereNull($column, $boolean, true);
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
        $this->joins[] = sprintf('%s JOIN %s ON %s %s %s', $type, $table, $column, $param, $refers);
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
        $this->orderByQuery[] = sprintf('%s %s', $name, $order === 'ASC' || $order === 'asc' ? 'ASC' : 'DESC');
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
        if (is_string($param)) {
            $param = [$param];
        }

        $this->groupByQuery = array_merge($this->groupByQuery, $param);
        return $this;
    }

    /**
     * Having syntax sql.
     *
     * @param string|callable $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     * @return Query
     */
    public function having(string|callable $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): Query
    {
        if (is_callable($column)) {
            $nested = new static;
            $column($nested);
            $this->havings[] = [
                'type' => 'nested',
                'boolean' => strtoupper($boolean),
                'query' => $nested,
            ];
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }

            $this->havings[] = [
                'type' => 'basic',
                'boolean' => strtoupper($boolean),
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        return $this;
    }

    /**
     * Simplify or having
     *
     * @param string|callable $column
     * @param mixed $operator
     * @param mixed $value
     * @return Query
     */
    public function orHaving(string|callable $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    /**
     * Limit syntax sql.
     *
     * @param int $param
     * @return Query
     */
    public function limit(int $param): Query
    {
        $this->limitQuery = intval($param);
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
        $this->offsetQuery = intval($param);
        return $this;
    }

    /**
     * Select raw syntax sql.
     *
     * @param string|array $select
     * @return Query
     */
    public function select(string|array $select): Query
    {
        if (is_string($select)) {
            $this->selects[] = [
                'sql' => $select,
                'param' => []
            ];

            return $this;
        }

        foreach ($select as $data) {
            if (is_string($data)) {
                $this->selects[] = [
                    'sql' => $data,
                    'param' => []
                ];

                continue;
            }

            $this->selects[] = [
                'sql' => $data[0],
                'param' => $data[1]
            ];
        }

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
        $this->query = $this->building();
        $this->status = static::FETCH_ALL;

        return $this->build($this->execute(function (DataBase $db): array {
            $sets = array();

            do {
                if (@connection_aborted()) {
                    break;
                }

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
        $this->query = $this->building();
        $this->status = static::FETCH;

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

        if (count($this->dates) > 0) {
            $data = [...$data, ...array_combine($this->dates, array_fill(0, count($this->dates), now('Y-m-d H:i:s.u')))];
        }

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

        $this->query = 'UPDATE ' . $this->table;
        $this->query .= ' SET ' . implode(', ', array_map(fn(string $field): string => $field . ' = ?', array_keys($data)));
        $this->param = array_values($data);

        if (count($this->wheres) > 0) {
            list($s, $p) = $this->buildWhere();
            $this->query .= ' WHERE ' . $s;
            $this->param = array_merge($this->param, $p);
        }

        $this->wheres = [];
        $this->query .= ';';

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
        $this->query = 'DELETE FROM ' . $this->table;

        if (count($this->wheres) > 0) {
            list($s, $p) = $this->buildWhere();
            $this->query .= ' WHERE ' . $s;
            $this->param = array_merge($this->param, $p);
        }

        $this->wheres = [];
        $this->query .= ';';

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
