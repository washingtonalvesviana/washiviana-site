# Requirements: Washiviana Site

**Defined:** 2026-04-10
**Core Value:** Evoluir o produto com previsibilidade e seguranca, preservando 100% do comportamento atual em producao enquanto o processo de entrega fica mais claro e rastreavel.

## v1 Requirements

Requirements for initial release of the GSD adaptation. Each maps to roadmap phases.

### Governance

- [ ] **GOV-01**: Projeto possui .planning/PROJECT.md, .planning/config.json, .planning/REQUIREMENTS.md, .planning/ROADMAP.md e .planning/STATE.md consistentes
- [ ] **GOV-02**: Cada requisito v1 possui rastreabilidade para exatamente uma fase do roadmap
- [ ] **GOV-03**: Cada fase do roadmap possui criterios de sucesso observaveis e verificaveis
- [ ] **GOV-04**: Instrucoes de trabalho do projeto sao geradas/atualizadas para enforce do fluxo GSD no repositorio

### Safety

- [ ] **SAFE-01**: Plano inicial nao inclui alteracao de comportamento funcional em producao
- [ ] **SAFE-02**: Mudancas futuras no codigo devem ser propostas em fases incrementais com rollback explicito
- [ ] **SAFE-03**: Toda fase com risco de producao define verificacoes minimas antes de marcar como concluida
- [ ] **SAFE-04**: Mudancas estruturais de maior impacto (rewrite/migracao disruptiva) ficam explicitamente fora de escopo no ciclo inicial

### Quality

- [ ] **QUAL-01**: Cada fase define evidencias objetivas de conclusao (artefatos, checks, validacoes)
- [ ] **QUAL-02**: Backlog de melhorias e dividas tecnicas e separado de escopo v1 para evitar drift
- [ ] **QUAL-03**: Requisitos v1 sao atomicos, testaveis e centrados em resultado observavel
- [ ] **QUAL-04**: O processo de planejamento prioriza slices pequenos com baixo raio de impacto

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Product Evolution

- **PROD-01**: Modernizacao progressiva de arquitetura interna para modular monolith por fatias verticais
- **PROD-02**: Fortalecimento de automacoes de publicacao com padrao outbox/idempotencia
- **PROD-03**: Camada de observabilidade operacional consolidada (metricas, alertas, saude de jobs)
- **PROD-04**: Recursos diferenciadores (rollout progressivo, assistente de IA com aprovacao humana, experimentacao)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Rewrite completo da aplicacao | Alto risco para sistema em producao e fora do objetivo da adequacao inicial ao GSD |
| Mudanca imediata de stack/framework central | Nao necessaria para organizar governanca, requisitos e roadmap com seguranca |
| Alterar contratos API/public/admin ja usados em producao na fase inicial | Violaria a restricao de zero regressao definida para este kickoff |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| GOV-01 | Phase 1 | Pending |
| GOV-02 | Phase 2 | Pending |
| GOV-03 | Phase 2 | Pending |
| GOV-04 | Phase 1 | Pending |
| SAFE-01 | Phase 1 | Pending |
| SAFE-02 | Phase 2 | Pending |
| SAFE-03 | Phase 2 | Pending |
| SAFE-04 | Phase 1 | Pending |
| QUAL-01 | Phase 3 | Pending |
| QUAL-02 | Phase 3 | Pending |
| QUAL-03 | Phase 3 | Pending |
| QUAL-04 | Phase 2 | Pending |

**Coverage:**
- v1 requirements: 12 total
- Mapped to phases: 12
- Unmapped: 0 ✓

---
*Requirements defined: 2026-04-10*
*Last updated: 2026-04-10 after roadmap creation*
