#!/usr/bin/env bash
# Aplicar migração em ambiente de staging (NUNCA execute diretamente em produção sem backup e confirmação)
# Uso: PGHOST=... PGPORT=... PGDATABASE=... PGUSER=... PGPASSWORD=... ./apply_migration_staging.sh
set -euo pipefail
MIGRATION_FILE="$(dirname "$0")/../migrations/013_artigos_social_variants.sql"
if [ ! -f "$MIGRATION_FILE" ]; then
  echo "Arquivo de migração não encontrado: $MIGRATION_FILE"
  exit 1
fi

# Criar backup rápido antes de aplicar (dump das tabelas afetadas)
echo "Gerando backup rápido das tabelas afetadas..."
BACKUP_DIR="./backups/migration_preapply_$(date +%Y%m%dT%H%M%S)"
mkdir -p "$BACKUP_DIR"
pg_dump --format=plain --no-owner --no-privileges -f "$BACKUP_DIR/tables_before_migration.sql" \
  --host="${PGHOST:-localhost}" --port="${PGPORT:-5432}" --username="${PGUSER:-postgres}" "${PGDATABASE:-washiviana}" \
  --table=artigos --table=publicacoes_redes --table=redes_sociais_config || true

# Aplicar migração em uma transação (Postgres executará comandos DDL mesmo em transação, mas usamos por segurança)
echo "Aplicando migração: $MIGRATION_FILE"
psql "host=${PGHOST:-localhost} port=${PGPORT:-5432} dbname=${PGDATABASE:-washiviana} user=${PGUSER:-postgres} password=${PGPASSWORD:-}" -v ON_ERROR_STOP=1 -f "$MIGRATION_FILE"

echo "Migração aplicada com sucesso em staging (verifique logs e execute os testes)."

echo "Agora execute: php tests/test_social_variants.php para rodar os testes automatizados (veja o README)."