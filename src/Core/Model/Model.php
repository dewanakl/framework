<?php

namespace Core\Model;

use ArrayIterator;
use Closure;
use Core\Facades\App;
use Exception;
use IteratorAggregate;
use JsonSerializable;
use ReturnTypeWillChange;
use Traversable;

/**
 * Representasi table database.
 * 
 * @method static \Core\Model\Query with(string|array $relational)
 * @method static \Core\Model\Query where(string $column, mixed $value, string $statment = '=', string $agr = 'AND')
 * @method static \Core\Model\Query whereNull(string $column, string $agr = 'AND')
 * @method static \Core\Model\Query whereNotNull(string $column, string $agr = 'AND')
 * @method static \Core\Model\Query join(string $table, string $column, string $refers, string $param = '=', string $type = 'INNER')
 * @method static \Core\Model\Query leftJoin(string $table, string $column, string $refers, string $param = '=')
 * @method static \Core\Model\Query rightJoin(string $table, string $column, string $refers, string $param = '=')
 * @method static \Core\Model\Query fullJoin(string $table, string $column, string $refers, string $param = '=')
 * @method static \Core\Model\Query orderBy(string $name, string $order = 'ASC')
 * @method static \Core\Model\Query groupBy(string|array $param)
 * @method static \Core\Model\Query limit(int $param)
 * @method static \Core\Model\Query offset(int $param)
 * @method static \Core\Model\Query select(string|array $param)
 * @method static \Core\Model\Query count(string $name = '*')
 * @method static \Core\Model\Query max(string $name)
 * @method static \Core\Model\Query min(string $name)
 * @method static \Core\Model\Query avg(string $name)
 * @method static \Core\Model\Query sum(string $name)
 * @method static \Core\Model\Query id(mixed $id, string|null $where = null)
 * @method static \Core\Model\Model get()
 * @method static \Core\Model\Model first()
 * @method static \Core\Model\Model find(mixed $id, string|null $where = null)
 * @method static \Core\Model\Model create(array $data)
 * @method static bool destroy(int $id)
 * @method static bool update(array $data)
 * @method static bool delete()
 * 
 * @see \Core\Model\Query
 *
 * @class Model
 * @package \Core\Model
 */
abstract class Model implements IteratorAggregate, JsonSerializable
{
    /**
     * Nama tabelnya.
     * 
     * @var string $table
     */
    protected $table;

    /**
     * Nama dari primaryKey.
     * 
     * @var string $primaryKey
     */
    protected $primaryKey = 'id';

    /**
     * Tipe dari dates.
     * 
     * @var array $dates
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Attributes hasil query.
     * 
     * @var array|bool $attributes
     */
    protected $attributes;

    /**
     * Mempunyai satu dari.
     * 
     * @param string $model
     * @param string $foreign_key
     * @param string|null $local_key
     * @return HasOne
     */
    protected function hasOne(string $model, string $foreign_key, string|null $local_key = null): HasOne
    {
        return new HasOne($model, $local_key ? $local_key : $this->primaryKey, $foreign_key);
    }

    /**
     * Mempunyai banyak dari.
     * 
     * @param string $model
     * @param string $foreign_key
     * @param string|null $local_key
     * @return HasMany
     */
    protected function hasMany(string $model, string $foreign_key, string|null $local_key = null): HasMany
    {
        return new HasMany($model, $foreign_key, $local_key ? $local_key : $this->primaryKey);
    }

    /**
     * Set attributenya.
     *
     * @param array|bool $data
     * @return Model
     */
    public function setAttribute(array|bool $data): Model
    {
        $this->attributes = $data;
        return $this;
    }

    /**
     * Set nama tabelnya.
     *
     * @param string $name
     * @return Model
     */
    public function setTable(string $name): Model
    {
        $this->table = $name;
        return $this;
    }

    /**
     * Hapus attribute dates dan primaryKey.
     *
     * @return Model
     */
    public function clearForDB(): Model
    {
        $this->dates = [];
        $this->primaryKey = null;
        return $this;
    }

    /**
     * Ambil attribute.
     *
     * @return array
     */
    public function attribute(): array
    {
        if (is_bool($this->attributes)) {
            return [];
        }

        return $this->attributes ?? [];
    }

    /**
     * Ubah objek agar bisa iterasi.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attribute());
    }

    /**
     * Ubah objek ke json secara langsung.
     *
     * @return array
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return $this->attribute();
    }

    /**
     * Ubah objek ke array.
     *
     * @return array
     */
    public function __serialize(): array
    {
        return $this->attribute();
    }

    /**
     * Kebalikan dari serialize.
     *
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->attributes = $data;
    }

    /**
     * Eksport to json.
     *
     * @return string|bool
     */
    public function toJson(): string|bool
    {
        return json_encode($this->attribute());
    }

    /**
     * Eksport to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        foreach ($this->attribute() as $key => $value) {
            $this->attributes[$key] = is_object($value) ? get_object_vars($value) : $value;
        }

        return $this->attribute();
    }

    /**
     * Get primaryKey.
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Error dengan fungsi.
     *
     * @param Closure|null $fn
     * @return mixed
     */
    public function fail(Closure|null $fn = null): mixed
    {
        if ($this->attributes) {
            return $this;
        }

        if ($fn === null) {
            return false;
        }

        return App::get()->resolve($fn);
    }

    /**
     * Refresh attributnya.
     *
     * @return Model
     */
    public function refresh(): Model
    {
        return $this->find($this->__get($this->primaryKey));
    }

    /**
     * Save perubahan pada attribute dengan primarykey.
     *
     * @return bool
     * 
     * @throws Exception
     */
    public function save(): bool
    {
        if (empty($this->primaryKey) || empty($this->__get($this->primaryKey))) {
            throw new Exception('Nilai primary key tidak ada !');
        }

        return $this->id($this->__get($this->primaryKey))->update($this->except([$this->primaryKey])->attribute());
    }

    /**
     * Ambil sebagian dari attribute.
     * 
     * @param array $only
     * @return Model
     */
    public function only(array $only): Model
    {
        $temp = [];
        foreach ($only as $ol) {
            $temp[$ol] = $this->__get($ol);
        }

        $this->attributes = $temp;
        return $this;
    }

    /**
     * Ambil kecuali dari attribute.
     * 
     * @param array $except
     * @return Model
     */
    public function except(array $except): Model
    {
        $temp = [];
        foreach ($this->attribute() as $key => $value) {
            if (!in_array($key, $except)) {
                $temp[$key] = $value;
            }
        }

        $this->attributes = $temp;
        return $this;
    }

    /**
     * Ambil nilai dari attribute.
     * 
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return ($this->__isset($name)) ? $this->attributes[$name] : null;
    }

    /**
     * Isi nilai ke model ini.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * 
     * @throws Exception
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->primaryKey == $name && $this->__isset($name)) {
            throw new Exception('Nilai primary key tidak bisa di ubah !');
        }

        $this->attributes[$name] = $value;
    }

    /**
     * Cek nilai dari attribute.
     * 
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Panggil method secara static.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        $class = get_called_class();
        return (new $class)->__call($method, $parameters);
    }

    /**
     * Panggil method secara object.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return App::get()->singleton(Query::class)
            ->setTable($this->table)
            ->setDates($this->dates)
            ->setPrimaryKey($this->primaryKey)
            ->setObject(get_called_class())
            ->{$method}(...$parameters);
    }
}
