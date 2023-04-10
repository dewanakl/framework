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
        $model = (new $this->model)->where($this->foreign_key, $this->getValueLocalKey());

        if (!is_null($this->callback)) {
            $callback = $this->callback;
            return $callback($model)->get();
        }

        return $model->get();
    }
}
