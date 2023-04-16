<?php

namespace Core\Model;

use Closure;

/**
 * Create relationship table.
 *
 * @class Relational
 * @package \Core\Model
 */
abstract class Relational
{
    /**
     * Object name model.
     * 
     * @var string $model
     */
    protected $model;

    /**
     * Foreign dari model.
     * 
     * @var string $foreign_key
     */
    protected $foreign_key;

    /**
     * local key dari object awal.
     * 
     * @var string|array $local_key
     */
    protected $local_key;

    /**
     * Callback dari query relational.
     * 
     * @var Closure|null $callback
     */
    protected $callback;

    /**
     * Init object.
     *
     * @param string $model
     * @param string $foreign_key
     * @param string $local_key
     * @param Closure|null $callback
     * @return void
     */
    public function __construct(string $model, string $foreign_key, string $local_key, Closure|null $callback = null)
    {
        $this->model = $model;
        $this->foreign_key = $foreign_key;
        $this->local_key = $local_key;
        $this->callback = $callback;
    }

    /**
     * Relasikan tabelnya.
     *
     * @return Model
     */
    abstract public function relational(): Model;

    /**
     * Ambil value local key.
     *
     * @return mixed
     */
    protected function getValueLocalKey(): mixed
    {
        $value = $this->local_key[1];
        $this->local_key = $this->local_key[0];
        return $value;
    }

    /**
     * Ambil nama local key.
     *
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->local_key;
    }

    /**
     * Tambahkan nilai local key.
     *
     * @param mixed $data
     * @return Relational
     */
    public function setLocalKey(mixed $data): Relational
    {
        $this->local_key = [
            $this->local_key,
            $data
        ];

        return $this;
    }
}
