# STATUS — Execução da Migração

> Log vivo de progresso. **Atualizar a cada etapa concluída.**
> Última atualização: 2026-09-28 — **Fases 2 e 3 entregues** (com deferrals documentados).

## Execução complementar (retomada; e-mail ignorado)

- [x] **(1) Migrations em PRODUÇÃO**: `016_sessions.sql` e `017_social_variants_status_width.sql` aplicadas (backup antes; `sessions` criada, `status` → 30). Site, admin e `/go-api` em 200.
- [x] **(2) Upload de mídia no Go**: `POST /api/v1/upload` (multipart) e `/api/v1/upload/delete`, mesmas regras de extensão/tamanho do PHP; exposto no `admin-next` (imagem principal de conteúdos e projetos). `upload_test.py` **7/7 PASS**.
  - Divergência consciente: o Go preserva a extensão original e **não** otimiza imagens (o PHP força `.jpg` após otimização GD).
- [x] **(2, cont.) IA de texto/artigo no Go**: `internal/ai` (multi-provedor: openai-compatível/vLLM, deepseek, openrouter, anthropic, ollama, gemini com fallback por cota) e endpoints `POST /api/v1/ai/text` e `/api/v1/ai/article`. Botão **"Gerar conteúdo com IA"** no admin-next (preenche título/resumo/conteúdo). `ai_test.py` **5/5 PASS** usando mock local (sem chamadas externas).
  - Pendente: geração de **imagem** (`generate_image`) e `generate_social_agent`.
- [x] **(2, cont.) SEO/traduções no Go**: `internal/i18n` (prompt + `GenerateJSON` multi-provedor) e endpoints `POST /api/v1/i18n/generate` (artigo/projeto, pt/en/es, upsert com slug único) e `POST /api/v1/i18n/status`. Botão **"Gerar traduções/SEO"** no admin-next (edição de conteúdo e projeto). `i18n_test.py` **10/10 PASS** (com mock, sem chamadas externas; cleanup por cascade).
- [x] **(2, cont.) Geração de imagem no Go**: `POST /api/v1/ai/image` (openai-compatível `images/generations` e gemini `generateContent` inlineData), salva em `uploads/`. Botão **"Gerar imagem"** no admin-next (conteúdo e projeto). `image_test.py` **5/5 PASS** (mock, sem chamadas externas). Otimização de imagem não portada (salva original).
- [x] **(2, cont.) `generate_social_agent` e config de redes sociais**: `POST /api/v1/ai/social-agent` (legenda por rede; multi-provedor — divergência consciente do PHP que força Gemini) e `GET/POST /api/v1/redes-sociais` (GET **sem expor segredos**; POST faz upsert e não sobrescreve segredos vazios). Tela **Redes sociais** (status) no admin-next. `social_test.py` **8/8 PASS**.
- [ ] (2, cont.) **Publicação** em redes (LinkedIn/Meta/TikTok/YouTube) — **bloqueada**: exige tokens OAuth válidos (o token do LinkedIn está **expirado** em produção) e tem efeito externo real; será implementada de forma gated/dry-run quando houver credencial válida.
- [x] **(3) Cutover de produção (parcial, seguro)**:
  - **API Go de produção** em `127.0.0.1:8082` (systemd `washiviana-go-api.service`, `api-prod.env`, banco de produção). `/go-api/` do nginx agora aponta para `:8082`. Admin novo passa a operar sobre **dados de produção** (admin PHP permanece como fallback).
  - Staging mantido em `:8081` (usado pelos testes) — separado.
  - **Worker `publish-scheduled` migrado para Go** (systemd `washiviana-go-publish-scheduled.timer`, 1 min), com o timer PHP `washiviana-social-publish.timer` **desabilitado antes** (sem duplicação). Log mostra execução OK.
  - `metrics` e `video` **migrados para Go** (timers PHP desabilitados); resta em PHP apenas `articles-publish` (publicação — depende de tokens).
- [x] **(4) `COOKIE_SECURE=1`** definido no `api-prod.env` (produção HTTPS). Testes locais em HTTP seguem com a instância de staging (`COOKIE_SECURE=0`).
- [ ] Publicação real em redes (LinkedIn/Meta) — depende de tokens OAuth válidos.

