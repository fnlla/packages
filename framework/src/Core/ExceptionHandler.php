<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Core;

use Fnlla\Core\ConfigRepository;
use ErrorException;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\RedirectTarget;
use Fnlla\Runtime\RequestContext;
use Fnlla\Contracts\Log\ErrorReporterInterface;
use Fnlla\Contracts\Log\LoggerInterface;
use Throwable;
use Fnlla\Authorization\AuthorizationException;
use Fnlla\Support\ValidationException;
use Fnlla\View\View;

/**
 * Exception handling and rendering for the HTTP kernel.
 *
 * @internal
 */
/**
 * @api
 */
final class ExceptionHandler
{
    public function __construct(
        private bool $debug = false,
        private ?Application $app = null,
        private ?ConfigRepository $config = null,
        private ?RequestContext $context = null
    ) {
    }

    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public function handleException(Throwable $exception): void
    {
        $this->report($exception);

        if (headers_sent()) {
            error_log($exception->getMessage());
            return;
        }

        $request = null;
        if ($this->app instanceof Application && $this->app->has(Request::class)) {
            try {
                $request = $this->app->make(Request::class);
            } catch (Throwable $e) {
                $request = null;
            }
        }

        if ($request === null && class_exists(Request::class)) {
            $basePath = '';
            $trustedProxies = [];
            if ($this->config instanceof ConfigRepository) {
                $basePath = (string) $this->config->get('base_path', '');
                $configured = $this->config->get('trusted_proxies', []);
                if (is_array($configured)) {
                    $trustedProxies = $configured;
                }
            }
            $request = Request::fromGlobals($basePath, $trustedProxies);
        }

        $response = $this->render($exception, $request);
        $response->send();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($error['type'], $fatal, true)) {
            return;
        }

