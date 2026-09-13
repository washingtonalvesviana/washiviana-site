<?php
require_once __DIR__ . '/../../api/config.php';

$stmt = $pdo->query("SELECT ativo, person_urn, organization_urn, publish_target, LENGTH(access_token) as token_len FROM redes_sociais_config WHERE rede = 'linkedin'");
$r = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== CONFIGURAÇÕES LINKEDIN ===\n";
echo "Ativo: " . ($r['ativo'] ? 'SIM' : 'NÃO') . "\n";
echo "Publish Target: " . ($r['publish_target'] ?? 'person') . "\n";
echo "Person URN: " . ($r['person_urn'] ?: '(vazio)') . "\n";
echo "Organization URN: " . ($r['organization_urn'] ?: '(vazio)') . "\n";
echo "Token Length: " . $r['token_len'] . " caracteres\n";
