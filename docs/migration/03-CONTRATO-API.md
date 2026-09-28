# 03 — Contrato da API (atual)

> Fonte da verdade do comportamento atual. O backend Go deve **preservar este contrato** durante a transição (*strangler*), ou o admin PHP quebra.
> Esqueleto OpenAPI em [openapi.yaml](openapi.yaml).

## 1. Convenções

- **URL base:** `https://washiviana.com/api/<arquivo>.php`
- **Transporte:** `POST` com `multipart/form-data` ou `application/x-www-form-urlencoded` (parâmetro `action`); leituras pontuais via `GET`.
- **Resposta:** JSON via `jsonResponse()` (`api/config.php:435`): `{ "success": bool, "message": string, ... }`.
- **Códigos:** `200` ok · `400` validação · `401` não autenticado · `403` CSRF/sessão · `404` não encontrado · `405` método · `500` erro.
- **Auth:** cookie de sessão PHP (`washiviana_sess`) + `user_id` em `$_SESSION`. Endpoints admin chamam `requireAuth()`/`isAuthenticated()`.
- **CSRF:** escritas validam `csrf_token` (`validateCsrfToken`, `api/config.php:413`).

## 2. Endpoints HTTP

### 2.1 Conteúdo — `artigos.php` (auth + CSRF em escritas)

| Action | Params principais | Retorno |
|---|---|---|
| `create` | `titulo`, `conteudo`, `slug?`, `resumo`, `categoria_id`, `autor`, `tipo_midia`, `destaque`, `prompt_texto`, `prompt_imagem`, `imagem_1x1`, `imagem_9x16`, `status_publicacao`, `data_agendamento`, `redes_destino`, mídia (upload) | `{success, artigo_id, slug, ...}` (`api/artigos.php:130`) |
| `update` | `id` + campos acima | `{success, message}` (`:294`) |
| `delete` | `id` | `{success}` (`:465`) |
| `get` | `id` | `{success, artigo}` (`:1138`) |
| `list` | `categoria?`, paginação | `{success, artigos}` (`:1164`) |
| `get_publicacoes` | `artigo_id` | publicações por rede (`:543`) |
| `list_social_variants` | `artigo_id` | variantes sociais (`:608`) |
| `save_social_variant` | `artigo_id`, `rede`, `caption`, `hashtags`, mídias | `{success, variant}` (`:623`) |
| `delete_social_variant` | `id` | `{success}` (`:678`) |
| `publish_social_variant` | `id` | publica na rede (`:716`) |
| `delete_linkedin_post` | `post_urn`/`id` | `{success}` (`:950`) |
| `get_linkedin_status` | `id` | status do post (`:1001`) |
| `publish_now_linkedin` | `artigo_id` | publica agora (`:1032`) |

### 2.2 Projetos — `projetos.php` (auth + CSRF em escritas)

Ações com aliases pt/en: `listar|list`, `buscar|get`, `criar|create`, `atualizar|update`, `deletar|delete`, `ordenar|order`, `toggle_status`, `toggle_destaque`, `save_linkedin_post`, `get_linkedin_post`, `delete_media` (`api/projetos.php:46-99`).

### 2.3 Categorias — `categorias.php` (auth + CSRF em escritas)

`listar`, `buscar`, `criar`, `atualizar`, `deletar`, `ordenar`, `toggle_status` (`api/categorias.php:42-66`). Escreve em `categorias` ou `categorias_artigos` conforme `target` (`getCategoryTables`, `:22`).

### 2.4 Auth — `auth.php`

| Action | Params | Retorno |
|---|---|---|
| `login` | `email`, `senha`, `csrf_token` | cria sessão (`:49`) |
| `logout` | — | destrói sessão (`:135`) |
| `check` | — | `{success, authenticated}` (`:165`) |
| `change_password` | `current_password`, `password`, `confirm_password` | `{success, message}` (`:186`) |

### 2.5 Configurações — `configuracoes.php`, `configuracoes-save.php`, `csrf.php`

- `GET configuracoes.php` → lista chave/valor (auth) (`:113`).
- `POST configuracoes-save.php` → salva **uma** chave: `chave`, `valor` (auth + CSRF) (`:20`). `openai_base_url` foi adicionado recentemente.
- `GET csrf.php` → `{success, csrf_token}` (auth) (`:15`).

### 2.6 IA / LLM

**`gemini.php`** (auth + CSRF), `action`: `test`, `generate_text`, `generate_image`, `generate_images_multi`, `generate_article`, `generate_social_agent` (`api/gemini.php:29-44`).
- Provedor/modelo por tipo via `getLlmProviderByType`/`getLlmModelByType` (`:53,60`).
- Para endpoints OpenAI-compatíveis self-hosted, envia `chat_template_kwargs.enable_thinking=false` quando `openai_base_url` está setado (evita `content` vazio em Qwen3).

