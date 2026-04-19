<?php
/**
 * Descobrir o Person URN correto usando o token
 */

echo "=== DESCOBRIR PERSON URN CORRETO ===\n\n";

$pdo = new PDO('getenv('PG_DSN') ?: 'pgsql:host=YOUR_HOST;port=5432;dbname=YOUR_DB'', getenv('PG_USER') ?: 'YOUR_DB_USER', getenv('PG_PASS') ?: 'YOUR_DB_PASSWORD');
$stmt = $pdo->query("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$linkedin = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $linkedin['access_token'];

// Método 1: userinfo (OpenID Connect)
echo "1. Tentando /v2/userinfo...\n";
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

// Método 2: /v2/me com projection
echo "2. Tentando /v2/me...\n";
$ch = curl_init('https://api.linkedin.com/v2/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'X-Restli-Protocol-Version: 2.0.0'
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   HTTP: $httpCode\n";
echo "   Response: $response\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (isset($data['id'])) {
        echo "✅ Person ID encontrado: " . $data['id'] . "\n";
        echo "   Person URN correto: urn:li:person:" . $data['id'] . "\n";
        
        // Atualizar no banco
        $newUrn = "urn:li:person:" . $data['id'];
        $stmt = $pdo->prepare("UPDATE redes_sociais_config SET person_urn = ? WHERE rede = 'linkedin'");
        $stmt->execute([$newUrn]);
        echo "\n✅ Person URN atualizado no banco!\n";
    }
}

// Método 3: REST API /rest/me
echo "\n3. Tentando /rest/me (versão 202411)...\n";
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

// Método 4: Usar OAuth Token Tool para descobrir
echo "4. Você também pode verificar manualmente:\n";
echo "   • Acesse: https://www.linkedin.com/developers/tools/oauth\n";
echo "   • Use seu app e gere um token\n";
echo "   • Na ferramenta, faça GET /v2/me\n";
echo "   • O 'id' retornado é seu Person ID correto\n";
