**GLOSSARY**

**-** **App root**: the project root where `config/`, `routes/`, and `public/` live.
**-** **Public root**: the `public/` directory served by the web server.
**-** **Provider**: a class that registers and boots services for the container.
**-** **Extension**: a Composer package integrating via a service provider.
**-** **Request/Response lifecycle**: the flow from `public/index.php` to the handler and back to the response.
**-** **Middleware**: a callable that wraps the handler and can modify the request or response.
**-** **views_path**: configuration value that tells fnlla (finella) where view templates are stored.
**-** **Auto-discovery**: reading provider metadata from Composer packages.
**-** **Provider cache**: cached provider list stored in `bootstrap/cache/providers.php`.
