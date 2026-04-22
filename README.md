# fnlla packages

This repository is the public packages hub for the fnlla ecosystem.

## Purpose

- Keep a single, readable entry point for package maintainers.
- Document where source-of-truth package code lives.
- Document how public package split repositories are published.

## Source Of Truth

- Monorepo source: `https://github.com/fnlla/framework`
- Package paths in monorepo: `packages/*`
- Framework core path in monorepo: `framework/`

## Shared Test Helpers

- `_shared/` contains test bootstrap helpers used by multiple packages.
- It is not a standalone Composer package and is not meant to be published to Packagist.

## Public Split Repositories

- `https://github.com/fnlla/framework-package` (`fnlla/framework`)
- `https://github.com/fnlla/ai` (`fnlla/ai`)
- `https://github.com/fnlla/audit` (`fnlla/audit`)
- `https://github.com/fnlla/deploy` (`fnlla/deploy`)
- `https://github.com/fnlla/monitoring` (`fnlla/monitoring`)
- `https://github.com/fnlla/oauth` (`fnlla/oauth`)
- `https://github.com/fnlla/standard` (`fnlla/standard`)
- `https://github.com/fnlla/queue` (`fnlla/queue`)
- `https://github.com/fnlla/scheduler` (`fnlla/scheduler`)
- `https://github.com/fnlla/mail` (`fnlla/mail`)
- `https://github.com/fnlla/ops` (`fnlla/ops`)
- `https://github.com/fnlla/pdf` (`fnlla/pdf`)
- `https://github.com/fnlla/rbac` (`fnlla/rbac`)
- `https://github.com/fnlla/search` (`fnlla/search`)
- `https://github.com/fnlla/settings` (`fnlla/settings`)
- `https://github.com/fnlla/docs` (`fnlla/docs`)
- `https://github.com/fnlla/testing` (`fnlla/testing`)
- `https://github.com/fnlla/debugbar` (`fnlla/debugbar`)

## Split Publish Command

Run in `fnlla/framework` monorepo:

```bash
php scripts/release/publish-public-splits.php --org=fnlla --tags=vX.Y.Z
```

This publishes `main` and selected tags to the public split repositories listed above.
