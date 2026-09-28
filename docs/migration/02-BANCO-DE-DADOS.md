# 02 — Banco de Dados

> PostgreSQL. Conexão em **`api/config.local.php`** (não versionado — contém usuário/senha). Não copiar segredos para cá.
> Contagem de linhas e estrutura verificadas em 2026-09-28 (`information_schema`).

## 1. Convenções

- **29 tabelas** no schema `public`.
- Chaves primárias `id integer` com `nextval(...)`, exceto tabelas de junção (`radar_item_topics`, `radar_topic_sources`) que usam PK composta.
- Timestamps **sem timezone** (`timestamp without time zone`) em quase tudo; exceção: `video_jobs` (com timezone).
- Uso intenso de **`jsonb`**: `redes_destino`, `dados_extras`, `schema_jsonld`, `video_meta`, `params`, `meta`, `source_item_ids`.
- i18n replicado por tabela `*_i18n` com coluna `lang` (`pt|en|es`) + campos de SEO (`meta_title`, `meta_description`, `og_*`, `keywords`, `schema_jsonld`, `status_traducao`).
- Unicidade de `slug` é garantida **na aplicação** (`ensureUniqueSlug`, `api/i18n_seo.php:111`), não por constraint.
- A migração `012_grant_sequences.sql` indica que o usuário do banco precisa de `GRANT` nas sequences.

## 2. Tabelas por domínio

### 2.1 Conteúdo (artigos/blog)

**`artigos` (16)** — PK `id`; FK `categoria_id → categorias_artigos.id`
`titulo`, `slug`, `resumo`, `conteudo`, `imagem_principal`, `autor`, `destaque`, `ativo`, `fonte_ia`, `prompt_usado`, `video_url`, `status` (default `rascunho`), `publicar_linkedin`, `publicar_instagram`, `linkedin_post_id`, `instagram_post_id`, `data_publicacao`, `data_agendamento`, `tipo_midia` (default `imagem`), `prompt_texto`, `prompt_imagem`, `imagem_1x1`, `imagem_9x16`, `recorrencia_tipo`, `recorrencia_dias`, `recorrencia_dia_mes`, `recorrencia_fim`, `redes_destino` (jsonb), `status_publicacao`, `erro_publicacao`, `ultima_tentativa`, `created_at`, `updated_at`.

**`artigos_i18n` (37)** — PK `id`; FK `artigo_id → artigos.id`
`lang`, `titulo`, `slug`, `resumo`, `conteudo`, `meta_title`, `meta_description`, `og_title`, `og_description`, `og_image`, `keywords`, `schema_jsonld` (jsonb), `status_traducao` (default `generated`), `generated_by`, `generated_at`, `created_at`, `updated_at`.

**`artigos_social_variants` (8)** — PK `id`; FK `artigo_id → artigos.id`
`rede`, `titulo`, `caption`, `hashtags`, `media_type` (default `imagem`), `image_1x1`, `image_9x16`, `video_file`, `video_meta` (jsonb), `status`, `scheduled_at`, `created_at`, `updated_at`.

**`categorias_artigos` (5)** — PK `id`
`nome`, `slug`, `descricao`, `cor` (default `#607AFB`), `ordem`, `ativo`, `created_at`.

**`categorias_artigos_i18n` (10)** — PK `id`; FK `categoria_id → categorias_artigos.id`
`lang`, `nome`, `slug`, `descricao`, `created_at`, `updated_at`.

**`historico_imagens` (0)** — PK `id`; FK `artigo_id → artigos.id`
`formato`, `arquivo`, `prompt_usado`, `modelo_ia`, `ativo`, `created_at`.

### 2.2 Projetos (portfólio)

**`projetos` (16)** — PK `id`; FK `categoria_id → categorias.id`
`titulo`, `slug`, `descricao`, `imagem_principal`, `imagens_galeria`, `tecnologias`, `url_projeto`, `destaque`, `ativo`, `ordem`, `prompt_descricao`, `prompt_linkedin`, `created_at`, `updated_at`.

**`projetos_i18n` (41)** — mesma estrutura de SEO de `artigos_i18n`, com FK `projeto_id → projetos.id`.

**`posts_linkedin` (0)** — PK `id`; FK `projeto_id → projetos.id` — `conteudo`, `prompt_usado`, `gerado_em`.

**`categorias` (6)** — categorias de projetos: `nome`, `slug`, `ordem`, `ativo`, `created_at`.
**`categorias_i18n` (12)** — FK `categoria_id → categorias.id` — `lang`, `nome`, `slug`, timestamps.

### 2.3 Redes sociais e publicação

**`redes_sociais_config` (5)** — PK `id` (uma linha por rede: linkedin, instagram, facebook, tiktok, youtube)
`ativo`, `client_id`, `client_secret`, `access_token`, `refresh_token`, `page_id`, `user_id`, `dados_extras` (jsonb), `person_urn`, `token_expires_at`, `organization_urn`, `publish_target` (default `person`), `created_at`, `updated_at`.

**`publicacoes_redes` (8)** — PK `id`; FK `artigo_id → artigos.id`
`rede`, `post_id`, `url_post`, `status` (default `pendente`), `erro_mensagem`, `publicado_em`, `created_at`.

**`agendamentos_posts` (0)** — PK `id`; FK `artigo_id → artigos.id`
`data_publicacao`, `rede_social`, `formato_imagem`, `status` (default `pendente`), `post_id`, `erro`, `created_at`, `executed_at`.

**`config_redes_formatos` (8)** — PK `id`
`rede`, `nome_exibicao`, `formato_padrao`, `formatos_aceitos`, `icone`, `ativo`, `ordem`.

