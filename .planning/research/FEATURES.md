# Feature Landscape

**Domain:** Production content/admin platform (projects, articles, social publishing, automation)
**Researched:** 2026-04-10

## Table Stakes

Features users expect. Missing = product feels incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Role-based access control (admin/editor/author/contributor) | Mature content operations require permission boundaries and delegated ownership | Med | Baseline for safe collaboration and compliance; aligns with WordPress role/capability model. |
| Draft/Publish workflow with explicit status | Teams need to iterate before going live | Med | Support Draft, Modified, Published states and safe unpublish paths. |
| Content scheduling (articles + social posts) | Editorial and social teams plan calendars, not one-off posts | Med | Must include schedule/unschedule, timezone handling, and missed-schedule recovery. |
| Editorial approval stages | Multi-person teams need review gates before publishing | Med | At minimum: To do -> In review -> Approved/Ready -> Published. |
| Version history and rollback for content | Accidental edits happen; teams need safe restore | Med | Diff + restore for individual entries is table stakes in 2026. |
| Audit trail for admin actions | Production platforms need traceability for incidents and compliance | Med | Capture who changed what and when, including publish/unpublish/config actions. |
| Channel-aware social composer | Social networks have different limits and formats | Med | Per-channel text/media variants with validation at compose-time. |
| Basic performance analytics (content + social) | Teams expect feedback loop for what works | Med | Per-article and per-channel reach/engagement/conversion trend visibility. |
| Internationalization-aware publishing | Multi-language routing/content is expected for mature sites | Med | Locale-aware drafts, publish status, and SEO metadata quality checks. |
| Reliable automation jobs with retries | Scheduled/automated publishing must be operationally dependable | High | Idempotent jobs, lock/claim semantics, retry with backoff, and observable failure states. |

## Differentiators

Features that set product apart. Not expected, but valued.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Release bundles across multiple entries/channels | Ship coordinated campaigns atomically instead of piecemeal edits | High | Group changes, validate, preview, schedule, publish as one release unit. |
| Progressive rollout controls for risky features/automations | Reduce production risk by exposing changes gradually | High | Percentage rollout, segment targeting, kill switch, and staged environment promotion. |
| Safe AI copilot for editorial and social drafting | Faster content throughput without losing human quality control | Med | Human-in-the-loop approvals, fact-check reminders, and policy guardrails are mandatory. |
| Experimentation layer for titles/captions/creative variants | Moves platform from publishing to measurable optimization | High | Built-in A/B variant tracking tied to analytics and promotion decisions. |
| Operational readiness dashboard (content + jobs + connectors) | Faster incident detection and recovery for admin teams | Med | Unified status for queues, scheduled jobs, API failures, connector health, and SLA alerts. |
| Campaign-level impact view across projects, articles, and social | Better strategic decisions than siloed metrics | High | Single timeline linking content changes to distribution and performance outcomes. |

## Anti-Features

Features to explicitly NOT build.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Big-bang rewrite of the existing PHP platform | High regression risk and slow time-to-value in a production brownfield system | Introduce capability slices behind flags and migrate incrementally. |
| Fully autonomous "publish without review" AI mode | High brand/compliance risk and hard-to-reverse incidents | Keep AI as draft assistant; enforce mandatory human approval for publish actions. |
| One-shot architecture migration tied to feature delivery | Couples product value to infra risk and blocks incremental rollout | Decouple feature delivery from deeper refactors; use strangler-style boundaries over time. |
| Overly complex enterprise workflow engine in first increments | Front-loads complexity before core reliability is hardened | Start with minimal stage model and evolve from observed team bottlenecks. |
| Cross-post identical content to all channels by default | Degrades performance and audience fit across networks | Keep channel-aware variants and validations as default behavior. |

## Feature Dependencies

```
RBAC -> Approval stages -> Publish permissions
Draft/Publish -> Version history -> Safe rollback
Draft/Publish -> Scheduling -> Reliable automation jobs
Reliable automation jobs -> Operational readiness dashboard
Channel-aware composer -> Social scheduling -> Social analytics
Core analytics -> Experimentation layer
Feature flags/rollouts -> Progressive release of differentiator features
i18n foundations -> Locale-aware workflow + locale-specific SEO quality checks
Release bundles -> Campaign-level impact view
```

## MVP Recommendation

Prioritize:
1. RBAC hardening + approval stages + publish permission enforcement
2. Draft/Publish, version history, and scheduling reliability (with retries/observability)
3. Channel-aware social publishing plus core analytics baseline

Defer: Release bundles and deep experimentation until operational reliability metrics are stable (job success rate, schedule accuracy, rollback confidence).

## Sources

- WordPress Roles and Capabilities (updated 2024-09-20): https://wordpress.org/documentation/article/roles-and-capabilities/ (HIGH)
- Strapi Draft & Publish: https://docs.strapi.io/cms/features/draft-and-publish (HIGH)
- Strapi Review Workflows: https://docs.strapi.io/cms/features/review-workflows (HIGH)
- Sanity Content Releases user guide (updated 2026-02-26): https://www.sanity.io/docs/content-releases (HIGH)
- Sanity Activity Feed (updated 2026-02-26): https://www.sanity.io/docs/activity-feed (HIGH)
- LaunchDarkly Percentage Rollouts: https://launchdarkly.com/docs/home/releases/percentage-rollouts (HIGH)
- Unleash Feature Flags: https://docs.getunleash.io/reference/feature-toggles (HIGH)

- Project context: .planning/PROJECT.md and .planning/codebase/ARCHITECTURE.md
