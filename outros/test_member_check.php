<?php
/**
 * Teste para verificar identidade do membro
 */

require_once 'api/config.php';

echo "=== VERIFICAÇÃO DE IDENTIDADE ===\n\n";

try {
    $pdo = new PDO(
        "pgsql:host=168.231.88.4;port=5434;dbname=washiviana",
        'postgres',
        'DevCleveris@2025'
    );
    
    $stmt = $pdo->query("SELECT access_token, person_urn FROM redes_sociais WHERE tipo = 'linkedin'");
    $linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$linkedin) {
        die("LinkedIn não configurado\n");
    }
    
    $token = $linkedin['access_token'];
    
    // Tentar obter userinfo com token atual
    echo "1. Testando /v2/userinfo...\n";
    $ch = curl_init('https://api.linkedin.com/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   HTTP: $httpCode\n";
    echo "   Response: $response\n\n";
    
    // Tentar obter perfil básico
    echo "2. Testando /v2/me (com versão 202411)...\n";
    $ch = curl_init('https://api.linkedin.com/v2/me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION,
            'X-Restli-Protocol-Version: 2.0.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   HTTP: $httpCode\n";
    echo "   Response: $response\n\n";
    
    // Tentar versão do Community Management API
    echo "3. Testando /rest/me (versão 202411)...\n";
    $ch = curl_init('https://api.linkedin.com/rest/me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION,
            'X-Restli-Protocol-Version: 2.0.0'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   HTTP: $httpCode\n";
    echo "   Response: $response\n\n";
    
    // POST de teste simples só com texto
    echo "4. Testando POST simples (só texto)...\n";
    
    $personUrn = $linkedin['person_urn'];
    echo "   Person URN: $personUrn\n";
    
    $postData = [
        "author" => $personUrn,
        "lifecycleState" => "PUBLISHED",
        "visibility" => "PUBLIC",
        "distribution" => [
            "feedDistribution" => "MAIN_FEED",
            "targetEntities" => [],
            "thirdPartyDistributionChannels" => []
        ],
        "commentary" => "Teste de API - " . date('Y-m-d H:i:s')
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
        // Extrair ID do header
        preg_match('/x-restli-id:\s*(.+)/i', $headers, $matches);
        echo "   ✅ POST CRIADO COM SUCESSO!\n";
        if (isset($matches[1])) {
            echo "   Post ID: " . trim($matches[1]) . "\n";
        }
    } else {
        echo "   ❌ Erro ao criar post\n";
        echo "   Body: $body\n";
        
        $errorData = json_decode($body, true);
        if ($errorData && isset($errorData['message'])) {
            echo "\n   Mensagem: " . $errorData['message'] . "\n";
        }
    }
    
    echo "\n=== RECOMENDAÇÕES ===\n";
    echo "Se continuar com 403:\n";
    echo "1. Vá ao LinkedIn Developer Portal\n";
    echo "2. Acesse sua App > Settings > App members\n";
    echo "3. Verifique se seu email está como Admin do app\n";
    echo "4. Em Products, confirme que 'Share on LinkedIn' está 'Added'\n";
    echo "5. O app pode estar em modo 'Development' - precisa estar em 'Published'\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
