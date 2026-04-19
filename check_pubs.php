<?php
$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');

echo "=== PUBLICAÇÕES ANTERIORES ===\n";
$r = $pdo->query('SELECT * FROM publicacoes_redes ORDER BY id DESC LIMIT 5');
$pubs = $r->fetchAll(PDO::FETCH_ASSOC);
print_r($pubs);

echo "\n=== DELETANDO PUBLICAÇÕES PARA PERMITIR REPUBLICAR ===\n";
// Se quiser republicar, precisa limpar o registro anterior
// $pdo->exec('DELETE FROM publicacoes_redes WHERE rede = \'linkedin\'');
// echo "Registros deletados\n";
