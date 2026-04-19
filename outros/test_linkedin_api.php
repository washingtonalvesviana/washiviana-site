<?php
/**
 * WASHIVIANA - Diagnóstico completo da integração LinkedIn
 * Execute este arquivo para verificar todos os pontos da integração
 */
require_once __DIR__ . '/api/config.php';

// Forçar exibição de erros para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<html><head><title>Diagnóstico LinkedIn</title>";
echo "<style>
body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
.card { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.success { color: #28a745; }
.error { color: #dc3545; }
.warning { color: #ffc107; }
.info { color: #17a2b8; }
h1 { color: #333; }
h2 { color: #666; border-bottom: 2px solid #eee; padding-bottom: 10px; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
.btn { display: inline-block; padding: 10px 20px; background: #0077b5; color: white; text-decoration: none; border-radius: 5px; margin: 5px 0; }
.btn:hover { background: #005a87; }
</style></head><body>";

echo "<h1>🔍 Diagnóstico LinkedIn - Washiviana</h1>";

// =============================================
// 1. VERIFICAR CONFIGURAÇÕES NO BANCO
// =============================================
echo "<div class='card'>";
echo "<h2>1. Configurações no Banco de Dados</h2>";

try {
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo "<p class='error'>❌ LinkedIn NÃO está configurado no banco de dados!</p>";
        echo "<p>Execute a migration: <code>migrations/002_criar_tabelas_redes.php</code></p>";
    } else {
        echo "<p class='success'>✅ Registro encontrado no banco</p>";
        
        $checks = [
            'ativo' => ['label' => 'Ativo', 'value' => $config['ativo'] ? 'Sim' : 'Não', 'ok' => $config['ativo']],
            'client_id' => ['label' => 'Client ID', 'value' => !empty($config['client_id']) ? substr($config['client_id'], 0, 10) . '...' : '(vazio)', 'ok' => !empty($config['client_id'])],
            'client_secret' => ['label' => 'Client Secret', 'value' => !empty($config['client_secret']) ? '***configurado***' : '(vazio)', 'ok' => !empty($config['client_secret'])],
            'access_token' => ['label' => 'Access Token', 'value' => !empty($config['access_token']) ? substr($config['access_token'], 0, 20) . '... (' . strlen($config['access_token']) . ' chars)' : '(vazio)', 'ok' => !empty($config['access_token'])],
            'publish_target' => ['label' => 'Publicar em', 'value' => ($config['publish_target'] ?? 'person') === 'organization' ? '🏢 Página da Empresa' : '👤 Perfil Pessoal', 'ok' => true],
            'person_urn' => ['label' => 'Person URN', 'value' => $config['person_urn'] ?: '(vazio)', 'ok' => !empty($config['person_urn']) && strpos($config['person_urn'], 'urn:li:person:') === 0],
            'organization_urn' => ['label' => 'Organization URN', 'value' => $config['organization_urn'] ?: '(vazio)', 'ok' => ($config['publish_target'] ?? 'person') !== 'organization' || (!empty($config['organization_urn']) && strpos($config['organization_urn'], 'urn:li:organization:') === 0)],
        ];
        
        echo "<table style='width:100%; border-collapse: collapse;'>";
        echo "<tr><th style='text-align:left; padding: 8px; border-bottom: 1px solid #ddd;'>Campo</th><th style='text-align:left; padding: 8px; border-bottom: 1px solid #ddd;'>Valor</th><th style='padding: 8px; border-bottom: 1px solid #ddd;'>Status</th></tr>";
        
        foreach ($checks as $field => $check) {
            $statusIcon = $check['ok'] ? "<span class='success'>✅</span>" : "<span class='error'>❌</span>";
            echo "<tr>";
            echo "<td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>{$check['label']}</strong></td>";
            echo "<td style='padding: 8px; border-bottom: 1px solid #eee;'><code>{$check['value']}</code></td>";
            echo "<td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>$statusIcon</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar token expiration
        if (!empty($config['token_expires_at'])) {
            $expiresAt = strtotime($config['token_expires_at']);
            $now = time();
            if ($expiresAt < $now) {
                echo "<p class='error'>⚠️ Token EXPIRADO em " . date('d/m/Y H:i', $expiresAt) . "</p>";
            } else {
                $daysLeft = round(($expiresAt - $now) / 86400);
                echo "<p class='success'>✅ Token válido até: " . date('d/m/Y H:i', $expiresAt) . " ($daysLeft dias restantes)</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao acessar banco: " . $e->getMessage() . "</p>";
}
echo "</div>";

// =============================================
// 2. TESTAR CONEXÃO COM LINKEDIN API
// =============================================
if (!empty($config['access_token'])) {
    echo "<div class='card'>";
    echo "<h2>2. Teste de Conexão com LinkedIn API</h2>";
    
    $accessToken = $config['access_token'];
    
    // Teste 1: /v2/userinfo (OpenID Connect)
    echo "<h3>2.1 Endpoint: /v2/userinfo</h3>";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/v2/userinfo',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "<p class='success'>✅ Sucesso (HTTP $httpCode)</p>";
        echo "<p>Nome: <strong>" . ($data['given_name'] ?? '') . " " . ($data['family_name'] ?? '') . "</strong></p>";
        echo "<p>Email: <strong>" . ($data['email'] ?? 'N/A') . "</strong></p>";
        echo "<p>Person URN correto: <code>urn:li:person:" . ($data['sub'] ?? '') . "</code></p>";
        
        // Verificar se o Person URN configurado está correto
        $personUrnCorreto = 'urn:li:person:' . $data['sub'];
        if ($config['person_urn'] !== $personUrnCorreto) {
            echo "<p class='warning'>⚠️ O Person URN configurado ({$config['person_urn']}) é diferente do correto ($personUrnCorreto)</p>";
        }
    } else {
        echo "<p class='warning'>⚠️ HTTP $httpCode - " . ($error ?: 'Token pode não ter scopes openid/profile') . "</p>";
        echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
    }
    
    // Teste 2: /v2/me (legado)
    echo "<h3>2.2 Endpoint: /v2/me (legado)</h3>";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/v2/me',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ]
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "<p class='success'>✅ Sucesso (HTTP $httpCode)</p>";
        echo "<p>ID: <code>" . ($data['id'] ?? '') . "</code></p>";
    } else {
        echo "<p class='warning'>⚠️ HTTP $httpCode (endpoint depreciado, OK se falhar)</p>";
    }
    
    // Teste 3: Verificar permissões para publicar
    echo "<h3>2.3 Teste de permissão para publicar</h3>";
    
    $testPostPayload = [
        'author' => $config['person_urn'],
        'commentary' => 'Teste de conexão - este post NÃO será publicado.',
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED'
        ],
        'lifecycleState' => 'PUBLISHED'
    ];
    
    // Testar múltiplas versões da API
    $versoes = ['202501', '202412', '202411', '202410'];
    $publicacaoOK = false;
    
    foreach ($versoes as $versao) {
        echo "<p>Testando versão <code>$versao</code>... ";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.linkedin.com/rest/posts',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($testPostPayload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0',
                'LinkedIn-Version: ' . $versao
            ]
        ]);
        
        // NÃO executar realmente o POST - só queremos ver se temos permissão
        // Para um teste real, descomente a linha abaixo:
        // $response = curl_exec($ch);
        
        // Por segurança, vamos apenas fazer um GET para verificar se podemos acessar a API de posts
        curl_close($ch);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.linkedin.com/rest/posts?author=' . urlencode($config['person_urn']) . '&q=author&count=1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0',
                'LinkedIn-Version: ' . $versao
            ]
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "<span class='success'>✅ OK</span></p>";
            $publicacaoOK = true;
            break;
        } elseif ($httpCode === 403) {
            $errorData = json_decode($response, true);
            echo "<span class='error'>❌ 403 Forbidden</span></p>";
            if (isset($errorData['message'])) {
                echo "<p class='error' style='margin-left: 20px;'>Erro: " . htmlspecialchars($errorData['message']) . "</p>";
            }
        } elseif ($httpCode === 426) {
            echo "<span class='warning'>⚠️ 426 - Versão não ativa</span></p>";
        } else {
            echo "<span class='warning'>⚠️ HTTP $httpCode</span></p>";
        }
    }
    
    if (!$publicacaoOK) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px;'>";
        echo "<h4>🔧 Soluções para o erro 403:</h4>";
        echo "<ol>";
        echo "<li><strong>Verificar produto 'Share on LinkedIn':</strong><br>";
        echo "Acesse <a href='https://www.linkedin.com/developers/apps' target='_blank'>LinkedIn Developers</a> → Seu App → Products → Verifique se 'Share on LinkedIn' está <strong>Approved</strong></li>";
        echo "<li><strong>Gerar NOVO token após aprovação:</strong><br>";
        echo "Se o produto foi aprovado recentemente, você PRECISA gerar um novo token.<br>";
        echo "Auth → OAuth 2.0 tools → Generate token → Selecione scope <code>w_member_social</code></li>";
        echo "<li><strong>Verificar Person URN:</strong><br>";
        echo "Certifique-se que o Person URN está correto e corresponde ao usuário do token</li>";
        echo "<li><strong>Aguardar propagação:</strong><br>";
        echo "Às vezes o LinkedIn leva alguns minutos para propagar as permissões</li>";
        echo "</ol>";
        echo "</div>";
    }
    
    echo "</div>";
}

// =============================================
// 3. VERIFICAR ESTRUTURA DAS TABELAS
// =============================================
echo "<div class='card'>";
echo "<h2>3. Estrutura das Tabelas</h2>";

try {
    // Verificar tabela redes_sociais_config
    $stmt = $pdo->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'redes_sociais_config'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) > 0) {
        echo "<p class='success'>✅ Tabela <code>redes_sociais_config</code> existe</p>";
        echo "<details><summary>Ver colunas</summary><pre>";
        foreach ($columns as $col) {
            echo "- {$col['column_name']} ({$col['data_type']})\n";
        }
        echo "</pre></details>";
    } else {
        echo "<p class='error'>❌ Tabela <code>redes_sociais_config</code> não encontrada</p>";
    }
    
    // Verificar tabela publicacoes_redes
    $stmt = $pdo->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'publicacoes_redes'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columns) > 0) {
        echo "<p class='success'>✅ Tabela <code>publicacoes_redes</code> existe</p>";
    } else {
        echo "<p class='warning'>⚠️ Tabela <code>publicacoes_redes</code> não encontrada</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro: " . $e->getMessage() . "</p>";
}
echo "</div>";

