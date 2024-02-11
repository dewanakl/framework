<?php

namespace Core\Database;

use Core\Database\Exception\DatabaseException;
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
     * @var object|null $pdo
     */
    private $pdo;

    /**
     * Statement dari query.
     *
     * @var object|null $stmt
     */
    private $stmt;

    /**
     * Buat objek database.
     *
     * @return void
     *
     * @throws PDOException
     */
    public function __construct()
    {
        if ($this->pdo === null) {
            if (env('DB_DRIV', '') ==  '') {
                throw new Exception('DB_DRIV not set');
            }

            $dsn = sprintf(
                '%s:host=%s;dbname=%s;port=%s;%s',
                env('DB_DRIV'),
                env('DB_HOST'),
                env('DB_NAME'),
                env('DB_PORT'),
                env('DB_OPTIONS', '')
            );

            try {
                $this->pdo = new PDO(
                    $dsn,
                    env('DB_USER'),
                    env('DB_PASS'),
                    $this->options()
                );
            } catch (PDOException $e) {
                $this->catchException($e);
            }
        }
    }

    /**
     * Opsi untuk PDO ini.
     *
     * @return array<int, mixed>
     */
    private function options(): array
    {
        $options = [
            PDO::ATTR_PERSISTENT => env('PDO_ATTR_PERSISTENT', 'true') == 'true',
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ];

        if (env('PDO_MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = env('PDO_MYSQL_ATTR_SSL_CA');
        }

        return $options;
    }

    /**
     * Get information this driver.
     *
     * @return array<string, mixed>
     */
    public function getInfoDriver(): array
    {
        $attributes = [
            'AUTOCOMMIT',
            'ERRMODE',
            'CASE',
            'CLIENT_VERSION',
            'CONNECTION_STATUS',
            'ORACLE_NULLS',
            'PERSISTENT',
            'PREFETCH',
            'SERVER_INFO',
            'SERVER_VERSION',
            'TIMEOUT'
        ];

        $data = [];
        foreach ($attributes as $val) {
            $result = null;

            try {
                $result = $this->pdo?->getAttribute(constant('PDO::ATTR_' . $val));
            } catch (PDOException) {
                continue;
            } finally {
                $data[$val] = $result;
            }
        }

        return $data;
    }

    /**
     * Get query now.
     *
     * @return string|null
     */
    public function getQueryString(): string|null
    {
        return $this->stmt?->queryString;
    }

    /**
     * Tangkap dan tampilkan errornya.
     *
     * @param Throwable $throw
     * @return void
     *
     * @throws DatabaseException
     */
    public function catchException(Throwable $throw): void
    {
        throw new DatabaseException($this, $throw);
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
     * Apakah masih ada transaction?.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Eksekusi raw sql.
     *
     * @param string $command
     * @return int
     *
     * @throws Throwable
     */
    public function exec(string $command): int
    {
        $result = 0;

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
     *
     * @throws Exception
     */
    public function query(string $query): void
    {
        $result = $this->pdo->prepare($query);

        if ($result === false) {
            throw new Exception('Error Processing query: ' . $query);
        }

        $this->stmt = $result;
    }

    /**
     * Siapkan juga valuenya.
     *
     * @param int|string $param
     * @param mixed $value
     * @return void
     *
     * @throws Exception
     */
    public function bind(int|string $param, mixed $value): void
    {
        $result = $this->stmt->bindValue(
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

        if (!$result) {
            throw new Exception('Error saat bindValue: ' . strval($param));
        }
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
            $result = false;
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
     * @return string|bool
     */
    public function lastInsertId(string|null $name = null): string|bool
    {
        return $this->pdo->lastInsertId($name);
    }
}
