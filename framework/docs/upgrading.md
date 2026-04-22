**UPGRADING**

fnlla (finella) follows Semantic Versioning (SemVer).

**SEMVER RULES**
**-** Patch releases (3.x.y) contain bug fixes only.
**-** Minor releases (3.x.0) add backward-compatible features.
**-** Major releases (4.0.0+) may introduce breaking changes.

**RECOMMENDED WORKFLOW**
**-** Read the changelog.
**-** Update `composer.json` constraints if needed.
**-** Run `composer update`.
**-** Run smoke tests.

**EXTENSIONS**
Upgrade packages independently but keep compatibility with `fnlla/framework ^3.0`.
