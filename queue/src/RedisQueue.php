<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Closure;
use Fnlla\Support\RedisConnector;
use Throwable;

final class RedisQueue implements QueueDriverInterface
{
    private ?\Redis $redis = null;
    private Closure $clock;

    public function __construct(
        private array $config = [],
        private string $queue = 'default',
        private int $defaultMaxAttempts = 3,
        private int $retryAfter = 60,
        private string $payloadSecret = '',
        private array $allowedJobClasses = [],
        ?callable $clock = null
    ) {
        $this->queue = $this->queue !== '' ? $this->queue : 'default';
        $this->defaultMaxAttempts = max(1, $this->defaultMaxAttempts);
        $this->retryAfter = max(1, $this->retryAfter);
        $this->allowedJobClasses = $this->normalizeAllowedJobClasses($this->allowedJobClasses);
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function dispatch(JobInterface $job): void
    {
        $redis = $this->connection();
        $id = (string) $redis->incr($this->key('id'));

        $payload = serialize($job);
        $jobClass = get_class($job);
        $signature = $this->signPayload($payload);
        $now = $this->now();
        $maxAttempts = $this->defaultMaxAttempts;

        $redis->multi();
        $redis->hMSet($this->jobKey($id), [
            'payload' => $payload,
            'job_class' => $jobClass,
            'signature' => $signature,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'created_at' => $now,
            'available_at' => $now,
            'reserved_at' => 0,
        ]);
        $redis->rPush($this->readyKey(), $id);
        $redis->exec();
    }

    public function pop(?int $retryAfter = null): ?QueuedJob
    {
        $redis = $this->connection();
        $now = $this->now();
        $retryAfter = $retryAfter !== null ? max(1, $retryAfter) : $this->retryAfter;

        $this->migrateDue($this->delayedKey(), $now, 'available_at', $now);
        $this->migrateDue($this->reservedKey(), $now, 'reserved_at', $now);

        $id = $this->reserveNext($redis, $retryAfter, $now);
        if ($id === null) {
            return null;
        }

        $payload = $redis->hGetAll($this->jobKey($id));
        if ($payload === []) {
            return null;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        $maxAttempts = (int) ($payload['max_attempts'] ?? $this->defaultMaxAttempts);

        return new QueuedJob(
            $id,
            (string) ($payload['payload'] ?? ''),
            (string) ($payload['job_class'] ?? ''),
            (string) ($payload['signature'] ?? ''),
            $attempts,
            $maxAttempts
        );
    }

    public function delete(int|string $id): void
    {
        $redis = $this->connection();
        $id = (string) $id;

        $redis->multi();
        $redis->del($this->jobKey($id));
        $redis->zRem($this->reservedKey(), $id);
        $redis->zRem($this->delayedKey(), $id);
        $redis->lRem($this->readyKey(), $id, 0);
        $redis->exec();
    }

    public function release(int|string $id, int $delaySeconds = 0): void
    {
        $redis = $this->connection();
        $id = (string) $id;
        $delaySeconds = max(0, $delaySeconds);
        $availableAt = $this->now() + $delaySeconds;

        $redis->multi();
        $redis->zRem($this->reservedKey(), $id);

        if ($delaySeconds > 0) {
            $redis->zAdd($this->delayedKey(), $availableAt, $id);
        } else {
            $redis->rPush($this->readyKey(), $id);
        }

        $redis->hMSet($this->jobKey($id), [
            'reserved_at' => 0,
            'available_at' => $availableAt,
        ]);
        $redis->exec();
    }

    public function fail(int|string $id, string $payload, ?Throwable $error = null): void
    {
        $redis = $this->connection();
        $id = (string) $id;

        $failure = [
            'payload' => $payload,
            'error' => $error ? $error->getMessage() : 'Job failed',
            'trace' => $error ? $error->getTraceAsString() : null,
            'failed_at' => $this->now(),
        ];
        $encoded = json_encode($failure, JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = '{"error":"Unable to encode failure payload."}';
        }

        $redis->multi();
        $redis->lPush($this->failedKey(), $encoded);
        $redis->del($this->jobKey($id));
        $redis->zRem($this->reservedKey(), $id);
        $redis->zRem($this->delayedKey(), $id);
        $redis->lRem($this->readyKey(), $id, 0);
        $redis->exec();
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    public function validate(QueuedJob $job): ?string
    {
        if ($job->jobClass() === '') {
            return 'Queued job class is missing.';
        }

        $expected = $this->signPayload($job->payload());
        if (!hash_equals($expected, $job->signature())) {
            return 'Invalid queued job signature.';
        }

        if ($this->allowedJobClasses !== ['*']) {
            $jobClass = $job->jobClass();
            if (!in_array($jobClass, $this->allowedJobClasses, true)) {
                return 'Queued job class is not allowed: ' . $jobClass;
            }
        }

        return null;
    }

    private function connection(): \Redis
    {
        if ($this->redis instanceof \Redis) {
            return $this->redis;
        }

        $this->redis = RedisConnector::connect($this->config);
        return $this->redis;
    }

    private function reserveNext(\Redis $redis, int $retryAfter, int $now): ?string
    {
        $script = <<<'LUA'
local ready = KEYS[1]
local reserved = KEYS[2]
local jobPrefix = ARGV[1]
local now = tonumber(ARGV[2])
local retryAfter = tonumber(ARGV[3])

local id = redis.call('lpop', ready)
if not id then
  return nil
end

local jobKey = jobPrefix .. id
if redis.call('exists', jobKey) == 0 then
  return nil
end

redis.call('hincrby', jobKey, 'attempts', 1)
redis.call('hset', jobKey, 'reserved_at', now)
redis.call('zadd', reserved, now + retryAfter, id)
return id
LUA;

        $jobPrefix = $this->jobKey('');
        $result = $redis->eval($script, [$this->readyKey(), $this->reservedKey(), $jobPrefix, $now, $retryAfter], 2);

        if ($result === false || $result === null) {
            return null;
        }

        return (string) $result;
    }

    private function migrateDue(string $sourceKey, int $now, string $field, ?int $availableAt = null): void
    {
        $redis = $this->connection();
        $limit = 200;

        while (true) {
            $ids = $redis->zRangeByScore($sourceKey, '-inf', (string) $now, ['limit' => [0, $limit]]);
            if ($ids === []) {
                break;
            }

            $redis->multi();
            foreach ($ids as $id) {
                $redis->zRem($sourceKey, $id);
                $redis->rPush($this->readyKey(), $id);
                $fields = [$field => 0];
                if ($availableAt !== null) {
                    $fields['available_at'] = $availableAt;
                }
                $redis->hMSet($this->jobKey((string) $id), $fields);
            }
            $redis->exec();

            if (count($ids) < $limit) {
                break;
            }
        }
    }

    private function key(string $suffix): string
    {
        return $this->prefix() . $this->queue . ':' . $suffix;
    }

    private function readyKey(): string
    {
        return $this->key('ready');
    }

    private function delayedKey(): string
    {
        return $this->key('delayed');
    }

    private function reservedKey(): string
    {
        return $this->key('reserved');
    }

    private function failedKey(): string
    {
        return $this->key('failed');
    }

    private function jobKey(string $id): string
    {
        return $this->key('job:' . $id);
    }

    private function prefix(): string
    {
        $prefix = (string) ($this->config['prefix'] ?? 'Fnlla:queue:');
        if ($prefix !== '' && !str_ends_with($prefix, ':')) {
            $prefix .= ':';
        }

        return $prefix;
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    private function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->payloadSecret);
    }

    private function normalizeAllowedJobClasses(array $allowed): array
    {
        if ($allowed === []) {
            return ['*'];
        }

        $list = [];
        foreach ($allowed as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $list[] = $item;
        }

        if ($list === []) {
            return ['*'];
        }

        if (in_array('*', $list, true)) {
            return ['*'];
        }

        return array_values(array_unique($list));
    }
}
