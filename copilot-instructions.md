<!-- GSD:project-start source:PROJECT.md -->
## Project

**Washiviana Site**

Washiviana Site e uma aplicacao web em PHP ja operando em producao, com area publica, painel administrativo e APIs para gestao de conteudo, projetos, artigos, i18n e integracoes sociais. Nesta fase, o objetivo e adotar a estrutura GSD para organizar planejamento, requisitos, roadmap e evolucao do produto sem alterar o comportamento atual em producao.

**Core Value:** Evoluir o produto com previsibilidade e seguranca, preservando 100% do comportamento atual em producao enquanto o processo de entrega fica mais claro e rastreavel.

### Constraints

- **Risco de Producao**: Zero regressao durante inicializacao GSD — sistema ja esta em operacao
- **Escopo Inicial**: Primeiro ciclo focado em planejamento e estrutura GSD — minimizar mudancas no runtime
- **Evolucao**: Melhorias devem ser incrementais e verificaveis por fase — reduzir chance de impacto acumulado
- **Ambiente**: Base brownfield PHP com multiplos modulos e integracoes — preservar compatibilidade operacional
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->
## Technology Stack

## Languages
- PHP - Main application and API endpoints in `index.php`, `admin/`, `api/`, and `scripts/`.
- SQL - Schema and migration scripts in `database.sql`, `database_postgres.sql`, `database_artigos.sql`, and `migrations/`.
- JavaScript - Admin/public browser code in `assets/js/admin.js` and `assets/js/main.js`.
- TypeScript - Next.js subproject config/runtime in `tailormade/next.config.ts` and `tailormade/tsconfig.json`.
- CSS - Site/admin styles in `assets/css/` and generated Tailwind bundle `assets/css/tailwind.min.css`.
## Runtime
- PHP runtime (repo docs require PHP 7.4+) for the main app (`docs/README.md`, `docs/README_DEPLOY.md`).
- Node.js runtime required for `tailormade/` and Next.js builds; lockfile indicates packages requiring Node >= 18 (`tailormade/package-lock.json`).
- Apache HTTP server with `.htaccess` rewrite/security/cache directives (`.htaccess`).
- Root app: No Composer manifest detected (`composer.json` not detected at repo root).
- Frontend subproject: npm with lockfile (`tailormade/package-lock.json`).
- Lockfile: present for `tailormade/`; not detected for PHP dependencies.
## Frameworks
- Backend: Custom PHP application (no framework detected) with shared bootstrap in `api/config.php`.
- Frontend (main app): server-rendered PHP templates in `index.php`, `projetos.php`, `projeto.php`, `artigo.php`.
- Frontend (subproject): Next.js 16.1.6 + React 19.2.4 (`tailormade/package-lock.json`, `tailormade/package.json`).
- Framework-driven test runner: Not detected.
- Script-style PHP tests/utilities in `tests/` and `outros/` (for example `tests/test_video_pipeline.php`, `outros/test_linkedin_api.php`).
- Tailwind CSS/PostCSS toolchain in `tailormade/package.json` and generated stylesheet consumed by PHP templates (`assets/css/tailwind.min.css`).
- FFmpeg required by video worker pipeline (`scripts/video_worker.php`).
- Cron-style workers/scripts in `scripts/worker_publish_articles.php` and `scripts/video_worker.php`.
## Key Dependencies
- PDO (PHP extension) for DB access and prepared statements in `api/config.php` and `api/auth.php`.
- cURL (PHP extension) for all external API calls in `api/linkedin.php`, `api/gemini.php`, `api/openai.php`, `api/facebook.php`, `api/instagram.php`.
- Session/cookie security settings and CSRF helpers centralized in `api/config.php` and used by auth/config APIs.
- MySQL and PostgreSQL support through runtime-selectable DSN in `api/config.php`.
- Next.js/React/Tailwind stack for `tailormade/` (`tailormade/package-lock.json`).
- Local filesystem storage for media in `uploads/` and upload helpers in `api/upload.php`.
## Configuration
- DB runtime config supports env overrides: `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET` in `api/config.php`.
- Local secret/config override file supported in `api/config.local.php` (present in repo).
- Dynamic base URL and session security computed at runtime in `api/config.php`.
- App-level API keys/providers are persisted in database config tables via `getConfig(...)` usage across `api/gemini.php`, `api/openai.php`, `api/llm-models.php`, and `api/configuracoes.php`.
- Apache runtime configuration in `.htaccess` (rewrite, headers, cache, PHP limits).
- Next.js app config in `tailormade/next.config.ts` and TypeScript config in `tailormade/tsconfig.json`.
- npm scripts for Next.js lifecycle in `tailormade/package.json`.
## Platform Requirements
- PHP + Apache + PDO + cURL + session support for core app (`api/config.php`, `.htaccess`).
- MySQL or PostgreSQL instance for app data (`api/config.php`, `database.sql`, `database_postgres.sql`).
- FFmpeg installed and available in PATH for video jobs (`scripts/video_worker.php`).
- Node.js + npm for `tailormade/` frontend workflows (`tailormade/package.json`).
- Shared hosting compatible deployment documented for Hostinger/FTP in `docs/README_DEPLOY.md`.
- HTTPS expected/enforced by `.htaccess` and secure-cookie logic in `api/config.php`.
- Persistent writable directories for `uploads/` and temporary session path creation (`api/config.php`).
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

