# Project Research Summary

**Project:** Washiviana Site
**Domain:** Brownfield PHP content and social publishing platform modernization
**Researched:** 2026-04-10
**Confidence:** HIGH

## Executive Summary

Washiviana Site is a production brownfield PHP platform with public content delivery, admin operations, and action-based APIs for articles, projects, i18n, and social publishing. The research converges on a modular monolith modernization strategy, not a framework rewrite: preserve current entry points and contracts, then extract internal logic into testable services behind stable facades. This is the fastest path to operational improvement without breaking current production behavior.

The recommended approach is safety-first and seam-first. Start by standardizing runtime/tooling (PHP 8.4 target with short compatibility lane, Composer, PHPUnit/PHPStan/Rector in scoped usage, Monolog + Sentry), then migrate endpoint logic incrementally using Strangler Fig and Branch by Abstraction. Feature priorities should emphasize table stakes for reliable operations: RBAC + approvals, draft/publish and scheduling reliability, social channel-aware composition, and baseline analytics.

The main risks are migration blast radius, unsafe data/schema changes, and process overhead without reliability gains. Mitigation is explicit and practical: small releasable slices, expand-contract DB discipline, outbox + idempotency for integrations, observability parity before cutover, short-lived flags/branches, and release/rollback playbooks as hard gates.

## Key Findings

### Recommended Stack

Research supports a conservative but modern baseline suited to production brownfield delivery. PHP 8.4 is the runtime target, with temporary 8.3 compatibility where needed to reduce rollback risk. Composer should become the dependency/autoload backbone while keeping current routing and entry files stable. Quality and migration tooling (PHPUnit 12.5, PHPStan 2.1, Rector 2.4) should be applied incrementally per touched module, never as first-pass repo-wide transformations.

Observability and operations are first-class stack choices: Monolog for structured logs and Sentry for exception/trace visibility. Infrastructure remains Apache + PHP-FPM with existing cron/worker model, hardened through retries, health checks, idempotency, and alerting before introducing larger platform shifts.

**Core technologies:**
- PHP 8.4.x: primary runtime target with safer modernization cadence for brownfield behavior preservation.
- Composer 2.9.x: controlled dependency and PSR-4 autoload adoption without rewriting entry points.
- MySQL 8.0 / PostgreSQL 16 compatibility: preserve dual-engine operational reality during migration.
- PHPUnit 12.5 + PHPStan 2.1 + Rector 2.4: regression safety, static risk detection, and scoped automation.
- Monolog 3.10 + Sentry 4.24: incident detection, faster triage, and lower migration MTTR.

### Expected Features

Feature research strongly favors operational reliability and editorial control as the core product baseline. In this domain, collaboration and publishing safety are expected, not optional. Differentiators should be added only after predictable delivery metrics are stable.

**Must have (table stakes):**
- RBAC with clear publish permissions and approval boundaries.
- Draft/modified/published lifecycle with safe unpublish/rollback.
- Scheduling for content and social publishing with timezone correctness and recovery.
- Version history + audit trail for traceability and incident response.
- Channel-aware social composition and validation.
- Baseline analytics for content/social performance.
- Reliable automation with retries, lock/claim semantics, and failure visibility.

**Should have (competitive):**
- Release bundles for coordinated campaign publishing.
- Progressive rollout controls and kill-switches.
- Human-in-the-loop AI drafting assistant with policy guardrails.
- Experimentation layer for title/caption/creative variants.
- Operational readiness dashboard and campaign-level impact view.

**Defer (v2+):**
- Full release-bundle orchestration at scale before core reliability gates are met.
- Deep experimentation programs before analytics quality and scheduling accuracy stabilize.
- Any autonomous publish mode without human approval.

### Architecture Approach

Architecture research is consistent: evolve into a modular monolith with explicit module boundaries while preserving current file-level entry points. Keep api/admin/public shells stable, route each action to one application use case, and isolate new domain logic under src/ modules (Content, Projects, I18n, SocialIntegrations, Publishing, AdminIdentity, Platform). Use adapters for coexistence, transactional outbox for integration reliability, and PSR contracts for new code.

**Major components:**
1. Web Front Controller + Admin UI shells: stable public/admin entry points and UX orchestration.
2. API Facade + Action Router: auth/csrf/validation plus deterministic action-to-usecase mapping.
3. Module Application/Domain/Infrastructure layers: business rules, repositories, and adapters.
4. Publishing Workers + Outbox Relay: reliable async processing for social/integration tasks.
5. Shared Platform services: logging, config, DB abstractions, idempotency, and error contracts.

### Critical Pitfalls

1. **Big-bang rewrite scope creep**: avoid by forcing thin-slice releases and strangler seams per endpoint/action.
2. **Skipping transitional architecture**: avoid by budgeting adapters/proxies/feature gates and planned retirement.
3. **Unsafe schema cutovers**: avoid with expand-contract migrations, rehearsals, and compatibility windows.
4. **Process before safety rails**: avoid by prioritizing CI smoke/contract checks, rollback drills, and observability.
5. **Feature-flag sprawl**: avoid with owner+expiry metadata, short-lived rollout flags, and debt cleanup tasks.

## Implications for Roadmap

Based on research, suggested phase structure:

