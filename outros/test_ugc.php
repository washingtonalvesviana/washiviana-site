<?php
/**
 * Teste usando UGC Posts API (método antigo que ainda funciona)
 */

echo "=== TESTE COM UGC POSTS API ===\n\n";

$pdo = new PDO('pgsql:host=168.231.88.4;port=5434;dbname=washiviana', 'postgres', 'DevCleveris@2025');
$stmt = $pdo->query("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $linkedin['access_token'];

// Primeiro descobrir o ID correto do autor
echo "1. Descobrindo ID do usuário autenticado...\n";

// Tentar diferentes endpoints para obter o ID
$endpoints = [
    'https://api.linkedin.com/v2/me' => [],
    'https://api.linkedin.com/v2/userinfo' => [],
];

$personId = null;

foreach ($endpoints as $url => $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge([
            'Authorization: Bearer ' . $token,
        ], $headers)
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   $url -> HTTP $httpCode\n";
    
    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if (isset($data['id'])) {
            $personId = $data['id'];
            echo "   ✅ ID encontrado: $personId\n";
            break;
        } elseif (isset($data['sub'])) {
            $personId = $data['sub'];
            echo "   ✅ Sub encontrado: $personId\n";
            break;
        }
    }
}

if (!$personId) {
    echo "\n❌ Não consegui descobrir o ID automaticamente.\n";
    echo "Vamos tentar com a API de UGC Posts que retorna erro mais descritivo...\n\n";
}

// Testar UGC Posts (API v2)
echo "\n2. Tentando UGC Posts API...\n";

$ugcPost = [
    "author" => "urn:li:person:38583269",  // Vamos tentar com o ID original primeiro
    "lifecycleState" => "PUBLISHED",
    "specificContent" => [
        "com.linkedin.ugc.ShareContent" => [
            "shareCommentary" => [
                "text" => "Teste de publicação - " . date('d/m/Y H:i:s')
            ],
            "shareMediaCategory" => "NONE"
        ]
    ],
    "visibility" => [
        "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"
    ]
];

$ch = curl_init('https://api.linkedin.com/v2/ugcPosts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($ugcPost),
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
$body = substr($response, $headerSize);
curl_close($ch);

echo "   HTTP: $httpCode\n";

if ($httpCode == 201) {
    echo "\n🎉 POST CRIADO COM SUCESSO!\n";
} else {
    echo "   Response: $body\n\n";
    
    // Extrair ID correto do erro se possível
    if (preg_match('/urn:li:person:([A-Za-z0-9_-]+)/', $body, $matches)) {
        echo "   Possível ID correto encontrado: " . $matches[1] . "\n";
    }
}

echo "\n=== SOLUÇÃO FINAL ===\n";
echo "Use a ferramenta OAuth do LinkedIn para descobrir seu ID:\n";
echo "1. Acesse: https://www.linkedin.com/developers/tools/oauth\n";
echo "2. Selecione seu app e gere um token\n";
echo "3. Na mesma página, use 'Make API Call'\n";
echo "4. GET https://api.linkedin.com/v2/me\n";
echo "5. O campo 'id' na resposta é seu Person ID correto\n";
