<?php
$pdo = new PDO('getenv('PG_DSN') ?: 'pgsql:host=YOUR_HOST;port=5432;dbname=YOUR_DB'', getenv('PG_USER') ?: 'YOUR_DB_USER', getenv('PG_PASS') ?: 'YOUR_DB_PASSWORD');
$r = $pdo->query('SELECT * FROM redes_sociais_config');
print_r($r->fetchAll(PDO::FETCH_ASSOC));
