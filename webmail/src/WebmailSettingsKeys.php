<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use Fnlla\Core\ConfigRepository;

final class WebmailSettingsKeys
{
    public function __construct(private ConfigRepository $config)
    {
    }

    public function resolve(string $key): string
    {
        if (!$this->isTenantScoped()) {
            return $key;
        }

        $error = $this->tenantScopeError();
        if ($error !== null) {
            throw new \RuntimeException($error);
        }

        $tenantId = $this->tenantId();
        if ($tenantId === null) {
            throw new \RuntimeException('Tenant context is not available for webmail settings.');
        }

        $prefix = $this->tenantPrefix();
        return $prefix . $tenantId . ':' . $key;
    }

    public function isTenantScoped(): bool
    {
        $webmail = $this->config->get('webmail', []);
        if (is_array($webmail) && array_key_exists('tenant_scoped', $webmail)) {
            return (bool) $webmail['tenant_scoped'];
        }

        return false;
    }

    public function tenantPrefix(): string
    {
        $webmail = $this->config->get('webmail', []);
        if (is_array($webmail) && isset($webmail['tenant_prefix'])) {
            $prefix = trim((string) $webmail['tenant_prefix']);
            if ($prefix !== '') {
                return rtrim($prefix, ':') . ':';
            }
        }

        return 'tenant:';
    }

    public function tenantId(): ?string
    {
        if (!class_exists(\Fnlla\Tenancy\TenantContext::class)) {
            return null;
        }

        $id = \Fnlla\Tenancy\TenantContext::id();
        if (!is_string($id)) {
            return null;
        }

        $id = trim($id);
        return $id !== '' ? $id : null;
    }

    public function tenantScopeError(): ?string
    {
        if (!$this->isTenantScoped()) {
            return null;
        }

        if (!class_exists(\Fnlla\Tenancy\TenantContext::class)) {
            return 'Tenant-scoped webmail settings require fnlla/tenancy.';
        }

        $id = \Fnlla\Tenancy\TenantContext::id();
        if (!is_string($id) || trim($id) === '') {
            return 'Tenant context is not available for webmail settings.';
        }

        return null;
    }
}


