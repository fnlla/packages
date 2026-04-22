<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Monitoring;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;

final class MonitoringAccessMiddleware
{
    public function __construct(private ConfigRepository $config)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $public = (bool) $this->config->get('monitoring.public', false);
        if ($public) {
            return $next($request);
        }

        $token = (string) $this->config->get('monitoring.access_token', '');
        if ($token === '') {
            return Response::json(['message' => 'Monitoring token not configured.'], 403);
        }

        $header = $request->getHeaderLine('Authorization');
        $queryToken = $request->getQueryParams()['token'] ?? null;
        $explicit = $request->getHeaderLine('X-Monitoring-Token');

        $candidate = '';
        if (is_string($queryToken) && $queryToken !== '') {
            $candidate = $queryToken;
        } elseif ($explicit !== '') {
            $candidate = $explicit;
        } elseif ($header !== '') {
            $candidate = preg_replace('/^Bearer\s+/i', '', $header) ?? '';
        }

        if ($candidate !== $token) {
            return Response::json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
