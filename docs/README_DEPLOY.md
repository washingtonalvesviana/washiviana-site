# Deploy e Operação — Washiviana Site

Guia de instalação, configuração e operação. O sistema roda em **Nginx + PHP-FPM** com **PostgreSQL**; também suporta MySQL e Apache (via `.htaccess`).

> Produção atual: repositório em `/var/www/washiviana.com`, com symlink `/home/washi/washiviana-site` (usado pelas units systemd).

---

## 1. Pré-requisitos

### Servidor

- **PHP 8.0+** (testado em 8.3) com extensões: `pdo`, `pdo_pgsql` (ou `pdo_mysql`), `curl`, `mbstring`, `gd`, `fileinfo`, `json`.
- **PostgreSQL 14+** (ou MySQL 5.7+).
- **Nginx + PHP-FPM** (cenário de produção) ou Apache com `mod_rewrite`.
- **FFmpeg** no `PATH` — necessário apenas para o worker de vídeo.

```bash
sudo apt update && sudo apt install -y ffmpeg
```

### Opcional

- Node.js 18+ apenas para o subprojeto `tailormade/`.

---

## 2. Obter o código

```bash
cd /var/www
git clone <url-do-repositorio> washiviana.com
cd washiviana.com
```

Opcional (mantém o path esperado pelas units systemd):

```bash
ln -s /var/www/washiviana.com /home/washi/washiviana-site
```

---

## 3. Banco de dados

### PostgreSQL (produção)

```bash
sudo -u postgres psql -c "CREATE DATABASE washiviana;"
sudo -u postgres psql -d washiviana -f database/schema_postgres.sql
sudo -u postgres psql -d washiviana -f database/seed.sql
```

Aplique as migrations na ordem (são incrementais e idempotentes na maioria dos casos):

```bash
for f in migrations/0*.sql; do
  echo "== $f =="
  sudo -u postgres psql -d washiviana -f "$f"
done
# migrations em PHP (002, 003, 004, 005) executam via CLI:
php migrations/002_criar_tabelas_redes.php
php migrations/003_add_person_urn.php
php migrations/004_agendamento_posts.php
php migrations/005_add_organization_urn.php
```

> As migrations `.sql` são PostgreSQL-first: aplique-as pelo `psql` e as migrations `.php` pelo CLI (elas usam `api/config.local.php`).

> `012_grant_sequences.sql` garante permissões de sequência para o role da aplicação — execute-o com superusuário ao usar um usuário não-dono do schema.

### MySQL

```bash
> O suporte a MySQL é legado e não é a produção atual; os scripts MySQL antigos estão em `database/legacy/`. Use PostgreSQL.
```

---

## 4. Configuração da aplicação

Crie **`api/config.local.php`** (não versionado):

```php
<?php
define('DB_DRIVER', 'pgsql');   // 'pgsql' ou 'mysql'
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'washiviana');
define('DB_USER', 'postgres');
define('DB_PASS', 'SUA_SENHA_FORTE');
define('DB_CHARSET', 'utf8');   // usado apenas no MySQL
```

Alternativamente, use variáveis de ambiente (`DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`).

A `BASE_URL` e o HTTPS são detectados automaticamente. Em produção atrás de proxy, garanta o header `X-Forwarded-Proto: https`.

### Permissões

```bash
chown -R www-data:www-data /var/www/washiviana.com
chmod -R 755 /var/www/washiviana.com
chmod -R 775 /var/www/washiviana.com/uploads
```

O save path de sessão é criado automaticamente em `sys_get_temp_dir()/washiviana_sessions` (fora do webroot).

---

## 5. Servidor web

### Nginx + PHP-FPM (recomendado)

- Sirva arquivos estáticos (`assets/`, `uploads/`) diretamente.
- Encaminhe `*.php` para o PHP-FPM.
- Rotas limpas (`/pt/...`, `/en/...`) são tratadas pelo `index.php` (front controller); configure `try_files` para o `index.php` quando o caminho não for um arquivo real.

Habilite compressão de assets (por padrão o Nginx comprime apenas HTML):

```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_min_length 256;
gzip_types text/plain text/css application/json application/javascript application/x-javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;
```

Bloqueie o acesso HTTP a caminhos sensíveis:

```nginx
location ~ ^/(api/config\.php|api/config\.local\.php|database.*\.sql|migrations/|scripts/|tests/|outros/|\.git/) {
    deny all;
    return 404;
}
location ~ /\.(?!well-known) { deny all; }
```

> **Importante:** os diretórios `scripts/`, `migrations/`, `tests/` e `outros/` e os scripts de token na raiz **não devem ser acessíveis pela web**.

### Apache

O `.htaccess` incluso já define rewrite, HTTPS, headers de segurança (CSP, `X-Frame-Options`), cache e limites de upload. Habilite `mod_rewrite`, `mod_headers` e `mod_expires`. O acesso é servido pelo `index.php`.

---

## 6. CSS (Tailwind)

O CSS consumido em produção é o arquivo **compilado e versionado** `assets/css/tailwind.min.css`; a entrada é `assets/css/tailwind-input.css`.

Hoje **não há configuração de build na raiz** do repositório. Se precisar recompilar (por exemplo, ao adicionar classes), crie uma configuração Tailwind apontando para os templates PHP e gere o arquivo, por exemplo:

```bash
npx tailwindcss -c tailwind.config.js -i assets/css/tailwind-input.css -o assets/css/tailwind.min.css --minify
```

Depois, incremente o parâmetro `?v=` das referências em `admin/includes/header.php` e nos templates públicos para quebrar cache.

---

## 7. Workers e agendamento

As units/timers de exemplo estão em `scripts/systemd/`. Instale como serviço do sistema:

