<?php
require_once __DIR__ . '/../api/config.php';
$stmt=$pdo->prepare('SELECT id, status_publicacao, data_agendamento FROM artigos WHERE status_publicacao = ? AND data_agendamento <= NOW()');
$stmt->execute(['agendado']);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;