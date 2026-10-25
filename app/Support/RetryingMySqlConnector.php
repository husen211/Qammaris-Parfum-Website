<?php

namespace App\Support;

use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Support\Sleep;
use PDOException;

/**
 * OPS-DB-BURST-01: Hostinger refuses new MySQL connections above roughly 20 per second with
 * "[2002] Operation not permitted". A refused connect is retried after a short, growing, jittered
 * pause so a burst of visitors or crawler requests becomes slightly slower instead of HTTP 500.
 * Any other connection error fails immediately as before.
 */
class RetryingMySqlConnector extends MySqlConnector
{
    /** Pauses before each retry, in milliseconds; the last failure is rethrown. */
    public const BACKOFF_MS = [100, 200, 400, 800];

    public function createConnection($dsn, array $config, array $options)
    {
        foreach ([...self::BACKOFF_MS, null] as $pause) {
            try {
                return parent::createConnection($dsn, $config, $options);
            } catch (PDOException $e) {
                if ($pause === null || ! self::refusedByConnectionLimit($e)) {
                    throw $e;
                }
                Sleep::usleep(($pause + random_int(0, intdiv($pause, 2))) * 1000);
            }
        }
    }

    public static function refusedByConnectionLimit(PDOException $e): bool
    {
        return str_contains($e->getMessage(), '[2002] Operation not permitted');
    }
}
