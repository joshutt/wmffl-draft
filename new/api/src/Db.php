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
            // Kept for the parts of the app that still format PHP-side
            // wall-clock strings for humans (e.g.
            // DraftStateService::recordLoginHeartbeat()'s date('Y-m-d H:i:s')
            // heartbeats) — those are written and read entirely in PHP, so
            // they only need to be internally consistent, not matched to
            // MySQL. Does NOT need to match MySQL's session timezone below;
            // see that option's comment for why.
            date_default_timezone_set('America/New_York');
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
                // Pin every session to a fixed UTC offset rather than
                // trusting the server's SYSTEM zone (or a named zone like
                // 'America/New_York', which silently requires the
                // mysql.time_zone_name tables to be loaded via
                // mysql_tzinfo_to_sql — not guaranteed on every host, and a
                // moving DST target even when it is). This is what let the
                // clock-reset bug resurface on staging with byte-identical
                // code: local and staging MySQL disagreed on SYSTEM tz, so
                // strtotime()/UNIX_TIMESTAMP() landed on different epochs
                // for the same stored TIMESTAMP. Repositories now read
                // TIMESTAMP columns via UNIX_TIMESTAMP() in SQL (see
                // DraftPickRepository, ClockStopRepository), which no longer
                // depends on this — but pinning it here means any other
                // query (ad hoc reports, a future repository) gets the same
                // guarantee for free instead of re-discovering this bug.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Could not connect to database: ' . $e->getMessage(), previous: $e);
        }
    }
}
