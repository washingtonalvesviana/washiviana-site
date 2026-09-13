<?php
/**
 * Script para ativar o LinkedIn no banco de dados
 */
require_once __DIR__ . '/../../api/config.php';

// Permitir POST ou GET
$ativar = $_POST['ativar'] ?? $_GET['ativar'] ?? true;

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Ativar LinkedIn</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; }
        .btn { display: inline-block; padding: 10px 20px; background: #0077b5; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .btn:hover { background: #005885; }
    </style>
</head>
<body>
    <h1>🔧 Ativar LinkedIn</h1>";

try {
    // Ativar LinkedIn
    $stmt = $pdo->prepare("
        UPDATE redes_sociais_config 
        SET ativo = true::boolean, updated_at = CURRENT_TIMESTAMP 
        WHERE rede = 'linkedin'
    ");
    $stmt->execute();
    
    $rowsAffected = $stmt->rowCount();
    
    if ($rowsAffected > 0) {
        // Se veio de POST (formulário), redirecionar
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Location: test_linkedin_credentials.php?ativado=1');
            exit;
        }
        
        echo "<div class='success'>
            <h2>✅ LinkedIn Ativado com Sucesso!</h2>
            <p>O LinkedIn foi ativado no banco de dados.</p>
        </div>";
        
        // Verificar status atual
        $stmt = $pdo->prepare("
            SELECT ativo, access_token, person_urn 
            FROM redes_sociais_config 
            WHERE rede = 'linkedin'
        ");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            echo "<div class='info'>
                <h3>📋 Status Atual:</h3>
                <ul>
                    <li><strong>Ativo:</strong> " . ($config['ativo'] === 't' || $config['ativo'] === true ? '✅ Sim' : '❌ Não') . "</li>
                    <li><strong>Access Token:</strong> " . (!empty($config['access_token']) ? '✅ Configurado' : '❌ Não configurado') . "</li>
                    <li><strong>Person URN:</strong> " . (!empty($config['person_urn']) ? '✅ ' . htmlspecialchars($config['person_urn']) : '❌ Não configurado') . "</li>
                </ul>
            </div>";
        }
        
        echo "<div class='info'>
            <p><strong>Próximos passos:</strong></p>
            <ol>
                <li>Teste as credenciais: <a href='test_linkedin_credentials.php' class='btn'>Testar Credenciais</a></li>
                <li>Ou vá em <a href='admin/redes-sociais.php'>Redes Sociais</a> para verificar</li>
                <li>Crie um artigo e publique no LinkedIn!</li>
            </ol>
        </div>";
        
    } else {
        echo "<div class='error'>
            <h2>⚠️ Nenhuma linha atualizada</h2>
            <p>Não foi encontrada configuração do LinkedIn no banco de dados.</p>
            <p>Configure primeiro em: <a href='admin/redes-sociais.php'>Redes Sociais</a></p>
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>
        <h2>❌ Erro</h2>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}

echo "</body></html>";
?>
