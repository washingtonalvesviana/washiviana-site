<?php
/**
 * Executa a migration 010_site_accesses.sql com segurança (CLI-only).
 * Uso:
 *   php scripts/migrate_site_accesses.php --confirm
 *   php scripts/migrate_site_accesses.php --dry-run
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$opts = getopt('', ['dry-run', 'confirm', 'file:']);
$migrationFile = $opts['file'] ?? (__DIR__ . '/../migrations/010_site_accesses.sql');

if (!is_file($migrationFile)) {
    fwrite(STDERR, "Migration não encontrada: {$migrationFile}\n");
    exit(1);
}

if (isset($opts['dry-run'])) {
    echo "-- DRY RUN --\n";
    echo file_get_contents($migrationFile);
    exit(0);
}

if (!isset($opts['confirm'])) {
    fwrite(STDERR, "Ação bloqueada. Reexecute com --confirm para aplicar a migration em produção.\n");
    exit(1);
}

require_once __DIR__ . '/../api/config.php';

function tableExists(PDO $pdo): bool {
    $driver = DB_DRIVER;
    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'site_accesses'");
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = 'site_accesses'");
    $stmt->execute([DB_NAME]);
    return (bool)$stmt->fetchColumn();
}

try {
    if (tableExists($pdo)) {
        echo "Tabela site_accesses já existe. Nada a fazer.\n";
        exit(0);
    }

    $sql = trim((string)file_get_contents($migrationFile));
    if ($sql === '') {
        fwrite(STDERR, "Migration vazia: {$migrationFile}\n");
        exit(1);
    }

    $pdo->exec($sql);
    echo "Migration aplicada com sucesso.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Erro ao aplicar migration: " . $e->getMessage() . "\n");
    exit(1);
}
