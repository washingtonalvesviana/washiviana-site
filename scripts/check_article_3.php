<?php
require_once __DIR__ . '/../api/config.php';
$stmt = $pdo->prepare('SELECT id, titulo, status_publicacao, data_agendamento, publicar_linkedin, linkedin_post_id, redes_destino FROM artigos WHERE id = ?');
$stmt->execute([3]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
// decodificar redes_destino se for JSON
try { $row['redes_destino'] = $row['redes_destino'] ? json_decode($row['redes_destino'], true) : []; } catch (Exception $e) {}
echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;