**`llm-models.php`** (GET, auth): lista modelos do provedor. Params: `provider`, `api_key?`, `ollama_base_url?`, `openai_base_url?` (`api/llm-models.php`).

**`gemini-models.php`** (GET, auth): lista modelos Gemini (`api/gemini-models.php:10`).

**`openai.php`** (legacy): `gerar`, `testar` (`:32-36`). Não é o caminho usado pelo admin atual.

### 2.7 i18n / SEO

- **`i18n_seo.php`** (POST, auth+CSRF): gera traduções/SEO para `entity` = `artigo|projeto`, `id`, `langs`. Retorna `{success, saved}` (`api/i18n_seo.php:551-749`).
- **`i18n_site.php`** (POST, auth+CSRF): gera i18n do site (strings/UI). Retorna `{success, ...}` (`api/i18n_site.php:399`).
- **`i18n_status.php`** (POST, auth+CSRF): `entity`, `id`, `lang`, `status` → atualiza `status_traducao` (`api/i18n_status.php:30-55`).
- **`get_i18n_seo.php`** (GET, auth): `entity`, `id`, `lang` → `{success, data}` (`api/get_i18n_seo.php:18-37`).

### 2.8 Redes sociais

**`redes-sociais.php`** (auth + CSRF em escrita)
- `GET ?action=start_oauth` → inicia OAuth (Meta) (`:27`).
- `POST ?action=update_publicacao_url` → `publicacao`, `url` (`:63`).
- Padrão (POST): salva config por rede (`rede`, `*_client_id`, `*_client_secret`, `*_access_token`, etc.) (`:105-183`).

**`linkedin.php`** (auth), `action`: `get_person_urn`, `test_connection`, `test_credentials`, `publish_post`, `publish_article`, `publish_image`, `publish_video`, `delete_post`, `initialize_image_upload`, `initialize_video_upload`, `finalize_video_upload` (`api/linkedin.php:36-76`).

**Callbacks OAuth (sem auth; validam `state`):**
- `api/oauth/linkedin-callback.php` → troca `code` por token, grava `access_token`/`token_expires_at`/`person_urn`.
- `api/oauth_callback.php` → Meta (Facebook/Instagram), troca token curto por longo e grava page token.

### 2.9 Upload e mídia

- **`upload.php`** (auth+CSRF): `upload` (arquivo) e `delete` (`api/upload.php:30-34`).
- **`videos.php`** (auth+CSRF): `enqueue_from_variant` (`id`), `job_status` (`job_id`) (`api/videos.php:24-115`).

### 2.10 Radar — `radar.php` (auth+CSRF)

Ações: `topics_list`, `topics_save`, `topics_delete`, `sources_list`, `sources_save`, `sources_delete`, `topic_sources_set`, `topic_sources_get`, `collect_run`, `items_list`, `ideas_list`, `ideas_generate`, `idea_discard`, `idea_to_draft`, `idea_sources`, `items_delete`, `analyze_hype` (`api/radar.php:26-81`).

### 2.11 Telemetria — `metrics.php`

`POST` (sem auth, valida **same-origin**) para registrar acesso em `site_accesses` (`api/metrics.php`). Usado por beacon JS do site público.

## 3. Bibliotecas (não são endpoints)

`api/i18n.php`, `api/i18n_ui.php`, `api/metrics_lib.php`, `api/radar_lib.php`, `api/facebook.php`, `api/instagram.php`, `api/image_optimizer.php`, `api/config.php`, `api/config.local.php`.

## 4. Notas para a migração

- O admin depende de **formato de resposta** e **nomes de campos** acima. Ao portar para Go, mantenha-os (ou versione uma nova API e atualize o admin novo).
- Leituras do site público **não passam pela API** (SQL direto). Só o admin e o radar usam APIs para mutação.
- `artigos.php` é o arquivo mais crítico (2300+ linhas) e concentra o fluxo de publicação social.

## 5. Deltas de paridade observados (PHP × Go)

Validação de `categorias listar` (Fase 0) mostrou igualdade total, **exceto**:

| Campo | PHP (PDO/pgsql) | Go (pgx) | Resolução |
|---|---|---|---|
| `created_at` | `2025-12-06 19:00:12.961968` | `2025-12-06T19:00:12.961968Z` (RFC3339) | Go **normaliza** para o formato do PHP em `backend-go/internal/store` (`phpTimeLayout`) |
| Ordem das chaves JSON | ordem do SELECT | ordem alfabética | Irrelevante (comparar JSON parseado) |

Inteiros e booleanos já vêm com tipos nativos no PHP 8.3 (sem `ATTR_STRINGIFY_FETCHES`), então alinham com o Go.

