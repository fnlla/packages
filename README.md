# fnlla packages

This repository is the public package hub for the fnlla ecosystem.

## Purpose

- Keep all package sources in one repository (`framework`, `ops`, `rbac`, etc.).
- Generate a Composer repository (`repository/packages.json` + `repository/dist/*.zip`) from this monorepo.
- Let `fnlla/fnlla` install dependencies from one public source URL.

## Shared Test Helpers

- `_shared/` contains test bootstrap helpers used by multiple packages.
- It is not a standalone Composer package and is not published as an installable package.

## Composer Repository Output

- Generated metadata: `repository/packages.json`
- Generated archives: `repository/dist/*.zip`
- Public Composer URL:
  - `https://raw.githubusercontent.com/fnlla/packages/main/repository`

## Build Repository Metadata

Run in this repository:

```bash
php tools/build-composer-repository.php --version=3.0.4
```

The command reads all first-level package directories that contain `composer.json`
(except `_shared`) and rebuilds `repository/`.

## GitHub Actions

- Manual workflow: `.github/workflows/build-composer-repository.yml`
- Input: `version` (for example `3.0.4`)
- Result: commits refreshed `repository/` content to `main`
