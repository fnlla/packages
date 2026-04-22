<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Csrf;

use Fnlla\Session\SessionInterface;

final class CsrfTokenManager
{
    public function __construct(
        private SessionInterface $session,
        private string $key = '_csrf_token',
        private int $bytes = 32
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get($this->key);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes($this->bytes));
            $this->session->put($this->key, $token);
        }
        return $token;
    }

    public function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        $current = $this->session->get($this->key);
        if (!is_string($current) || $current === '') {
            return false;
        }
        return hash_equals($current, $token);
    }
}
