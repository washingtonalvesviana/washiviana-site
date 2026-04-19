<?php
require_once __DIR__ . '/../api/config.php';
$stmt = $pdo->prepare('SELECT id, rede, post_id, url_post, status, erro_mensagem, publicado_em FROM publicacoes_redes WHERE artigo_id = ?');
$stmt->execute([3]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;