# Washiviana Site

Plataforma de conteúdo e portfólio **multilíngue (pt/en/es)** em PHP, com painel administrativo, publicação social automatizada (LinkedIn/Instagram/Facebook), geração de conteúdo por IA multi-provedor, radar de tendências, calendário editorial e pipeline de vídeo (Reels).

O sistema já opera em produção. Este documento reflete o estado atual do código.

---

## Visão geral

- **Site público** renderizado no servidor (PHP), com rotas limpas por idioma.
- **Painel admin** com autenticação por sessão, CRUD de projetos/artigos/categorias, agendamento, redes sociais, radar e configurações.
- **APIs internas** (`api/*.php`) com dispatch por `action`, CSRF, autenticação e respostas JSON.
- **Workers CLI** para publicação agendada e geração de vídeo, executados por systemd timers ou cron.
- **Banco relacional** com suporte a PostgreSQL (produção) e MySQL, evoluído por migrations incrementais.

---

## Funcionalidades

### Site público

- Home com últimos conteúdos, mini-bio e seções institucionais.
- Páginas: **Conteúdos**, **Projetos**, **Sobre** e as landings **Automação & IA**, **Tech Insights** e **Design & Experiências Digitais**.
- Detalhe de **artigo** (`/artigo/{slug}`) e de **projeto** (`/projeto/{slug}`), com galeria (imagens e vídeos) e modal/carrossel.
- SEO: `meta_title`, `meta_description`, Open Graph, schema JSON-LD, `sitemap.php` e `robots.txt`.
- Mídia validada na renderização: só exibe arquivo que existe fisicamente em `uploads/` (com fallback visual).
- Beacon de analytics de acesso (`api/metrics.php`).

### Painel administrativo (`admin/`)

| Página | Descrição |
|---|---|
| `index.php` / `logout.php` | Login e encerramento de sessão |
| `dashboard.php` | Estatísticas e últimos itens |
| `projetos.php` | CRUD de projetos + IA Copilot (descrição e post LinkedIn) |
| `artigos.php` | CRUD de conteúdos, agendamento, mídia, variantes sociais e i18n/SEO |
| `calendario.php` | Visualização mensal dos posts agendados |
| `radar.php` | Pesquisa de temas, coleta e geração de ideias |
| `categorias.php` | Categorias de projetos |
| `redes-sociais.php` | Credenciais/tokens e conexão das redes (OAuth) |
| `configuracoes.php` | Configurações do site, IA e integrações |

### Publicação social e agendamento

- **Variantes sociais** por artigo/rede (`artigos_social_variants`): legenda, imagens 1:1 e 9:16, `scheduled_at` e status.
- Publicação **manual** (botão Publicar) ou **automática** via worker.
- Registro de publicações em `publicacoes_redes`, com status e mensagem de erro.
- Redes: **LinkedIn** (publicação de texto/artigo/imagem/vídeo, perfil ou organização), **Instagram** e **Facebook** via Graph API. TikTok/YouTube existem como configuração.
- Vídeo (Reels): job assíncrono enfileirado a partir da variante, processado com provider de vídeo ou **FFmpeg** como fallback (formato vertical 1080x1920).

### IA (multi-provedor)

- Provedores de texto/imagem/vídeo configuráveis: **Gemini, OpenAI, DeepSeek, OpenRouter, Anthropic, Ollama**.
- Ações: gerar texto, imagem, artigo, agente social, múltiplas imagens e teste de conexão.
- Prompts editáveis para descrição de projeto e post social.
- i18n/SEO por IA: gera traduções EN/ES e metadados a partir do PT.

### Internacionalização (pt/en/es)

- Roteamento por prefixo de idioma com detecção por `Accept-Language`/cookie e redirecionamentos.
- Entidades traduzidas: `artigos_i18n`, `projetos_i18n`, `ui_strings`, `configuracoes_i18n`.
- Status de tradução: `draft`, `generated`, `reviewed`.

---

## Stack tecnológica

