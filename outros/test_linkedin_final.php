<?php
/**
 * Teste FINAL LinkedIn com client_secret do banco
 */

echo "=== TESTE FINAL LINKEDIN ===\n\n";

$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');

$stmt = $pdo->query("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);

$token = $linkedin['access_token'];
$clientId = $linkedin['client_id'];
$clientSecret = $linkedin['client_secret'];
$personUrn = $linkedin['person_urn'];

echo "Client ID: $clientId\n";
echo "Client Secret: " . substr($clientSecret, 0, 20) . "...\n";
echo "Person URN: $personUrn\n";
echo "Token (início): " . substr($token, 0, 30) . "...\n\n";

// Introspection com credenciais do banco
echo "1. Verificando validade do token...\n";
$ch = curl_init('https://www.linkedin.com/oauth/v2/introspectToken');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'token' => $token,
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ])
]);
$response = curl_exec($ch);
curl_close($ch);

$tokenInfo = json_decode($response, true);
echo "   Response: " . json_encode($tokenInfo, JSON_PRETTY_PRINT) . "\n\n";

if (isset($tokenInfo['error'])) {
    echo "⚠️ Erro na verificação do token!\n";
    echo "O client_secret pode estar incorreto ou o token foi gerado com outro app.\n\n";
    
    echo "VERIFICAÇÃO NECESSÁRIA:\n";
    echo "1. Acesse https://www.linkedin.com/developers/apps\n";
    echo "2. Selecione sua app 'Washiviana'\n";
    echo "3. Vá em 'Auth' tab\n";
    echo "4. Copie o 'Client Secret' atual\n";
    echo "5. Atualize no admin em 'Redes Sociais' > LinkedIn\n\n";
}

// Tentar post mesmo assim
echo "2. Tentando criar post...\n";
$postData = [
    "author" => $personUrn,
    "lifecycleState" => "PUBLISHED",
    "visibility" => "PUBLIC",
    "distribution" => [
        "feedDistribution" => "MAIN_FEED",
        "targetEntities" => [],
        "thirdPartyDistributionChannels" => []
    ],
    "commentary" => "Teste de publicação via API - " . date('d/m/Y H:i')
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

echo "   HTTP: $httpCode\n";

if ($httpCode == 201) {
    echo "   ✅ POST CRIADO COM SUCESSO!\n";
    preg_match('/x-restli-id:\s*(.+)/i', $headers, $matches);
    if (isset($matches[1])) {
        echo "   Post ID: " . trim($matches[1]) . "\n";
        echo "\n🎉 Verifique seu LinkedIn - o post foi publicado!\n";
    }
} else {
    echo "   ❌ Erro\n";
    echo "   Body: $body\n";
    
    if ($httpCode == 403) {
        echo "\n=== CAUSA PROVÁVEL DO 403 ===\n";
        echo "O app LinkedIn está em modo DESENVOLVIMENTO.\n\n";
        echo "PARA RESOLVER:\n";
        echo "1. Acesse: https://www.linkedin.com/developers/apps\n";
        echo "2. Selecione a app 'Washiviana'\n";
        echo "3. Vá em 'Settings'\n";
        echo "4. Adicione seu email em 'App members'\n";
        echo "   (O email DEVE ser o mesmo da conta LinkedIn que autorizou)\n";
        echo "5. Aguarde 2-3 minutos e tente novamente\n\n";
        
        echo "OU\n\n";
        
        echo "O token foi gerado com OUTRA conta LinkedIn:\n";
        echo "1. Verifique se o token foi gerado com a mesma conta que é admin do app\n";
        echo "2. Se não, gere um novo token OAuth usando a conta admin\n";
    }
}
