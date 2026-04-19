<?php
require_once __DIR__ . '/api/config.php';
$stmt = $pdo->prepare('INSERT INTO radar_ideas (topic_id, titulo, angulo, resumo, outline, tags, status, source_item_ids, ai_model, ai_prompt, ai_raw) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([1, 'Título de teste em PT', 'Ângulo de teste', 'Resumo em português completo aqui.', "- Ponto 1\n- Ponto 2", 'tag1,tag2', 'nova', '[]', 'test-model', 'prompt', '{"ideas":[]}']);
echo "Inserted\n";