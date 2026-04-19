#!/usr/bin/env bash
# Backup do banco antes de aplicar migração
# Uso: PGHOST=... PGPORT=... PGDATABASE=... PGUSER=... PGPASSWORD=... ./backup_db_for_migration.sh
set -euo pipefail
TIMESTAMP=$(date +%Y%m%dT%H%M%S)
OUTDIR="./backups/migration_backup_${TIMESTAMP}"
mkdir -p "$OUTDIR"

# Backup completo (dump do banco inteiro)
echo "Fazendo dump completo do banco para $OUTDIR/full_dump.sql"
pg_dump --format=plain --no-owner --no-privileges --verbose -f "$OUTDIR/full_dump.sql" \
  --host="${PGHOST:-localhost}" --port="${PGPORT:-5432}" --username="${PGUSER:-postgres}" "${PGDATABASE:-washiviana}"

# Backup específico das tabelas críticas (artigos, publicacoes_redes, redes_sociais_config)
echo "Fazendo dump de tabelas específicas: artigos, publicacoes_redes, redes_sociais_config"
pg_dump --format=plain --no-owner --no-privileges --verbose -f "$OUTDIR/tables_artigos_publicacoes.sql" \
  --host="${PGHOST:-localhost}" --port="${PGPORT:-5432}" --username="${PGUSER:-postgres}" "${PGDATABASE:-washiviana}" \
  --table=artigos --table=publicacoes_redes --table=redes_sociais_config --table=artigos_social_variants || true

echo "Backup concluído em: $OUTDIR"

echo "Observações importantes:"
echo " - Este script exige que as variáveis PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD estejam definidas no ambiente."
echo " - Se você estiver rodando em um servidor, execute com um usuário que tenha permissão para criar dumps."

echo "Para restaurar (exemplo): psql -U user -h host -d target_db -f $OUTDIR/tables_artigos_publicacoes.sql"
