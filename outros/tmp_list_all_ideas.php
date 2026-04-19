<?php
require_once __DIR__ . '/api/config.php';
$stmt = $pdo->query('SELECT i.*, t.nome as topic_nome FROM radar_ideas i JOIN radar_topics t ON t.id=i.topic_id ORDER BY t.nome ASC, i.created_at DESC');
$arr = $stmt->fetchAll();
file_put_contents('/tmp/list_all_ideas.json', json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "Wrote /tmp/list_all_ideas.json\n";