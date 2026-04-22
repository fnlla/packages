# fnlla packages

Public package hub for the fnlla ecosystem.

[![Repository Build](https://github.com/fnlla/packages/actions/workflows/build-composer-repository.yml/badge.svg)](https://github.com/fnlla/packages/actions/workflows/build-composer-repository.yml)

## Purpose

- Keep all package sources in one repository (`framework`, `ops`, `rbac`, etc.).
- Generate a Composer repository (`repository/packages.json` + `repository/dist/*.zip`) from this monorepo.
- Let `fnlla/fnlla` install dependencies from one public source URL.

## Ecosystem Position

- Source of truth for framework development is private `fnlla/framework`.
- Public starter is `fnlla/fnlla`.
- Public installer is `fnlla/installer` (`fnlla new`).
- This repository is the public distribution layer consumed by Composer.

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

## Professional Release Baseline

- Keep package constraints aligned (`fnlla/framework:^3.0`).
- Keep branch aliases aligned (for example `3.0.x-dev`).
- Verify starter bootstrap after every release:
  - `composer create-project fnlla/fnlla my-app`
