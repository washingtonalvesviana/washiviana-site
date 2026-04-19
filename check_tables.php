<?php
$pdo = new PDO('getenv('PG_DSN') ?: 'pgsql:host=YOUR_HOST;port=5432;dbname=YOUR_DB'', getenv('PG_USER') ?: 'YOUR_DB_USER', getenv('PG_PASS') ?: 'YOUR_DB_PASSWORD');
echo "Tabelas:\n";
$tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
foreach ($tables as $t) { 
    echo "- " . $t['table_name'] . "\n"; 
}

echo "\nConteúdo de configuracoes:\n";
$config = $pdo->query("SELECT * FROM configuracoes");
foreach ($config as $c) {
    echo json_encode($c, JSON_PRETTY_PRINT) . "\n";
}
