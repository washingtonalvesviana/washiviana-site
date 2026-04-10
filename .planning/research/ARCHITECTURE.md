# Architecture Patterns

**Domain:** Brownfield PHP content platform (public site + admin + action-based APIs + scripts)
**Researched:** 2026-04-10

## Recommended Architecture

Keep a **modular monolith** and evolve it with **Strangler Fig + Branch by Abstraction**, not a full rewrite.

Target shape for this codebase:

1. Preserve existing entry points (`index.php`, `admin/*.php`, `api/*.php`, `scripts/*.php`).
2. Add a thin application kernel for shared concerns (request context, auth, csrf, response, error handling).
3. Route each `action` endpoint call into module application services (not inline endpoint logic).
4. Isolate modules behind explicit boundaries while still sharing one database.
5. Add transitional adapters so legacy and new module code can coexist per feature.

Recommended module map (inside one repo/process):

- `Content` (articles/categories/media)
- `Projects`
- `I18n`
- `SocialIntegrations` (linkedin/instagram/facebook)
- `Publishing` (scheduled jobs/workers)
- `AdminIdentity` (session auth/csrf/permissions)
- `Platform` (logging, telemetry, config, db, http abstractions)

This structure supports incremental replacement and continuous releases: each feature can move endpoint-by-endpoint or action-by-action without freezing product delivery.

### Component Boundaries

| Component | Responsibility | Communicates With |
|-----------|---------------|-------------------|
| Web Front Controller (`index.php`) | Public route resolution and page rendering | I18n module, Content/Projects query services, Platform |
| Admin UI (`admin/*.php`) | Authenticated backoffice screens and UX orchestration | AdminIdentity, API Facade |
| API Facade (`api/*.php`) | HTTP in/out, auth/csrf guards, action routing, response envelope | Application services by module, Platform |
| Application Services (new `src/Modules/*/Application`) | Use-case orchestration, transactions, policy checks | Domain services, repositories, outbox, Platform |
| Domain Layer (new `src/Modules/*/Domain`) | Business rules and invariants | Application services |
| Infrastructure Adapters (new `src/Modules/*/Infrastructure`) | PDO repositories, external API clients, file storage, legacy adapters | DB, external APIs, legacy files |
| Background Workers (`scripts/*.php`) | Async and scheduled processing | Publishing module services, outbox relay, Platform |
| Shared Platform (`src/Platform/*`) | Logging, telemetry, config, db connection, idempotency helpers | All modules |

### Data Flow

Public request flow (incremental target):

1. HTTP request enters `index.php`.
2. Route/locale resolution happens via i18n helpers.
3. Front controller delegates to module query service (directly or via adapter).
4. Service reads from repository and returns DTO/view model.
5. Template/page renders response.

Admin API flow (incremental target):

1. Browser calls `api/<module>.php?action=...`.
2. API Facade enforces session auth + csrf + input validation.
3. Action router maps to one application service use case.
4. Service executes transaction through repository interfaces.
5. JSON response returned using standardized envelope and error mapping.

Async/event flow (incremental target):

1. Write transaction persists business data and outbox row atomically.
2. Worker/relay process reads pending outbox rows.
3. Relay invokes integration adapter (social API, webhook, etc.).
4. Relay marks success/failure with retry metadata and idempotency key.

## Patterns to Follow

### Pattern 1: Strangler Fig Seams Around Existing Endpoints
**What:** Keep current files as stable shells and move internal logic into module services behind adapters.
**When:** Any endpoint with growing complexity or repeated logic.
**Example:**
```php
<?php
// api/artigos.php (facade)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Modules\Content\Application\ArticleService;
use App\Modules\Content\Infrastructure\PdoArticleRepository;

$service = new ArticleService(new PdoArticleRepository($pdo));

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'save') {
    $result = $service->saveArticle($_POST);
    jsonResponse(['success' => true, 'data' => $result]);
    return;
}

jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
```

### Pattern 2: Branch by Abstraction for Risky Replacements
**What:** Introduce interfaces and run old/new implementations side by side behind a switch.
**When:** Replacing critical internals (query logic, social publisher, auth checks).
**Example:**
```php
interface Publisher {
    public function publish(array $payload): PublishResult;
}

final class LegacyPublisherAdapter implements Publisher { /* wrap old code */ }
final class NewPublisher implements Publisher { /* new implementation */ }

$publisher = $flags['new_publisher'] ? new NewPublisher(...) : new LegacyPublisherAdapter(...);
```

### Pattern 3: Action-to-UseCase Mapping (No Fat Endpoints)
**What:** Each `action` maps to one explicit application command/query handler.
**When:** All `api/*.php` files.
**Example:**
```php
$map = [
  'create' => CreateProjectHandler::class,
  'update' => UpdateProjectHandler::class,
  'list' => ListProjectsHandler::class,
];
```

