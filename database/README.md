# Banco de dados

Esta pasta concentra o schema e a carga inicial do banco.

## Modelo

| Papel | Arquivo | Descrição |
|---|---|---|
| **Fonte de verdade** | `schema_postgres.sql` | Esquema canônico PostgreSQL, gerado da produção com `pg_dump --schema-only --no-owner --no-privileges`. Use para instalações novas. |
| **Carga inicial** | `seed.sql` | Dados mínimos (usuário admin, categorias e configurações). Idempotente. |
| **Upgrade** | `../migrations/` | Migrations incrementais para evoluir instalações existentes. Append-only. |
| **Legado** | `legacy/` | Arquivos `.sql` antigos (MySQL e bootstrap PostgreSQL incompleto). Não usar. |

## Instalação nova (PostgreSQL)

```bash
psql -d washiviana -f database/schema_postgres.sql
psql -d washiviana -f database/seed.sql
```

## Instalação/upgrade existente

Instalações já criadas evoluem aplicando as migrations em `migrations/`:

```bash
for f in migrations/0*.sql; do psql -d washiviana -f "$f"; done
php migrations/002_criar_tabelas_redes.php
php migrations/003_add_person_urn.php
php migrations/004_agendamento_posts.php
php migrations/005_add_organization_urn.php
```

## Manutenção do schema canônico

Ao alterar o schema em produção (via migration aplicada), regenere o snapshot canônico:

```bash
pg_dump -h localhost -U postgres -d washiviana --schema-only --no-owner --no-privileges > database/schema_postgres.sql
```

Não edite `schema_postgres.sql` à mão.

## Observações

- `legacy/database.mysql.legacy.sql` e `legacy/database_artigos.mysql.legacy.sql` são scripts MySQL antigos, sem relação com a produção atual.
- `legacy/database_postgres.legacy.sql` era o bootstrap PostgreSQL original, porém incompleto/desatualizado (ex.: `artigos` sem `categoria_id` e com colunas inexistentes).
- A coluna morta `redes_sociais_config.token_expira_em` (duplicata de `token_expires_at`) foi removida pela migration `015_drop_token_expira_em.sql`.
