<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

final class HealthChecker
{
    private array $checks = [];

    public function __construct(array $checks)
    {
        $this->checks = $checks;
    }

    public static function fromConfig(array $config): self
    {
        $definitions = $config['checks'] ?? [];
        $checks = [];

        if (is_array($definitions)) {
            foreach ($definitions as $name => $definition) {
                if (is_callable($definition)) {
                    $checks[] = ['name' => (string) $name, 'check' => $definition];
                    continue;
                }

                if (!is_array($definition)) {
                    continue;
                }

                $type = (string) ($definition['type'] ?? '');
                $checkName = (string) ($definition['name'] ?? $name);

                if ($type === 'php') {
                    $min = (string) ($definition['min'] ?? '8.5.0');
                    $checks[] = [
                        'name' => $checkName,
                        'check' => fn (): array => [
                            'ok' => version_compare(PHP_VERSION, $min, '>='),
                            'message' => 'PHP ' . PHP_VERSION . ' (min ' . $min . ')',
                        ],
                    ];
                    continue;
                }

                if ($type === 'extension') {
                    $ext = (string) ($definition['ext'] ?? $definition['name'] ?? '');
                    $checks[] = [
                        'name' => $checkName !== '' ? $checkName : 'ext:' . $ext,
                        'check' => fn (): array => [
                            'ok' => $ext !== '' && extension_loaded($ext),
                            'message' => 'Extension ' . $ext,
                        ],
                    ];
                    continue;
                }

                if ($type === 'writable') {
                    $path = (string) ($definition['path'] ?? '');
                    $checks[] = [
                        'name' => $checkName !== '' ? $checkName : 'writable:' . $path,
                        'check' => fn (): array => [
                            'ok' => $path !== '' && is_writable($path),
                            'message' => $path,
                        ],
                    ];
                    continue;
                }

                if ($type === 'env') {
                    $envKey = (string) ($definition['key'] ?? '');
                    $checks[] = [
                        'name' => $checkName !== '' ? $checkName : 'env:' . $envKey,
                        'check' => fn (): array => [
                            'ok' => $envKey !== '' && getenv($envKey) !== false && getenv($envKey) !== '',
                            'message' => $envKey,
                        ],
                    ];
                }
            }
        }

        return new self($checks);
    }

    public function run(): array
    {
        $results = [];
        $ok = true;

        foreach ($this->checks as $check) {
            $name = (string) ($check['name'] ?? 'check');
            $callable = $check['check'] ?? null;
            if (!is_callable($callable)) {
                continue;
            }

            $result = $callable();
            $passed = is_array($result) ? (bool) ($result['ok'] ?? false) : (bool) $result;
            $message = is_array($result) ? (string) ($result['message'] ?? '') : '';

            $results[] = [
                'name' => $name,
                'ok' => $passed,
                'message' => $message,
            ];

            if (!$passed) {
                $ok = false;
            }
        }

        return [
            'ok' => $ok,
            'checks' => $results,
        ];
    }
}

