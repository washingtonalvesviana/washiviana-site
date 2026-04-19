<?php
require_once __DIR__ . '/api/config.php';

// Buscar token
$stmt = $pdo->query("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$row = $stmt->fetch();
$token = $row['access_token'];

echo "Token: " . substr($token, 0, 50) . "...\n";
echo "Tamanho: " . strlen($token) . " chars\n\n";

// Testar token com introspection
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.linkedin.com/v2/userinfo',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Teste /v2/userinfo ===\n";
echo "HTTP: $httpCode\n";
echo "Response: $response\n\n";

// Testar com /v2/me
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.linkedin.com/v2/me',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'X-Restli-Protocol-Version: 2.0.0'
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== Teste /v2/me ===\n";
echo "HTTP: $httpCode\n";
echo "Response: $response\n\n";

// Testar publicação direta com versão 202411
echo "=== Teste de Publicação (202411) ===\n";

$payload = [
    'author' => 'urn:li:person:38583269',
    'commentary' => 'Teste API ' . date('H:i:s'),
    'visibility' => 'PUBLIC',
    'distribution' => [
        'feedDistribution' => 'MAIN_FEED',
        'targetEntities' => [],
        'thirdPartyDistributionChannels' => []
    ],
    'lifecycleState' => 'PUBLISHED',
    'isReshareDisabledByAuthor' => false
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.linkedin.com/rest/posts',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
        'LinkedIn-Version: ' . LINKEDIN_API_VERSION
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
echo "Response: $response\n";

if ($httpCode === 201) {
    echo "\n✅ SUCESSO! Post publicado!\n";
} elseif ($httpCode === 403) {
    echo "\n❌ Erro 403: Token sem permissão w_member_social\n";
    $data = json_decode($response, true);
    if (isset($data['message'])) {
        echo "Mensagem: " . $data['message'] . "\n";
    }
}
