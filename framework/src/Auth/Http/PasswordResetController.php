<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth\Http;

use Fnlla\Auth\PasswordResetManager;
use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\ValidationException;

final class PasswordResetController
{
    public function __construct(private PasswordResetManager $resets, private ConfigRepository $config)
    {
    }

    public function showRequest(): Response
    {
        return $this->render('auth/password/forgot');
    }

    public function sendLink(Request $request): Response
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['errors' => $e->errors()], $e->status());
            }
            return $this->render('auth/password/forgot', [
                'errors' => $e->errors(),
                'old' => $e->oldInput(),
            ], $e->status());
        }

        $user = $this->resets->findUser(['email' => $data['email']]);
        if ($user === null) {
            return $this->genericResponse($request);
        }

        $token = $this->resets->createToken($user);
        $debug = getenv('APP_DEBUG') === '1';
        if ($request->wantsJson()) {
            return $debug
                ? Response::json(['message' => 'Reset token generated', 'token' => $token])
                : Response::json(['message' => 'If your email exists, a reset link was sent.']);
        }

        if ($debug) {
            return $this->render('auth/password/forgot', ['status' => 'Token: ' . $token]);
        }

        return $this->render('auth/password/forgot', ['status' => 'If your email exists, a reset link was sent.']);
    }

    public function showReset(string $token): Response
    {
        return $this->render('auth/password/reset', ['token' => $token]);
    }

    public function reset(Request $request): Response
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
                'password' => 'required|string|min:8',
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['errors' => $e->errors()], $e->status());
            }
            return $this->render('auth/password/reset', [
                'errors' => $e->errors(),
                'old' => $e->oldInput(),
                'token' => $request->input('token'),
            ], $e->status());
        }

        $confirm = $request->input('password_confirmation');
        if (is_string($confirm) && $confirm !== $data['password']) {
            return $this->render('auth/password/reset', [
                'errors' => ['password' => ['Password confirmation does not match.']],
                'old' => ['email' => $data['email']],
                'token' => $data['token'],
            ], 422);
        }

        $user = $this->resets->findUser(['email' => $data['email']]);
        if ($user === null) {
            return $this->render('auth/password/reset', [
                'errors' => ['email' => ['User not found.']],
                'old' => ['email' => $data['email']],
                'token' => $data['token'],
            ], 422);
        }

        $ok = $this->resets->reset($user, $data['token'], $data['password']);
        if (!$ok) {
            return $this->render('auth/password/reset', [
                'errors' => ['token' => ['Reset token is invalid or expired.']],
                'old' => ['email' => $data['email']],
                'token' => $data['token'],
            ], 422);
        }

        $redirect = (string) $this->config->get('auth.redirects.reset', '/login');
        return $request->wantsJson()
            ? Response::json(['message' => 'Password updated'])
            : Response::redirect($redirect);
    }

    private function genericResponse(Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['message' => 'If your email exists, a reset link was sent.']);
        }

        return $this->render('auth/password/forgot', [
            'status' => 'If your email exists, a reset link was sent.',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = [], int $status = 200): Response
    {
        if (function_exists('view')) {
            $response = view($template, $data);
            return $status === 200 ? $response : $response->withStatus($status);
        }

        return Response::html('View engine not available', 500);
    }
}