**`metricas_publicacoes` (0)** — PK `id`; FK `publicacao_id → publicacoes_redes.id`
`data_coleta`, `visualizacoes`, `curtidas`, `comentarios`, `compartilhamentos`, `cliques`, `alcance`, `engajamento`, `dados_extras` (jsonb).

### 2.4 Vídeo

**`video_jobs` (4)** — PK `id`; FK `variant_id → artigos_social_variants.id`
`status` (default `pending`), `attempts`, `last_error`, `output_file`, `params` (jsonb), `created_at`/`updated_at` (timestamptz).

### 2.5 Radar de tendências

**`radar_topics` (1)** — FK `categoria_artigos_id → categorias_artigos.id` — `nome`, `descricao`, `keywords`, `idiomas` (default `pt,en`), `regioes` (default `br,us,eu`), `ativo`, timestamps.
**`radar_sources` (1)** — `nome`, `tipo`, `url`, `config` (jsonb), `ativo`, timestamps.
**`radar_topic_sources` (1)** — PK composta (`topic_id`, `source_id`), FKs para `radar_topics`/`radar_sources`.
**`radar_items` (25)** — FK `source_id → radar_sources.id` — `url`, `url_norm`, `titulo`, `descricao`, `snippet`, `tipo_midia`, `published_at`, `fetched_at`, `idioma`, `regiao`, `score`, `raw` (jsonb).
**`radar_item_topics` (25)** — PK composta (`item_id`, `topic_id`), FKs correspondentes.
**`radar_ideas` (3)** — FK `topic_id → radar_topics.id` — `titulo`, `angulo`, `resumo`, `outline`, `tags`, `status` (default `nova`), `source_item_ids` (jsonb), `ai_model`, `ai_prompt`, `ai_raw`, timestamps.
**`radar_runs` (16)** — `started_at`, `finished_at`, `status` (default `running`), `triggered_by`, `log`, `meta` (jsonb).

### 2.6 Configuração, i18n e UI

**`configuracoes` (49)** — PK `id`; `chave`, `valor`. Chaves atuais (sem valores):
`anthropic_api_key, deepseek_api_key, gemini_api_key, gemini_image_model, gemini_max_tokens, gemini_model, home_card_1..4_{icon,link,subtexto,titulo}, home_frase_impacto, ia_instrucoes, ia_instrucoes_linkedin, ia_instrucoes_projeto, llm_auto_validate_on_load, llm_image_model, llm_image_provider, llm_text_model, llm_text_provider, llm_video_model, llm_video_provider, mini_bio, notify_email, ollama_api_key, ollama_base_url, openai_api_key, openai_base_url, openrouter_api_key, sentry_dsn, site_email, site_github, site_instagram, site_linkedin, site_subtitulo, site_telefone, site_titulo, site_url`.

**`configuracoes_i18n` (22)** — PK `id`; `chave`, `lang`, `valor`, timestamps.
**`ui_strings` (344)** — PK `id`; `chave`, `lang`, `texto`, timestamps (strings da UI do site).

### 2.7 Auth e telemetria

**`usuarios` (1)** — `nome`, `email`, `senha` (BCRYPT), `created_at`.
**`site_accesses` (533)** — beacon de acessos: `path`, `user_agent`, `ip`, `created_at` (alimentado por `api/metrics.php`).

## 3. Migrations existentes (`migrations/`)

| Arquivo | Assunto |
|---|---|
| `001_artigos_redes_sociais.sql` | base de artigos + redes |
| `002_criar_tabelas_redes.php` | tabelas de redes sociais |
| `003_add_person_urn.php` | LinkedIn person URN |
| `004_agendamento_posts.php` | agendamento |
| `005_add_organization_urn.php` | LinkedIn org URN |
| `006_i18n_seo.sql` | i18n/SEO de artigos e projetos |
| `007_site_ui_i18n.sql` | `ui_strings`, `configuracoes_i18n` |
| `008_radar_research.sql` | tabelas do radar |
| `009_projetos_ordem_null.sql` | ajuste de ordenação |
| `010_site_accesses.sql` | telemetria de acessos |
| `011_add_project_prompts.sql` | prompts de projeto |
| `012_grant_sequences.sql` | permissões de sequences |
| `013_artigos_social_variants.sql` | variantes sociais |
| `014_video_jobs.sql` | fila de vídeo |
| `015_drop_token_expira_em.sql` | limpeza de coluna legada |
| `016_sessions.sql` | sessões do backend Go (Fase 2) — aplicada em staging **e produção** |
| `017_social_variants_status_width.sql` | amplia `artigos_social_variants.status` p/ 30 (corrige bug latente do worker PHP) — staging **e produção** |

## 4. Backup, restore e migração

- Script de backup: **`scripts/backup_db_for_migration.sh`** (usar antes de qualquer mudança de schema/deploy).
- Migração de dados para o backend novo: **não requer transformação** — o Go conecta no mesmo banco e reutiliza as tabelas. Nada é recadastrado.
- Se for necessário recriar o banco: aplicar `migrations/` em ordem + repor dados via `pg_dump`/`pg_restore`.

## 5. Pontos de atenção

- `getConfig()` tem cache em `$_SESSION['config']` — não confundir "valor no banco" com "valor em uso".
- `configuracoes.valor` é `text`; booleanos/JSON são serializados como string (ex.: `llm_auto_validate_on_load`).
- `uploads/` (~1.8GB) fica em disco; os nomes de arquivo referenciados nas tabelas apontam para essa pasta.
