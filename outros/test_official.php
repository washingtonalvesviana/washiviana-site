<?php
/**
 * Teste EXATO conforme documentação oficial do LinkedIn
 * https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/share-on-linkedin
 */

echo "=== TESTE CONFORME DOCUMENTAÇÃO OFICIAL ===\n\n";

$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');
$stmt = $pdo->query("SELECT access_token, person_urn FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $linkedin['access_token'];

echo "Token: " . substr($token, 0, 50) . "...\n\n";

// A documentação usa /v2/ugcPosts com este formato exato
$personUrn = "urn:li:person:38583269";

echo "Tentando com Person URN: $personUrn\n\n";

$requestBody = [
    "author" => $personUrn,
    "lifecycleState" => "PUBLISHED",
    "specificContent" => [
        "com.linkedin.ugc.ShareContent" => [
            "shareCommentary" => [
                "text" => "Hello World! Teste de integração com LinkedIn API! 🚀"
            ],
            "shareMediaCategory" => "NONE"
        ]
    ],
    "visibility" => [
        "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"
    ]
];

echo "Request Body:\n" . json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestBody),
    CURLOPT_HEADER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
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

echo "HTTP Code: $httpCode\n\n";

if ($httpCode == 201) {
    echo "🎉🎉🎉 POST CRIADO COM SUCESSO! 🎉🎉🎉\n\n";
    preg_match('/X-RestLi-Id:\s*(.+)/i', $headers, $matches);
    if (isset($matches[1])) {
        echo "Post ID: " . trim($matches[1]) . "\n";
    }
    echo "\n✅ Verifique seu perfil do LinkedIn!\n";
} else {
    echo "Response Headers:\n$headers\n";
    echo "Response Body:\n$body\n\n";
    
    $error = json_decode($body, true);
    if ($error && isset($error['message'])) {
        echo "Mensagem de erro: " . $error['message'] . "\n";
    }
}

echo "\n=== VERIFICAÇÃO DO TOKEN ===\n";
echo "Seu token pode não estar associado ao Person URN correto.\n\n";
echo "SOLUÇÃO:\n";
echo "1. Acesse a ferramenta OAuth: https://www.linkedin.com/developers/tools/oauth\n";
echo "2. Após gerar o token, role para baixo\n";
echo "3. Na seção 'Make an API call', faça:\n";
echo "   GET https://api.linkedin.com/v2/me\n";
echo "4. Veja o 'id' retornado - esse é seu Person URN correto!\n";
echo "5. Se der erro, tente: GET https://api.linkedin.com/v2/userinfo\n";