## Naming Patterns
- `snake_case.php` is the dominant pattern for API and script files (examples: `api/redes-sociais.php`, `api/configuracoes-save.php`, `scripts/worker_publish_scheduled.php`).
- Top-level route pages also use kebab/snake style in Portuguese (examples: `design-experiencias.php`, `automacao-ia.php`).
- Use `camelCase` for function names in most modules (examples: `jsonResponse`, `validateCsrfToken`, `notifyJobFailure` in `api/config.php`; `checkAuth` in `api/auth.php`).
- Portuguese verb names are common in domain APIs (examples: `criarArtigo`, `atualizarArtigo`, `deletarArtigo` in `api/artigos.php`; `publicarNoInstagramViaAPI` in `api/instagram.php`).
- Local variables primarily use `camelCase` (examples: `$filtroStatus`, `$artigoI18n`, `$videoProvider` in `admin/artigos.php` and `api/videos.php`).
- Database column identifiers remain `snake_case` in SQL and array keys (examples: `status_publicacao`, `data_agendamento`, `redes_destino` in `api/artigos.php`).
- Strict typed signatures appear in selected helper functions but are not global (examples: `getConfigI18n(string $chave, ?string $lang = null): ?string` and `notifyJobFailure(array $jobData, string $message): void` in `api/config.php`).
## Code Style
- No formatter configuration file detected (`.editorconfig`, `.phpcs.xml`, `.prettierrc`, `composer.json` not detected at repository root).
- Code style is manually maintained with frequent section banners and docblocks (examples in `api/config.php`, `api/artigos.php`).
- No lint configuration detected (`phpstan.neon`, `psalm.xml`, `eslint` configs not detected).
- Style consistency is enforced by existing code patterns rather than automated rules.
## Import Organization
- Not used. Includes are resolved with `__DIR__` and relative paths (examples: `__DIR__ . '/../api/config.php'` in `tests/test_social_variants.php`, `__DIR__ . '/config.php'` in API files).
## Error Handling
- API endpoints return early with `jsonResponse([...], status)` for validation/auth/method failures (examples: `api/auth.php`, `api/videos.php`, `api/configuracoes.php`).
- Business logic blocks use `try/catch (Exception $e)` with logging and sanitized JSON responses (examples: `api/auth.php`, `api/artigos.php`, `api/projetos.php`).
- A shutdown handler catches fatal parse/runtime errors and serializes JSON fallback in `api/artigos.php`.
- CLI scripts and ad-hoc tests use `echo` + explicit `exit(code)` (examples: `tests/test_video_pipeline.php`, `scripts/video_worker.php`).
## Logging
- Use `error_log(...)` for server/API diagnostics and integration failures (`api/artigos.php`, `api/config.php`, `api/llm-models.php`).
- CLI workers print timestamped lines via helper logger (`logmsg` in `scripts/video_worker.php`).
- Sensitive operations log partial tokens/prefixes instead of full values in some flows (`api/artigos.php`, `api/csrf.php`).
## Comments
- Docblocks describe endpoint purpose and operational constraints at file top (`api/auth.php`, `tests/test_social_variants.php`).
- Inline comments explain non-obvious behavior such as fallback logic, date normalization, and provider failover (`api/artigos.php`, `api/videos.php`, `scripts/video_worker.php`).
- Not applicable in this PHP codebase.
- PHPDoc-style comments are used, but formal type annotations are partial.
## Function Design
- Large procedural endpoint functions are common in feature-heavy files (examples: `criarArtigo`/`atualizarArtigo` in `api/artigos.php`).
- Utility helpers are centralized in `api/config.php` and reused broadly.
- Input is usually pulled directly from `$_POST`/`$_GET` with defaults and `trim(...)`/sanitization (examples: `api/artigos.php`, `api/auth.php`).
- Database access consistently uses prepared statements with positional placeholders (`$pdo->prepare(...); $stmt->execute([...])`).
- HTTP/API handlers terminate via `jsonResponse` (which calls `exit`).
- Utility functions return scalar/array status objects (`uploadFile`, `validateEmail`, `isVideoFilename` in `api/config.php`).
## Module Design
- No class-based module system. Reusable behavior is exposed through global functions loaded with `require_once`.
- Shared runtime and utility functions are concentrated in `api/config.php`.
- Not used.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

