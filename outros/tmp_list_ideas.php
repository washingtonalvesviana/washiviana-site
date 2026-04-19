<?php
require_once __DIR__ . '/api/config.php';
$topicId = 1;
$stmt = $pdo->prepare("SELECT i.*, t.nome AS topic_nome FROM radar_ideas i JOIN radar_topics t ON t.id = i.topic_id WHERE i.topic_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$topicId]);
$data = $stmt->fetchAll();
file_put_contents('/tmp/list_ideas.json', json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "Wrote /tmp/list_ideas.json\n";