- **PHP** procedural (sem framework no runtime), PDO e cURL.
- **PostgreSQL** (produção) e **MySQL** (compatibilidade) via `DB_DRIVER`.
- **JavaScript** vanilla no público e no admin; **Tailwind CSS** compilado em `assets/css/tailwind.min.css` (versionado).
- **FFmpeg** para o pipeline de vídeo.
- **Nginx + PHP-FPM** em produção; `.htaccess` fornecido para cenários Apache.
- **systemd** (timers) ou cron para os workers.
- Subprojeto isolado **Next.js** em `tailormade/` (experimento de landing/slides, não faz parte do runtime PHP).

### Requisitos

- PHP 8.x (extensões: `pdo`, `pdo_pgsql`/`pdo_mysql`, `curl`, `mbstring`, `gd`; `fileinfo`).
- PostgreSQL 14+ (ou MySQL 5.7+).
- FFmpeg no `PATH` (necessário apenas para o worker de vídeo).
- Escrita em `uploads/` e diretório de sessão temporário.

---

## Estrutura do projeto

```
washiviana.com/
├── index.php                 # Front controller (roteamento i18n + home)
├── conteudos.php projetos.php projeto.php artigo.php sobre.php
├── automacao-ia.php tech-insights.php design-experiencias.php
├── sitemap.php robots.txt
├── admin/                    # Painel administrativo (requireAuth)
│   ├── includes/             # header.php, sidebar.php
│   └── *.php                 # dashboard, projetos, artigos, calendario, radar...
├── api/                      # Endpoints JSON (dispatch por action)
│   ├── config.php            # Bootstrap, PDO, sessão, auth, CSRF, helpers
│   ├── auth.php artigos.php projetos.php categorias.php upload.php videos.php
│   ├── linkedin.php instagram.php facebook.php redes-sociais.php oauth_callback.php
│   ├── gemini.php openai.php llm-models.php gemini-models.php
│   ├── radar.php radar_lib.php metrics.php image_optimizer.php
│   └── i18n.php i18n_ui.php i18n_seo.php i18n_site.php i18n_status.php get_i18n_seo.php
├── includes/site-footer.php  # Rodapé compartilhado
├── scripts/                  # Workers e utilitários CLI
│   ├── worker_publish_articles.php    # Publica artigos agendados (LinkedIn)
│   ├── worker_publish_scheduled.php   # Publica variantes sociais agendadas
│   ├── video_worker.php               # Processa video_jobs (FFmpeg/provider)
│   ├── radar_run.php                  # Coleta do Radar via CLI
│   └── systemd/                       # Units e timers de exemplo
├── migrations/               # Migrations incrementais (001..014)
├── assets/                   # css/js/imgs do site e do admin
├── uploads/                  # Mídia enviada (não versionada)
├── tests/                    # Testes/checagens pontuais (social variants, vídeo)
├── docs/                     # Documentação
└── tailormade/               # Subprojeto Next.js (opcional)
```

---

## Rotas públicas

- `/` → redireciona para o idioma detectado.
- `/{pt|en|es}/` → home.
- `/{lang}/conteudos`, `/{lang}/projetos`, `/{lang}/sobre`.
- `/{lang}/automacao-ia`, `/{lang}/tech-insights`, `/{lang}/design-experiencias`.
- `/{lang}/artigo/{slug}` (aliases `article`, `articulo`).
- `/{lang}/projeto/{slug}` (aliases `project`, `proyecto`).
- `/admin/` → painel; `/api/*.php` → endpoints JSON; `/uploads/*` → mídia.

---

## Configuração

Credenciais e overrides locais ficam em **`api/config.local.php`** (não versionado). Ele é carregado automaticamente por `api/config.php` quando existir.

```php
define('DB_DRIVER', 'pgsql');   // 'pgsql' ou 'mysql'
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'washiviana');
define('DB_USER', 'postgres');
define('DB_PASS', '...');
```

Também é possível definir via variáveis de ambiente: `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`.

