<?php

namespace Core\Model;

/**
 * Create one to one relationship.
 *
 * @class HasOne
 * @package \Core\Model
 */
final class HasOne extends Relational
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

        return $model->first();
    }
}
