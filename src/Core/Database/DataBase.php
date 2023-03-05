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
        $dsn = sprintf(
            '%s:host=%s;dbname=%s;port=%s;',
            env('DB_DRIV'),
            env('DB_HOST'),
            env('DB_NAME'),
            env('DB_PORT')
        );

        $option = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

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
     * @param int|null $type
     * @return void
     */
    public function bind(int|string $param, mixed $value, int|null $type = null): void
    {
        if ($type === null) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
                    break;
            }
        }

        $this->stmt->bindValue($param, $value, $type);
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
        } catch (Exception $e) {
            $this->catchException($e);
        }

        return $result;
    }

    /**
     * Tampilkan semua.
     *
     * @return array|bool
     */
    public function all(): array|bool
    {
        if (!$this->execute()) {
            return false;
        }

        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tampilkan satu aja.
     *
     * @return array|bool
     */
    public function first(): array|bool
    {
        if (!$this->execute()) {
            return false;
        }

        return $this->stmt->fetch(PDO::FETCH_ASSOC);
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
