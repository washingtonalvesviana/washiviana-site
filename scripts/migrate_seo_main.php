<?php
require_once __DIR__ . '/../api/config.php';

echo "Iniciando migração de SEO para tabelas principais...\n";

$tables = ['artigos', 'projetos'];
$columns = [
    'meta_title' => 'VARCHAR(255)',
    'meta_description' => 'TEXT',
    'og_title' => 'VARCHAR(255)',
    'og_description' => 'TEXT',
    'keywords' => 'TEXT',
    'schema_jsonld' => 'TEXT'
];

foreach ($tables as $table) {
    echo "Processando tabela: $table\n";
    foreach ($columns as $column => $type) {
        try {
            // Verificar se a coluna já existe
            $check = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = ?");
            $check->execute([$table, $column]);
            if ($check->fetch()) {
                echo "  - Coluna $column já existe em $table. Pulando.\n";
                continue;
            }

            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
            echo "  + Coluna $column adicionada a $table.\n";
        } catch (Exception $e) {
            echo "  ! Erro ao adicionar $column a $table: " . $e->getMessage() . "\n";
        }
    }
}

echo "Migração concluída.\n";