### Pattern 4: Transactional Outbox for Reliable Integrations
**What:** Store outbound integration events in DB in same transaction, deliver asynchronously.
**When:** Social posting, notifications, cross-module propagation.
**Example:**
```php
$pdo->beginTransaction();
$repo->save($entity);
$outbox->append('social.post.requested', $payload, $idempotencyKey);
$pdo->commit();
```

### Pattern 5: Contract-First Platform Standards (PSR-4/3/7/15)
**What:** Adopt autoloading, logging, and HTTP contracts incrementally.
**When:** For all new code; legacy code wraps into adapters over time.
**Example:**
```php
// composer.json
{
  "autoload": {
    "psr-4": {
      "App\\": "src/"
    }
  }
}
```

## Anti-Patterns to Avoid

### Anti-Pattern 1: Big-bang Framework Rewrite
**What:** Rebuilding everything in a new framework before shipping value.
**Why bad:** High regression risk, long freeze, uncertain parity with current behavior.
**Instead:** Keep runtime stable and migrate feature slices through adapters.

### Anti-Pattern 2: Shared God Utility Growth in `api/config.php`
**What:** Continuing to add unrelated business logic to bootstrap helpers.
**Why bad:** Tight coupling and hidden dependencies block modularization.
**Instead:** Move business rules to module services; keep bootstrap infra-only.

### Anti-Pattern 3: Endpoint-Level SQL and Policy Logic Duplication
**What:** Repeating validation, auth, and SQL per `action` block.
**Why bad:** Inconsistent behavior and hard-to-test regressions.
**Instead:** Centralize policy and transaction boundaries in application services.

## Scalability Considerations

| Concern | At 100 users | At 10K users | At 1M users |
|---------|--------------|--------------|-------------|
| Request handling | Current PHP-FPM + OPcache is usually sufficient | Add endpoint profiling, strict N+1 control, caching per hot read path | Add horizontal web scaling, queue-heavy async offload, selective service extraction only where needed |
| DB load | Single primary DB | Add indexes, query budgets, read/write separation for reporting | Partition hot tables, archival strategy, separate analytical workloads |
| Background jobs | Cron scripts | Dedicated worker supervisor, retries, dead-letter handling | Multi-worker sharding, idempotency enforcement, outbox relay scaling |
| Integrations | Synchronous calls tolerated | Prefer async via outbox + worker | Rate-limit orchestration, circuit breakers, per-provider isolation |
| Observability | Basic logs | Structured PSR-3 logs + metrics/traces | Full distributed telemetry + SLO alerts |

## Incremental Build Order (Roadmap-Oriented)

1. **Stabilize Runtime Contract**
- Freeze public/admin/API response contracts.
- Add smoke tests on critical paths before any refactor.

2. **Introduce Foundation Layer**
- Add Composer autoload + `src/` skeleton + PSR-4 namespace.
- Add Platform services for logging/config/error response.

3. **Carve First Vertical Slice (Content module)**
- Migrate one high-change endpoint (`api/artigos.php`) to action-to-usecase mapping.
- Keep legacy behavior via adapter fallback.

4. **Standardize API Facade Pattern**
- Apply same facade + service mapping to `projetos`, `categorias`, `radar`.
- Centralize auth/csrf/validation middleware behavior.

5. **Harden Async Integrations**
- Introduce outbox table and relay worker for social publishing/automation.
- Add idempotency keys and retry policy.

6. **Expand Modular Boundaries**
- Move i18n, social integrations, and publishing logic into dedicated modules.
- Keep shared DB, avoid premature microservice split.

7. **Operational Maturity**
- Add telemetry (logs/metrics/traces), release health checks, and rollback playbooks.
- Define extraction criteria: only split service when module has independent scaling/deploy need.

## Sources

- Martin Fowler, Strangler Fig (updated 2024): https://martinfowler.com/bliki/StranglerFigApplication.html (HIGH)
- Martin Fowler, Branch by Abstraction: https://martinfowler.com/bliki/BranchByAbstraction.html (HIGH)
- microservices.io, Strangler Application: https://microservices.io/patterns/refactoring/strangler-application.html (MEDIUM)
- microservices.io, Transactional Outbox: https://microservices.io/patterns/data/transactional-outbox.html (MEDIUM)
- PHP-FIG PSR-4 Autoloading: https://www.php-fig.org/psr/psr-4/ (HIGH)
- PHP-FIG PSR-3 Logger: https://www.php-fig.org/psr/psr-3/ (HIGH)
- PHP-FIG PSR-7 HTTP Message: https://www.php-fig.org/psr/psr-7/ (HIGH)
- PHP-FIG PSR-15 Middleware/Handlers: https://www.php-fig.org/psr/psr-15/ (HIGH)
- Composer autoloading docs: https://getcomposer.org/doc/01-basic-usage.md#autoloading (HIGH)
- PHP supported versions lifecycle: https://www.php.net/supported-versions.php (HIGH)
- PHP OPcache manual: https://www.php.net/manual/en/book.opcache.php (HIGH)
- OpenTelemetry PHP docs (last modified Jan 2026): https://opentelemetry.io/docs/languages/php/ (HIGH)
