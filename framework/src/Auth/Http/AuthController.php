<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth\Http;

use Fnlla\Auth\AuthManager;
use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\ValidationException;
use RuntimeException;

final class AuthController
{
    public function __construct(private AuthManager $auth, private ConfigRepository $config)
    {
    }

    public function showLogin(): Response
    {
        return $this->render('auth/login');
    }

    public function login(Request $request): Response
    {
        try {
            $data = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'remember' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['errors' => $e->errors()], $e->status());
            }
            return $this->render('auth/login', [
                'errors' => $e->errors(),
                'old' => $e->oldInput(),
            ], $e->status());
        }

        $remember = false;
        if (array_key_exists('remember', $data)) {
            $remember = (bool) $data['remember'];
        }

        $result = $this->auth->attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ], $remember, $request);

        if (!$result->authenticated) {
            if ($request->wantsJson()) {
                return Response::json(['message' => 'Invalid credentials'], 422);
            }
            return $this->render('auth/login', [
                'errors' => ['email' => ['Invalid credentials']],
                'old' => ['email' => $data['email']],
            ], 422);
        }

        $redirect = (string) $this->config->get('auth.redirects.login', '/');
        $response = $request->wantsJson()
            ? Response::json(['message' => 'Logged in'])
            : Response::redirect($redirect);

        $cookie = $this->auth->rememberCookie();
        if ($cookie !== null && $result->rememberToken !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->header($result->rememberToken));
        }

        return $response;
    }

    public function showRegister(): Response
    {
        return $this->render('auth/register');
    }

    public function register(Request $request): Response
    {
        try {
            $data = $request->validate([
                'name' => 'nullable|string|max:120',
                'email' => 'required|email',
                'password' => 'required|string|min:8',
            ]);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['errors' => $e->errors()], $e->status());
            }
            return $this->render('auth/register', [
                'errors' => $e->errors(),
                'old' => $e->oldInput(),
            ], $e->status());
        }

        $confirm = $request->input('password_confirmation');
        if (is_string($confirm) && $confirm !== $data['password']) {
            return $this->render('auth/register', [
                'errors' => ['password' => ['Password confirmation does not match.']],
                'old' => ['email' => $data['email'], 'name' => $data['name'] ?? ''],
            ], 422);
        }

        $user = $this->auth->register($data);
        if ($user === null) {
            throw new RuntimeException('Unable to register user.');
        }

        $this->auth->login($user, false);

        $redirect = (string) $this->config->get('auth.redirects.register', '/');
        return $request->wantsJson()
            ? Response::json(['message' => 'Registered'], 201)
            : Response::redirect($redirect);
    }

    public function logout(Request $request): Response
    {
        $cookie = $this->auth->rememberCookie();
        $rememberToken = null;
        if ($cookie !== null) {
            $rememberToken = $request->getCookieParams()[$cookie->name()] ?? null;
        }

        $this->auth->logout(is_string($rememberToken) ? $rememberToken : null);

        $redirect = (string) $this->config->get('auth.redirects.logout', '/login');
        $response = $request->wantsJson()
            ? Response::json(['message' => 'Logged out'])
            : Response::redirect($redirect);

        if ($cookie !== null) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->forgetHeader());
        }

        return $response;
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
