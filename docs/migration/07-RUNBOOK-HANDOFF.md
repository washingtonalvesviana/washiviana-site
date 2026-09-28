# 07 — Runbook & Handoff

> Guia operacional para o próximo agente. **Produção.** Toda ação que altere o sistema deve ter rollback definido.

## 1. Acessos e caminhos

| Item | Caminho / valor |
|---|---|
| Projeto (real) | `/var/www/washiviana.com` |
| Deploy (symlink nginx) | `/home/washi/washiviana-site -> /var/www/washiviana.com` |
| Config DB (segredos) | `api/config.local.php` (**não versionado**) |
| Uploads | `uploads/` (~1.8 GB) |
| Migrations | `migrations/` |
| Workers systemd | `/etc/systemd/system/washiviana-*.{service,timer}` |
| Nginx | `/etc/nginx/sites-available/washiviana.com` |
| PHP-FPM | `php8.3-fpm` (socket `unix:/run/php/php8.3-fpm.sock`) |
| Sessões PHP | ver §5 (dois caminhos possíveis) |
| Planejamento GSD | `.planning/` (PROJECT, REQUIREMENTS, ROADMAP, STATE) |

**Segredos** (não colar em docs/commits): senha do banco, `*_api_key` (Gemini/OpenAI/DeepSeek/OpenRouter/Anthropic/Ollama), tokens OAuth (LinkedIn/Meta/TikTok/YouTube) em `redes_sociais_config`. Chaves de config ficam na tabela `configuracoes` (lista em `02`).

## 2. Estado atual do repositório (2026-09-28)

- Branch: `master`.
- **Há alterações não commitadas** (correções recentes aplicadas em produção, ainda não commitadas):
  - `api/gemini.php`, `api/llm-models.php`, `api/configuracoes.php`, `api/i18n_seo.php`, `api/i18n_site.php` — base URL OpenAI-compatível + `enable_thinking=false`.
  - `admin/artigos.php` — validação de salvar + spinners.
  - `assets/js/admin.js`, `assets/css/admin.css` — indicador global de processamento (`WVLoading`).
  - `admin/index.php`, `admin/senha.php` — spinners.
  - `admin/redes-sociais.php` — aviso de token LinkedIn expirado.
  - Novos docs: `docs/migration/*`, `docs/*copy-pack*`, `docs/prompts-imagens-artigos.md`.
- **Verificar com o usuário antes de commitar** (o projeto exige pedido explícito para commit).

## 3. Operação do dia a dia

```bash
# Ver timers e status dos workers
systemctl list-timers --all | grep -i washi
systemctl status washiviana-social-publish.service

# Rodar um worker manualmente (debug)
cd /home/washi/washiviana-site
sudo -u washi php scripts/worker_publish_scheduled.php

# Logs
journalctl -u washiviana-social-publish.service -n 100 --no-pager
tail -f /var/log/nginx/washiviana.error.log

# Testar conexão do banco (sem expor senha)
php -r 'require "api/config.php"; echo $pdo->query("SELECT 1")->fetchColumn(), PHP_EOL;'
```

- Recarregar PHP sem downtime: `sudo systemctl reload php8.3-fpm`.
- Recarregar nginx após mudar config: `sudo nginx -t && sudo systemctl reload nginx`.

## 4. Backup / restore

```bash
# Backup (usar antes de qualquer mudança de schema/deploy)
bash scripts/backup_db_for_migration.sh

# Restore (último recurso) — conferir o formato gerado pelo script antes
# pg_restore -d washiviana <arquivo>
```
**Sempre** validar o backup em staging antes de migrar dados.

## 5. Pegadinhas conhecidas (ler antes de depurar)

1. **Cache de configuração na sessão:** `getConfig()` guarda em `$_SESSION['config']`. Alterar o banco não reflete até a sessão expirar/limpar. Sintoma típico: "troquei no banco e continua o valor antigo". Solução: salvar via admin (limpa o cache) ou novo login.
2. **Dois diretórios de sessão:** `/tmp/washiviana_sessions` (criado por root, 0700) e o fallback `/var/lib/php/sessions` (usado pelo `www-data`). O login pode gravar no segundo. Ver headers `X-Washiviana-Session-*` em `admin/index.php`.
3. **Deploy por symlink:** editar `/var/www/washiviana.com` reflete em produção na hora — **não há build de PHP**. Cuidado com edições "de teste".
4. **OPcache:** `validate_timestamps=On` (revalida ~2s) — mudanças de PHP entram sozinhas; não é necessário reiniciar o FPM por isso.
5. **Uploads grandes:** `uploads/` tem ~1.8 GB; não incluir no git nem em deploys que copiem a árvore toda.
6. **`/api/config*.php` bloqueado no nginx** — não tente acessar por URL.
7. **`api/artigos.php` é gigante (2300+ linhas)** e mistura controller + regra. Alterações ali pedem testes de publicação.
8. **Token LinkedIn expira em 60 dias.** Estado em 2026-09-28: **expirado em 2026-02-06** e sem `refresh_token` — requer reautorização OAuth no admin (`Redes Sociais`). A tela agora avisa.