// =============================================
// 4. LOGS RECENTES DE ERRO
// =============================================
echo "<div class='card'>";
echo "<h2>4. Verificar Logs de Erro PHP</h2>";

$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile) && is_readable($logFile)) {
    $lines = file($logFile);
    $linkedinErrors = [];
    foreach (array_slice($lines, -200) as $line) {
        if (stripos($line, 'linkedin') !== false || stripos($line, '403') !== false) {
            $linkedinErrors[] = $line;
        }
    }
    
    if (!empty($linkedinErrors)) {
        echo "<p class='warning'>⚠️ Erros relacionados ao LinkedIn encontrados:</p>";
        echo "<pre style='max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars(implode('', array_slice($linkedinErrors, -20)));
        echo "</pre>";
    } else {
        echo "<p class='success'>✅ Nenhum erro recente do LinkedIn nos logs</p>";
    }
} else {
    echo "<p class='info'>ℹ️ Não foi possível acessar o arquivo de log PHP</p>";
    echo "<p>Arquivo de log: <code>" . ($logFile ?: 'não definido') . "</code></p>";
}
echo "</div>";

// =============================================
// 5. AÇÕES RÁPIDAS
// =============================================
echo "<div class='card'>";
echo "<h2>5. Ações Rápidas</h2>";
echo "<a class='btn' href='admin/redes-sociais.php'>⚙️ Configurar Redes Sociais</a> ";
echo "<a class='btn' href='https://www.linkedin.com/developers/apps' target='_blank'>🔗 LinkedIn Developers</a> ";
echo "<a class='btn' href='migrations/003_add_person_urn.php'>🔄 Executar Migration 003</a> ";
echo "<a class='btn' href='test_linkedin_api.php?refresh=1' style='background: #6c757d;'>🔄 Atualizar Diagnóstico</a>";
echo "</div>";

echo "<p style='text-align: center; margin-top: 30px; color: #666;'>Diagnóstico gerado em " . date('d/m/Y H:i:s') . "</p>";
echo "</body></html>";
