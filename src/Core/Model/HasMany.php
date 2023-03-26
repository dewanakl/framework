<?php

namespace Core\Model;

/**
 * Create one to many relationship.
 *
 * @class HasMany
 * @package \Core\Model
 */
final class HasMany extends Relational
{
    /**
     * Relasikan tabelnya.
     *
     * @return Model
     */
    public function relational(): Model
    {
        $model = $this->model->where($this->foreign_key, $this->local_key[1]);

        if (!is_null($this->callback)) {
            $callback = $this->callback;
            $model = $callback($model);
        }

        return $model->get();
    }
}
