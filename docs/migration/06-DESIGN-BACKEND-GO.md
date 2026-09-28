# 06 — Design do Backend em Go

> Proposta de arquitetura para o novo backend. Decisões podem ser refinadas na Fase 0.

## 1. Princípios de design

- **Um binário, dois modos:** `api` (servidor HTTP) e `worker` (jobs) — mesmo módulo, comandos separados.
- **PostgreSQL como fonte única de verdade** (pgx + sqlc). Nenhuma cópia de dados.
- **Compatibilidade de contrato** com o admin atual durante a transição (formato `{success, message}`, nomes de campos, ações).
- **Sem regra de negócio no handler:** handlers finos → serviços de domínio.
- **Config por ambiente**, sem segredos no repositório.

## 2. Layout de módulos (proposto)

```
washiviana-go/
├── cmd/
│   ├── api/main.go            # servidor HTTP
│   └── worker/main.go         # `worker publish-articles|publish-scheduled|video|metrics|radar`
├── internal/
│   ├── config/                # leitura de env + validação
│   ├── httpx/                 # router, middlewares (auth, csrf, logging, recover, cors p/ beacon)
│   ├── api/
│   │   ├── artigos/
│   │   ├── projetos/
│   │   ├── categorias/
│   │   ├── config/
│   │   ├── i18n/
│   │   ├── social/            # linkedin, meta, tiktok, youtube
│   │   ├── radar/
│   │   ├── videos/
│   │   └── metrics/           # beacon + coleta
│   ├── domain/                # entidades e serviços (Article, Project, Publication...)
│   ├── store/                 # sqlc gerado + repositórios (pgxpool)
│   ├── ai/                    # abstração de provedores LLM/imagem
│   ├── media/                 # uploads/, otimização, FFmpeg
│   ├── auth/                  # sessões/token, CSRF, senha (bcrypt)
│   └── queue/                 # claim atômico de jobs
├── db/
│   ├── migrations/            # SQL versionado (adotar/expandir as migrations atuais)
│   └── queries/               # .sql do sqlc
└── deploy/
    ├── washiviana-api.service
    └── washiviana-*-worker.{service,timer}
```

Tecnologias sugeridas: `net/http` (roteamento nativo 1.22) ou `chi`; `pgx/v5` + `sqlc`; `log/slog` (JSON); `golang-migrate` ou `goose` para migrations; `godotenv` só em dev.

## 3. Acesso ao banco

- **Não alterar o schema** na Fase 1/2, exceto adições compatíveis (ex.: tabela `sessions`).
- `sqlc` gera código tipado a partir das queries; repositórios testáveis.
- Manter **nomes de tabelas/colunas atuais** (ver `02-BANCO-DE-DADOS.md`).
- Migrations: criar `db/migrations-go/` numerado a partir de `1000_` para não colidir com o `migrations/` do PHP; ou adotar o diretório atual com tabela de controle (`schema_migrations`).

## 4. Autenticação e CSRF

- **Sessão server-side** em tabela `sessions` (`id`, `user_id`, `token_hash`, `csrf_token`, `expires_at`, `created_at`) — compatível com a recomendação C da Fase 2.
- Cookie **HttpOnly + Secure + SameSite=Lax**; nome de cookie próprio do novo admin (não reutilizar `washiviana_sess`).
- **CSRF:** token por sessão, enviado em header (`X-CSRF-Token`) no novo admin.
- Senha: manter **bcrypt** (compatível com `usuarios.senha` já existente) — assim o mesmo usuário funciona nos dois admins durante a transição.
- **Não** tentar ler `$_SESSION` do PHP.

## 5. Camada de IA (`internal/ai`)

- Interface:
  ```go
  type Provider interface {
      GenerateText(ctx context.Context, model, prompt string, maxTokens int) (string, error)
      GenerateImage(ctx context.Context, model, prompt string) ([]byte, string, error)
  }
  ```
- Implementação OpenAI-compatível com `baseURL` configurável (vLLM/LM Studio) — mesma semântica de `openai_base_url`.
- Para modelos híbridos (Qwen3) em vLLM: enviar `chat_template_kwargs.enable_thinking=false` quando `baseURL` customizada (replica o fix atual).
- Provedores: gemini, openai, deepseek, openrouter, anthropic, ollama (mesma matriz de chaves em `configuracoes`).
- Python sidecar (futuro): implementar a mesma interface chamando `http://127.0.0.1:8090`.

## 6. Publicação social

- Port de `api/linkedin.php` + fluxo de `api/artigos.php:1600-2015` (imagem → upload → UGC post).
- **Pré-checagem de token** (equivalente a `:1659-1673`) com mensagem amigável em 401.
- Meta (Facebook/Instagram) conforme `api/facebook.php`/`api/instagram.php`; TikTok/YouTube conforme config.
- Sempre registrar em `publicacoes_redes` e respeitar idempotência (`post_id`).

## 7. Media/uploads

- Reutilizar **`uploads/`** (mesma pasta, mesmas convenções de nome) para não quebrar referências do site PHP.
- Manter `assetVersion()`/cache-busting equivalente no admin novo.
- FFmpeg via `exec` (igual hoje), com detecção de binário e logs.

## 8. API do novo backend

