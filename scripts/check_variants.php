<?php
require_once __DIR__ . '/../api/config.php';
$id = 3;
$stmt = $pdo->prepare('SELECT id, rede, status, scheduled_at, created_at, updated_at FROM artigos_social_variants WHERE artigo_id = ? ORDER BY id');
$stmt->execute([$id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;