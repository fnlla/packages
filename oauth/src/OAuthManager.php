<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\OAuth;

use League\OAuth2\Client\Provider\GenericProvider;
use RuntimeException;

final class OAuthManager
{
    public function __construct(private array $config = [])
    {
    }

    public function provider(string $name): GenericProvider
    {
        $providers = $this->config['providers'] ?? [];
        if (!is_array($providers) || !isset($providers[$name]) || !is_array($providers[$name])) {
            throw new RuntimeException('OAuth provider not configured: ' . $name);
        }

        $cfg = $providers[$name];
        $clientId = (string) ($cfg['client_id'] ?? '');
        $clientSecret = (string) ($cfg['client_secret'] ?? '');
        $redirectUri = (string) ($cfg['redirect_uri'] ?? '');
        $urlAuthorize = (string) ($cfg['authorize_url'] ?? '');
        $urlAccessToken = (string) ($cfg['token_url'] ?? '');
        $urlResourceOwnerDetails = (string) ($cfg['resource_url'] ?? '');

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '' || $urlAuthorize === '' || $urlAccessToken === '') {
            throw new RuntimeException('OAuth provider missing required configuration: ' . $name);
        }

        return new GenericProvider([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'urlAuthorize' => $urlAuthorize,
            'urlAccessToken' => $urlAccessToken,
            'urlResourceOwnerDetails' => $urlResourceOwnerDetails,
        ]);
    }

    public function authorizeUrl(string $name, array $options = []): string
    {
        $provider = $this->provider($name);
        return $provider->getAuthorizationUrl($options);
    }

    public function getAccessToken(string $name, string $code): mixed
    {
        $provider = $this->provider($name);
        return $provider->getAccessToken('authorization_code', ['code' => $code]);
    }
}
