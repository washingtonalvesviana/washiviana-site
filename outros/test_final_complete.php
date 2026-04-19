<?php
/**
 * Teste FINAL com token completo (openid + profile + w_member_social + email)
 */

$token = "AQUQ4iLN0foKMDb8njrsavPqS5-advd0BucZtuvHxCABe_MQ3fhoZn4-DCwtZnbwvWl6B28qhOyg_n12Hq_aul1B15VI1qsq7oCgKfbNWUnMunPtR6-gb9PqCVo1rsc4K8LtitoU3YwJQmrtonZ8PGIFeHcv8fjPsawwO7LOu-TY9iNMbL5mYk2sRk4FWZLrKM-l7Q4Of2QLAkfzXyxlwpn9cMQ5Nt83ah0jUUJ1obggREiMWDXzpD8HsrPqmLXVy_H8nc9mC6cCC4Tmhb98egqrlQVbI-aaJf8cgGXN9Q8_aWCia95LPq2l0RMTnUZaG5NdUyspZpURGJjbvlDjG-_CXV2cTA";

echo "=== TESTE FINAL COM TODOS OS SCOPES ===\n\n";

// 1. Descobrir o Person URN correto via /v2/userinfo
echo "1. Descobrindo Person URN via /v2/userinfo...\n";
$ch = curl_init('https://api.linkedin.com/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP: $httpCode\n";
echo "   Response: $response\n\n";

$userInfo = json_decode($response, true);

if ($httpCode == 200 && isset($userInfo['sub'])) {
    $personId = $userInfo['sub'];
    $personUrn = "urn:li:person:" . $personId;
    
    echo "✅ Person ID encontrado: $personId\n";
    echo "✅ Person URN: $personUrn\n\n";
    
    // Atualizar no banco
    $pdo = new PDO('getenv('PG_DSN') ?: 'pgsql:host=YOUR_HOST;port=5432;dbname=YOUR_DB'', getenv('PG_USER') ?: 'YOUR_DB_USER', getenv('PG_PASS') ?: 'YOUR_DB_PASSWORD');
    $stmt = $pdo->prepare("UPDATE redes_sociais_config SET access_token = ?, person_urn = ? WHERE rede = 'linkedin'");
    $stmt->execute([$token, $personUrn]);
    echo "✅ Token e Person URN atualizados no banco!\n\n";
    
    // 2. Agora publicar!
    echo "2. Publicando no LinkedIn...\n";
    
    $postData = [
        "author" => $personUrn,
        "lifecycleState" => "PUBLISHED",
        "visibility" => "PUBLIC",
        "distribution" => [
            "feedDistribution" => "MAIN_FEED",
            "targetEntities" => [],
            "thirdPartyDistributionChannels" => []
        ],
        "commentary" => "🚀 Minha primeira publicação via API do LinkedIn! Integração funcionando perfeitamente. #API #LinkedIn #Automação"
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
        echo "\n";
        echo "🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉\n";
        echo "🎉                                    🎉\n";
        echo "🎉   POST CRIADO COM SUCESSO!!!       🎉\n";
        echo "🎉                                    🎉\n";
        echo "🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉🎉\n\n";
        
        preg_match('/x-restli-id:\s*(.+)/i', $headers, $matches);
        if (isset($matches[1])) {
            echo "Post ID: " . trim($matches[1]) . "\n";
        }
        echo "\n✅ Verifique seu perfil do LinkedIn!\n";
        echo "   https://www.linkedin.com/in/washington-alves-viana-38583269/\n";
    } else {
        echo "   ❌ Erro: $body\n";
    }
} else {
    echo "❌ Não foi possível obter userinfo\n";
    echo "   Erro: $response\n";
}