## 6. Rotina de deploy (padrão atual)

1. `bash scripts/backup_db_for_migration.sh`
2. Aplicar mudanças (código/migrations compatíveis).
3. `php -l` nos arquivos alterados; smoke test das páginas principais (pt/en/es) e do admin.
4. `sudo systemctl reload php8.3-fpm` (se necessário).
5. Monitorar `journalctl` e `nginx.error.log` por alguns minutos.

## 7. Antes de começar a migração (checklist)

- [ ] Ler `README`, `01`–`06`.
- [ ] Rodar backup e validar restore em staging.
- [ ] Confirmar versões: `go version`, `python3 --version`, `node -v`.
- [ ] Definir estratégia de auth (§4 do plano) com o usuário.
- [ ] Congelar/expandir o contrato (`03` + `openapi.yaml`).
- [ ] Garantir que PHP e Go **nunca** publiquem o mesmo job ao mesmo tempo.

## 8. Não fazer

- Não alterar schema de forma destrutiva (o site público PHP depende dele).
- Não commitar segredos nem `api/config.local.php`.
- Não rodar dois workers de publicação simultâneos para o mesmo tipo de job.
- Não apontar `/api/` ou `/admin/` para o Go antes da paridade validada.
- Não assumir que a dor é só backend: a **UI do admin** é o problema visível; o plano cobre o admin novo na Fase 2.

## 9. Como retomar o trabalho

1. `git status` e revisar as alterações não commitadas.
2. Ler `.planning/STATE.md` e `docs/migration/README.md`.
3. Escolher a próxima tarefa pelo plano (`05`), começando pela **Fase 0**.
4. Abrir PRs pequenas e revisáveis; validar em staging antes de produção.

## 10. Serviço Go (Fase 0 — implementado)

- Código: `backend-go/` (module `washiviana/backend`, Go 1.22, pgx v5.6.0).
- Binário: `/home/washi/washiviana-go/washiviana-api`
- Ambiente (segredos): `/home/washi/washiviana-go/api.env` (perm 600, **fora do webroot**)
- Banco apontado hoje: **`washiviana_staging`** (snapshot de produção).
- Porta: **8081** (endpoints `/health`, `/api/v1/categorias`).

```bash
# Build
export GOTOOLCHAIN=local
go -C backend-go vet ./...
go -C backend-go build -ldflags "-X main.version=0.1.0" -o /home/washi/washiviana-go/washiviana-api ./cmd/api

# Rodar (foreground, para debug)
set -a; . /home/washi/washiviana-go/api.env; set +a
/home/washi/washiviana-go/washiviana-api

# Smoke (health é aberto; /api/v1/* exige X-Internal-Token)
curl -s http://127.0.0.1:8081/health
set -a; . /home/washi/washiviana-go/api.env; set +a
curl -s -H "X-Internal-Token: $INTERNAL_TOKEN" "http://127.0.0.1:8081/api/v1/categorias?target=projetos"

# Contract tests PHP x Go + auth smoke (Fase 2a) — requer o serviço rodando
python3 backend-go/test/contract/contract_test.py

# Testes de escrita (Fase 2b) — CRUD no STAGING, com cleanup
python3 backend-go/test/contract/write_test.py           # categorias
python3 backend-go/test/contract/write_artigos_test.py   # artigos
python3 backend-go/test/contract/write_projetos_test.py  # projetos
python3 backend-go/test/contract/write_config_test.py   # configurações (restaura valores)
```

> **Escritas só no staging.** O serviço aponta para `washiviana_staging`; jamais aponte `write_test.py` para produção.

> O serviço está em `127.0.0.1:8081` (não exposto). `/api/v1/*` aceita sessão (cookie) ou `X-Internal-Token`.

### Sessões (Fase 2a)

- Migration `migrations/016_sessions.sql` — **aplicada apenas em `washiviana_staging`**:
  ```bash
  PGPASSWORD=*** psql -h localhost -U postgres -d washiviana_staging -f migrations/016_sessions.sql
  ```
- Usuário de teste (somente staging): `contract-test@staging.local` / senha de teste (não existe em produção).
- `COOKIE_SECURE=1` e `SESSION_TTL_HOURS=168` são lidos do `api.env`.
- **Não aplicar `016` em produção** antes do cutover da Fase 2(b).

> **Segurança:** o diretório `backend-go/` está bloqueado no nginx (`location ^~ /backend-go/ { return 404; }`). Sem isso, `.go`/`go.mod` seriam servidos como estáticos. Não remover esse bloqueio.

