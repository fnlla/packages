<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use Fnlla\Support\Env;

final class WebmailCipher
{
    private ?string $rawKey = null;
    private ?string $key = null;

    public function canEncrypt(): bool
    {
        return $this->rawKey() !== '' && function_exists('openssl_encrypt');
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, 'enc:v1:');
    }

    public function encrypt(string $value): string
    {
        if ($value === '' || !$this->canEncrypt()) {
            return $value;
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            return $value;
        }

        return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $value): string
    {
        if ($value === '' || !$this->isEncrypted($value)) {
            return $value;
        }

        if (!$this->canEncrypt()) {
            return '';
        }

        $payload = base64_decode(substr($value, 7), true);
        if ($payload === false || strlen($payload) < 28) {
            return '';
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext === false ? '' : $plaintext;
    }

    private function rawKey(): string
    {
        if ($this->rawKey !== null) {
            return $this->rawKey;
        }

        $key = (string) Env::get('WEBMAIL_SETTINGS_KEY', Env::get('APP_KEY', ''));
        $key = trim($key);
        if ($key === '') {
            $this->rawKey = '';
            return '';
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if (is_string($decoded) && $decoded !== '') {
                $key = $decoded;
            }
        }

        $this->rawKey = $key;
        return $this->rawKey;
    }

    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $this->key = hash('sha256', $this->rawKey(), true);
        return $this->key;
    }
}


