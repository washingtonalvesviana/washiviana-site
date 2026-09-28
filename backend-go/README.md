# backend-go

Serviço Go (API + workers) que **reutiliza o PostgreSQL existente**. Início da migração *strangler* (ver `docs/migration/`).

## Requisitos

- Go **1.22** (toolchain local). `pgx` fixado em **v5.6.0** por compatibilidade com Go 1.22 (versões ≥ v5.7 exigem Go ≥ 1.23).
- Acesso ao PostgreSQL (mesmo banco do PHP).

## Layout

```
cmd/api/            servidor HTTP
internal/config/    configuração por variáveis de ambiente
internal/store/     acesso ao PostgreSQL (pgx/pgxpool)
internal/httpapi/   rotas, middlewares e handlers
```

## Configuração (variáveis de ambiente)

| Variável | Padrão | Descrição |
|---|---|---|
| `HOST` | `127.0.0.1` | Bind (mantenha localhost até haver auth) |
| `PORT` | `8081` | Porta HTTP |
| `APP_ENV` | `development` | Ambiente |
| `APP_VERSION` | `0.0.1` | Versão reportada (sobreposta por `-ldflags`) |
| `DATABASE_URL` | — | DSN pgx (tem prioridade) |
| `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS` | — | Usadas se `DATABASE_URL` ausente |
| `DB_MAX_CONNS` | `5` | Máx. de conexões no pool |
| `INTERNAL_TOKEN` | — | Se definido, aceita header `X-Internal-Token` em `/api/v1/*` (serviço-a-serviço/testes) |
| `COOKIE_SECURE` | `0` | `1` para enviar o cookie de sessão apenas em HTTPS |
| `SESSION_TTL_HOURS` | `168` | Validade da sessão (7 dias) |

> Em produção/staging use um `EnvironmentFile` do systemd com permissão `600`, **fora do webroot**.

## Executar

```bash
export GOTOOLCHAIN=local
go mod tidy
go vet ./...
go build -ldflags "-X main.version=0.1.0" -o /home/washi/washiviana-go/washiviana-api ./cmd/api

set -a; . /home/washi/washiviana-go/api.env; set +a
/home/washi/washiviana-go/washiviana-api
```

## Endpoints

| Método | Rota | Espelha (PHP) |
|---|---|---|
| GET | `/health` | — (status + ping) |
| POST | `/api/v1/auth/login` | `auth.php` action=login |
| POST | `/api/v1/auth/logout` | `auth.php` action=logout |
| GET | `/api/v1/auth/me` | `auth.php` action=check |
| GET | `/api/v1/categorias?target=projetos\|artigos&ativas=1` | `categorias.php` action=listar |
| POST | `/api/v1/categorias` | `categorias.php` action=criar |
| PUT | `/api/v1/categorias/{id}` | `categorias.php` action=atualizar |
| DELETE | `/api/v1/categorias/{id}?target=` | `categorias.php` action=deletar |
| POST | `/api/v1/categorias/ordenar` | `categorias.php` action=ordenar |
| POST | `/api/v1/categorias/{id}/toggle?target=` | `categorias.php` action=toggle_status |
| GET | `/api/v1/artigos?status=&categoria=` | `artigos.php` action=list |
| GET | `/api/v1/artigos/{id}` | `artigos.php` action=get |
| POST | `/api/v1/artigos` | `artigos.php` action=create |
| PUT | `/api/v1/artigos/{id}` | `artigos.php` action=update |
| DELETE | `/api/v1/artigos/{id}` | `artigos.php` action=delete |
| GET | `/api/v1/projetos?categoria_id=&status=&destaque=&limit=&offset=` | `projetos.php` action=listar |
| GET | `/api/v1/projetos/{id}` | `projetos.php` action=buscar (id) |
| GET | `/api/v1/projetos/slug/{slug}` | `projetos.php` action=buscar (slug) |
| POST | `/api/v1/projetos` | `projetos.php` action=criar |
| PUT | `/api/v1/projetos/{id}` | `projetos.php` action=atualizar |
| DELETE | `/api/v1/projetos/{id}` | `projetos.php` action=deletar |
| POST | `/api/v1/projetos/ordenar` | `projetos.php` action=ordenar |
| POST | `/api/v1/projetos/{id}/toggle-status` | `projetos.php` action=toggle_status |
| POST | `/api/v1/projetos/{id}/toggle-destaque` | `projetos.php` action=toggle_destaque |
| POST | `/api/v1/projetos/{id}/linkedin-post` | `projetos.php` action=save_linkedin_post |
| GET | `/api/v1/linkedin-posts/{id}` | `projetos.php` action=get_linkedin_post |
| POST | `/api/v1/projetos/{id}/media/delete` | `projetos.php` action=delete_media |
| GET | `/api/v1/i18n-seo?entity=artigo\|projeto&id=&lang=` | `get_i18n_seo.php` |
| GET | `/api/v1/configuracoes` | (listagem de configs permitidas) |
| POST | `/api/v1/configuracoes` | `configuracoes.php` (bulk save) |
| POST | `/api/v1/configuracoes/item` | `configuracoes-save.php` (single) |

> Rotas `/api/v1/*` aceitam **sessão** (cookie) ou `X-Internal-Token`. `/auth/me` exige sessão.