```bash
sudo cp scripts/systemd/washiviana-articles-publish.* /etc/systemd/system/
sudo cp scripts/systemd/washiviana-social-publish.* /etc/systemd/system/
sudo cp scripts/systemd/washiviana-metrics.* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now washiviana-articles-publish.timer
sudo systemctl enable --now washiviana-social-publish.timer
sudo systemctl enable --now washiviana-metrics.timer
```

Os arquivos assumem path `/home/washi/washiviana-site` e usuário `washi` — ajuste `User` e `WorkingDirectory` se o seu ambiente for diferente.

### Vídeo (Reels)

O `video_worker.php` também pode rodar por timer/cron. Ele:
- consome jobs `pending` de `video_jobs`;
- usa o provider de vídeo configurado e faz **fallback para FFmpeg**;
- notifica falhas por e-mail (`notify_email`) e, se configurado, no Sentry;
- requer `ffmpeg` no `PATH` (valida antes de processar).

### Métricas sociais

O `metrics_worker.php` coleta métricas (curtidas/comentários/alcance) das publicações e grava snapshots em `metricas_publicacoes`. Roda por timer a cada 6h. Requer token válido e permissões de insights de cada rede; falhas são registradas sem interromper o worker.

```bash
php scripts/metrics_worker.php --rede=linkedin --limit=20   # execução manual
```

### Alternativa via cron

```cron
* * * * * /usr/bin/php /var/www/washiviana.com/scripts/worker_publish_articles.php
* * * * * /usr/bin/php /var/www/washiviana.com/scripts/worker_publish_scheduled.php
* * * * * /usr/bin/php /var/www/washiviana.com/scripts/video_worker.php
0 */6 * * * /usr/bin/php /var/www/washiviana.com/scripts/metrics_worker.php
```

Verificações úteis:

```bash
sudo systemctl status washiviana-social-publish.timer
php scripts/worker_publish_scheduled.php        # execução manual (debug)
sudo journalctl -u washiviana-social-publish.service -n 200
```

---

## 8. Primeiro acesso e integrações

1. No admin (`/admin/`), faça login com o usuário semeado em `database/seed.sql` (e-mail `contact@washiviana.com`) e **troque a senha imediatamente**.
2. Em **Admin → Configurações**, defina: dados do site, `notify_email`, provedor/modelo de IA e as chaves de API.
3. Em **Admin → Redes Sociais**, configure as credenciais:
   - **LinkedIn**: client id/secret, tokens e escolha entre perfil pessoal ou organização.
   - **Instagram/Facebook**: Page Access Token e Page ID; opcionalmente `facebook_api_version` (padrão `v17.0`).
   - Redirect URI do callback: `https://SEU_DOMINIO/api/oauth_callback.php?rede=instagram` (e `?rede=facebook` se aplicável).

Consulte [README_SOCIAL_VARIANTS.md](README_SOCIAL_VARIANTS.md) para o roteiro completo de teste (OAuth, publicação manual, agendada e vídeo).

---

## 9. Atualização (deploy de nova versão)

```bash
cd /var/www/washiviana.com
git pull
# aplique novas migrations, se houver
for f in migrations/<novas>.sql; do sudo -u postgres psql -d washiviana -f "$f"; done
# se alterou CSS, rode o build do Tailwind e ajuste o ?v=
sudo systemctl reload php8.3-fpm   # ajuste a versão do PHP-FPM
```

Não é necessário reiniciar os workers para mudanças de código (eles rodam `oneshot` a cada minuto).

---

## 10. Segurança em produção

- [ ] `api/config.local.php` fora do versionamento e inacessível via web.
- [ ] Bloquear HTTP para `scripts/`, `migrations/`, `tests/`, `outros/`, `database*.sql` e `.git/`.
- [ ] Remover scripts de token/debug do servidor (`update_new_token.php`, `outros/test_*.php`).
- [ ] Senha do admin trocada; `display_errors` desligado.
- [ ] HTTPS ativo e cookies `Secure`.
- [ ] Backups regulares do PostgreSQL e de `uploads/`.
- [ ] Rotacionar quaisquer credenciais que já tenham sido expostas em texto plano.

---

## 11. Solução de problemas

| Sintoma | Verificações |
|---|---|
| "Cannot connect to database" | Credenciais em `api/config.local.php`; `DB_DRIVER` correto; banco acessível |
| Upload falha | Permissões de `uploads/` (775), limites no Nginx/PHP (`upload_max_filesize`, `post_max_size`) |
| IA não responde | Chave do provedor em Admin → Configurações; testar conexão; modelo válido |
| Worker não publica | `journalctl` da unit; status da variante (`pronto`) e `scheduled_at`; `publicacoes_redes.erro_mensagem` |
| Vídeo falha | `ffmpeg` no `PATH`; `last_error` em `video_jobs`; provider de vídeo configurado |
| 504 em geração i18n | Operação longa; gerar idiomas individualmente; revisar timeout do PHP-FPM |
| Imagem não aparece | Arquivo inexistente em `uploads/` (o template só exibe mídia existente); conferir nome no banco |

---

## 12. Checklist de deploy

- [ ] Banco criado e schema/migrations aplicados.
- [ ] `api/config.local.php` configurado.
- [ ] Permissões de arquivos e `uploads/` ajustadas.
- [ ] Nginx/Apache servindo o site e bloqueando paths sensíveis.
- [ ] FFmpeg instalado (se usar vídeo).
- [ ] Timers systemd habilitados.
- [ ] Login admin testado e senha trocada.
- [ ] Chaves de IA e redes sociais configuradas.
- [ ] Backup inicial do banco e de `uploads/`.
- [ ] Smoke test: home, listagem, detalhe de artigo/projeto, salvar no admin, publicar variante de teste.
