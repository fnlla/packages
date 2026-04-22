<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Session;

interface SessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;

    public function forget(string $key): void;

    public function all(): array;

    public function flash(string $key, mixed $value): void;

    public function getFlash(string $key, mixed $default = null): mixed;

    public function regenerateId(bool $deleteOld = true): void;
}
