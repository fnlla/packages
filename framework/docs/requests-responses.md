**REQUESTS AND RESPONSES**

**REQUEST**
Create a request from globals:
```php
$request = \Fnlla\\Http\Request::fromGlobals();
```

Access data:
**-** Query: `$request->getQueryParams()`
**-** Post body: `$request->getParsedBody()`
**-** Headers: `$request->getHeaderLine('User-Agent')`
**-** Cookies: `$request->getCookieParams()`
**-** Files: `$request->getUploadedFiles()`

**RESPONSE**
Create a response:
```php
use Fnlla\\Http\Response;

return Response::json(['ok' => true]);
```

Helpers:
**-** `Response::html($html, $status = 200)`
**-** `Response::json($data, $status = 200)`
**-** `Response::text($text, $status = 200)`
**-** `Response::redirect($url, $status = 302)`

**STREAMING**
Use `Response::stream()` or `Response::file()` for large payloads.

**HEADERS AND STATUS**
```php
$response = Response::html('OK')
    ->withHeader('X-App', 'fnlla (finella)')
    ->withStatus(201);
```

fnlla (finella) also adds `X-Request-Id`, `X-Trace-Id`, and `X-Span-Id` by default. Disable via `config/http/http.php`.
