<?php

namespace Core\Model;

/**
 * Create one to many relationship.
 *
 * @class HasMany
 * @package \Core\Model
 */
class HasMany
{
    /**
     * Object model.
     * 
     * @var Model $model
     */
    private $model;

    /**
     * Foreign dari model.
     * 
     * @var string $foreign_key
     */
    private $foreign_key;

    /**
     * local key dari object awal.
     * 
     * @var string $local_key
     */
    private $local_key;

    /**
     * Init object.
     *
     * @param string $model
     * @param string $foreign_key
     * @param string $local_key
     * @return void
     */
    function __construct(string $model, string $foreign_key, string $local_key)
    {
        $this->model = new $model;
        $this->foreign_key = $foreign_key;
        $this->local_key = $local_key;
    }

    /**
     * Relasikan tabelnya.
     *
     * @return Model
     */
    public function relational(): Model
    {
        return $this->model->where($this->foreign_key, $this->local_key[1])->get();
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
     * @return HasMany
     */
    public function setLocalKey(mixed $data): HasMany
    {
        $this->local_key = [
            $this->local_key,
            $data
        ];

        return $this;
    }
}