## Autenticação

- Sessões **server-side** na tabela `sessions` (migration `016_sessions.sql`).
- Token cru só viaja no cookie `wv_go_sess` (HttpOnly, SameSite=Lax); o banco guarda apenas o **SHA-256**.
- Senha verificada com **bcrypt** (compatível com o hash do PHP).
- CSRF por sessão (a exigir nas escritas): header `X-CSRF-Token` = `csrf_token` retornado no login/`me`.
- **Segurança:** `016_sessions.sql` foi aplicada **só no staging**. Aplicar em produção apenas no cutover da Fase 2(b).

## Paridade com o PHP

- **7/7 contract tests PASS** (`test/contract/contract_test.py`).
- Tipos alinhados: `int`/`bool` nativos; `COUNT(*)` como número.
- **Timestamps** no formato do PDO/pgsql (`YYYY-MM-DD HH:MM:SS.ffffff`) via `phpTimeLayout`.
- **json/jsonb** entregues como **texto cru** (pgx `TextCodec` registrado em `AfterConnect`), igual ao PDO — evita divergência de objeto vs string.
- `imagens_galeria` (texto) é decodificada para array, como o `json_decode` do PHP.
- Ordem de chaves no JSON pode variar (irrelevante ao comparar JSON parseado).

## Worker (Fase 3d)

```bash
go -C backend-go build -ldflags "-X main.version=0.9.0" -o /home/washi/washiviana-go/washiviana-worker ./cmd/worker
set -a; . /home/washi/washiviana-go/api.env; set +a
/home/washi/washiviana-go/washiviana-worker publish-scheduled --limit 10
```

- `publish-scheduled [--limit N]`: reclama variantes agendadas via **claim atômico** (`FOR UPDATE SKIP LOCKED` + mudança de status) e as marca como `pronto_para_publicacao` (igual ao worker PHP; sem chamadas externas). Dois workers concorrentes **não** processam o mesmo item (`worker_test.py`).
- `metrics [--rede=] [--limit=] [--dry-run]`: coleta métricas (LinkedIn/Instagram/Facebook) e grava snapshots. `--dry-run` só lista elegíveis. Está o proxy de fetch portado de `metrics_lib.php` (não validado com tokens reais). `metrics_worker_test.py`.
- `video [--limit=] [--dry-run]`: **somente dry-run** (lista `video_jobs` pendentes). O executor (FFmpeg + provedores) ainda **não** foi portado — execução real é bloqueada de propósito. `video_worker_test.py`.
- Não habilitar em produção junto com o worker PHP (ver `07` §13).

## Testes de contrato

```bash
# requer o serviço rodando (:8081) e lê o token de /home/washi/washiviana-go/api.env
python3 backend-go/test/contract/contract_test.py
```

- `contract_test.py` — **13/13 PASS**: 7 de paridade + 6 de auth.
  - Paridade pública (`categorias`, `projetos`): Go × PHP via HTTP.
  - Paridade autenticada (`artigos`, `i18n-seo`): `test/contract/php_ref.php` (sessão fake, somente leitura) contra o banco de produção.
  - Auth smoke: usuário de teste do **staging** (`contract-test@staging.local`); sobrescreva com `GO_TEST_EMAIL`/`GO_TEST_PASSWORD`.
- `write_test.py` — **11/11 PASS**: CRUD de categorias no **staging** com cleanup garantido e paridade de slug (Go == PHP).
- `write_artigos_test.py` — **12/12 PASS**: CRUD de artigos no staging (validações, slug `gerarSlug`, sufixo de duplicado, `data_publicacao`, `redes_destino` jsonb) com cleanup.
- `write_projetos_test.py` — **16/16 PASS**: CRUD de projetos, toggles, ordenar, post LinkedIn e delete de mídia, com cleanup.
- `write_config_test.py` — **8/8 PASS**: list/save (item e bulk) de configurações, com **restauração dos valores originais**.
- `worker_test.py` — **6/6 PASS**: claim atômico de variantes agendadas com 2 workers concorrentes (sem duplicação).

> Escritas adiadas conscientemente: upload de mídia, publicação automática no LinkedIn e remoção de arquivos/posts no delete (artigos e projetos). Fase 2(b) concluída.

> Escritas só rodam contra o staging (o serviço aponta para `washiviana_staging`). Nunca rode testes de escrita apontando para produção.

## Segurança

- O diretório `backend-go/` fica **bloqueado no nginx** (`location ^~ /backend-go/ { return 404; }`) — sem isso, arquivos `.go`/`go.mod` ficam expostos (o `try_files` do site serve qualquer arquivo existente).
- Nunca commitar `api.env`/segredos (ver `.gitignore`).
- A API é exposta em `/go-api/` (proxy nginx) e **exige sessão**; o header `X-Internal-Token` só é aceito de **loopback** (serviço-a-serviço/testes).
- Em produção, definir `COOKIE_SECURE=1` (site HTTPS-only).

## Próximos passos

- Fase 1: mais endpoints de leitura + contract tests PHP×Go.
- Fase 2: escritas, auth própria (sessão/token) e admin novo.
- Fase 3: workers (com *claim* atômico para evitar publicação duplicada).