## 11. Guardrails de segurança adicionados

- Dumps do banco ficam em `/home/washi/backups/` (fora do webroot).
- Backups de config nginx em `/home/washi/backups/nginx-washiviana.com.bak.*`.
- Antes de publicar qualquer código Go dentro do webroot, garantir o `location ^~ /backend-go/` no nginx.
- Ao criar novos diretórios sensíveis no repo, adicionar regra `return 404` equivalente.

## 12. Admin novo (Fase 2c)

- SPA estática em `admin-next/`, servida em **`https://washiviana.com/admin-next/`**.
- API em **`https://washiviana.com/go-api/`** (proxy → `127.0.0.1:8081`). Exige sessão.
- Login: usuário real do admin (tabela `usuarios`). Em staging há o usuário de teste `contract-test@staging.local`.
- Backup do nginx antes de novas rotas: `/home/washi/backups/nginx-washiviana.com.bak.*` + `nginx -t` + `systemctl reload nginx`.

```bash
# smoke público
curl -s https://washiviana.com/go-api/health
curl -s -o /dev/null -w "%{http_code}\n" https://washiviana.com/admin-next/

# login via rota pública
curl -s -c /tmp/wv.cookies -X POST https://washiviana.com/go-api/api/v1/auth/login \
  -H 'Content-Type: application/json' -d '{"email":"...","password":"..."}'
```

> **Pré-cutover:** definir `COOKIE_SECURE=1` no `api.env` (o site é HTTPS-only). Durante testes locais (HTTP) mantenha `0`.

## 12.1 Produção Go (API + worker) — ATIVO

- **API Go de produção**: `washiviana-go-api.service` (systemd), `127.0.0.1:8082`, env `/home/washi/washiviana-go/api-prod.env` (600, `COOKIE_SECURE=1`, banco `washiviana`).
- Nginx `/go-api/` → `127.0.0.1:8082`. O admin novo (`/admin-next/`) opera sobre **produção**.
- **Staging** continua em `:8081` (background/`api.env`, banco `washiviana_staging`) — usado pelos testes.

```bash
systemctl status washiviana-go-api.service
journalctl -u washiviana-go-api.service -n 50 --no-pager
curl -s https://washiviana.com/go-api/health   # deve mostrar "env":"production"
```

- **Rollback da API**: no nginx, voltar `proxy_pass` de `/go-api/` para `http://127.0.0.1:8081/` (ou remover o location) e `systemctl reload nginx`.

## 12.2 Worker Go em produção (publish-scheduled)

- Unit/timer: `washiviana-go-publish-scheduled.{service,timer}` (1 min), usa `api-prod.env`.
- O timer PHP `washiviana-social-publish.timer` foi **desabilitado** no cutover (evitar duplicação).

```bash
# rollback do worker (voltar ao PHP)
sudo systemctl disable --now washiviana-go-publish-scheduled.timer
sudo systemctl enable --now washiviana-social-publish.timer
```

> `metrics` e `video` continuam em PHP. Publicação real em redes segue bloqueada por tokens OAuth.

## 13. Workers em Go (Fase 3d)

- Binário: `/home/washi/washiviana-go/washiviana-worker` (fonte em `backend-go/cmd/worker`).
- Subcomandos:
  - `publish-scheduled [--limit N]` — claim atômico de variantes agendadas.
  - `metrics [--rede=] [--limit=] [--dry-run]` — coleta de métricas (fetch por rede portado; não validado com tokens reais).
  - `video [--limit=] [--dry-run]` — **só dry-run**; executor de vídeo (FFmpeg + provedores) ainda não portado (execução real bloqueada de propósito).
- Lê o ambiente do mesmo `api.env` (banco `washiviana_staging` hoje).

```bash
set -a; . /home/washi/washiviana-go/api.env; set +a
/home/washi/washiviana-go/washiviana-worker publish-scheduled --limit 10
python3 backend-go/test/contract/worker_test.py          # concorrência/claim atômico
python3 backend-go/test/contract/metrics_worker_test.py  # métricas (dry-run/falha graciosa)
python3 backend-go/test/contract/video_worker_test.py    # vídeo (dry-run/bloqueio)
```

### Cutover (NÃO fazer sem parar o timer PHP — risco de duplicação)

```bash
# 1) PARAR o timer PHP correspondente (exemplo: variantes agendadas)
sudo systemctl stop washiviana-social-publish.timer
sudo systemctl disable washiviana-social-publish.timer
# 2) Só então habilitar um timer equivalente para o worker Go (a criar).
#    Rollback: reabilitar o timer PHP e parar o Go.
```

> O claim é atômico (SKIP LOCKED + mudança de status), mas **não** rode os dois motores em produção ao mesmo tempo.
> **Migrations `016` e `017` já foram aplicadas em produção** (2026-09-28, com backup).
