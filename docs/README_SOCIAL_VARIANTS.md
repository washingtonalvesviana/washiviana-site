# Social Variants (Instagram/Facebook) - Backup & Migração

Passos seguros para aplicar a migração e testar em staging.

## 1) Backup (obrigatório)
Execute no servidor/staging com variáveis de ambiente:

PGHOST=... PGPORT=... PGDATABASE=... PGUSER=... PGPASSWORD=... ./scripts/backup_db_for_migration.sh

Isso criará um diretório `backups/migration_backup_TIMESTAMP` com o dump completo e dump das tabelas críticas.

## 2) Aplicar migração em staging
Nunca rode diretamente em produção sem backup e confirmação.

PGHOST=... PGPORT=... PGDATABASE=... PGUSER=... PGPASSWORD=... ./scripts/apply_migration_staging.sh

O script fará um backup rápido antes de aplicar a migração.

## 3) Testes automatizados (staging)
Após aplicar migração, execute:

php tests/test_social_variants.php

O script criará um artigo temporário, inserirá uma variante social, verificará CRUD e fará cleanup.

## 4) Próximos passos após testes OK
- Implementar publicação via Instagram/Facebook Graph API (revisar permissões, tokens e fluxos de review). **Implementação inicial já adicionada**: funções de publicação para Instagram e Facebook foram criadas (`api/instagram.php`, `api/facebook.php`) e está disponível um endpoint para publicar uma variante imediatamente (`api/artigos.php?action=publish_social_variant&id={variant_id}`).
- No Admin: é possível gerar e salvar variantes e usar o botão **Publicar** ao lado de cada variante para publicar manualmente (interface em `admin/artigos.php`).
- Implementar UI para agendamento de publicação (reaproveitar campo `scheduled_at` em `artigos_social_variants`).
- Implementar pipeline de vídeo (FFmpeg) e opção de usar galeria de imagens como referência (planejado).

**Observações importantes**:
- Para publicar automaticamente é necessário configurar as credenciais no admin → Redes Sociais (Instagram/Facebook).  
  - Instagram: `access_token` (Page Access Token) e `page_id` (Instagram Business Account ID).  
  - Facebook: `access_token` (Page Access Token) e `page_id` (Facebook Page ID).
- Use `facebook_api_version` no `configuracoes` se quiser definir versão diferente do Graph API (padrão `v17.0`).

---

## Checklist de testes (ordem recomendada)

Siga os passos abaixo e marque cada etapa como concluída antes de prosseguir para a próxima. Esta sequência garante que cada componente seja validado isoladamente e em conjunto.

1) Preparar App no Meta (Facebook developers)
   - [ ] Criar App no Facebook Developers (tipo "Business"/"None").
   - [ ] Ativar product **Instagram Graph API** e **Facebook Login**.
   - [ ] Em Settings → Basic, copiar **App ID** e **App Secret** e colar em Admin → Redes Sociais.
   - [ ] Definir Redirect URI: `<?php echo BASE_URL; ?>/api/oauth_callback.php?rede=instagram` (adicionar também para facebook se desejar).
   - [ ] Em App → Roles, adicione testers/test users para testes iniciais.

2) Autenticar via Admin (OAuth)
   - [ ] No Admin → Redes Sociais, clique em **Conectar Instagram**, autorize o app na nova janela.
   - [ ] Verificar mensagem de sucesso na janela de callback.
   - [ ] Confirmar em Admin que o **Access Token** e **Page ID** foram gravados automaticamente (campo Access Token deve mostrar '✅ Conectado').

3) Testar publicação manual (Publicar agora)
   - [ ] Criar um artigo de teste no Admin (rascunho)
   - [ ] Gerar Variante Social (caption + imagens 1:1 e 9:16) usando o card de Social do artigo.
   - [ ] Salvar variante e definir status = **pronto** (no select de status da variante).
   - [ ] Clicar **Publicar** ao lado da variante (Preview → Publicar). Confirmar retorno de sucesso e checar `publicacoes_redes` no DB para registro do post.
   - [ ] Conferir no Instagram/Facebook se o post apareceu na conta/Page configurada.

4) Testar agendamento automático (Worker)
   - [ ] Salvar variante com `scheduled_at` = agora ou alguns minutos no passado, status = **pronto**.
   - [ ] Aguarde até 1 minuto (systemd timer roda a cada minuto).
   - [ ] Verificar `publicacoes_redes` e status da variante (deve ser `publicado` em sucesso).
   - [ ] Verificar logs do service (`sudo journalctl -u washiviana-social-publish.service -n 200`) para mensagens de execução.

