# Roadmap: Washiviana Site

## Overview

Roadmap de adocao GSD para projeto brownfield em producao, priorizando seguranca operacional e zero regressao. A sequencia entrega primeiro estrutura e trilhos de seguranca, depois rastreabilidade e gates de risco para execucao incremental, e por fim controles de qualidade orientados a evidencias para manter escopo v1 estavel.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Estrutura GSD e Safety Charter** - Estabelece artefatos base e limites explicitos de seguranca sem mudar runtime.
- [ ] **Phase 2: Rastreabilidade e Gates de Risco** - Consolida mapeamento requisito-fase, criterios observaveis e regras de execucao incremental.
- [ ] **Phase 3: Qualidade por Evidencias e Controle de Escopo** - Fecha o ciclo com definicao objetiva de conclusao e blindagem contra scope drift.

## Phase Details

### Phase 1: Estrutura GSD e Safety Charter
**Goal**: Projeto opera com base documental e regras iniciais de seguranca para evolucao sem regressao em producao
**Depends on**: Nothing (first phase)
**Requirements**: GOV-01, GOV-04, SAFE-01, SAFE-04
**Success Criteria** (what must be TRUE):
  1. Arquivos base de governanca (.planning/PROJECT.md, .planning/config.json, .planning/REQUIREMENTS.md, .planning/ROADMAP.md e .planning/STATE.md) existem e estao coerentes entre si
  2. Instrucoes de trabalho do repositorio orientam explicitamente o fluxo GSD esperado para planejamento e execucao
  3. O ciclo inicial permanece restrito a estrutura/documentacao, sem alterar comportamento funcional ativo em producao
  4. Escopo exclui de forma explicita rewrite e migracoes disruptivas durante a adocao inicial
**Plans**: TBD

### Phase 2: Rastreabilidade e Gates de Risco
**Goal**: Toda evolucao planejada passa a ser rastreavel por fase, verificavel e executada em slices pequenos com rollback explicito
**Depends on**: Phase 1
**Requirements**: GOV-02, GOV-03, SAFE-02, SAFE-03, QUAL-04
**Success Criteria** (what must be TRUE):
  1. Cada requisito v1 aponta para exatamente uma fase no mapa de rastreabilidade
  2. Cada fase do roadmap declara de 2 a 5 criterios de sucesso observaveis por comportamento
  3. Fases com risco de producao definem verificacoes minimas objetivas antes de serem marcadas como concluidas
  4. Fases futuras de mudanca de codigo descrevem rollback explicito e sequenciamento incremental de baixo raio de impacto
**Plans**: TBD

### Phase 3: Qualidade por Evidencias e Controle de Escopo
**Goal**: Conclusao de fases e evolucao do produto passam a ser guiadas por evidencias objetivas, requisitos atomicos e backlog separado
**Depends on**: Phase 2
**Requirements**: QUAL-01, QUAL-02, QUAL-03
**Success Criteria** (what must be TRUE):
  1. Cada fase define evidencias objetivas de conclusao (artefatos, checks ou validacoes) antes de avancar
  2. Melhorias e dividas tecnicas fora do v1 ficam registradas em backlog separado sem contaminar escopo atual
  3. Requisitos v1 permanecem atomicos, testaveis e formulados como resultados observaveis
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Estrutura GSD e Safety Charter | 0/TBD | Not started | - |
| 2. Rastreabilidade e Gates de Risco | 0/TBD | Not started | - |
| 3. Qualidade por Evidencias e Controle de Escopo | 0/TBD | Not started | - |