        $exception = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );

        $this->handleException($exception);
    }

    public function render(Throwable $exception, ?Request $request = null): Response
    {
        $status = 500;
        $title = 'Internal Server Error';
        $detail = 'Server Error';
        $extensions = [];

        $errorId = $this->resolveRequestId();
        if ($errorId !== '') {
            $extensions['error_id'] = $errorId;
        }

        if ($exception instanceof ValidationException) {
            $status = $exception->status();
            $title = 'Validation Failed';
            $detail = $exception->getMessage();
            $extensions['errors'] = $exception->errors();
            $extensions['bag'] = $exception->bag();

            if ($request !== null && !$request->wantsJson()) {
                $redirectTo = RedirectTarget::fromReferer($request, '/');
                return Response::redirect($redirectTo, 302)
                    ->withErrors($exception->errors(), $exception->bag())
                    ->withInput($exception->oldInput());
            }
        }
        if ($exception instanceof AuthorizationException) {
            $status = $exception->status();
            $title = $status === 401 ? 'Unauthorized' : 'Forbidden';
            $detail = $exception->getMessage();
        }

        if ($this->debug) {
            $extensions['debug'] = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        if ($request !== null && $request->wantsJson()) {
            return $this->problemResponse($status, $title, $detail, $request, $extensions);
        }

        $payload = [
            'message' => $detail,
            'error_id' => $extensions['error_id'] ?? '',
            'errors' => $extensions['errors'] ?? [],
        ];
        if (isset($extensions['debug']) && is_array($extensions['debug'])) {
            $payload['exception'] = $extensions['debug']['exception'];
            $payload['exception_message'] = $extensions['debug']['message'];
            $payload['file'] = $extensions['debug']['file'];
            $payload['line'] = $extensions['debug']['line'];
            $payload['trace'] = $extensions['debug']['trace'];
        }

        return Response::html($this->renderHtml($payload, $status), $status);
    }

    public function report(Throwable $exception): void
    {
        try {
            $logger = $this->resolveLogger();
            $reporter = $this->resolveReporter();
            if (!$logger instanceof LoggerInterface && !$reporter instanceof ErrorReporterInterface) {
                return;
            }

            $context = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ];

            $errorId = $this->resolveRequestId();
            if ($errorId !== '') {
                $context['error_id'] = $errorId;
            }

            if ($logger instanceof LoggerInterface) {
                $logger->error('Unhandled exception', $context);
            }
            if ($reporter instanceof ErrorReporterInterface) {
                $reporter->report($exception, $context);
            }
        } catch (Throwable $e) {
            // Ignore logging errors.
        }
    }

    private function renderHtml(array $payload, int $status): string
    {
        $custom = $this->renderCustomView($status, $payload);
        if ($custom !== null) {
            return $custom;
        }

        $title = $status === 404 ? 'Not Found' : ($status === 422 ? 'Unprocessable Entity' : 'Server Error');
        $message = htmlspecialchars((string) ($payload['message'] ?? $title), ENT_QUOTES, 'UTF-8');

        $details = '';
        $errorId = '';
        if (isset($payload['error_id'])) {
            $errorId = '<p>Error ID: ' . htmlspecialchars((string) $payload['error_id'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $errorList = '';
        if ($status === 422 && isset($payload['errors']) && is_array($payload['errors'])) {
            $items = [];
            foreach ($payload['errors'] as $field => $messages) {
                if (!is_array($messages)) {
                    $messages = [$messages];
                }
                foreach ($messages as $msg) {
                    $items[] = '<li><strong>' . htmlspecialchars((string) $field, ENT_QUOTES, 'UTF-8') . '</strong>: '
                        . htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') . '</li>';
                }
            }
            if ($items !== []) {
                $errorList = '<ul>' . implode('', $items) . '</ul>';
            }
        }
        if ($this->debug) {
            $exception = htmlspecialchars((string) ($payload['exception'] ?? ''), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars((string) ($payload['file'] ?? ''), ENT_QUOTES, 'UTF-8');
            $line = htmlspecialchars((string) ($payload['line'] ?? ''), ENT_QUOTES, 'UTF-8');
            $trace = '';
            if (!empty($payload['trace']) && is_array($payload['trace'])) {
                $traceLines = array_map(
                    fn ($lineItem) => htmlspecialchars((string) $lineItem, ENT_QUOTES, 'UTF-8'),
                    $payload['trace']
                );
                $trace = '<pre>' . implode("\n", $traceLines) . '</pre>';
            }
            $details = $errorId . $errorList . '<h3>' . $exception . '</h3><p>' . $file . ':' . $line . '</p>' . $trace;
        } else {
            $details = $errorId . $errorList;
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title><style>body{font-family:Arial, sans-serif;margin:0;padding:40px;background:#f8fafc;color:#0f172a}.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;max-width:960px}</style></head>'
            . '<body><div class="card"><h1>' . $title . '</h1><p>' . $message . '</p>' . $details . '</div></body></html>';
    }

    private function renderCustomView(int $status, array $payload): ?string
    {
        if (!$this->app instanceof Application) {
            return null;
        }

        $template = null;
        if ($status === 404) {
            $template = 'errors/404';
        } elseif ($status >= 500) {
            $template = 'errors/500';
        }

        if ($template === null) {
            return null;
        }

        $title = $status === 404 ? 'Not Found' : ($status === 422 ? 'Unprocessable Entity' : 'Server Error');
        $data = [
            'status' => $status,
            'title' => $title,
            'message' => (string) ($payload['message'] ?? $title),
            'error_id' => (string) ($payload['error_id'] ?? ''),
            'errors' => $payload['errors'] ?? [],
        ];

        if ($this->debug) {
            $data['exception'] = $payload['exception'] ?? '';
            $data['exception_message'] = $payload['exception_message'] ?? '';
            $data['file'] = $payload['file'] ?? '';
            $data['line'] = $payload['line'] ?? '';
            $data['trace'] = $payload['trace'] ?? [];
        }

        $html = View::render($this->app, $template, $data);
        return $html !== '' ? $html : null;
    }

    private function resolveLogger(): ?LoggerInterface
    {
        if (!$this->app instanceof Application) {
            return null;
        }

        if (!$this->app->has(LoggerInterface::class)) {
            return null;
        }

        try {
            $logger = $this->app->make(LoggerInterface::class);
            return $logger instanceof LoggerInterface ? $logger : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function resolveReporter(): ?ErrorReporterInterface
    {
        if (!$this->app instanceof Application) {
            return null;
        }

        if (!$this->app->has(ErrorReporterInterface::class)) {
            return null;
        }

        try {
            $reporter = $this->app->make(ErrorReporterInterface::class);
            return $reporter instanceof ErrorReporterInterface ? $reporter : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function resolveRequestId(): string
    {
        if ($this->context instanceof RequestContext) {
            return $this->context->requestId();
        }

        if ($this->app instanceof Application && $this->app->has(RequestContext::class)) {
            try {
                $context = $this->app->make(RequestContext::class);
                if ($context instanceof RequestContext) {
                    return $context->requestId();
                }
            } catch (Throwable $e) {
                return '';
            }
        }

        return '';
    }

    private function problemResponse(int $status, string $title, string $detail, ?Request $request, array $extensions = []): Response
    {
        $payload = [
            'type' => $this->problemType($status),
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($request instanceof Request) {
            $uri = $request->getUri();
            $instance = $uri->getPath();
            $query = $uri->getQuery();
            if ($query !== '') {
                $instance .= '?' . $query;
            }
            if ($instance !== '') {
                $payload['instance'] = $instance;
            }
        }

        foreach ($extensions as $key => $value) {
            if (is_string($key) && $key !== '' && !array_key_exists($key, $payload)) {
                $payload[$key] = $value;
            }
        }

        return Response::json(
            $payload,
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8']
        );
    }

    private function problemType(int $status): string
    {
        return match ($status) {
            400 => 'https://errors.Fnlla.dev/http/bad-request',
            401 => 'https://errors.Fnlla.dev/http/unauthorized',
            403 => 'https://errors.Fnlla.dev/http/forbidden',
            404 => 'https://errors.Fnlla.dev/http/not-found',
            405 => 'https://errors.Fnlla.dev/http/method-not-allowed',
            409 => 'https://errors.Fnlla.dev/http/conflict',
            422 => 'https://errors.Fnlla.dev/http/validation',
            429 => 'https://errors.Fnlla.dev/http/rate-limit',
            default => 'https://errors.Fnlla.dev/http/internal-server-error',
        };
    }

}



