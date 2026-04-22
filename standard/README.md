**fnlla (finella) STANDARD**

fnlla (finella) Standard is a meta-package that installs the full default web stack.
It contains no runtime code and only aggregates official packages.

**INCLUDED PACKAGES**
**-** `fnlla/framework`
**-** `fnlla/ops`
**-** `fnlla/rbac`
**-** `fnlla/settings`
**-** `fnlla/audit`
**-** `fnlla/deploy`

Development-only:
**-** `fnlla/debugbar` (require-dev)
**-** `fnlla/testing` (require-dev)

**INSTALLATION**
```bash
composer require fnlla/standard
```

**PROVIDER DISCOVERY**
All included packages expose their providers via `extra.fnlla.providers`.
fnlla (finella) auto-discovery will register them automatically once the dependencies are installed.

**LICENSE**
Proprietary
