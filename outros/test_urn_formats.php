<?php
/**
 * Teste com diferentes formatos de author URN
 */

echo "=== TESTE COM DIFERENTES FORMATOS ===\n\n";

$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');
$stmt = $pdo->query("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $linkedin['access_token'];

echo "Token atual: " . substr($token, 0, 40) . "...\n\n";

// Primeiro, vamos tentar obter o ID do usuário autenticado
echo "1. Tentando obter ID do usuário...\n";

// Método: usar /rest/posts para obter uma resposta mais detalhada
$testUrns = [
    'urn:li:person:38583269',
    'urn:li:member:38583269',
];

foreach ($testUrns as $urn) {
    echo "\n--- Testando com: $urn ---\n";
    
    $postData = [
        "author" => $urn,
        "lifecycleState" => "PUBLISHED",
        "visibility" => "PUBLIC",
        "distribution" => [
            "feedDistribution" => "MAIN_FEED",
            "targetEntities" => [],
            "thirdPartyDistributionChannels" => []
        ],
        "commentary" => "Teste " . date('H:i:s')
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
        echo "✅ SUCESSO!\n";
        break;
    } else {
        $error = json_decode($body, true);
        if ($error) {
            echo "Erro: " . json_encode($error, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

echo "\n\n=== VERIFICAÇÃO ADICIONAL ===\n";
echo "O 403 persistente geralmente significa:\n\n";
echo "1. O app NÃO está verificado com a Company Page\n";
echo "   - Você precisa clicar em 'Verify' na aba Settings\n";
echo "   - E aprovar a verificação na URL fornecida\n\n";
echo "2. OU você precisa usar a API direta para membros:\n";
echo "   Tente usar a ferramenta do LinkedIn:\n";
echo "   https://www.linkedin.com/developers/tools/oauth/token-inspector\n";
echo "   Cole seu token e veja os detalhes\n";
