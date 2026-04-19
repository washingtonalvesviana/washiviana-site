<?php
$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');
$r = $pdo->query('SELECT * FROM redes_sociais_config');
print_r($r->fetchAll(PDO::FETCH_ASSOC));
