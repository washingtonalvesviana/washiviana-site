# 05 — Plano de Migração

> Meta: **backend em Go** (API + workers) reutilizando o PostgreSQL, com **zero downtime**, e admin novo responsivo.
> Python só entra depois, para IA pesada.

## 1. Princípios

1. **Strangler Fig:** o novo serviço cresce ao lado do PHP; o nginx decide a rota. PHP permanece como fallback até o fim.
2. **Schema congelado/compatível:** só mudanças **aditivas** (novas colunas com default, novas tabelas). O site público PHP depende do schema atual.
3. **Sem big-bang:** cada endpoint/worker é migrado e validado isoladamente.
4. **Observabilidade primeiro:** logs estruturados, healthcheck e métricas antes de mover tráfego.
5. **Rollback sempre definido:** toda fase tem como voltar (nginx/DNS/flag + backup).

## 2. Fases

### Fase 0 — Fundação (sem impacto em produção)
- Congelar contrato: expandir `03-CONTRATO-API.md` + `openapi.yaml`; baseline do schema (`02`).
- Backup: rodar `scripts/backup_db_for_migration.sh` e validar restore em staging.
- Criar esqueleto do serviço Go (`cmd/api`, `cmd/worker`), conexão PostgreSQL (`pgx` + `sqlc`), config por env.
- Criar staging (pode ser o mesmo host em porta separada, ex.: `:8081`) + healthcheck.
- Definir convenção de erros/logs e pipeline de testes (unit + contract).
- **Saída:** serviço Go sobe em staging, conecta no banco (read-only) e responde `/health`.

### Fase 1 — Leitura em paralelo (pilot)
- Portar endpoints **somente leitura** (ex.: `categorias listar`, `artigos list/get`, `get_i18n_seo`).
- Rotear por prefixo no nginx **sem trocar o admin**: ex. `GET /go-api/...` para testes internos.
- **Contract tests** comparam respostas PHP × Go para os mesmos dados.
- **Saída:** paridade ≥ 100% nos endpoints de leitura do piloto; zero erro em produção.

### Fase 2 — Escrita + Auth + Admin novo
- Portar escritas (artigos/projetos/categorias/configurações). Manter **mesmo formato de resposta**.
- Auth: introduzir modelo de sessão que o Go emite (ver §4). Construir o **novo admin responsivo** consumindo a API Go.
- Apontar o novo admin para o Go; manter admin PHP disponível (rota alternativa) durante a transição.
- **Saída:** admin novo cobre 100% das funções usadas; admin PHP pode ser desativado.

### Fase 3 — Workers, IA e site público
- Portar workers para Go (`cmd/worker`): publicação agendada, métricas, vídeo, radar.
  - **Risco alto de publicação duplicada** — ver §5.
- Avaliar Python sidecar para IA (§6).
- (Opcional) migrar site público para Next.js/Go, quando o admin já estiver estável.
- **Saída:** PHP desativado; sistema 100% Go (+ Python opcional).

## 3. Zero downtime (técnicas)

- **Nginx strangler:** rotas específicas para o Go, resto no PHP.
  ```nginx
  # exemplo de convivência
  location ^~ /api2/            { proxy_pass http://127.0.0.1:8081/; }
  location ^~ /admin-next/      { proxy_pass http://127.0.0.1:3200/; }
  # sem alterar /api/ nem /admin/ até a fase de corte
  ```
- **Feature flag** por endpoint (env/config) para ligar/desligar o Go sem deploy.
- **Migrations aditivas** aplicadas antes do deploy do código que as usa (expand/contract).
- **Healthcheck** (`/health`) + `systemd`/nginx readiness antes de receber tráfego.
- **Deploy azul/verde** em porta separada; troca de `proxy_pass` é o "switch".

## 4. Estratégia de autenticação (fricção principal)

Hoje: sessão **PHP** (`washiviana_sess`) + cache de config em `$_SESSION['config']`.

Opções:
- **A. Go lê a sessão PHP** (mesmo cookie): exige ler os arquivos de sessão do PHP — frágil (dois save paths, formato proprietário). **Não recomendado.**
- **B. Auth nova no Go** (JWT ou sessão server-side em tabela/Redis) + novo admin. Conviver com a sessão PHP durante a transição.
- **C. Sessões em banco** (tabela `sessions`) usada por ambos.

**Recomendado:** **B + C** — criar `sessions` (ou Redis) no Go, emitir token para o novo admin; manter PHP intacto até aposentar o admin antigo. Não tentar compartilhar `$_SESSION`.

## 5. Risco de publicação duplicada (crítico)

PHP workers e Go workers não podem publicar o mesmo item. Mitigações:
- **Claim atômico:** `UPDATE ... SET status='processing' WHERE id=$1 AND status='pendente' RETURNING id` (transação) — só quem "pega" publica.
- **Transição controlada:** desligar o timer PHP **antes** de ligar o Go para o mesmo tipo de job (`systemctl stop/disable washiviana-*-publish.timer`), e vice-versa para rollback.
- **Idempotência:** checar `publicacoes_redes`/`post_id` antes de publicar.
- Testar em staging com contas de teste.

## 6. Python: quando (e como) entrar

- **Não** no início: hoje IA = HTTP para provedores LLM (Go faz bem).
- Entrar quando houver: inferência local, **embeddings/vector (pgvector)**, RAG, processamento de imagem, avaliação de modelos.
- **Interface:** sidecar HTTP interno (`127.0.0.1:8090`) com contrato claro; Go chama via HTTP e mantém a abstração `ai` (mesmo padrão de `getLlmProviderByType`).
- **Não** acoplar Python ao banco diretamente sem necessidade; expor via API do sidecar.

## 7. Critérios de aceitação (paridade)

- Contract tests PHP × Go para cada endpoint migrado (mesmo JSON, mesmos códigos).
- Fluxo ponta a ponta no staging: criar artigo → gerar IA → traduzir/SEO → agendar → publicar (conta de teste) → métricas.
- Site público sem regressão (smoke test nas páginas principais pt/en/es + sitemap).
- Nenhuma migração de schema destrutiva.

## 8. Rollback

| Elemento | Como reverter |
|---|---|
| Rota nginx | Reapontar `proxy_pass` para PHP (ou remover `location`) |
| Código Go | Parar serviço; PHP continua atendendo |
| Schema | Migrations aditivas não exigem rollback; se necessário, coluna fica órfã sem quebrar |
| Worker | Reabilitar timer PHP correspondente |
| Dados | Restore do backup (`pg_restore`) — último recurso |

## 9. Estimativas (ordem de grandeza)

- Fase 0: dias.
- Fase 1: 1–2 semanas.
- Fase 2 (admin novo incluso): 3–6 semanas.
- Fase 3: variável (workers + opcional site público).

> Ajustar após a Fase 0, com o contrato mapeado e medido.
