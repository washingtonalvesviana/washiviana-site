# admin-next

Novo admin (Fase 2c da migração), **SPA estática responsiva sem build**, consumindo a API Go.

## Como funciona

- Arquivos: `index.html`, `app.js`, `styles.css`. Servidos pelo nginx em `/admin-next/`.
- API: `const API = '/go-api'` (mesma origem). O cookie de sessão (`wv_go_sess`) vai automaticamente.
- CSRF: o token retornado no login/`me` é enviado no header `X-CSRF-Token` nas escritas.

## Funcionalidades atuais

- Login / logout (sessão server-side).
- Shell responsivo (sidebar com menu hambúrguer no mobile).
- Dashboard (contagens de conteúdos, projetos, categorias).
- Conteúdos: listar, criar, editar, excluir.
- Projetos: listar, criar, editar, excluir, alternar ativo/destaque.
- Categorias (de conteúdo): listar, criar, excluir.
- Configurações: campos do site (título, subtítulo, frase, bio, contatos/redes) com save em massa.
- **Upload de imagem principal** em conteúdos e projetos (via `/go-api/api/v1/upload`).
- **Gerar conteúdo com IA**: no formulário de conteúdo, informe o tema e o botão preenche título/resumo/conteúdo (via `/go-api/api/v1/ai/article`).
- **Gerar traduções/SEO** (pt/en/es) na edição de conteúdo e de projeto (via `/go-api/api/v1/i18n/generate`).
- **Gerar imagem por IA** (prompt + botão) em conteúdo e projeto (via `/go-api/api/v1/ai/image`).
- **Redes sociais**: tela de status (ativa, token, validade) via `/go-api/api/v1/redes-sociais` (sem exibir segredos).

## Pendente (próximos incrementos)

- **Publicação** em redes (LinkedIn/Meta) — depende de OAuth/tokens válidos.
- Reaproveitar o design de referência (`docs/layout.md`).

> `GET /go-api/api/v1/configuracoes` **não devolve chaves secretas** (API keys, tokens, Sentry).

## Segurança / operação

- A API `/go-api/` exige sessão; o bypass por `X-Internal-Token` só vale de loopback.
- **Produção**: definir `COOKIE_SECURE=1` no `/home/washi/washiviana-go/api.env` (site é HTTPS-only).
- Não há build: basta editar os arquivos (nginx serve direto). Para invalidar cache, ajuste a query do `<script>`/`<link>` se necessário.
