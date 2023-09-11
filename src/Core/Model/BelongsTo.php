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
     * Run callback.
     *
     * @param Query $query
     * @return Model
     */
    private function runCallback(Query $query): Model
    {
        if (!is_null($this->callback)) {
            $callback = $this->callback;
            return $callback($query)->first();
        }

        return $query->first();
    }

    /**
     * Relasikan tabelnya.
     *
     * @return Model
     */
    public function relational(): Model
    {
        $localKey = $this->getValueLocalKey();

        if ($this->recursive && !is_null($localKey)) {
            return $this->loop($localKey, Query::Fetch, function (Query $query): Model {
                return $this->runCallback($query);
            });
        }

        return $this->runCallback((new $this->model)->where($this->foreign_key, $localKey));
    }
}