## Programa "eliminar o PHP" (em andamento)

- [x] **(a) Worker `metrics` cortado para Go**: systemd `washiviana-go-metrics.{service,timer}` (6h) ativo; `washiviana-metrics.timer` (PHP) **desabilitado antes** (sem duplicação). Validado contra produção: chamou a API real do LinkedIn e tratou "token expirado" com graça (`fail`, sem crash).
- [x] (a) Worker **`video` em Go (FFmpeg) e CORTADO em produção**: `washiviana-go-video.{service,timer}` (1 min) ativo; `washiviana-video-worker.timer` (PHP) **desabilitado antes**. `video_worker_test.py` **5/5 PASS** (gera vídeo real a partir de imagem, grava `output_file`/`video_file` e limpa). Provedor de vídeo por IA fica gated (Go usa FFmpeg).
- [x] (b, parcial) **Vídeos (fila)** → Go: `POST /api/v1/videos/enqueue` e `GET /api/v1/videos/jobs/{id}`. Divergência: sem auto-geração de imagens via script PHP (exige gerar imagens antes). `videos_beacon_test.py`.
- [x] (b, parcial) **Beacon de métricas** → Go: `POST /api/v1/metrics/beacon` (same-origin + rate limit 60s por ip+path). `videos_beacon_test.py` **10/10 PASS**.
- [x] (b, parcial) **Auth completo** → Go: `POST /api/v1/auth/change-password` (bcrypt, validações, invalida as outras sessões do usuário) e **`GET /api/v1/ai/gemini-models`** (lista modelos v1+v1beta, separa texto/imagem). `auth_models_test.py` **7/7 PASS** (senha alterada e RESTAURADA; chave Gemini restaurada).
- [x] (b) **i18n do site** → Go (completo): `POST /api/v1/i18n/site` traduz **UI strings** (chunks), **configs** e **categorias** (`categorias_i18n`/`categorias_artigos_i18n`) via LLM, com upsert. `i18nsite_test.py` **7/7 PASS** (mock; chaves afetadas restauradas).
  - Correção: conversores passam a tratar `int32` (int4) do pgx — afetava IDs de categorias.
- [x] (b, parcial) **Radar (CRUD/listas)** → Go: temas (list/save/delete), fontes (list/save/delete), vínculo tema↔fonte, itens (list/delete por ids ou url_like), ideias (list/discard/sources). `radar_test.py` **16/16 PASS**.
- [x] (b, parcial) **Radar (IA/clustering)** → Go: `POST /api/v1/radar/ideas/generate` (prompt + `GenerateJSON` multi-provedor; grava em `radar_ideas`) e `POST /api/v1/radar/hype` (clustering por Levenshtein + métricas velocity/hype_score/is_trending em `radar_items.raw`). `radar_ai_test.py` **4/4 PASS** (mock; sem chamadas externas).
- [x] (b, parcial) **Radar `idea_to_draft`** → Go: `POST /api/v1/radar/ideas/{id}/to-draft` (modos `ai` e `simple`; cria artigo rascunho em `artigos`, marca a ideia como `virou_artigo`, slug único). `radar_draft_test.py` **7/7 PASS** (mock + cleanup).
- [x] (b, parcial) **Radar `collect_run`** → Go: `POST /api/v1/radar/collect` (RSS 2.0/Atom, `scrape` de metadados e `api` com `*_path`; SSRF guard; dedupe por `url_norm` sem tracking; registra `radar_runs`). `radar_collect_test.py` **7/7 PASS** (feed RSS local).
  - Nota: bypass de loopback para testes via `ALLOW_LOOPBACK_FETCH=1` (apenas no staging; produção `false`).
  - Adiado (documentado): backup CSV no delete de itens.
