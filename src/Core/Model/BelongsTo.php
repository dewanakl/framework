<?php

namespace Core\Model;

/**
 * Create one to one relationship.
 *
 * @class BelongsTo
 * @package \Core\Model
 */
final class BelongsTo extends Relational
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
            return $callback($model)->first();
        }

        return $model->first();
    }
}
