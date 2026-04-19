<?php
/**
 * Teste final - descobrir member ID via posts API
 */

require_once __DIR__ . '/api/config.php';

echo "=== DESCOBRIR MEMBER ID CORRETO ===\n\n";

$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');
$stmt = $pdo->query("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $linkedin['access_token'];

// A API do LinkedIn quando você envia um author errado, retorna o author esperado no erro
// Vamos provocar esse erro de forma inteligente

echo "Método 1: Tentar post sem author para ver o erro...\n\n";

// Post sem author válido
$postData = [
    "author" => "urn:li:person:test",  // Inválido de propósito
    "lifecycleState" => "PUBLISHED",
    "visibility" => "PUBLIC",
    "distribution" => [
        "feedDistribution" => "MAIN_FEED",
        "targetEntities" => [],
        "thirdPartyDistributionChannels" => []
    ],
    "commentary" => "teste"
];

$ch = curl_init('https://api.linkedin.com/rest/posts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postData),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'LinkedIn-Version: ' . LINKEDIN_API_VERSION,
        'X-Restli-Protocol-Version: 2.0.0',
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $httpCode\n";
echo "Response: $response\n\n";

// Tentar extrair qualquer URN da resposta
if (preg_match_all('/urn:li:(person|member):([A-Za-z0-9_-]+)/', $response, $matches, PREG_SET_ORDER)) {
    echo "URNs encontrados na resposta:\n";
    foreach ($matches as $match) {
        echo "  - urn:li:{$match[1]}:{$match[2]}\n";
    }
}

echo "\n\n=== ALTERNATIVA: USAR ORGANIZATION ID ===\n";
echo "Já que temos o App ID 229727211, podemos tentar publicar na Company Page.\n\n";

// Buscar organization_urn
$stmt = $pdo->query("SELECT organization_urn FROM redes_sociais_config WHERE rede = 'linkedin'");
$org = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Organization URN no banco: " . ($org['organization_urn'] ?: 'não definido') . "\n";

// Se não tem, vamos tentar com ID 110352846 que foi mencionado antes
echo "\nTentando publicar na Company Page (urn:li:organization:110352846)...\n";

$postData = [
    "author" => "urn:li:organization:110352846",
    "lifecycleState" => "PUBLISHED",
    "visibility" => "PUBLIC",
    "distribution" => [
        "feedDistribution" => "MAIN_FEED",
        "targetEntities" => [],
        "thirdPartyDistributionChannels" => []
    ],
    "commentary" => "🚀 Teste de publicação via API - Washiviana"
];

$ch = curl_init('https://api.linkedin.com/rest/posts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postData),
    CURLOPT_HEADER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'LinkedIn-Version: ' . LINKEDIN_API_VERSION,
        'X-Restli-Protocol-Version: 2.0.0',
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body = substr($response, $headerSize);
curl_close($ch);

echo "HTTP: $httpCode\n";

if ($httpCode == 201) {
    echo "\n🎉 POST NA COMPANY PAGE CRIADO COM SUCESSO!\n";
} else {
    echo "Response: $body\n";
}
