<?php

namespace Tests\Unit;

use App\Support\RetryingMySqlConnector;
use Illuminate\Support\Sleep;
use PDO;
use PDOException;
use Tests\TestCase;

/** OPS-DB-BURST-01: only Hostinger's connection-rate refusal is retried, with growing pauses. */
class RetryingMySqlConnectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    public function test_the_mysql_driver_uses_the_retrying_connector(): void
    {
        $this->assertInstanceOf(RetryingMySqlConnector::class, app('db.connector.mysql'));
    }

    public function test_refused_connects_are_retried_until_one_succeeds(): void
    {
        $connector = $this->connector(['SQLSTATE[HY000] [2002] Operation not permitted', 'SQLSTATE[HY000] [2002] Operation not permitted']);

        $this->assertInstanceOf(PDO::class, $connector->createConnection('mysql:host=localhost', [], []));
        $this->assertSame(3, $connector->attempts);
        Sleep::assertSleptTimes(2);
        // 100 ms then 200 ms, each with up to 50% jitter.
        Sleep::assertSlept(fn ($pause) => $pause->totalMicroseconds >= 100_000 && $pause->totalMicroseconds <= 150_000, 1);
        Sleep::assertSlept(fn ($pause) => $pause->totalMicroseconds >= 200_000 && $pause->totalMicroseconds <= 300_000, 1);
    }

    public function test_other_connection_errors_fail_immediately(): void
    {
        $connector = $this->connector(['SQLSTATE[HY000] [1049] Unknown database "qammaris"']);

        try {
            $connector->createConnection('mysql:host=localhost', [], []);
            $this->fail('Expected the unknown database error.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('[1049]', $e->getMessage());
        }
        $this->assertSame(1, $connector->attempts);
        Sleep::assertNeverSlept();
    }

    public function test_the_refusal_is_rethrown_after_the_last_pause(): void
    {
        $connector = $this->connector(array_fill(0, 10, 'SQLSTATE[HY000] [2002] Operation not permitted'));

        $this->expectException(PDOException::class);
        try {
            $connector->createConnection('mysql:host=localhost', [], []);
        } finally {
            $this->assertSame(count(RetryingMySqlConnector::BACKOFF_MS) + 1, $connector->attempts);
            Sleep::assertSleptTimes(count(RetryingMySqlConnector::BACKOFF_MS));
        }
    }

    /** @param  list<string>  $failures  messages thrown by the first connect attempts */
    private function connector(array $failures): RetryingMySqlConnector
    {
        return new class($failures) extends RetryingMySqlConnector
        {
            public int $attempts = 0;

            public function __construct(private array $failures) {}

            protected function createPdoConnection($dsn, $username, $password, $options)
            {
                $this->attempts++;
                if ($message = array_shift($this->failures)) {
                    throw new PDOException($message);
                }

                return new PDO('sqlite::memory:');
            }
        };
    }
}
