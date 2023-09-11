<?php

namespace Core\Database\Exception;

use Core\Database\DataBase;
use ErrorException;
use Throwable;

/**
 * Database exception class.
 *
 * @class DatabaseException
 * @package \Core\Database\Exception
 */
class DatabaseException extends ErrorException
{
    /**
     * Database object.
     *
     * @var Database $db
     */
    private $db;

    /**
     * Init object.
     *
     * @param Database $db
     * @param Throwable $th
     * @return void
     */
    public function __construct(DataBase $db, Throwable $th)
    {
        $this->db = $db;
        parent::__construct($th->getMessage());
    }

    /**
     * Get query string from this exception.
     *
     * @return string|null
     */
    public function getQueryString(): string|null
    {
        return $this->db->getQueryString();
    }

    /**
     * Get driver information.
     *
     * @return array<string, mixed>
     */
    public function getInfoDriver(): array
    {
        return $this->db->getInfoDriver();
    }
}
