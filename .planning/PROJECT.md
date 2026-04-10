# Washiviana Site

## What This Is

Washiviana Site e uma aplicacao web em PHP ja operando em producao, com area publica, painel administrativo e APIs para gestao de conteudo, projetos, artigos, i18n e integracoes sociais. Nesta fase, o objetivo e adotar a estrutura GSD para organizar planejamento, requisitos, roadmap e evolucao do produto sem alterar o comportamento atual em producao.

## Core Value

Evoluir o produto com previsibilidade e seguranca, preservando 100% do comportamento atual em producao enquanto o processo de entrega fica mais claro e rastreavel.

## Requirements

### Validated

- ✓ Site publico com roteamento e paginas institucionais/conteudo/projetos/artigos — existente
- ✓ Painel administrativo com autenticacao baseada em sessao e controle de acesso — existente
- ✓ APIs internas para CRUD, configuracoes e operacoes do admin via endpoints PHP — existente
- ✓ Base de dados relacional com suporte operacional documentado e migrations/scripts — existente
- ✓ Integracoes sociais e automacoes auxiliares (LinkedIn, Instagram, Facebook, LLMs e rotinas de publicacao) — existente

### Active

- [ ] Estruturar governanca GSD completa no repositorio (.planning/config.json, REQUIREMENTS.md, ROADMAP.md, STATE.md)
- [ ] Definir requisitos v1 orientados a operacao segura e evolucao incremental sem regressao
- [ ] Criar roadmap em fases pequenas, verificaveis e com rastreabilidade requisito-fase

### Out of Scope

- Alterar comportamento funcional de fluxos ja ativos em producao — evitar regressao durante a adocao inicial do GSD
- Refatoracoes profundas de arquitetura no kickoff da estrutura GSD — reduzir risco na fase de inicializacao
- Migracoes disruptivas de stack/framework nesta etapa — prioridade atual e governanca e planejamento seguro

## Context

Aplicacao brownfield em producao com base principal em PHP procedural e endpoints por acao, com bootstrap compartilhado e controles transversais em api/config.php. Ha estrutura de paginas publicas, admin, APIs, scripts de manutencao/automacao, migrations e ativos frontend. O mapeamento de codigo ja foi gerado em .planning/codebase para orientar as proximas decisoes sem suposicoes.

## Constraints

- **Risco de Producao**: Zero regressao durante inicializacao GSD — sistema ja esta em operacao
- **Escopo Inicial**: Primeiro ciclo focado em planejamento e estrutura GSD — minimizar mudancas no runtime
- **Evolucao**: Melhorias devem ser incrementais e verificaveis por fase — reduzir chance de impacto acumulado
- **Ambiente**: Base brownfield PHP com multiplos modulos e integracoes — preservar compatibilidade operacional

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Tratar o projeto como brownfield em producao | Ha sistema ativo com codigo e operacao consolidados | ✓ Good |
| Adocao inicial GSD sem mexer em features/runtime | Prioridade do usuario e nao danificar o que ja existe | ✓ Good |
| Fase 1 focada em estrutura e documentacao GSD | Ganhar previsibilidade antes de qualquer mudanca funcional | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via /gsd-transition):
1. Requirements invalidated? -> Move to Out of Scope with reason
2. Requirements validated? -> Move to Validated with phase reference
3. New requirements emerged? -> Add to Active
4. Decisions to log? -> Add to Key Decisions
5. "What This Is" still accurate? -> Update if drifted

**After each milestone** (via /gsd-complete-milestone):
1. Full review of all sections
2. Core Value check -> still the right priority?
3. Audit Out of Scope -> reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-10 after initialization*