## Pattern Overview
- Request bootstrap and shared helpers are centralized in `api/config.php` and reused by public pages, admin pages, API endpoints, scripts, migrations, and tests.
- Public routing is handled by a front controller in `index.php` using i18n-aware route helpers from `api/i18n.php`.
- API modules in `api/*.php` dispatch by `action` values (from POST/GET) and execute procedural handlers over PDO.
## Layers
- Purpose: Render public pages and localized routes.
- Location: repository root (`index.php`, `conteudos.php`, `projetos.php`, `projeto.php`, `artigo.php`, `sobre.php`).
- Contains: HTML templates mixed with query logic and helper calls.
- Depends on: `api/config.php`, `api/i18n.php`, shared footer `includes/site-footer.php`.
- Used by: Browser requests for site routes.
- Purpose: Render CMS/admin interfaces and forms.
- Location: `admin/*.php`, shared UI partials in `admin/includes/header.php` and `admin/includes/sidebar.php`.
- Contains: Auth-guarded pages (`requireAuth()`), dashboard queries, JS `fetch` calls to API endpoints (for example login in `admin/index.php` calls `api/auth.php`).
- Depends on: `api/config.php` for session/auth/PDO helpers.
- Used by: Authenticated admin users.
- Purpose: Execute CRUD, authentication, publishing, metrics, radar, i18n API operations.
- Location: `api/*.php` (examples: `api/auth.php`, `api/artigos.php`, `api/projetos.php`, `api/radar.php`, `api/upload.php`).
- Contains: `action` switch dispatch, request validation, CSRF checks, JSON responses via `jsonResponse()`.
- Depends on: `api/config.php`, optional feature libs like `api/radar_lib.php` and `api/image_optimizer.php`.
- Used by: Admin UI AJAX, scripts, and occasionally server-side includes.
- Purpose: Background execution, batch updates, migrations, and operational tooling.
- Location: `scripts/*.php`, `scripts/maintenance/*.php`, `migrations/*.php`, plus workers and utilities (for example `scripts/worker_publish_scheduled.php`, `scripts/maintenance/update_token.php`).
- Contains: Cron/systemd-compatible workers and one-off maintenance routines.
- Depends on: `api/config.php` and feature modules (`api/radar_lib.php`).
- Used by: Cron/systemd/manual CLI execution.
## Data Flow
- Session state is PHP-native and initialized in `api/config.php` (custom session name, secure cookie parameters, inactivity timeout).
- Persistent state is relational DB accessed through shared PDO object `$pdo` from `api/config.php`.
## Key Abstractions
- Purpose: Provide DB connection, session lifecycle, auth helpers, CSRF helpers, sanitization, upload and formatting utilities.
- Examples: `api/config.php`.
- Pattern: Centralized procedural utility module imported via `require_once`.
- Purpose: Normalize language, build localized URLs, map route prefixes per locale.
- Examples: `api/i18n.php`, usage in `index.php`.
- Pattern: Pure helper functions consumed by public pages and route redirection logic.
- Purpose: Encapsulate domain logic that is reused by API and scripts.
- Examples: `api/radar_lib.php`, `api/image_optimizer.php`.
- Pattern: Procedural library files with callable functions imported by endpoints/workers.
## Entry Points
- Location: `index.php`
- Triggers: Requests to non-admin/non-api paths.
- Responsibilities: Language routing, page dispatch, homepage rendering.
- Location: `admin/index.php`
- Triggers: Access to admin login.
- Responsibilities: Session bootstrap for login, CSRF token bootstrap, client-side auth API call.
- Location: `api/*.php` (for example `api/auth.php`, `api/artigos.php`, `api/upload.php`, `api/radar.php`).
- Triggers: AJAX/form requests from admin and automation.
- Responsibilities: Action dispatch, auth/CSRF enforcement, JSON responses.
- Location: `scripts/*.php` (for example `scripts/worker_publish_scheduled.php`, `scripts/radar_run.php`).
- Triggers: Cron/systemd/manual CLI.
- Responsibilities: Background processing, scheduled operations, maintenance.
## Error Handling
- API modules call `jsonResponse([...], statusCode)` and guard with `try/catch` + `error_log` (for example `api/auth.php`, `api/artigos.php`, `api/radar.php`).
- `api/artigos.php` uses `ob_start()` + `register_shutdown_function()` to prevent accidental non-JSON fatal output leakage.
## Cross-Cutting Concerns
- Uses PHP `error_log()` across API and scripts (`api/artigos.php`, `api/auth.php`, `scripts/worker_publish_scheduled.php`).
- Input sanitization/validation occurs in endpoint handlers plus shared helpers in `api/config.php`.
- CSRF validation is enforced on POST for sensitive APIs (`api/auth.php`, `api/upload.php`, `api/artigos.php`, `api/radar.php`).
- Session-based auth with helpers `isAuthenticated()` and `requireAuth()` from `api/config.php`.
- Admin pages require session guard; API endpoints reject unauthorized requests with HTTP 401.
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->
## Project Skills

No project skills found. Add skills to any of: `.github/skills/`, `.agents/skills/`, `.cursor/skills/`, or `.github/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
