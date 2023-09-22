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
            $result = $result->get();
        }

        $with = $this->getWith();
        if ($with) {
            $result->map(function (object $data) use ($with): object {
                foreach ($with as $value) {
                    $data->{$value->getAlias()} = $value->setLocalKey($data->{$value->getLocalKey()})->relational();
                }

                return $data;
            });
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
            return $this->loop($localKey, Query::FetchAll, function (Query $query): Model {
                return $this->runCallback($query);
            });
        }

        return $this->runCallback((new $this->model)->where($this->foreign_key, $localKey));
    }
}
