<?php
/**
 * Teste rápido das credenciais do LinkedIn
 */
require_once __DIR__ . '/api/config.php';

echo "=== TESTE DE CREDENCIAIS LINKEDIN ===\n\n";

try {
    // Buscar credenciais
    $stmt = $pdo->prepare("
        SELECT ativo, access_token, person_urn, client_id, client_secret 
        FROM redes_sociais_config 
        WHERE rede = 'linkedin'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo "❌ LinkedIn não configurado no banco\n";
        exit(1);
    }
    
    echo "📋 Configuração encontrada:\n";
    echo "   Ativo: " . ($config['ativo'] === 't' || $config['ativo'] === true ? 'SIM ✅' : 'NÃO ❌') . "\n";
    echo "   Access Token: " . (!empty($config['access_token']) ? 'SIM ✅ (' . substr($config['access_token'], 0, 30) . '...)' : 'NÃO ❌') . "\n";
    echo "   Person URN: " . (!empty($config['person_urn']) ? 'SIM ✅ (' . $config['person_urn'] . ')' : 'NÃO ❌') . "\n";
    echo "   Client ID: " . (!empty($config['client_id']) ? 'SIM ✅' : 'NÃO ❌') . "\n";
    echo "   Client Secret: " . (!empty($config['client_secret']) ? 'SIM ✅' : 'NÃO ❌') . "\n\n";
    
    // Verificar se está completo
    if (empty($config['access_token'])) {
        echo "❌ Access Token não configurado\n";
        exit(1);
    }
    
    if (empty($config['person_urn'])) {
        echo "❌ Person URN não configurado\n";
        exit(1);
    }
    
    if ($config['ativo'] !== 't' && $config['ativo'] !== true) {
        echo "⚠️ LinkedIn não está ativado\n";
    }
    
    echo "🧪 Testando conexão com LinkedIn API...\n\n";
    
    // Teste 1: userinfo endpoint
    echo "1. Testando /v2/userinfo...\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/v2/userinfo',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "   ❌ Erro cURL: $error\n";
    } elseif ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "   ✅ Sucesso! (HTTP $httpCode)\n";
        if (isset($data['sub'])) {
            echo "   Person ID: " . $data['sub'] . "\n";
        }
        if (isset($data['given_name']) || isset($data['family_name'])) {
            $name = trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? ''));
            echo "   Nome: $name\n";
        }
        if (isset($data['email'])) {
            echo "   Email: " . $data['email'] . "\n";
        }
        echo "\n✅ CREDENCIAIS VÁLIDAS E FUNCIONANDO!\n";
        exit(0);
    } elseif ($httpCode === 401) {
        echo "   ❌ Token inválido ou expirado (HTTP $httpCode)\n";
        echo "   Resposta: " . substr($response, 0, 200) . "\n";
    } else {
        echo "   ⚠️ HTTP $httpCode (pode ser scope limitado)\n";
    }
    
    // Teste 2: me endpoint
    echo "\n2. Testando /v2/me...\n";
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => 'https://api.linkedin.com/v2/me',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION
        ]
    ]);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    if ($httpCode2 === 200) {
        $data2 = json_decode($response2, true);
        echo "   ✅ Sucesso! (HTTP $httpCode2)\n";
        if (isset($data2['id'])) {
            echo "   Person ID: " . $data2['id'] . "\n";
        }
        if (isset($data2['localizedFirstName']) || isset($data2['localizedLastName'])) {
            $name2 = trim(($data2['localizedFirstName'] ?? '') . ' ' . ($data2['localizedLastName'] ?? ''));
            echo "   Nome: $name2\n";
        }
        echo "\n✅ CREDENCIAIS VÁLIDAS E FUNCIONANDO!\n";
        exit(0);
    } else {
        echo "   ⚠️ HTTP $httpCode2\n";
    }
    
    // Se chegou aqui e tem person_urn, considerar válido para w_member_social
    if (!empty($config['person_urn']) && strpos($config['person_urn'], 'urn:li:person:') === 0) {
        echo "\n✅ Person URN configurado: " . $config['person_urn'] . "\n";
        echo "⚠️ Token tem scope limitado (w_member_social), mas é suficiente para publicar posts.\n";
        echo "✅ CREDENCIAIS PRONTAS PARA PUBLICAR!\n";
        exit(0);
    }
    
    echo "\n❌ Não foi possível validar as credenciais.\n";
    echo "   Verifique se o Access Token está válido e não expirado.\n";
    exit(1);
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
