# 04 — Lógica de Negócio

> O que o backend faz hoje, onde está no código e o que **precisa ser preservado** na migração para Go.

## 1. Ciclo de vida de conteúdo

1. **Criação/edição** em `admin/artigos.php` → `api/artigos.php` (`criarArtigo:130`, `atualizarArtigo:294`). Campos: título, slug, resumo, conteúdo (HTML), categoria, autor, mídia, agendamento, redes destino.
2. **Geração por IA** (`api/gemini.php`):
   - `generate_article` → `gerarArtigo()` (`:1529`) monta prompt com `ia_instrucoes` + tema + formato obrigatório (`Título/Slug/Categoria/Resumo/Conteúdo`) e chama o provedor de texto.
   - `extrairCampo` (`:1681`) e `limparTextoIA` (`:1701`) fazem parsing do texto retornado.
3. **Traduções/SEO** (`api/i18n_seo.php`): gera `artigos_i18n`/`projetos_i18n` (título, slug, resumo, conteúdo, meta/OG, keywords, schema JSON-LD) por idioma; `status_traducao` gerido por `api/i18n_status.php`.
4. **Publicação** (etapa 3 abaixo).
5. **Agendamento** por `data_agendamento`/`recorrencia_*` + `redes_destino` (jsonb) na tabela `artigos`.

## 2. Geração de imagens

- `generate_image` / `generate_images_multi` em `api/gemini.php`: `gerarImagem()` (`:1142`), `gerarImagensMultiplosFormatos()` (`:874`, formatos 1:1 e 9:16), `criarVersaoRecortada()` (`:1022`), `gerarImagemComGeminiFlash` (`:1269`), `gerarImagemComImagen` (`:1400`), `salvarImagemGerada` (`:1457`), `otimizarImagemResultado` (`:1483`).
- Otimização: `api/image_optimizer.php` (`otimizarImagemParaRedesSociais`).
- Arquivos vão para `uploads/` (prefixos `ai_`, `ai_1x1_`).

## 3. Publicação social

### LinkedIn (mais completo)
- `api/linkedin.php`: `publish_post`, `publish_article`, `publish_image`, `publish_video`, `delete_post`, além de `initialize_*`/`finalize_video_upload` para vídeo via UGC API.
- `api/artigos.php`: `publicarNoLinkedInViaAPI` (`:1600`) faz o fluxo completo (imagem → upload → UGC post), com **candidatos de versão** (`buildLinkedInVersionCandidates:1544`) e `resolverImagemParaLinkedIn` (`:1568`).
- **Pré-checagem de token:** `api/artigos.php:1659-1673` faz um `GET` de teste; em `401` retorna `"Token expirado ou inválido. Gere um novo Access Token no LinkedIn Developers."`
- Renovação: OAuth em `admin/redes-sociais.php` (botão "Conectar com LinkedIn (OAuth)", scope `openid profile w_member_social`) → `api/oauth/linkedin-callback.php` grava `access_token`, `token_expires_at`, `person_urn`.

### Meta (Facebook/Instagram)
- `api/facebook.php` (`publicarNoFacebookViaAPI`), `api/instagram.php` (`publicarNoInstagramViaAPI`).
- Tokens longos via `api/oauth_callback.php` (troca short→long, resolve page token e `instagram_business_account`).

### Orquestração
- `api/artigos.php:1201` `publicarNasRedesSociais($artigoId, $redesDestinoJson, $dadosArtigo)` decide as redes.
- Registro em `publicacoes_redes` (`registrarPublicacao`, `api/linkedin.php:941`).

## 4. Workers (agendamento)

Executam via **systemd timers** (ver `01`):

| Script | Papel |
|---|---|
| `scripts/worker_publish_articles.php` | Publica artigos agendados (LinkedIn) |
| `scripts/worker_publish_scheduled.php` | Publica variantes sociais agendadas (por rede) |
| `scripts/video_worker.php` | Processa `video_jobs` com FFmpeg |
| `scripts/metrics_worker.php` | Coleta métricas sociais (`api/metrics_lib.php`) |

Auxiliares em `scripts/`: `radar_run.php`, `list_scheduled.php`, `regenerate_missing_images.php`, entre outros (ver `ls scripts/`).

## 5. Pipeline de vídeo

- Fila: `video_jobs` (via `api/videos.php` `enqueue_from_variant`).
- Processamento: `scripts/video_worker.php` usa **FFmpeg** (detecta com `command -v ffmpeg`; falha com log se ausente).
- Output: `output_file` em `uploads/`; usado por `artigos_social_variants.video_file`.

## 6. Abstração de IA/LLM

- Provedores: `gemini`, `openai`, `deepseek`, `openrouter`, `anthropic`, `ollama` (`api/gemini.php:53,95`).
- Modelos por tipo: `llm_text_provider/model`, `llm_image_provider/model`, `llm_video_provider/model` (tabela `configuracoes`).
- Chamada HTTP central: `callJsonHttp` (`:113`); `gerarTextoComProvider` (`:143`), `gerarImagemComProvider` (`:294`), `testarConexaoProvider` (`:344`).
- **Base URL OpenAI-compatível:** quando `openai_base_url` está preenchido, o provedor `openai` usa esse endpoint (vLLM/LM Studio).
- **Fix de modelos híbridos (Qwen3):** com base customizada, envia `chat_template_kwargs: {enable_thinking:false}` para evitar `content` vazio (raciocínio consumindo todos os tokens). Também aplicado em `api/i18n_seo.php` e `api/i18n_site.php`.
- Radar usa IA via `radarGeminiGenerate` (`api/radar_lib.php:488`).

## 7. Radar de tendências

- Coleta RSS/scraping: `radarCollectSource` (`api/radar_lib.php:312`), `radarParseRss` (`:123`), `radarComputeScore` (`:226`).
- Ideias por IA: `radarGenerateIdeasForTopic` (`:680`), `radarIdeaToDraftArticle` (`:852`), `radarIdeaToCreateSimpleDraft` (`:950`), `radarAnalyzeHype` (`:1000`).
- API e UI: `api/radar.php`, `admin/radar.php`.

## 8. Métricas e telemetria

- `api/metrics_lib.php`: `metricsFetchLinkedIn/Instagram/Facebook`, `collectMetricsForPublication`, `storeMetricsSnapshot`.
- `api/metrics.php`: beacon same-origin → `site_accesses`.

## 9. i18n do site

- `api/i18n.php`: detecção de idioma (path/`Accept-Language`/cookie), prefixos de rota, `htmlLang`, rotas helper.
- `api/i18n_ui.php`: `t()`/`tn()` sobre `ui_strings` com cache.
- Conteúdo editorial i18n nas tabelas `*_i18n`.

## 10. Padrões do admin (front-end) e mudanças recentes

- `assets/js/admin.js`: `WVProgress` (overlay com barra) e **`WVLoading`** (indicador global) que **envolve `window.fetch`** — qualquer requisição mostra spinner no canto.
- `assets/css/admin.css`: classe `.wv-global-loading` + `.spinner`.
- `admin/artigos.php`:
  - `setIaButtonLoading()` (Gerar Texto/Imagem) e `setSubmitLoading()` (Salvar).
  - Form com `novalidate` + validação JS (Título/Conteúdo) com troca de aba e foco — corrige o bloqueio silencioso de `required` em aba oculta.
- `admin/index.php` e `admin/senha.php`: loader com spinner.
- `admin/redes-sociais.php`: aviso de **token LinkedIn expirado** + status considerando `token_expires_at`.

> Manter esses comportamentos no admin novo (feedback de carregamento e validação explícita) — foram correções de bugs reais já reportados.
