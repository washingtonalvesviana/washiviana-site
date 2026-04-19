<?php
require_once __DIR__ . '/../api/config.php';
$stmt=$pdo->prepare('INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, status, publicado_em, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
$stmt->execute(['linkedin', 3, 'urn:li:share:7419452043775332352', null, 'publicado']);
echo "Inserido registro publicacoes_redes\n";