<?php
require_once __DIR__ . '/../api/config.php';
$stmt = $pdo->prepare("UPDATE artigos SET status_publicacao = 'agendado', data_agendamento = NOW() - INTERVAL '1 minute', linkedin_post_id = NULL WHERE id = ?");
$stmt->execute([3]);
echo "Article 3 reset to agendado and ready to publish.\n";