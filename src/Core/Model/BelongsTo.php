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
        if ($this->callback) {
            $callback = $this->callback;
            $result = $callback($query);
        } else {
            $result = $query;
        }

        if ($result instanceof Query) {
            $result = $result->first();
        }

        $with = $this->getWith();
        if ($with) {
            foreach ($with as $loop) {
                $result[$loop->getAlias()] = $loop->setLocalKey($result[$loop->getLocalKey()])->relational();
            }
        }

        return $result;
    }

    /**
     * Relasikan tabelnya.
     *
     * @return Model
     */
    public function relational(): Model
    {
        $localKey = $this->getValueLocalKey();

        if ($this->recursive && $localKey) {
            return $this->loop($localKey, Query::Fetch, function (Query $query): Model {
                return $this->runCallback($query);
            });
        }

        return $this->runCallback((new $this->model)->where($this->foreign_key, $localKey));
    }
}