- [ ] (b) Demais endpoints → Go: linkedin/facebook/instagram, oauth callbacks (`linkedin-callback.php`, `oauth_callback.php`), categorias i18n e publicação (gated).
- [x] (c) **REVERTIDO — NÃO migrar o frontend público**: o cutover do site público para o Go foi **desfeito** (nginx restaurado do backup `nginx-washiviana.com.bak.20260928T220049`); o site público voltou a ser servido pelo PHP original (com formatação/imagens). O código Go do site permanece em `backend-go/internal/site` **sem roteamento**. **Escopo confirmado: só backend + admin; frontend público NÃO é para mexer.** (Notas abaixo ficam como histórico do protótipo, não aplicadas.)
  - `GET /site/{lang}/` (home), `/conteudos`, `/projetos`, `/sobre`, `/artigo/{slug}`, `/projeto/{slug}` (detalhe usa `*_i18n` por idioma; 404 quando inexistente).
  - `GET /site/{lang}/automacao-ia`, `/tech-insights`, `/design-experiencias` (landings; filtros por categoria/tag, com i18n).
  - `GET /sitemap.xml` (URLs finais + hreflang) e `GET /robots.txt`.
  - `site_test.py` **32/32 PASS**; validado via `https://washiviana.com` (todas as páginas 200), `/admin`, `/admin-next`, `/go-api`, `/assets` e `/api` intactos.
  - **Não** roteado no nginx público (site PHP continua no ar). Próximo: refino visual e cutover **após sua validação de design/SEO**.
- [ ] (d) Aposentar **admin PHP** após paridade (o site público **permanece em PHP** — não migrar o frontend).
  - Rollback do site: remover o bloco "Site público em Go" e a regra "PHP público aposentado" do nginx (backup em `/home/washi/backups/nginx-*.bak.*`).
- [x] (e, parcial) **`generate_images_multi`** → Go: `POST /api/v1/ai/images-multi` gera a imagem base e cria recortes **1:1** e **9:16** (`ai_1x1_*.jpg`/`ai_9x16_*.jpg`), removendo a base. `images_multi_test.py` **6/6 PASS**.
  - **Otimizador portado** (`internal/ai/optimize.go`): resize bilinear sem ampliar + recompressão JPEG até ≤500KB (qualidade 85→60), aplicado aos recortes (1200x1200 e 1080x1920), como no PHP.
- [~] (f) **CI** — workflow `.github/workflows/go-backend.yml` **criado e commitado** (`go vet`/`go build`/`go test`, `node --check`, `compileall`, `openapi.yaml`). Checks validados **localmente**.
  - ⚠️ **Bloqueado na execução**: o GitHub Actions não roda — **"account is locked due to a billing issue"** (billing do usuário). O workflow em si não tem erro.
  - Extra: **backup CSV** no delete de itens do radar (portado; `radar_test.py` cobre).

> Workers em Go: `go-publish-scheduled`, `go-metrics`, `go-video`. Ainda em PHP: apenas `washiviana-articles-publish` (publicação no LinkedIn — depende de tokens).

## ⚠️ Bloqueio do e-mail de conclusão

- A notificação para `washingtonalvesviana@gmail.com` **não pôde ser enviada**: o servidor **não tem MTA** (`/usr/sbin/sendmail` ausente; sem postfix/msmtp; `mail()` e `sendNotificationEmail()` retornam falha com `"/usr/sbin/sendmail: not found"`).
- Conteúdo do e-mail salvo em **`/home/washi/backups/migration-complete-email.txt`** para envio manual.
- Para habilitar o envio automático é preciso configurar um MTA/relay SMTP (credenciais) — decisão do usuário.

## Legenda

- `[ ]` pendente · `[~]` em andamento · `[x]` concluído

## Fase 0 — Fundação ✅ CONCLUÍDA

- [x] **0.1 Backup + restore validado em staging**
  - Backup: `/home/washi/backups/migration_backup_20260928T183207/` (`full_dump.sql` ~1.2 MB + tabelas críticas).
  - Restore validado em **`washiviana_staging`**: 29 tabelas, 16 artigos, 344 ui_strings, sem erros.
  - Dumps ficam **fora** do webroot.
- [x] **0.2 Congelar contrato**
  - `03-CONTRATO-API.md` + `openapi.yaml` como baseline.
  - Delta de paridade conhecido documentado (timestamp).
- [x] **0.3 Esqueleto do serviço Go**
  - Código em **`backend-go/`** (module `washiviana/backend`, Go 1.22, pgx v5.6.0).
  - `internal/config` (env), `internal/store` (pgxpool), `internal/httpapi` (`/health`, `/api/v1/categorias`).
  - `go vet` e `go build` limpos.
