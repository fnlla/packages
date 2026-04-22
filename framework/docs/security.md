**SECURITY**

**CSRF**
CSRF protection is a core module and depends on core sessions. Use `CsrfTokenManager` and `CsrfMiddleware` to store and validate tokens for state-changing requests.

**COOKIES AND SESSIONS**
**-** Use `HttpOnly` for session cookies.
**-** Use `Secure` in production.
**-** Choose an appropriate `SameSite` policy.

**TRUSTED PROXIES**
Configure trusted proxies explicitly in config. Do not default to `*`.

**INPUT VALIDATION**
Validate input at boundaries and normalise data. Use allow-lists and explicit types.

**PRODUCTION HARDENING**
**-** `APP_ENV=prod` and `APP_DEBUG=0`
**-** HTTPS everywhere
**-** Protect `storage/` and `config/`
**-** Rotate secrets and keys regularly
