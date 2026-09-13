<?php
/**
 * WASHIVIANA PORTFOLIO - Script para Criar Usuário Admin
 * Execute este arquivo apenas UMA VEZ para criar o usuário administrador
 */

require_once __DIR__ . '/../../api/config.php';

echo "<h1>Criar Usuário Administrador</h1>";

try {
    // Verificar se usuário já existe
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE email = 'contact@washiviana.com'");
    $result = $stmt->fetch();
    
    if ($result['total'] > 0) {
        echo "<p style='color: orange;'>⚠️ Usuário contact@washiviana.com já existe!</p>";
        echo "<p>Se esqueceu a senha, delete o usuário e execute este script novamente.</p>";
        echo "<p><strong>Para deletar:</strong> Acesse phpMyAdmin → tabela 'usuarios' → Delete o registro</p>";
        exit;
    }
    
    // Criar usuário
    $nome = 'Washington Viana';
    $email = 'contact@washiviana.com';
    $senha = 'Washiviana@2026';
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$nome, $email, $senhaHash]);
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb;'>";
    echo "<h2 style='color: #155724;'>✅ Usuário criado com sucesso!</h2>";
    echo "<p><strong>Email:</strong> contact@washiviana.com</p>";
    echo "<p><strong>Senha:</strong> Washiviana@2026</p>";
    echo "<p><a href='admin/index.php' style='color: #007bff;'>→ Fazer Login Agora</a></p>";
    echo "</div>";
    
    echo "<br><p style='color: red;'><strong>IMPORTANTE:</strong> Delete este arquivo (criar-admin.php) após criar o usuário!</p>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border: 1px solid #f5c6cb;'>";
    echo "<h2 style='color: #721c24;'>❌ Erro ao criar usuário</h2>";
    echo "<p><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    
    // Verificar se é erro de tabela não existe
    if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
        echo "<br><div style='background: #fff3cd; padding: 20px; border-radius: 8px; border: 1px solid #ffc107;'>";
        echo "<h3>📋 A tabela 'usuarios' não existe!</h3>";
        echo "<p>Você precisa importar o schema <strong>database/schema_postgres.sql</strong> primeiro.</p>";
        echo "<p><strong>Passos:</strong></p>";
        echo "<ol>";
        echo "<li>Acesse <strong>phpMyAdmin</strong></li>";
        echo "<li>Selecione o banco <strong>washiviana_portfolio</strong></li>";
        echo "<li>Clique em <strong>Importar</strong></li>";
        echo "<li>Selecione o arquivo <strong>database/schema_postgres.sql</strong></li>";
        echo "<li>Clique em <strong>Executar</strong></li>";
        echo "<li>Depois volte aqui e atualize esta página</li>";
        echo "</ol>";
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Admin - Washiviana</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
        a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
</body>
</html>