### Phase 1: Runtime and Delivery Safety Baseline
**Rationale:** All further change depends on reliable rollback and visibility.
**Delivers:** Composer root contract, PHP 8.3/8.4 compatibility lane, smoke tests for critical flows, structured logs and Sentry, initial CI quality gates.
**Addresses:** Automation reliability, auditability prerequisites, safer publish workflows.
**Avoids:** Process-overhead trap and blind migrations without observability parity.

### Phase 2: API Facade Standardization and First Vertical Slice
**Rationale:** Establish the reusable migration pattern on one high-churn endpoint before scaling.
**Delivers:** Action-to-usecase mapping, thin endpoint shell, first extracted module service (Content/articles), contract tests.
**Uses:** PSR-4 autoload, PHPStan baseline, scoped Rector where safe.
**Implements:** Strangler seam + Branch by Abstraction for coexistence.

### Phase 3: Editorial Governance Core (RBAC + Workflow)
**Rationale:** Publish safety and collaboration controls are table stakes and risk reducers.
**Delivers:** Role/capability hardening, approval stages, publish permission checks, audit logging for critical admin actions.
**Addresses:** Must-have collaboration and compliance expectations.
**Avoids:** Behavior drift in auth/policy through centralized application services.

### Phase 4: Scheduling and Integration Reliability
**Rationale:** Scheduled publishing and social automation are high-impact failure domains.
**Delivers:** Transactional outbox, idempotent worker flows, retry/backoff, dead-letter handling, channel-aware validation for social publishing.
**Addresses:** Scheduling reliability and dependable connector operations.
**Avoids:** Connector outages, duplicate posts, and fragile synchronous integration paths.

### Phase 5: Analytics and Operations Readiness
**Rationale:** Teams need decision-grade feedback before advanced optimization features.
**Delivers:** Baseline content/social analytics, job health dashboards, release health checks, SLO-aligned alerts.
**Addresses:** Performance visibility and operational confidence.
**Avoids:** Metric overload without action thresholds by tying metrics to promote/hold/rollback decisions.

### Phase 6: Differentiators Under Controlled Rollout
**Rationale:** Competitive features should follow stable core operations.
**Delivers:** Progressive rollouts, release bundles, AI drafting assist (human approval required), early experimentation capabilities.
**Addresses:** Strategic differentiation and campaign velocity.
**Avoids:** Premature complexity and unsafe autonomous publishing.

### Phase Ordering Rationale

- Sequence follows hard dependencies: safety rails -> architecture seam pattern -> governance core -> async reliability -> analytics -> differentiators.
- Grouping aligns with module boundaries and migration mechanics, reducing cross-domain regressions.
- Order directly mitigates top pitfalls by containing blast radius and requiring operational gates before higher-risk capabilities.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 4:** Social provider API specifics, rate limits, retry semantics, and idempotency edge cases.
- **Phase 6:** AI policy/compliance controls, experimentation telemetry quality, and release-bundle guardrails.
- **Phase 1 (targeted):** Deployment inventory for MySQL/PostgreSQL variants and PHP compatibility matrix.

Phases with standard patterns (skip research-phase):
- **Phase 2:** Strangler + branch-by-abstraction + action mapping are well-documented and already tailored.
- **Phase 3:** RBAC/workflow/audit patterns are mature and implementation-focused rather than research-heavy.
- **Phase 5:** Baseline observability and dashboard patterns are standard once metric ownership is defined.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Versioning and tooling recommendations are grounded in official release channels and fit brownfield constraints. |
| Features | HIGH | Table stakes and differentiators are backed by current CMS/platform norms and validated dependencies. |
| Architecture | HIGH | Strong consensus on modular monolith + strangler/outbox patterns for this repo shape. |
| Pitfalls | HIGH | Risks and mitigations align with established modernization and delivery engineering guidance. |

**Overall confidence:** HIGH

### Gaps to Address

- Production environment inventory gap: validate exact runtime and DB versions across all deployed environments before enforcing upgrade gates.
- Data model evolution gap: map highest-risk tables/jobs and prepare expand-contract migration rehearsal scripts early.
- Connector behavior gap: confirm per-channel API quotas, failure semantics, and webhook consistency for resilient scheduling.
- Baseline quality gap: identify top critical user journeys for contract test golden fixtures before broad refactoring.

## Sources

### Primary (HIGH confidence)
- PHP official lifecycle and releases; Composer docs; PHPUnit/PHPStan/Rector official releases/docs.
- PHP-FIG PSR-3/4/7/15 specifications and Composer autoloading guidance.
- Martin Fowler Strangler Fig, Branch by Abstraction, Transitional Architecture.
- Microsoft Azure Strangler pattern guidance.
- Sentry PHP docs and Monolog package/release references.
- Strapi workflows/draft-publish, WordPress roles/capabilities, Sanity releases/activity docs.
- LaunchDarkly and Unleash feature rollout best-practice docs.

### Secondary (MEDIUM confidence)
- microservices.io transactional outbox and strangler implementation framing.
- Trunk-based development operational guidance.
- DORA capability mapping references for delivery risk controls.

### Tertiary (LOW confidence)
- None identified as decision-critical for this synthesis.

---
*Research completed: 2026-04-10*
*Ready for roadmap: yes*