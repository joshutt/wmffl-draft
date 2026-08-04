<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection wrapper — replaces the legacy global $conn / mysqli_connect
 * pattern in src/utils/start.php. Reads connection details from config/db.ini
 * (gitignored; see config/db.ini.default for the template), the same
 * untracked-and-pre-provisioned convention as conf/wmffl.conf.
 */
final class Db
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect(self::loadConfig());
        }

        return self::$instance;
    }

    /**
     * @return array{host:string,port:string,dbname:string,username:string,password:string}
     */
    private static function loadConfig(): array
    {
        $path = __DIR__ . '/../config/db.ini';

        if (!is_file($path)) {
            throw new RuntimeException(
                "Missing {$path} — copy config/db.ini.default to config/db.ini and fill in credentials."
            );
        }

        $config = parse_ini_file($path, true);

        if ($config === false || !isset($config['DB_Values'])) {
            throw new RuntimeException("Could not parse [DB_Values] section from {$path}");
        }

        $values = $config['DB_Values'];

        foreach (['host', 'dbname', 'username', 'password'] as $key) {
            if (!isset($values[$key]) || $values[$key] === '') {
                throw new RuntimeException("config/db.ini is missing a value for DB_Values.{$key}");
            }
        }

        $values['port'] ??= '3306';

        return $values;
    }

    /**
     * @param array{host:string,port:string,dbname:string,username:string,password:string} $config
     */
    private static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['dbname'],
        );

        try {
            return new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Could not connect to database: ' . $e->getMessage(), previous: $e);
        }
    }
}
