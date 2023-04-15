<?php

namespace Core\Database;

use Exception;
use PDO;
use PDOException;
use Throwable;

/**
 * Hubungkan ke database yang ada dengan pdo.
 *
 * @class DataBase
 * @package \Core\Database
 */
class DataBase
{
    /**
     * Object PDO.
     * 
     * @var object $pdo
     */
    private $pdo;

    /**
     * Statement dari query.
     * 
     * @var object|false $stmt
     */
    private $stmt;

    /**
     * Buat objek database.
     *
     * @return void
     * 
     * @throws PDOException
     */
    function __construct()
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                '%s:host=%s;dbname=%s;port=%s;',
                env('DB_DRIV'),
                env('DB_HOST'),
                env('DB_NAME'),
                env('DB_PORT')
            );

            $option = [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            ];

            if (env('MYSQL_ATTR_SSL_CA')) {
                $option[PDO::MYSQL_ATTR_SSL_CA] = env('MYSQL_ATTR_SSL_CA');
            }
        }

        try {
            if ($this->pdo === null) {
                $this->pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS'), $option);
            }
        } catch (PDOException $e) {
            $this->catchException($e);
        }
    }

    /**
     * Tangkap dan tampilkan errornya.
     *
     * @param Throwable $e
     * @return void
     * 
     * @throws Exception
     */
    public function catchException(Throwable $e): void
    {
        if (debug()) {
            $sql = empty($this->stmt->queryString) ? '' : PHP_EOL . PHP_EOL . 'SQL: "' . $this->stmt->queryString . '"';
            throw new Exception($e->getMessage() . $sql);
        }

        unavailable();
    }

    /**
     * Mulai transaksinya.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaksinya.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Kembalikan transaksinya.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Eksekusi raw sql.
     *
     * @param string $command
     * @return int|bool
     * 
     * @throws Throwable
     */
    public function exec(string $command): int|bool
    {
        $result = null;

        try {
            $result = $this->pdo->exec($command);

            if ($result === false) {
                throw new Exception('Error saat mengeksekusi');
            }
        } catch (Throwable $e) {
            $this->catchException($e);
        }

        return $result;
    }

    /**
     * Siapkan querynya.
     *
     * @param string $query
     * @return void
     */
    public function query(string $query): void
    {
        $this->stmt = $this->pdo->prepare($query);
    }

    /**
     * Siapkan juga valuenya.
     *
     * @param int|string $param
     * @param mixed $value
     * @return void
     */
    public function bind(int|string $param, mixed $value): void
    {
        $this->stmt->bindValue(
            $param,
            $value,
            match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                is_resource($value) => PDO::PARAM_LOB,
                default => PDO::PARAM_STR
            }
        );
    }

    /**
     * Eksekusi juga.
     *
     * @return bool
     * 
     * @throws Exception
     */
    public function execute(): bool
    {
        $result = false;

        try {
            $result = $this->stmt->execute();

            if (!$result) {
                throw new Exception('Error saat mengeksekusi');
            }
        } catch (Exception $e) {
            $this->catchException($e);
        }

        return $result;
    }

    /**
     * Dapatkan data per baris.
     *
     * @return mixed
     */
    public function fetch(): mixed
    {
        return $this->stmt->fetch();
    }

    /**
     * Closes the cursor.
     *
     * @return bool
     */
    public function close(): bool
    {
        return $this->stmt->closeCursor();
    }

    /**
     * Hitung jumlah rownya.
     *
     * @return int
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    /**
     * Dapatkan idnya.
     * 
     * @param string|null $name
     * @return int|string|bool
     */
    public function lastInsertId(string|null $name = null): int|string|bool
    {
        return $this->pdo->lastInsertId($name);
    }
}