| Campo | PHP (PDO/pgsql) | Go (pgx) | Resolução |
|---|---|---|---|
| `redes_destino` (jsonb) | string JSON crua (ex.: `[{"rede": "linkedin", ...}]`) | objeto/array decodificado | Go registra `json`/`jsonb` com `TextCodec` → texto cru |
| `schema_jsonld` (jsonb) | string JSON crua | objeto/array decodificado | idem |
| `imagens_galeria` (text) | array (via `json_decode`) | string | Go decodifica para array (`decodeGaleria`) |

## 6. Backend Go (Fase 1) — espelhamento

Endpoints de leitura em `backend-go/` (paridade validada por contract tests):

| Go | PHP |
|---|---|
| `POST /api/v1/auth/login` | `auth.php?action=login` |
| `POST /api/v1/auth/logout` | `auth.php?action=logout` |
| `GET /api/v1/auth/me` | `auth.php?action=check` |
| `GET /api/v1/categorias` | `categorias.php?action=listar` |
| `POST /api/v1/categorias` | `categorias.php?action=criar` |
| `PUT /api/v1/categorias/{id}` | `categorias.php?action=atualizar` |
| `DELETE /api/v1/categorias/{id}` | `categorias.php?action=deletar` |
| `POST /api/v1/categorias/ordenar` | `categorias.php?action=ordenar` |
| `POST /api/v1/categorias/{id}/toggle` | `categorias.php?action=toggle_status` |
| `GET /api/v1/artigos` | `artigos.php?action=list` |
| `GET /api/v1/artigos/{id}` | `artigos.php?action=get` |
| `POST /api/v1/artigos` | `artigos.php?action=create` |
| `PUT /api/v1/artigos/{id}` | `artigos.php?action=update` |
| `DELETE /api/v1/artigos/{id}` | `artigos.php?action=delete` |
| `GET /api/v1/projetos` | `projetos.php?action=listar` |
| `GET /api/v1/projetos/{id}` / `/slug/{slug}` | `projetos.php?action=buscar` |
| `POST /api/v1/projetos` | `projetos.php?action=criar` |
| `PUT /api/v1/projetos/{id}` | `projetos.php?action=atualizar` |
| `DELETE /api/v1/projetos/{id}` | `projetos.php?action=deletar` |
| `POST /api/v1/projetos/ordenar` | `projetos.php?action=ordenar` |
| `POST /api/v1/projetos/{id}/toggle-status` \| `.../toggle-destaque` | `projetos.php?action=toggle_status \| toggle_destaque` |
| `POST /api/v1/projetos/{id}/linkedin-post` | `projetos.php?action=save_linkedin_post` |
| `GET /api/v1/linkedin-posts/{id}` | `projetos.php?action=get_linkedin_post` |
| `POST /api/v1/projetos/{id}/media/delete` | `projetos.php?action=delete_media` |
| `GET /api/v1/i18n-seo` | `get_i18n_seo.php` |
| `POST /api/v1/ai/text` | `gemini.php` action=generate_text (texto) |
| `POST /api/v1/ai/article` | `gemini.php` action=generate_article |
| `POST /api/v1/ai/image` | `gemini.php` action=generate_image |
| `POST /api/v1/ai/social-agent` | `gemini.php` action=generate_social_agent |
| `GET /api/v1/redes-sociais` | (config de redes, sem segredos) |
| `POST /api/v1/redes-sociais` | (upsert de config de rede) |
| `POST /api/v1/i18n/generate` | `i18n_seo.php` (gera traduções/SEO) |
| `POST /api/v1/i18n/status` | `i18n_status.php` |
| `POST /api/v1/upload` | `upload.php` action=upload |
| `POST /api/v1/upload/delete` | `upload.php` action=delete |

- Autenticação: **sessão server-side** (tabela `sessions`, migration `016`, aplicada em staging **e produção**) + cookie `HttpOnly`, com alternativa `X-Internal-Token` (loopback apenas). Bind em `127.0.0.1`; exposto via nginx em `/go-api/`.
- **Escritas** exigem sessão **e** `X-CSRF-Token` (sem bypass de token interno). Categorias, artigos, projetos, configurações e upload já portados.
- **Upload**: preserva a extensão original e **não** otimiza imagens (divergência consciente do PHP, que força `.jpg` após otimização GD).
- **Adiado:** geração de IA (gemini), SEO/traduções (escrita), redes sociais/publicação, publicação automática no LinkedIn e remoção de arquivos/posts no delete.
- Contract tests: `contract_test.py` (13/13), `write_test.py` (11/11), `write_artigos_test.py` (12/12), `write_projetos_test.py` (16/16), `write_config_test.py` (8/8), `upload_test.py` (7/7), `worker_test.py` (6/6), `metrics_worker_test.py` (4/4), `video_worker_test.py` (2/2).
