<?php
require_once __DIR__ . '/api/config.php';

$newToken = getenv('LINKEDIN_NEW_TOKEN') ?: 'YOUR_NEW_LINKEDIN_TOKEN';

echo "Token a salvar: " . strlen($newToken) . " caracteres\n";

$expiresAt = date('Y-m-d H:i:s', time() + 5184000); // 60 dias

$stmt = $pdo->prepare("UPDATE redes_sociais_config SET access_token = ?, token_expires_at = ? WHERE rede = 'linkedin'");
$result = $stmt->execute([$newToken, $expiresAt]);

echo "Resultado: " . ($result ? "OK" : "ERRO") . "\n";
echo "Rows affected: " . $stmt->rowCount() . "\n";

// Verificar
$stmt = $pdo->query("SELECT LENGTH(access_token) as len FROM redes_sociais_config WHERE rede = 'linkedin'");
$row = $stmt->fetch();
echo "Token salvo com: " . $row['len'] . " caracteres\n";
