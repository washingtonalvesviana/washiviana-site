<?php
require_once __DIR__ . '/../api/config.php';
$stmt=$pdo->prepare('UPDATE artigos SET data_agendamento = NOW() - INTERVAL \'1 minute\', status_publicacao = ? WHERE id = ?');
$stmt->execute(['agendado', 3]);
echo "agendamento setado para agora\n";