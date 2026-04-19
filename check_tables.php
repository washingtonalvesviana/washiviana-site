<?php
$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');
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