Configurações do site, IA e integrações são armazenadas na tabela `configuracoes` (chave-valor) e editadas em **Admin → Configurações / Redes Sociais**. Principais chaves: `site_titulo`, `site_subtitulo`, `site_email`, `site_telefone`, `site_linkedin`, `site_instagram`, `home_frase_impacto`, `mini_bio`, `notify_email`, `llm_text_provider`, `llm_text_model`, `llm_image_provider`, `openai_api_key`, `gemini_api_key`, `deepseek_api_key`, `openrouter_api_key`, `anthropic_api_key`, `ollama_base_url`, `facebook_api_version`.

A `BASE_URL` é detectada automaticamente a partir da requisição; HTTPS é inferido (`HTTPS`, porta 443 ou `X-Forwarded-Proto`).

---

## Banco de dados

- Schema base: `database.sql` (MySQL) e `database_postgres.sql` (PostgreSQL).
- Evolução: `migrations/001..014` (PostgreSQL-first), cobrindo artigos/redes, agendamento, URNs LinkedIn, i18n/SEO, radar, acessos, prompts, variantes sociais e `video_jobs`.
- Produção usa PostgreSQL; `config.local.php` seleciona o driver.

Tabelas principais: `usuarios`, `categorias`, `projetos`, `categorias_artigos`, `artigos`, `artigos_i18n`, `projetos_i18n`, `ui_strings`, `configuracoes`, `configuracoes_i18n`, `redes_sociais_config`, `publicacoes_redes`, `artigos_social_variants`, `video_jobs`, `radar_*`, `site_accesses`, `posts_linkedin`.

---

## Workers e agendamento

Em produção, os workers rodam a cada minuto via **systemd timers** (`scripts/systemd/`), ou por cron.

- `worker_publish_articles.php` — artigos com `status_publicacao = 'agendado'` e `data_agendamento <= NOW()`.
- `worker_publish_scheduled.php` — variantes sociais com `scheduled_at` vencido e status `pronto`.
- `video_worker.php` — jobs `pending` de `video_jobs`; usa provider de vídeo e faz fallback para FFmpeg.
- `radar_run.php` — coleta do Radar (`--topic=ID` ou `--all`).

Falhas de job podem notificar por e-mail (`notify_email`) e, se configurado, para o Sentry.

---

## Segurança

- Senhas com **bcrypt**; login em `api/auth.php`.
- **PDO com prepared statements** em todo o acesso a dados.
- **CSRF** obrigatório em POSTs sensíveis (`generateCsrfToken`/`validateCsrfToken`).
- Sessão endurecida: nome próprio, cookie `HttpOnly`/`SameSite=Lax`/`Secure` sob HTTPS, timeout de inatividade e save path fora do webroot.
- Uploads validados por extensão/tamanho; nomes normalizados e URLs só geradas para arquivos existentes.
- Headers de segurança, CSP, `X-Frame-Options` e cache via `.htaccess` (cenário Apache).

> Boas práticas: nunca versionar `api/config.local.php`, manter `uploads/` fora do git e restringir acesso HTTP a scripts de debug (a pasta `outros/` e scripts de token na raiz não fazem parte do runtime).

---

## Scripts utilitários

- `scripts/audit_project_media.php` — audita referências de mídia de projetos e valida arquivos em `uploads/`.
- Demais scripts em `scripts/` e `outros/` são ferramentas de manutenção/debug; alguns estão no `.gitignore`. Executar apenas via CLI.

---

## Documentação relacionada

- [README_DEPLOY.md](README_DEPLOY.md) — instalação, deploy e operação.
- [README_SOCIAL_VARIANTS.md](README_SOCIAL_VARIANTS.md) — publicação social, variantes, worker e systemd.

---

## Limitações conhecidas / próximos passos

- O CSS do Tailwind é consumido como arquivo compilado versionado; **não há, hoje, configuração de build na raiz** do repositório.
- Analytics social é parcial (registro de publicações; painel consolidado ainda não existe).
- Botão "gerar i18n/SEO em lote" (`gerarI18nSeoBulkArtigos`) está referenciado na UI sem implementação (pré-existente).
- RBAC é binário (autenticado/não autenticado); não há papéis/permissões granulares.
- `admin/artigos.php` está em CRLF; normalizar quando conveniente.

---

© Washington Viana. Todos os direitos reservados.
