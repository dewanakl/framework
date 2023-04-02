<?php

namespace Core\Database;

use Closure;
use Core\Facades\App;

class Connection
{
    /**
     * @var DataBase $db
     */
    private $db;

    private $query;

    private $queryLog;

    private $queryDuration;

    function __construct()
    {
        $this->db = App::get()->singleton(DataBase::class);
    }

    private function recordQueryLog()
    {
        $this->queryLog[] = [$this->query, round((microtime(true) - $this->queryDuration) * 1000, 2)];
    }

    public function getRecordQueryLog(): array
    {
        return $this->queryLog;
    }

    public function prepare(string $query, array $binding)
    {
        $this->queryDuration = microtime(true);
        $this->query = $query;

        $this->db->query($query);
        foreach ($binding as $key => $value) {
            $this->db->bind(is_string($key) ? $key : $key + 1, $value);
        }
    }

    /**
     * Tampilkan semua.
     *
     * @param Closure|null $callback
     * @return array
     */
    public function get(Closure $callback = null): array
    {
        $this->db->execute();

        $sets = [];
        while ($record = $this->db->getStatement()->fetch()) {
            $sets[] = $callback ? $callback($record) : $record;
        }

        $this->recordQueryLog();
        return $sets;
    }

    /**
     * Tampilkan satu aja.
     *
     * @param Closure|null $callback
     * @return array
     */
    public function first(Closure $callback = null): array
    {
        $this->db->execute();
        $record = (object) $this->db->getStatement()->fetch();
        $set = $callback ? $callback($record) : $record;

        $this->recordQueryLog();
        return $set;
    }

    public function affectingStatement(): int
    {
        $this->db->execute();
        return $this->db->rowCount();
    }
}