Notificações de falhas
- Você pode configurar um email em Admin → Configurações → <strong>Email para notificações</strong> (campo `notify_email`). Quando um job de vídeo falhar repetidamente, uma notificação será enviada para esse email com detalhes do job e a mensagem de erro.
- Se você usa Sentry e tiver o SDK instalado, coloque o DSN em Admin → Configurações → <strong>Sentry DSN</strong>. O worker tentará enviar um evento de erro para o Sentry quando disponível.

5) Testar geração de vídeo (Reels)
   - [ ] No preview da variante, clique em **Gerar Vídeo (Reels)** (agora enfileira um job assíncrono).
   - [ ] Você também pode enfileirar diretamente na lista de variantes com o botão **🎬 Gerar Vídeo** ao lado de cada variante.
   - [ ] O endpoint `api/videos.php?action=enqueue_from_variant` retorna `job_id`. Use `api/videos.php?action=job_status&job_id=...` para checar o estado.
   - [ ] Execute o worker manualmente (debug): `php ./scripts/video_worker.php` ou habilite o systemd timer (ex.: `washiviana-video-worker.timer`).
   - [ ] Ao concluir, o job terá `status='success'` e `output_file` com o arquivo em `/uploads`.
   - [ ] O Admin mostrará o status do job na lista de variantes (⏳ pending/processing → ✅ success) e um link para reproduzir o vídeo.
   - [ ] Teste publicar o vídeo como variante (set `media_type='video'` / status = `pronto` e usar Publish manual).

### Systemd unit (exemplo)

Você pode adicionar os seguintes arquivos no servidor em `/etc/systemd/system` e habilitar o timer:

- `/etc/systemd/system/washiviana-video-worker.service`

```ini
[Unit]
Description=Washiviana Video Worker
After=network.target

[Service]
Type=oneshot
WorkingDirectory=/home/washi/washiviana-site
ExecStart=/usr/bin/php ./scripts/video_worker.php

[Install]
WantedBy=multi-user.target
```

- `/etc/systemd/system/washiviana-video-worker.timer`

```ini
[Unit]
Description=Run washiviana video worker every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min

[Install]
WantedBy=timers.target
```

Comandos úteis:
- `sudo systemctl daemon-reload && sudo systemctl enable --now washiviana-video-worker.timer`
- Checar logs: `sudo journalctl -u washiviana-video-worker.service -n 200`

Instalação do FFmpeg (se não estiver presente)
- Debian/Ubuntu: `sudo apt update && sudo apt install -y ffmpeg`
- Alternativa via snap: `sudo snap install ffmpeg`

> Nota: o worker verifica a presença de `ffmpeg` no PATH antes de processar jobs e retorna erro legível se faltar a dependência.

6) Observações finais
   - Se algo falhar, cole o erro do `publicacoes_redes.erro_mensagem` ou dos logs do worker e eu analiso.
   - Para testes em produção, lembre-se de revisar permissões do App (review) caso queira publicar para públicos externos.

---

Se preferir, eu executo estas etapas de teste em staging e trago um relatório detalhado para cada passo (logs, screenshots, exemplo de posts). Quer que eu execute os testes de autenticação e publicação em staging agora? (responda sim/não).

## Operações do Worker (systemd) e resolução de problemas 🔧

- Unit e timer: `washiviana-social-publish.service` e `washiviana-social-publish.timer` (rodando a cada minuto por padrão).

Comandos úteis:
- Checar status do timer: `sudo systemctl status washiviana-social-publish.timer` ✅
- Rodar o worker manualmente (debug): `php ./scripts/worker_publish_scheduled.php` 🔁
- Ver logs do último run: `sudo journalctl -u washiviana-social-publish.service -n 200`
- Reiniciar timer (após alterações nas units): `sudo systemctl daemon-reload && sudo systemctl restart washiviana-social-publish.timer`

Problemas comuns e correções rápidas:
- Mensagens de `Constant already defined`: ocorreu em alguns runs iniciais devido a `define()` duplicado em arquivos de config. Eu já incluí guards `if (!defined(...))` nas principais constantes (`BASE_URL`, `SESSION_TIMEOUT`, `LINKEDIN_API_VERSION`, etc.). Se ainda aparecerem avisos, verifique se não existem includes duplicados do `config.php` ou finais de scripts que chamem `require_once` de forma inesperada.
- Erros de permissão no migration: rodei backup e apliquei migration com superuser quando necessário. Em produção, peça ao DBA para revisar permissões antes de aplicar.

> Dica: para testar rapidamente sem publicar para a conta real, crie um Instagram test user/page no App do Facebook e use esses dados para validar o fluxo de publicação (tokens e Page access tokens são diferentes do Instagram Basic Display).
---

Se quiser, eu posso executar os passos 1 e 2 em staging agora (com suas credenciais/variáveis de ambiente), e depois rodar os testes e enviar relatório detalhado. Não tocarei em produção sem sua confirmação explícita.