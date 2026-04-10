# Domain Pitfalls

**Domain:** Production brownfield transition (PHP product with active users)
**Researched:** 2026-04-10

## Critical Pitfalls

Mistakes that commonly trigger outages, long incident windows, or costly rewrites.

### Pitfall 1: Big-bang replacement disguised as "incremental"
**What goes wrong:** Teams start with an all-or-nothing rewrite scope, then lose control of blast radius and timeline.
**Why it happens:** Modernization goals are not decomposed into independently releasable slices.
**Consequences:** Regressions, release freezes, and unfinished migration programs.
**Prevention:** Use a strangler approach with seam-first decomposition, routing, and small functional cutovers.
**Detection:** Large PRs touching unrelated domains, inability to deploy partial value, migration milestones defined only as "done when old system replaced."
**Confidence:** HIGH (Fowler + Microsoft pattern guidance)

### Pitfall 2: No transitional architecture budget
**What goes wrong:** Teams avoid temporary adapters/proxies because they look like "waste," then are forced into risky direct rewires.
**Why it happens:** Planning optimizes for end-state purity and ignores migration path engineering.
**Consequences:** High-coupling changes, rollback difficulty, prolonged outages during cutover.
**Prevention:** Explicitly plan temporary components (proxy, anti-corruption adapter, dual-write guardrails) and explicit retirement criteria.
**Detection:** Statements like "we cannot route only part of traffic" or "rollback means full deploy rollback only."
**Confidence:** HIGH (Fowler transitional architecture + Azure strangler considerations)

### Pitfall 3: Database cutovers without expand-contract discipline
**What goes wrong:** Schema changes break legacy read/write paths or background jobs still expecting old shape.
**Why it happens:** Application and database changes are released as tightly coupled one-shot migrations.
**Consequences:** Runtime SQL errors, data corruption risk, blocked deploys.
**Prevention:** Adopt expand-contract flow: additive schema first, backfill/verify, dual-read/dual-write period, then contract.
**Detection:** Non-additive migrations in early phases, no compatibility window, no migration rehearsal on production-like data.
**Confidence:** MEDIUM (strong pattern consensus; project concerns confirm DB portability risk)

### Pitfall 4: Process rollout that adds ceremony before safety
**What goes wrong:** New planning/process layers slow delivery but do not reduce incidents.
**Why it happens:** Teams adopt templates and approvals without first establishing engineering safety rails.
**Consequences:** Local workarounds, shadow process, lower trust in modernization effort.
**Prevention:** Sequence process change behind practical controls: CI health checks, rollback playbooks, release checklists, and small-batch WIP limits.
**Detection:** Longer lead time with unchanged or worse change failure rate; "checklist complete" but repeated prod incidents.
**Confidence:** HIGH (DORA capabilities on small batches, work visibility, CI/CD, observability)

### Pitfall 5: Feature-flag sprawl and hidden branching complexity
**What goes wrong:** Flags accumulate and create unpredictable behavior matrices.
**Why it happens:** No expiry policy, unclear ownership, and no cleanup work in sprint plans.
**Consequences:** Debugging complexity, incident triage confusion, stale dead paths.
**Prevention:** Enforce flag naming/ownership/expiry metadata, keep rollout flags short-lived, and track flag debt as scheduled work.
**Detection:** Flags older than one release cycle, unknown flag owner, incident RCA citing unexpected flag interactions.
**Confidence:** HIGH (Unleash operational best practices)

## Moderate Pitfalls

### Pitfall 1: Long-lived branches during brownfield change
**What goes wrong:** Integration is delayed and merge conflicts hide regressions until late.
**Prevention:** Use short-lived branches (or trunk-first workflow), integrate daily, and gate merges with fast CI.
**Confidence:** MEDIUM-HIGH (Trunk-based development guidance)

### Pitfall 2: Modernization without observability parity
**What goes wrong:** New path ships without equivalent logs/metrics/traces, making incident diagnosis slower than in legacy path.
**Prevention:** Define "observability parity" as exit criteria for each migrated slice before traffic expansion.
**Confidence:** MEDIUM (industry practice + DORA monitoring capability)

### Pitfall 3: Inconsistent behavior contracts between old and new paths
**What goes wrong:** Edge-case behavior diverges (auth, validation, i18n, error format) and users experience non-deterministic results.
**Prevention:** Add contract tests and golden request/response fixtures before switching traffic.
**Confidence:** MEDIUM (common brownfield migration failure mode)

## Minor Pitfalls

### Pitfall 1: Documentation lag during phased migration
**What goes wrong:** Runbooks and architecture docs lag behind reality.
**Prevention:** Make docs update part of Definition of Done for each migration slice.
**Confidence:** MEDIUM

### Pitfall 2: Over-broad phase scope
**What goes wrong:** "Phase" contains too many moving parts; verification becomes ambiguous.
**Prevention:** Keep phases outcome-based and independently verifiable, with explicit blast-radius boundaries.
**Confidence:** HIGH

### Pitfall 3: Metric overload without decision linkage
**What goes wrong:** Many dashboards, unclear action thresholds.
**Prevention:** Tie each metric to a concrete release decision (promote, hold, rollback).
**Confidence:** MEDIUM

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Warning Signs | Mitigation |
|-------------|---------------|---------------|------------|
| Phase 1: Planning baseline and guardrails | Process ceremony exceeds operational value | More meetings/docs, same incident profile | Introduce only minimum governance; prioritize release safety controls first |
| Phase 2: Test and deployment safety rails | False confidence from partial tests | Green checks but repeated runtime failures | Add smoke + contract + rollback drill gates for high-risk endpoints |
| Phase 3: Endpoint decomposition (thin routers/services) | Breaking hidden coupling in monolithic endpoints | Unrelated admin/API behaviors regress after local refactor | Extract one action at a time behind stable endpoint contracts |
| Phase 4: Integrations and social publishing hardening | Expanding unsupported channels too early | Scheduler backlog growth, repeated channel failures | Keep unsupported channels disabled until adapters + retries + observability are production-ready |
| Phase 5: Data and migration hardening | Unsafe schema cutovers | Deploy blocked by migration rollback issues; runtime SQL errors | Enforce expand-contract and rehearsal on production-like snapshots |
| Phase 6: Process scaling across team | Reintroducing long-lived branches and large batches | Merge pain rises, lead time drifts up | Enforce WIP limits, short-lived branches, and continuous integration cadence |

## Sources

- Martin Fowler - Strangler Fig Application (updated 2024-08-22): https://martinfowler.com/bliki/StranglerFigApplication.html (HIGH)
- Martin Fowler et al. - Transitional Architecture (2022-03-28): https://martinfowler.com/articles/patterns-legacy-displacement/transitional-architecture.html (HIGH)
- Microsoft Azure Architecture Center - Strangler Fig pattern (last updated 2025-02-19): https://learn.microsoft.com/en-us/azure/architecture/patterns/strangler-fig (HIGH)
- Google Cloud Architecture / DORA capabilities: https://docs.cloud.google.com/architecture/devops/devops-measurement (MEDIUM-HIGH)
- Trunk-Based Development guidance: https://trunkbaseddevelopment.com/ (MEDIUM)
- Unleash docs - Feature flag best practices (copyright 2026): https://docs.getunleash.io/guides/feature-flag-best-practices (HIGH)
- Project context: .planning/PROJECT.md and .planning/codebase/CONCERNS.md (HIGH for project-specific risk prioritization)
