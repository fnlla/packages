<?php

/**
 * FnllaPHP framework
 * Licensed under the Proprietary License.
 */

declare(strict_types=1);

namespace Fnlla\Debugbar;

use Fnlla\Support\Psr\Log\LoggerInterface;
use PDO;
use PDOStatement;
use Throwable;

final class DebugPDO extends PDO
{
    private static ?LoggerInterface $logger = null;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DebugPDOStatement::class, []]);
    }

    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function logger(): ?LoggerInterface
    {
        return self::$logger;
    }

    public function exec(string $statement): int|false
    {
        $start = microtime(true);
        $result = parent::exec($statement);
        $elapsed = (microtime(true) - $start) * 1000;
        DebugbarCollector::addQuery($statement, [], $elapsed, is_int($result) ? $result : 0, 'exec');
        $this->logQuery('exec', $statement, [], $elapsed, is_int($result) ? $result : 0);
        return $result;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $start = microtime(true);
        $statement = parent::query($query, $fetchMode, ...$fetchModeArgs);
        $elapsed = (microtime(true) - $start) * 1000;
        $rowCount = $statement instanceof PDOStatement ? $statement->rowCount() : 0;
        DebugbarCollector::addQuery($query, [], $elapsed, $rowCount, 'query');
        $this->logQuery('query', $query, [], $elapsed, $rowCount);
        return $statement;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $stmt = parent::prepare($query, $options);
        return $stmt;
    }

    private function logQuery(string $type, string $sql, array $params, float $timeMs, int $rowCount): void
    {
        $logger = self::$logger;
        if (!$logger instanceof LoggerInterface) {
            return;
        }
        try {
            $logger->debug('sql.' . $type, [
                'sql' => $sql,
                'params' => $params,
                'time_ms' => round($timeMs, 2),
                'row_count' => $rowCount,
            ]);
        } catch (Throwable $e) {
            // Ignore logging errors.
        }
    }
}

final class DebugPDOStatement extends PDOStatement
{
    protected function __construct(mixed ...$args)
    {
        // PDO passes constructor args internally; accept them silently.
        if ($args !== []) {
            // no-op
        }
    }

    public function execute(?array $params = null): bool
    {
        $start = microtime(true);
        $result = parent::execute($params);
        $elapsed = (microtime(true) - $start) * 1000;
        $params = $params ?? [];
        DebugbarCollector::addQuery($this->queryString ?? '', $params, $elapsed, $this->rowCount(), 'execute');
        $logger = DebugPDO::logger();
        if ($logger instanceof LoggerInterface) {
            try {
                $logger->debug('sql.execute', [
                    'sql' => $this->queryString ?? '',
                    'params' => $params,
                    'time_ms' => round($elapsed, 2),
                    'row_count' => $this->rowCount(),
                ]);
            } catch (Throwable $e) {
                // Ignore logging errors.
            }
        }
        return $result;
    }
}
