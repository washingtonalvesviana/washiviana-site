<?php
require_once __DIR__ . '/../api/config.php';
$stmt = $pdo->query("SELECT ativo, LENGTH(access_token) as token_len, person_urn, organization_urn, publish_target FROM redes_sociais_config WHERE rede='linkedin'");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;