- [x] **0.4 Build + execução em `:8081` + smoke**
  - Binário: `/home/washi/washiviana-go/washiviana-api`; env: `/home/washi/washiviana-go/api.env` (600).
  - Aponta para o banco **`washiviana_staging`**.
  - `/health` → `{"status":"ok","db":"ok",...}`.
  - **Paridade 100%** com o PHP em `categorias listar` (targets `projetos` e `artigos`, com `ativas=1`).
- [x] **0.5 Proteção do diretório + site no ar**
  - Descoberto: `backend-go/go.mod` estava **publicamente acessível** (HTTP 200).
  - Corrigido: `location ^~ /backend-go/ { return 404; }` no nginx (backup em `/home/washi/backups/nginx-washiviana.com.bak.*`).
  - Verificado: `/pt/`, `/en/`, `/es/`, `/pt/conteudos`, `/pt/projetos`, `/admin/` → 200; `/backend-go/*` → 404.
- [x] **0.6 Documentação atualizada** (este arquivo + `01`, `03`, `06`, `07`, `backend-go/README.md`)

### Artefatos

| Artefato | Local |
|---|---|
| Código Go | `backend-go/` |
| Binário + env | `/home/washi/washiviana-go/` (`washiviana-api`, `api.env` 600) |
| Backup do banco | `/home/washi/backups/migration_backup_20260928T183207/` |
| Backup nginx | `/home/washi/backups/nginx-washiviana.com.bak.20260928T183642` |
| Banco de staging | `washiviana_staging` (PostgreSQL local) |

## Fase 1 — Leitura em paralelo ✅ CONCLUÍDA (sem cutover)

- [x] Endpoints read-only portados e **paritários**:
  - `GET /api/v1/categorias` (target projetos|artigos)
  - `GET /api/v1/artigos` (filtros `status`, `categoria`)
  - `GET /api/v1/artigos/{id}`
  - `GET /api/v1/projetos` (filtros `categoria_id`, `status`, `destaque`, `limit`, `offset`)
  - `GET /api/v1/projetos/{id}` e `/api/v1/projetos/slug/{slug}`
  - `GET /api/v1/i18n-seo?entity=&id=&lang=`
- [x] **Contract tests PHP×Go automatizados** — `backend-go/test/contract/contract_test.py`: **7/7 PASS**.
  - Públicos (categorias, projetos) comparados via HTTP; autenticados (artigos, i18n-seo) via referência CLI `php_ref.php`.
- [x] **Hardening**: bind em `127.0.0.1` + header `X-Internal-Token` em `/api/v1/*`.
- [x] Fix de paridade de **jsonb**: pgx configurado para entregar `json`/`jsonb` como texto (igual ao PDO). Ver `03` §5.
- [ ] Rota pública no nginx (`/go-api/`): **adiada por segurança** — só após auth (Fase 2). O serviço roda em localhost.

### Evidência (execução)

```
PASS  categorias listar target=projetos
PASS  categorias listar target=artigos
PASS  projetos listar
PASS  projetos buscar id=17
PASS  artigos listar
PASS  artigos buscar id=34
PASS  i18n-seo artigo id=2 lang=en
RESULTADO: TUDO OK
```

## Fase 2 — Escrita + Auth + Admin novo

### (a) Autenticação própria no Go ✅ CONCLUÍDA
- [x] Migration aditiva **`016_sessions.sql`** (tabela `sessions`, sem FK destrutiva).
  - Aplicada **somente em `washiviana_staging`**. Produção **não alterada** (verificado: `to_regclass('sessions')` = falso).
