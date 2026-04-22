**CHANGELOG**

All notable changes to fnlla/framework will be documented in this file.
For the monorepo-wide changes (packages, starter app, docs), see `CHANGELOG.md`.
This format follows Keep a Changelog and Semantic Versioning.

**[UNRELEASED]**

**ADDED**

**CHANGED**
**-** Config loader now supports grouped config subdirectories with prefix-aware keys.

**DEPRECATED**

**REMOVED**

**FIXED**

**SECURITY**

**[3.0.0] - 2026-04-18**

**ADDED**

**CHANGED**
**-** Application version constant updated to 3.0.0.

**DEPRECATED**

**REMOVED**

**FIXED**

**SECURITY**

**[2.5.3] - 2026-04-04**

**ADDED**
**-** Config loader support for grouped config subdirectories (e.g. `config/ai/*`).

**CHANGED**
**-** AI doctor checks accept grouped config paths.
**-** Application version constant updated to 2.5.3.

**[2.5.2] - 2026-04-02**

**CHANGED**
**-** Application version constant updated to 2.5.2.
**-** Configuration access now uses `ConfigRepository` directly.

**REMOVED**
**-** Legacy `AppConfig` wrapper.
**-** Legacy Database public API classes removed from `Fnlla\\Database\*` (e.g., `Database`, `DatabaseManager`, `Migrator`, `QueryBuilder`, `SchemaInspector`) after framework consolidation.

**[2.5.1] - 2026-03-31**

**CHANGED**
**-** Application version constant updated to 2.5.1.

**[2.5.0] - 2026-03-31**

**ADDED**
**-** `HttpClient::postJson()` and `HttpClient::postJsonJson()` helpers for JSON requests.

**CHANGED**

**DEPRECATED**

**REMOVED**

**FIXED**

**SECURITY**

**[2.2.0] - 2026-03-25**

**ADDED**
**-** Warm kernel support for long-running servers (`HttpKernel::boot()`).
**-** Routes cache compiler (`Fnlla\\Http\RouteCacheCompiler`) for production builds.
**-** Response tracing headers by default: `X-Request-Id`, `X-Trace-Id`, `X-Span-Id` (configurable).
**-** ConfigRepository-first accessors (`Application::configRepository()`, `Container::configRepository()`).
**-** `routes_cache_strict` to allow non-fatal route cache generation when closures are present.
**-** Resetter registry for per-request cleanup in long-running mode.
**-** `Fnlla\\Support\UploadPolicy` for upload validation and filename sanitization.
**-** `Fnlla\\Database\SchemaInspector` for cross-driver schema checks.
**-** `Fnlla\\Support\HttpClient` lightweight HTTP client with JSON helpers.
**-** `Fnlla\\Support\ReportPaginator` for simple pagination on report datasets.
**-** `Fnlla\\Support\ValidationHelper` to flatten validation errors.
**-** `Fnlla\\Support\FallbackLogger` for file-based logging when PSR logger is unavailable.

**CHANGED**
**-** `ReportPaginator::links()` now accepts an optional container and returns an empty string when unavailable.

**FIXED**
**-** Documentation updates for routing cache, warm kernel, and tracing headers.

**[2.1.0] - 2026-03-09**

**CHANGED**
**-** Application version constant updated to 2.1.0.

**[2.0.0] - 2026-03-08**

**CHANGED**
**-** Application version constant updated to 2.0.0.

**[1.3.7] - 2026-03-07**

**ADDED**
**-** Router now includes a `patch()` convenience method.

**CHANGED**
**-** Application version constant updated to 1.3.7.

**[1.3.6] - 2026-03-07**

**CHANGED**
**-** Application version constant updated to 1.3.6.

**[1.3.5] - 2026-02-28**

**CHANGED**
**-** Application version constant updated to 1.3.5.

**[1.3.4] - 2026-02-25**

**ADDED**
**-** Render app-provided error views (`errors/404`, `errors/500`) when available.

**CHANGED**
**-** Application version constant updated to 1.3.4.

**[1.3.3] - 2026-02-25**

**CHANGED**
**-** Application version constant updated to 1.3.3.

**[1.3.2] - 2026-02-25**

**CHANGED**
**-** Application version constant updated to 1.3.2.

**[1.3.1] - 2026-02-25**

**CHANGED**
**-** Application version constant updated to 1.3.1.

**[1.3.0] - 2026-02-25**

**CHANGED**
**-** Application version constant updated to 1.3.0.
**-** Branch aliases aligned to 1.3.x.

**[1.2.9] - 2026-02-25**

**ADDED**
**-** `Fnlla\\Support\RedisConnector` helper for Redis connections.
**-** `Application::NAME_ORIGIN` constant for project name provenance.

**[1.2.7] - 2026-02-24**

**ADDED**
**-** Error reporter hook (`Fnlla\\Contracts\Log\ErrorReporterInterface`) in `ExceptionHandler`.

**CHANGED**
**-** Document log configuration options in framework docs.
**-** Source headers updated to reflect proprietary licensing.

**[1.2.6] - 2026-02-24**

**ADDED**
**-** Middleware aliases (`middleware_aliases`) for shorter middleware names.

**CHANGED**
**-** `Request::wantsJson()` now honors `Accept` quality values and AJAX detection.

**FIXED**
**-** Routes cache is validated before loading; invalid cache is ignored (or throws in debug).
**-** Config cache now validates basic structure before loading.

**[1.2.5] - 2026-02-23**

**ADDED**
**-** Global helpers: `route()`, `url()`, `site_url()`, `absolute_url()`, `asset()`.

**CHANGED**
**-** Router returns `204` for `OPTIONS` when a route exists and sets `Allow` header.

**FIXED**
**-** 405 responses include `Allow` header with permitted methods.

**[1.2.4] - 2026-02-23**

**FIXED**
**-** Router now accepts any `ResponseInterface` returned by handlers/middleware.

**[1.0.0] - 2026-02-17**

**ADDED**
**-** Stable 1.0 core: HTTP kernel, router, request/response, container, configuration, and error handling.
**-** Service provider discovery and provider cache.
**-** Optional modules delivered as separate packages.
