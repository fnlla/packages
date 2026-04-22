**fnlla (finella) FRAMEWORK DOCUMENTATION**

This documentation targets the `fnlla/framework` package. It is written in UK English and reflects the actual behaviour of the 3.x line.

**HOW TO READ THIS DOCUMENTATION**
Start with `getting-started.md`, then go through routing, middleware, and configuration. Use the monorepo docs (`documentation/src/getting-started.md`, `documentation/src/framework.md`, `documentation/src/operations.md`) for app-level guidance.

**CORE VS OPTIONAL MODULES**
The framework core includes the full application foundation (kernel, router, container, configuration, error handling, HTTP, auth, sessions, cookies, CSRF, database, ORM, cache, logging, and console).
Optional packages add specialised capabilities such as queue, scheduler, mail, ops middleware, monitoring, and external adapters.

**HIGHLIGHTS**
**-** Warm kernel support for long-running servers (`HttpKernel::boot()`).
**-** Response tracing headers: `X-Request-Id`, `X-Trace-Id`, `X-Span-Id` (configurable).

**ENABLING OPTIONAL MODULES**
**-** Install the package via Composer.
**-** Ensure its service provider is discovered (or register manually).
**-** Add middleware to your HTTP pipeline if the module provides one.

**CONTENTS**
**-** `getting-started.md`
**-** `architecture.md`
**-** `requests-responses.md`
**-** `views.md`
**-** `error-handling.md`
**-** `discovery-and-cache.md`
**-** `providers.md`
**-** `extensions.md`
**-** `deployment-vps.md`
**-** `enterprise-go-live-checklist.md`
**-** `directory-structure.md`
**-** `upgrading.md`
**-** `faq.md`
**-** `glossary.md`

**VERSION COMPATIBILITY**
These docs target fnlla (finella) v3.0 and later. If you are on a different major version, check the matching documentation.
