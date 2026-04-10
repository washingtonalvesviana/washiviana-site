# Technology Stack

**Project:** Washiviana Site (Brownfield PHP Platform Modernization)
**Researched:** 2026-04-10

## Recommended Stack

### Core Framework
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PHP (FPM) | 8.4.x (target), 8.3.x (temporary compatibility lane) | Main runtime for site, admin, and API endpoints | 8.4 is a stable modernization target with active ecosystem support; safer than jumping directly to newest major runtime in a brownfield app. |
| Composer | 2.9.x | Dependency and autoload management for incremental modernization | Enables controlled package adoption in a repo that currently lacks a root dependency contract. |
| Custom front controller + incremental service extraction | Existing architecture, standardized through Composer PSR-4 autoloading | Keep routing/bootstrap behavior stable while moving business logic from endpoint files into testable services | Lowest regression-risk path: preserve URL/API behavior first, refactor internals second. |

### Database
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| MySQL | 8.0 LTS-compatible target | Primary relational store where MySQL is deployed | Mature default for PHP hosting and existing SQL script compatibility. |
| PostgreSQL | 16.x-compatible target | Alternative/parallel relational deployment already present in project patterns | Keep dual-engine support as an explicit compatibility contract during migration. |

### Infrastructure
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Apache HTTPD + PHP-FPM | Apache 2.4 + PHP-FPM 8.4 | Preserve current production model and rewrite behavior while modernizing runtime | Avoids operational rewrite during code modernization; compatible with existing .htaccess/front-controller pattern. |
| Cron + supervised CLI workers | Existing pattern, formalized with health checks | Run scheduled publishing, social posting, and background media tasks | Matches current architecture; add reliability controls before introducing any queue platform migration. |
| Sentry SDK | 4.24.x | Error monitoring, traces, and log correlation for production safety | Fastest way to improve incident detection and reduce regression MTTR in a legacy-heavy codebase. |

### Supporting Libraries
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| phpunit/phpunit | 12.5.x baseline (13.x for greenfield modules only) | Regression and integration testing | Pin to 12.5.x while stabilizing legacy behavior; evaluate 13.x after CI is green on upgraded runtime. |
| phpstan/phpstan | 2.1.x | Static analysis to catch defects before deploy | Start at low strictness and ratchet upward per directory after baseline cleanup. |
| rector/rector | 2.4.x | Automated safe refactors and upgrade rules | Use dry-run and scoped rulesets on touched directories only; never run repo-wide first pass on brownfield code. |
| monolog/monolog | 3.10.x | Structured application logging (PSR-3) | Standardize logs from API/admin/workers before adding additional telemetry sinks. |
| sentry/sentry | 4.24.x | Unified exceptions, traces, optional logs | Enable early in modernization to detect breakage from runtime and refactor changes. |

## Brownfield-First Modernization Pattern (2026)

1. Establish safety net before behavior changes:
   - Introduce Composer at repo root.
   - Add smoke tests for critical paths (home, article view, admin auth, publish flows).
   - Add Sentry + structured logs.
2. Upgrade runtime in controlled stages:
   - Ensure code runs on PHP 8.3 first.
   - Move to PHP 8.4 target after deprecation cleanup.
   - Keep a short-lived compatibility lane for rollback.
3. Enforce non-invasive quality gates:
   - PHPStan baseline + incremental tightening.
   - PHPUnit required for touched files/flows.
   - Rector only in dry-run + reviewed patches.
4. Refactor by seam, not by rewrite:
   - Extract services from high-churn endpoint files.
   - Keep existing HTTP contracts and DB schema stable until coverage and observability improve.

## What To Avoid

- Full framework rewrite (Laravel/Symfony migration) as a first modernization step.
  - High blast radius and behavior drift for current procedural/action-based endpoints.
- Jumping directly to newest runtime branch (for example immediate 8.5-only target) without 8.4 stabilization.
  - Increases simultaneous variables during migration.
- Big-bang Rector or auto-fix runs across entire repository.
  - Brownfield procedural files often need scoped, human-reviewed transformations.
- Introducing brand-new router stack as initial change.
  - Keep current dispatch model stable first; routing replacement can follow after service extraction and tests.

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Runtime target | PHP 8.4.x | PHP 8.5.x immediately | 8.5 is viable but less conservative for initial brownfield stabilization; adopt after 8.4 hardening. |
| Architecture strategy | Incremental modernization in-place | Full rewrite on a new framework | Rewrite risk is disproportionate for a production platform with existing admin/API behavior contracts. |
| Static analysis | PHPStan 2.1.x | Psalm (latest) | PHPStan has strong current momentum in brownfield upgrade workflows and direct synergy with Rector migration loops. |
| Routing for new modules | Keep existing dispatcher, optionally add Slim 4 only for isolated new API slices | FastRoute-first replacement | FastRoute stable line is old and 2.x is beta; not ideal as core migration anchor. |
| Observability | Sentry + Monolog | Build-only custom log stack first | Slower path to actionable production diagnostics during migration. |

## Installation

```bash
# 1) Initialize Composer (if missing)
composer init --no-interaction

# 2) Core runtime modernization dependencies
composer require monolog/monolog:^3.10 sentry/sentry:^4.24

# 3) Quality and migration toolchain
composer require --dev phpunit/phpunit:^12.5 phpstan/phpstan:^2.1 rector/rector:^2.4

# 4) Optional microframework only for isolated new modules (not full rewrite)
composer require slim/slim:^4.15
```

## Source-Backed Notes

- Use Slim only as an opt-in boundary for new modules, not as a rewrite trigger.
- Avoid anchoring strategy on `nikic/fast-route` as primary modernization choice right now:
  - stable is old (`v1.3.0`), while `2.0.0-beta1` is pre-release.

## Sources

- PHP supported versions and releases (official):
  - https://www.php.net/supported-versions.php
  - https://www.php.net/releases/
- Composer downloads/channels (official):
  - https://getcomposer.org/download/
- PHPUnit releases (official):
  - https://github.com/sebastianbergmann/phpunit/releases
- PHPStan releases (official):
  - https://github.com/phpstan/phpstan/releases
- Rector releases and process docs (official):
  - https://github.com/rectorphp/rector/releases
  - https://getrector.com/documentation
- Slim docs/releases (official):
  - https://www.slimframework.com/docs/v4/
  - https://github.com/slimphp/Slim/releases
- Monolog package/releases (official):
  - https://packagist.org/packages/monolog/monolog
  - https://github.com/Seldaek/monolog/releases
- Sentry PHP docs/releases (official):
  - https://docs.sentry.io/platforms/php/
  - https://github.com/getsentry/sentry-php/releases
- FastRoute package/releases (official):
  - https://packagist.org/packages/nikic/fast-route
  - https://github.com/nikic/FastRoute/releases

## Confidence

- Runtime and tooling versions: HIGH (official release channels)
- Migration pattern recommendations for this repository shape: MEDIUM-HIGH (official docs + brownfield architecture fit)
- Database version pinning in this project specifically: MEDIUM (requires environment inventory confirmation across current deployments)