- **REST** + JSON, versionada (`/api/v1/...`), documentada em OpenAPI.
- Para a transição, oferecer um **modo compatível** que aceita `action` e devolve o mesmo JSON atual (permite reaproveitar o admin PHP durante a Fase 1).
- Formato de erro sugerido (mantendo compatibilidade):
  ```json
  { "success": false, "message": "Texto legível", "code": "VALIDATION_ERROR", "details": {} }
  ```

## 9. Workers e agendamento

- `cmd/worker` com subcomandos; systemd timers por subcomando.
- **Claim atômico** de jobs (ver `05-PLANO-MIGRACAO.md` §5) para não duplicar publicação.
- Ordem de migração sugerida: `metrics` (baixo risco) → `video` → `publish-scheduled` → `publish-articles` → `radar`.
- Logs estruturados + `notifyJobFailure` equivalente (email/log).

## 10. Deploy

- `washiviana-api.service` (usuário `washi`, `:8081`), healthcheck `/health`.
- Nginx: `location ^~ /api2/ { proxy_pass http://127.0.0.1:8081/; }` (ou `/go-api/`).
- Novo admin: outro serviço (Next.js :3200) em `location ^~ /admin-next/`.
- Migrations rodam no deploy (idempotentes).

## 11. Mapeamento PHP → Go (handlers)

| API PHP | Pacote Go sugerido |
|---|---|
| `artigos.php` | `internal/api/artigos` (+ `domain/publication`) |
| `projetos.php` | `internal/api/projetos` |
| `categorias.php` | `internal/api/categorias` |
| `auth.php`, `csrf.php` | `internal/auth` |
| `configuracoes*.php` | `internal/api/config` |
| `gemini.php`, `llm-models.php`, `gemini-models.php` | `internal/ai` + `internal/api/ai` |
| `i18n_seo.php`, `i18n_site.php`, `i18n_status.php`, `get_i18n_seo.php` | `internal/api/i18n` |
| `linkedin.php`, `facebook.php`, `instagram.php`, `redes-sociais.php`, `oauth*` | `internal/api/social` + `internal/social` |
| `upload.php`, `videos.php`, `image_optimizer.php` | `internal/media` + `internal/api/media` |
| `radar.php`, `radar_lib.php` | `internal/api/radar` + `internal/radar` |
| `metrics.php`, `metrics_lib.php` | `internal/api/metrics` + `internal/metrics` |

## 12. Qualidade

- **Contract tests** PHP × Go (golden files) durante a transição.
- Testes unitários de domínio e integração com Postgres real (docker ou banco de staging).
- `golangci-lint`, `go vet`, `go test ./...` no CI.
- Migrations e queries revisadas por PR.

## 13. Riscos de design

- **Duplicar auth/CSRF** é inevitável na transição — isolar bem para remover o PHP depois.
- **Não** mover a lógica de publicação para Go sem os testes de idempotência (risco de post duplicado).
- **Não** alterar nomes de arquivos em `uploads/` nem colunas usadas pelo site público.

## 14. Implementação atual (Fases 0 e 1 — feitas)

- **Módulo:** `backend-go/` (`module washiviana/backend`, `go 1.22`).
- **Dependências:** `github.com/jackc/pgx/v5@v5.6.0` (pinado; versões ≥ v5.7 exigem Go ≥ 1.23). `sqlc` ainda não introduzido.
- **Pacotes:** `cmd/api`, `internal/config`, `internal/store`, `internal/httpapi`; harness de teste em `test/contract/`.
- **Endpoints (leitura):** `/health`, `/api/v1/categorias`, `/api/v1/artigos`, `/api/v1/artigos/{id}`, `/api/v1/projetos`, `/api/v1/projetos/{id}` (e `/slug/{slug}`), `/api/v1/i18n-seo`.
- **Segurança:** bind `127.0.0.1`; `/api/v1/*` aceita sessão (cookie `wv_go_sess`) ou `X-Internal-Token`.
- **Auth (Fase 2a):** `internal/auth` + tabela `sessions` (migration `016`), bcrypt, token em SHA-256, CSRF por sessão. Aplicada **só em staging**.
- **Paridade:** `phpTimeLayout` (timestamps), `TextCodec` para json/jsonb, `decodeGaleria`; **13/13 tests PASS** (7 paridade + 6 auth).
- **Operação:** binário em `/home/washi/washiviana-go/washiviana-api`, env em `/home/washi/washiviana-go/api.env` (600), banco `washiviana_staging`, porta 8081.
- **Nginx:** `location ^~ /backend-go/ { return 404; }` (bloqueio do código-fonte no webroot).
- **Escritas (Fase 2b) — concluída:** categorias, artigos, projetos e configurações, com CSRF obrigatório. `internal/textutil` replica `sanitize`/`generateSlug`/`gerarSlug`/`uniqid`. Adiados conscientemente: uploads de mídia, publicação automática no LinkedIn e remoção de arquivos do disco.
- **Worker (Fase 3d):** `cmd/worker` — `publish-scheduled` (claim atômico, 6/6), `metrics` (4/4; fetch por rede portado, não exercitado com tokens reais) e `video` (**só dry-run**; executor FFmpeg/provedores adiado). Cutover NÃO habilitado (timers seguem PHP).
- **IA:** providers remotos já portáveis direto em Go; sidecar Python **avaliado e adiado** (ver `08`). Costura: `internal/ai`.
- **Ainda não feito:** workers `metrics`/`video`, site público, `sqlc`, CI, e recursos de SEO/mídia/redes no admin novo.
