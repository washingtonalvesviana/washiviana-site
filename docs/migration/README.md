# Handoff de Migração — Washiviana

> Documentação de transferência para continuar o desenvolvimento em **outra sessão / outro agente de IA**.
> Última atualização: 2026-09-28.

## Objetivo

Planejar e executar a modernização do site **sem deixar o site fora do ar** e **sem perder dados**:

- **Backend novo em Go** (API + workers), reutilizando o **mesmo PostgreSQL**.
- **Python** apenas onde houver necessidade real de IA (inferência local, embeddings, RAG, imagem) — não no início.
- **Frontend novo** (admin responsivo primeiro, site público depois).
- **Banco de dados 100% reaproveitado** — nenhum dado é recadastrado.

## TL;DR do estado atual

- Backend: **PHP 8.3 procedural** (sem framework), APIs por `action` em `api/*.php` (**31 arquivos, ~25 endpoints**).
- Banco: **PostgreSQL** (produção), **29 tabelas**, volume pequeno.
- Site público: PHP com i18n (pt/en/es), lê o banco **direto**.
- Admin: PHP server-rendered, lê o banco direto **e** chama `../api/*.php` via `fetch`.
- Workers: **systemd timers** rodando PHP (`scripts/*.php`) a cada minuto / 6h.
- Já existe app **Next.js/React/TS** em `tailormade/` (porta 3100) atrás do nginx.
- Runtime disponível no servidor: **Go 1.22**, **Python 3.12**, **Node 22**, **npm/pnpm**.

## Regras de ouro (não negociáveis)

1. **Não deixar o site fora do ar.** Migração sempre em paralelo (*strangler*), com fallback.
2. **Não alterar o schema sem migration versionada** (`migrations/`). O site público PHP depende do schema atual.
3. **Não vazar segredos** em commits/docs. Credenciais ficam em `api/config.local.php` (não versionado) e no banco.
4. **Nunca fazer deploy sem backup** do banco (`scripts/backup_db_for_migration.sh`).
5. **Uma mudança de cada vez**, com rollback definido antes de aplicar.

## Índice

| Documento | Conteúdo |
|---|---|
| [01-ARQUITETURA-ATUAL.md](01-ARQUITETURA-ATUAL.md) | Stack, deploy, nginx, sessões, workers, serviços externos |
| [02-BANCO-DE-DADOS.md](02-BANCO-DE-DADOS.md) | 29 tabelas, colunas, FKs, migrations, backup |
| [03-CONTRATO-API.md](03-CONTRATO-API.md) | Todos os endpoints, ações, parâmetros e respostas |
| [04-LOGICA-DE-NEGOCIO.md](04-LOGICA-DE-NEGOCIO.md) | Publicação social, IA/LLM, vídeo, i18n/SEO, radar, métricas |
| [05-PLANO-MIGRACAO.md](05-PLANO-MIGRACAO.md) | Fases, entregáveis, critérios de saída, rollback |
| [06-DESIGN-BACKEND-GO.md](06-DESIGN-BACKEND-GO.md) | Layout proposto do serviço Go, auth, workers, cutover |
| [07-RUNBOOK-HANDOFF.md](07-RUNBOOK-HANDOFF.md) | Como operar/testar/deployar; pegadinhas; checklist |
| [08-PYTHON-SIDECAR-AVALIACAO.md](08-PYTHON-SIDECAR-AVALIACAO.md) | Avaliação do sidecar Python de IA (Fase 3e) |
| [openapi.yaml](openapi.yaml) | Esqueleto OpenAPI do contrato atual (a expandir) |

## Como usar este handoff

1. Leia `01` e `02` para entender o terreno.
2. Leia `03` e `04` para entender o contrato e a lógica que precisam ser preservados.
3. Siga `05` (plano) e `06` (design) para implementar.
4. Use `07` (runbook) em toda operação no servidor.

## Estado da execução

Progresso detalhado em **[STATUS.md](STATUS.md)**. Situação em 2026-09-28:

- **Fase 0 — concluída:** backup validado em staging; contrato congelado; serviço Go em `:8081`.
- **Fase 1 — concluída:** leitura (categorias, artigos, projetos, i18n-seo) com paridade e contract tests.
- **Fase 2(a) — concluída:** autenticação própria (sessão em `sessions`, bcrypt, CSRF).
- **Fase 2(b) — concluída:** escritas de categorias, artigos, projetos e configurações (testes com rollback/cleanup).
- **Fase 2(c) — entregue:** novo admin responsivo (`admin-next/`) via `/go-api/` — login, dashboard, conteúdos, projetos, categorias e configurações. **Pendentes (futuro):** SEO/traduções, upload de mídia e redes sociais na UI.
- **Fase 3(d) — parcial:** workers `publish-scheduled` (claim atômico validado) e `metrics`. **Adiados:** executor de `video` (FFmpeg + provedores externos) e cutover dos timers (seguem PHP).
- **Fase 3(e) — concluída:** sidecar Python **avaliado e adiado** (PoC em `ai-sidecar/`).
- **Fase 3(f) — opcional:** site público não migrado.

**Produção intacta:** site, admin PHP e dados no ar; nenhum tráfego de produção foi cortado para o Go. Deferrals são conscientes e documentados (validar com tokens/mídia reais).

As correções anteriores a este handoff (spinner global do admin, correção de salvar artigo, base URL OpenAI-compatível no LLM, aviso de token expirado do LinkedIn) estão descritas em `04` e `07`.
