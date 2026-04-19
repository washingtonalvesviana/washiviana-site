<?php
require_once __DIR__ . '/api/config.php';

// Usar PDO global do config ou fallback para o PostgreSQL de diagnóstico
$pdo = $pdo ?? new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');

// Atualizar Person URN
$stmt = $pdo->prepare('UPDATE redes_sociais_config SET person_urn = ? WHERE rede = ?');
$stmt->execute(['urn:li:person:NcTNn4bkqY', 'linkedin']);

echo "✅ Person URN atualizado para: urn:li:person:NcTNn4bkqY\n\n";

// Agora testar publicação
$stmt = $pdo->query("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $linkedin['access_token'];

echo "Tentando publicar com URN correto...\n";

$postData = [
    "author" => "urn:li:person:NcTNn4bkqY",
    "lifecycleState" => "PUBLISHED",
    "visibility" => "PUBLIC",
    "distribution" => [
        "feedDistribution" => "MAIN_FEED",
        "targetEntities" => [],
        "thirdPartyDistributionChannels" => []
    ],
    "commentary" => "🚀 Teste de publicação automática via API - " . date('d/m/Y H:i:s')
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
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
curl_close($ch);

echo "HTTP: $httpCode\n";

if ($httpCode == 201) {
    echo "\n🎉🎉🎉 POST CRIADO COM SUCESSO! 🎉🎉🎉\n\n";
    preg_match('/x-restli-id:\s*(.+)/i', $headers, $matches);
    if (isset($matches[1])) {
        echo "Post ID: " . trim($matches[1]) . "\n";
    }
    echo "\n✅ Verifique seu LinkedIn - o post foi publicado!\n";
} else {
    echo "❌ Erro: $body\n";
}
