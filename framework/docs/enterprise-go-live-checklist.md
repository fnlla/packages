**ENTERPRISE GO-LIVE CHECKLIST**

This checklist is a fast pre-release gate for enterprise deployments built on fnlla (finella).
Use it together with `documentation/src/operations.md` before each production cut.

**1) RELEASE GOVERNANCE**
**-** Release owner and incident owner assigned.
**-** Release version, scope, rollback target, and change window approved.
**-** Support policy and roadmap references are current.

**2) SECURITY BASELINE**
**-** CI pipeline green (`CI`, `OSV`, unit and smoke checks).
**-** Security policy file is present (`.github/SECURITY.md`).
**-** Vulnerability scan is clean for production dependencies.
**-** Release workflow remains manual (`workflow_dispatch`).

**3) OPERATIONAL READINESS**
**-** Release gate passes (`scripts/release/release-gate.sh`).
**-** Support policy consistency check passes.
**-** API snapshot and docs sync checks pass.
**-** Monitoring and alerting targets are defined for the release.

**4) COMMERCIAL ENTERPRISE BAR**
**-** SLA/SLO and incident response path documented for customers.
**-** Legal terms/licensing are explicit for commercial usage.
**-** Backup and restore drill evidence is available.
**-** Auditability is preserved through PR, changelog, and release notes history.

**GO/NO-GO COMMANDS**

```bash
php scripts/ci/check-enterprise-readiness.php --strict
bash scripts/release/release-gate.sh --mode=monorepo
```

If any mandatory check fails, stop the release and fix findings first.
