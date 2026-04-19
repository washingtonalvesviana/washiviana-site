<?php
/**
 * Teste direto da API do LinkedIn - descobrir versão correta
 */
require_once __DIR__ . '/api/config.php';

echo "<h1>Teste de Versões da API LinkedIn</h1>";

// Buscar credenciais
$stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config || empty($config['access_token'])) {
    die("LinkedIn não configurado");
}

$accessToken = $config['access_token'];
$authorUrn = $config['publish_target'] === 'organization' && !empty($config['organization_urn']) 
    ? $config['organization_urn'] 
    : $config['person_urn'];

echo "<p><strong>Author URN:</strong> $authorUrn</p>";
echo "<p><strong>Token Length:</strong> " . strlen($accessToken) . " chars</p>";

// Payload de teste
$payload = [
    'author' => $authorUrn,
    'commentary' => 'Teste de publicação via API - ' . date('Y-m-d H:i:s'),
    'visibility' => 'PUBLIC',
    'distribution' => [
        'feedDistribution' => 'MAIN_FEED',
        'targetEntities' => [],
        'thirdPartyDistributionChannels' => []
    ],
    'lifecycleState' => 'PUBLISHED',
    'isReshareDisabledByAuthor' => false
];

echo "<h2>Payload:</h2>";
echo "<pre>" . json_encode($payload, JSON_PRETTY_PRINT) . "</pre>";

// Versões para testar
$versoes = ['202401', '202402', '202403', '202404', '202405', '202406', '202407', '202408', '202409', '202410', '202411', '202412'];

echo "<h2>Testando versões...</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Versão</th><th>HTTP Code</th><th>Resultado</th></tr>";

foreach ($versoes as $versao) {
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0'
    ];
    
    if ($versao !== 'none') {
        $headers[] = 'LinkedIn-Version: ' . $versao;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/rest/posts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $headers
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    $color = 'white';
    $result = '';
    
    if ($httpCode === 201) {
        $color = '#d4edda';
        $result = '✅ SUCESSO! Post URN: ' . ($data['id'] ?? 'N/A');
    } elseif ($httpCode === 426) {
        $color = '#fff3cd';
        $result = '⚠️ Versão não ativa: ' . ($data['message'] ?? '');
    } elseif ($httpCode === 403) {
        $color = '#f8d7da';
        $result = '❌ Sem permissão: ' . ($data['message'] ?? '');
    } elseif ($httpCode === 401) {
        $color = '#f8d7da';
        $result = '❌ Token inválido/expirado';
    } else {
        $color = '#e2e3e5';
        $result = "HTTP $httpCode: " . substr($response, 0, 200);
    }
    
    echo "<tr style='background: $color'>";
    echo "<td><strong>$versao</strong></td>";
    echo "<td>$httpCode</td>";
    echo "<td>$result</td>";
    echo "</tr>";
    
    // Se deu sucesso, parar
    if ($httpCode === 201) {
        echo "</table>";
        echo "<h2 style='color: green;'>✅ Publicação bem-sucedida com versão: $versao</h2>";
        exit;
    }
}

echo "</table>";

echo "<h2 style='color: red;'>❌ Nenhuma versão funcionou</h2>";

echo "<h3>Diagnóstico:</h3>";
echo "<ul>";
echo "<li>Se todas retornaram <strong>426</strong>: A API LinkedIn REST pode estar indisponível temporariamente</li>";
echo "<li>Se todas retornaram <strong>403</strong>: O token não tem permissão <code>w_member_social</code>. Gere um novo token.</li>";
echo "<li>Se todas retornaram <strong>401</strong>: O token está expirado ou inválido.</li>";
echo "</ul>";

echo "<h3>Próximos passos:</h3>";
echo "<ol>";
echo "<li>Acesse <a href='https://www.linkedin.com/developers/apps' target='_blank'>LinkedIn Developers</a></li>";
echo "<li>Selecione seu app → Products → Verifique se 'Share on LinkedIn' está <strong>Approved</strong></li>";
echo "<li>Vá em Auth → OAuth 2.0 tools → Generate Token</li>";
echo "<li>Selecione o scope <strong>w_member_social</strong></li>";
echo "<li>Copie o novo token e atualize em Redes Sociais</li>";
echo "</ol>";
