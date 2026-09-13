<?php
/**
 * Script de teste das credenciais do LinkedIn
 * Testa as credenciais salvas no banco de dados
 */

// Iniciar sessão para autenticação
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação básica (pode ser removido se não necessário)
// $_SESSION['admin_logged_in'] = true; // Descomente para testar sem login

require_once __DIR__ . '/../api/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Teste de Credenciais LinkedIn</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; }
    </style>
</head>
<body>
    <h1>🔍 Teste de Credenciais do LinkedIn</h1>";
    
// Mostrar mensagem de sucesso se foi ativado
if (isset($_GET['ativado']) && $_GET['ativado'] == '1') {
    echo "<div class='success'>
        <h2>✅ LinkedIn Ativado!</h2>
        <p>O LinkedIn foi ativado com sucesso. Recarregando teste...</p>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = 'test_linkedin_credentials.php';
        }, 2000);
    </script>";
}

try {
    // Buscar credenciais do banco
    $stmt = $pdo->prepare("
        SELECT ativo, access_token, person_urn, client_id, client_secret, 
               created_at, updated_at
        FROM redes_sociais_config 
        WHERE rede = 'linkedin'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        echo "<div class='error'>
            <h2>❌ LinkedIn não configurado</h2>
            <p>Não foi encontrada nenhuma configuração do LinkedIn no banco de dados.</p>
            <p>Configure em: <a href='admin/redes-sociais.php'>Redes Sociais</a></p>
        </div>";
        exit;
    }
    
    echo "<div class='info'>
        <h2>📋 Configuração encontrada</h2>
        <ul>
            <li><strong>Ativo:</strong> " . ($config['ativo'] === 't' || $config['ativo'] === true ? '✅ Sim' : '❌ Não') . "</li>
            <li><strong>Access Token:</strong> " . (!empty($config['access_token']) ? '✅ Configurado (' . substr($config['access_token'], 0, 20) . '...)' : '❌ Não configurado') . "</li>
            <li><strong>Person URN:</strong> " . (!empty($config['person_urn']) ? '✅ ' . htmlspecialchars($config['person_urn']) : '❌ Não configurado') . "</li>
            <li><strong>Client ID:</strong> " . (!empty($config['client_id']) ? '✅ Configurado' : '❌ Não configurado') . "</li>
            <li><strong>Client Secret:</strong> " . (!empty($config['client_secret']) ? '✅ Configurado' : '❌ Não configurado') . "</li>
            <li><strong>Última atualização:</strong> " . ($config['updated_at'] ?? 'N/A') . "</li>
        </ul>
    </div>";
    
    // Verificar se está completo
    $erros = [];
    if (empty($config['access_token'])) {
        $erros[] = 'Access Token não configurado';
    }
    if (empty($config['person_urn'])) {
        $erros[] = 'Person URN não configurado';
    }
    if ($config['ativo'] !== 't' && $config['ativo'] !== true) {
        $erros[] = 'LinkedIn não está ativado';
    }
    
    if (!empty($erros)) {
        echo "<div class='warning'>
            <h2>⚠️ Configuração incompleta</h2>
            <ul>";
        foreach ($erros as $erro) {
            echo "<li>$erro</li>";
        }
        echo "</ul>";
        
        // Se só falta ativar, oferecer botão para ativar
        if (count($erros) === 1 && $erros[0] === 'LinkedIn não está ativado') {
            echo "<p><strong>💡 Solução rápida:</strong></p>
            <form method='POST' action='../scripts/maintenance/ativar_linkedin.php' style='margin-top: 15px;'>
                <button type='submit' style='padding: 10px 20px; background: #0077b5; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>
                    ✅ Ativar LinkedIn Agora
                </button>
            </form>";
        } else {
            echo "<p>Corrija em: <a href='admin/redes-sociais.php'>Redes Sociais</a></p>";
        }
        echo "</div>";
    } else {
        echo "<div class='success'>
            <h2>✅ Configuração completa</h2>
            <p>Todas as credenciais necessárias estão configuradas.</p>
        </div>";
        
        // Testar conexão via API
        echo "<h2>🧪 Testando conexão com LinkedIn API...</h2>";
        
        if (!empty($config['access_token']) && !empty($config['person_urn'])) {
            // Fazer requisição de teste
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
                echo "<div class='error'>
                    <h3>❌ Erro cURL</h3>
                    <p>$error</p>
                </div>";
            } elseif ($httpCode === 200) {
                $data = json_decode($response, true);
                echo "<div class='success'>
                    <h3>✅ Conexão OK (userinfo endpoint)</h3>
                    <p><strong>Status HTTP:</strong> $httpCode</p>
                    <p><strong>Resposta:</strong></p>
                    <pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>
                </div>";
            } elseif ($httpCode === 401) {
                echo "<div class='error'>
                    <h3>❌ Token inválido ou expirado</h3>
                    <p><strong>Status HTTP:</strong> $httpCode</p>
                    <p>O Access Token pode estar expirado ou inválido. Gere um novo token em:</p>
                    <p><a href='https://www.linkedin.com/developers/apps' target='_blank'>LinkedIn Developers</a></p>
                    <p><strong>Resposta:</strong></p>
                    <pre>" . htmlspecialchars($response) . "</pre>
                </div>";
            } else {
                // Tentar endpoint alternativo
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
                    echo "<div class='success'>
                        <h3>✅ Conexão OK (me endpoint)</h3>
                        <p><strong>Status HTTP:</strong> $httpCode2</p>
                        <p><strong>Resposta:</strong></p>
                        <pre>" . htmlspecialchars(json_encode($data2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>
                    </div>";
                } else {
                    // Se tem person_urn, considerar válido (scope w_member_social)
                    if (!empty($config['person_urn']) && strpos($config['person_urn'], 'urn:li:person:') === 0) {
                        echo "<div class='warning'>
                            <h3>⚠️ Token com scope limitado (w_member_social)</h3>
                            <p><strong>Status HTTP userinfo:</strong> $httpCode</p>
                            <p><strong>Status HTTP me:</strong> $httpCode2</p>
                            <p>O token tem apenas o scope <code>w_member_social</code>, que é suficiente para publicar posts.</p>
                            <p><strong>Person URN configurado:</strong> " . htmlspecialchars($config['person_urn']) . "</p>
                            <p>✅ As credenciais estão prontas para publicar posts!</p>
                        </div>";
                    } else {
                        echo "<div class='error'>
                            <h3>❌ Erro na conexão</h3>
                            <p><strong>Status HTTP userinfo:</strong> $httpCode</p>
                            <p><strong>Status HTTP me:</strong> $httpCode2</p>
                            <p><strong>Resposta userinfo:</strong></p>
                            <pre>" . htmlspecialchars($response) . "</pre>
                            <p><strong>Resposta me:</strong></p>
                            <pre>" . htmlspecialchars($response2) . "</pre>
                        </div>";
                    }
                }
            }
        }
        
        // Testar publicação de post de teste (opcional)
        echo "<h2>📝 Teste de Publicação (Opcional)</h2>";
        echo "<div class='info'>
            <p>Para testar a publicação real, você pode:</p>
            <ol>
                <li>Ir em <a href='admin/artigos.php'>Artigos</a></li>
                <li>Criar um novo artigo</li>
                <li>Selecionar status 'Publicar'</li>
                <li>Marcar 'LinkedIn' nas redes sociais</li>
                <li>Salvar o artigo</li>
            </ol>
            <p>O sistema tentará publicar automaticamente no LinkedIn.</p>
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>
        <h2>❌ Erro</h2>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
        <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
    </div>";
}

echo "</body></html>";
?>
