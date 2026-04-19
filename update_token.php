<?php
require_once __DIR__ . '/api/config.php';

$newToken = 'AQVAgckK7pOfaRZhXua2gLCKcw4M1_Xkcpk93GcP_U95QOzdT8v7N8yyIq2jfl09nCrl_Lmtfyi-BIw4AJrwKnfYQNnpAXooLXP-cUfK7DTVkCXEWS6vyQ_mQW3xpAqUF6uPEX_SuZme4F-qR3NZEzWM5R48iBcyTVUWWLR8xnCqNgeADyMmHjXEsGuunfS1l2Kugex2BwlnlvRGGhSYE3_0JfKkcuelpMDrzAC0Zra7V9LldoJQ-mn7nObnx2s33hviGNrK2eAiJ6s8_YL8GeyzrvnZtx9JUxXp5LiFe3ZlNABYCIZXK0Bz7y1gSPvy-jEEGTBsLh5xsvZUPTsqtXAdlC1chQ';

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
