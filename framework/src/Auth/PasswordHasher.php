<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use Fnlla\Core\ConfigRepository;
use RuntimeException;

final class PasswordHasher
{
    private string $driver;
    /**
     * @var array<string, mixed>
     */
    private array $bcryptOptions;
    /**
     * @var array<string, mixed>
     */
    private array $argonOptions;

    public function __construct(ConfigRepository $config)
    {
        $driver = (string) $config->get('auth.password.driver', 'bcrypt');
        $this->driver = strtolower($driver);
        $this->bcryptOptions = (array) $config->get('auth.password.bcrypt', ['cost' => 12]);
        $defaultMemory = defined('PASSWORD_ARGON2_DEFAULT_MEMORY_COST') ? PASSWORD_ARGON2_DEFAULT_MEMORY_COST : 65536;
        $defaultTime = defined('PASSWORD_ARGON2_DEFAULT_TIME_COST') ? PASSWORD_ARGON2_DEFAULT_TIME_COST : 4;
        $defaultThreads = defined('PASSWORD_ARGON2_DEFAULT_THREADS') ? PASSWORD_ARGON2_DEFAULT_THREADS : 2;
        $this->argonOptions = (array) $config->get('auth.password.argon2id', [
            'memory_cost' => $defaultMemory,
            'time_cost' => $defaultTime,
            'threads' => $defaultThreads,
        ]);
    }

    public function hash(string $plain): string
    {
        $algo = $this->resolveAlgo();
        $options = $this->resolveOptions($algo);
        $hash = password_hash($plain, $algo, $options);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to hash password.');
        }
        return $hash;
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        $algo = $this->resolveAlgo();
        $options = $this->resolveOptions($algo);
        return password_needs_rehash($hash, $algo, $options);
    }

    private function resolveAlgo(): string
    {
        if ($this->driver === 'argon2id' || $this->driver === 'argon') {
            if (!defined('PASSWORD_ARGON2ID')) {
                throw new RuntimeException('Argon2id is not available in this PHP build.');
            }
            return (string) PASSWORD_ARGON2ID;
        }

        return (string) PASSWORD_BCRYPT;
    }

    /**
     * @return array<string, int>
     */
    private function resolveOptions(string $algo): array
    {
        if ($algo === PASSWORD_ARGON2ID) {
            return [
                'memory_cost' => (int) ($this->argonOptions['memory_cost'] ?? PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
                'time_cost' => (int) ($this->argonOptions['time_cost'] ?? PASSWORD_ARGON2_DEFAULT_TIME_COST),
                'threads' => (int) ($this->argonOptions['threads'] ?? PASSWORD_ARGON2_DEFAULT_THREADS),
            ];
        }

        return [
            'cost' => (int) ($this->bcryptOptions['cost'] ?? 12),
        ];
    }
}
