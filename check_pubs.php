<?php
$pdo = new PDO('getenv('PG_DSN') ?: 'pgsql:host=YOUR_HOST;port=5432;dbname=YOUR_DB'', getenv('PG_USER') ?: 'YOUR_DB_USER', getenv('PG_PASS') ?: 'YOUR_DB_PASSWORD');

echo "=== PUBLICAÇÕES ANTERIORES ===\n";
$r = $pdo->query('SELECT * FROM publicacoes_redes ORDER BY id DESC LIMIT 5');
$pubs = $r->fetchAll(PDO::FETCH_ASSOC);
print_r($pubs);

echo "\n=== DELETANDO PUBLICAÇÕES PARA PERMITIR REPUBLICAR ===\n";
// Se quiser republicar, precisa limpar o registro anterior
// $pdo->exec('DELETE FROM publicacoes_redes WHERE rede = \'linkedin\'');
// echo "Registros deletados\n";