- [x] Endpoints: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`.
- [x] Sessão server-side: token aleatório (32 bytes) guardado só como **SHA-256**; cookie `HttpOnly`/`SameSite=Lax` (`Secure` por `COOKIE_SECURE=1`); CSRF por sessão (será exigido nas escritas).
- [x] Senha validada com **bcrypt** (`golang.org/x/crypto/bcrypt`) — compatível com o hash do PHP.
- [x] Middleware: `/api/v1/*` aceita **sessão** ou **`X-Internal-Token`** (serviço-a-serviço/testes); `/auth/me` exige sessão.
- [x] Usuário de teste **no staging** (`contract-test@staging.local`) — não existe em produção.
- [x] Validação: contract tests agora com **13/13 PASS** (7 de paridade + 6 de auth).

### (b) Escritas ✅ CONCLUÍDA

- [x] **Categorias** (CRUD + ordenar + toggle) portadas com CSRF e paridade de slug.
  - Endpoints: `POST /api/v1/categorias`, `PUT /api/v1/categorias/{id}`, `DELETE /api/v1/categorias/{id}`, `POST /api/v1/categorias/ordenar`, `POST /api/v1/categorias/{id}/toggle`.
  - `internal/textutil` replica `sanitize()` e `generateSlug()` do PHP (validação: slug Go == slug PHP).
  - Teste: `backend-go/test/contract/write_test.py` — **11/11 PASS** (create, slug==PHP, CSRF 403, duplicado 400, listar, update, toggle, ordenar, delete bloqueado com itens, delete, remoção).
  - Staging exercitado e **limpo** (categorias 6→6); produção intacta.
- [x] **Artigos** (create/update/delete) portadas.
  - Endpoints: `POST /api/v1/artigos`, `PUT /api/v1/artigos/{id}`, `DELETE /api/v1/artigos/{id}`.
  - `textutil.SlugifyArticle` replica `gerarSlug()` (inclui sufixo `-<unix>` em slug duplicado); `data_publicacao` setada ao publicar; validações "Título é obrigatório"/"Conteúdo é obrigatório".
  - Teste: `backend-go/test/contract/write_artigos_test.py` — **12/12 PASS**. Staging 16→16; produção intacta.
  - **Adiado (documentado):** upload de mídia, publicação automática no LinkedIn, remoção de arquivos e exclusão de posts em redes no delete.
- [x] **Projetos** (create/update/delete, ordenar, toggle-status, toggle-destaque, post LinkedIn, delete de mídia) portadas.
  - Endpoints: `POST /api/v1/projetos`, `POST /api/v1/projetos/ordenar`, `PUT/DELETE /api/v1/projetos/{id}`, `POST /api/v1/projetos/{id}/toggle-status`, `.../toggle-destaque`, `.../linkedin-post`, `.../media/delete`, `GET /api/v1/linkedin-posts/{id}`.
  - Slug regenerado ao mudar o título; sufixo `-<uniqid>` em duplicado; galeria (texto JSON) preservada.
  - Teste: `backend-go/test/contract/write_projetos_test.py` — **16/16 PASS**. Staging 16→16; produção intacta.
  - **Adiado (documentado):** upload de mídia e remoção de arquivos do disco.
- [x] **Configurações** (list + save em massa + save item) portadas.
  - Endpoints: `GET /api/v1/configuracoes`, `POST /api/v1/configuracoes` (bulk), `POST /api/v1/configuracoes/item`.
  - Apenas chaves permitidas; compatibilidade provider Gemini → modelos legados; CSRF obrigatório.
  - Teste: `backend-go/test/contract/write_config_test.py` — **8/8 PASS**, com **restauração dos valores originais** (rollback).

### (c) Admin novo responsivo — em andamento

- [x] **Admin novo agora em `/admin/`** (nginx serve a SPA `admin-next/` em `/admin/`); o admin PHP antigo continua em **`/admin-legacy/`** (fallback para recursos ainda não portados, ex.: publicação). `/admin-next/` segue acessível.
  - Rollback: remover o bloco `/admin/` do nginx (backup `nginx-*.bak.*`) — o PHP volta em `/admin/`.

- [x] **Fundação entregue** (SPA estática responsiva, sem build): `admin-next/` (`index.html`, `app.js`, `styles.css`).
  - Login (sessão + CSRF), shell responsivo (sidebar com hambúrguer no mobile), Dashboard (contagens), **Conteúdos** (list/create/edit/delete) e **Categorias** (list/create/delete).
  - Consome a API Go via `/go-api/` (mesma origem; cookie de sessão httpOnly).
- [x] **Infra**: nginx com `location ^~ /go-api/` (proxy → 127.0.0.1:8081) e `location ^~ /admin-next/` (SPA). Backup do nginx em `/home/washi/backups/`.
- [x] **Segurança**: bypass por `X-Internal-Token` agora vale **somente para loopback** — verificado que de fora retorna 401.
- [x] **Validação**: login/sessão/reads/logout pela rota pública HTTPS OK; site e admin PHP seguem 200.
- [x] **Projetos** (list/create/edit/delete + toggles) e **Configurações** (campos do site, save em massa) adicionados ao novo admin.
- [x] **Segurança**: `GET /api/v1/configuracoes` **não devolve chaves secretas** (`*api_key`, `*secret*`, `*token*`, `sentry_dsn`) — verificado.
- [ ] SEO/Traduções, mídia e redes sociais no novo admin
- [ ] **Antes do cutover**: definir `COOKIE_SECURE=1` no `api.env` (produção é HTTPS-only)

> Nota de segurança: mantido bind em `127.0.0.1`; nenhuma rota pública no nginx ainda.

## Fase 3 — Workers, IA e site público

### (d) Workers em Go — em andamento
- [x] **Claim atômico** implementado: `internal/store/worker.go` (`UPDATE ... WHERE id IN (SELECT ... FOR UPDATE SKIP LOCKED) RETURNING`).
- [x] **Worker `publish-scheduled`** portado (`cmd/worker`) — marca variantes agendadas como `pronto_para_publicacao` (sem chamadas externas, igual ao PHP).
- [x] **Bug latente corrigido por migration aditiva** `017_social_variants_status_width.sql`: `status` era `varchar(20)` e `pronto_para_publicacao` (22) não cabia — também afetava o worker PHP. Aplicada **só no staging** (prod segue 20; aplicar no cutover).
- [x] **Teste de concorrência**: `worker_test.py` — **6/6 PASS** (2 workers concorrentes reclamam 20 variantes sem duplicação; re-execução idempotente).
- [x] **Worker `metrics`** portado (`internal/metrics` + seleção + snapshots; `--rede=`, `--limit=`, `--dry-run`).
  - `metrics_worker_test.py` — **4/4 PASS** (dry-run lista elegíveis; rede sem coletor falha graciosamente **sem** chamadas externas e sem snapshot).
  - Fetchers LinkedIn/Instagram/Facebook portados de `metrics_lib.php` (não exercitados por exigirem tokens válidos).
- [x] **Worker `video` (Go/FFmpeg)**: `internal/video` porta o pipeline (segmentos com fade, concat, mix de música); cutover do timer feito. `video_worker_test.py` **5/5 PASS**.
  - **Executor de vídeo (FFmpeg + provedores externos) adiado**: portar 615 linhas de pipeline externo sem poder validar seria arriscado.
- [ ] **Cutover de workers NÃO habilitado**: timers de produção continuam PHP. Procedimento seguro em `07` §13 (parar timer PHP antes de habilitar o Go).

### (e) Python sidecar (IA) ✅ AVALIADO
- Decisão: **não introduzir Python agora** — IA atual é HTTP para provedores; Go cobre bem.
- Gatilhos para adotar (embeddings/RAG, inferência local, visão, áudio) e interface proposta documentados em `08-PYTHON-SIDECAR-AVALIACAO.md`.
- PoC validada localmente (`ai-sidecar/app.py`, bind `127.0.0.1:8090`, `/health` + `/v1/embed`); **não implantada**.

### (f) Site público (opcional) — pendente

## Decisões

| Data | Decisão | Motivo |
|---|---|---|
| 2026-09-28 | Backend em **Go**; Python só para IA pesada, depois | Performance, binário único, workers; IA atual é HTTP |
| 2026-09-28 | Go **1.22** + pgx **v5.6.0** | Versões de pgx ≥ v5.7 exigem Go ≥ 1.23 |
| 2026-09-28 | Timestamps normalizados no formato do PHP | Paridade no modo *strangler* |
| 2026-09-28 | Backup/dumps **fora do webroot** | Evitar exposição |
| 2026-09-28 | Migração *strangler* via nginx | Zero downtime |

## Riscos abertos

- Publicação duplicada na transição de workers (`05` §5).
- Auth: sessões PHP não serão compartilhadas com o Go (`05` §4).
- Migração de todo o restante do admin ainda pendente (Fase 2).
