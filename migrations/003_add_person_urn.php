<?php
/**
 * Migration: Adicionar coluna person_urn na tabela redes_sociais_config
 */
require_once __DIR__ . '/../api/config.php';

echo "<h2>Migration: Adicionar person_urn</h2>";

try {
    // Verificar se a coluna já existe
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'redes_sociais_config' AND column_name = 'person_urn'
    ");
    
    if ($stmt->rowCount() === 0) {
        // Adicionar coluna person_urn
        $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN person_urn VARCHAR(255) DEFAULT ''");
        echo "<p style='color: green;'>✅ Coluna 'person_urn' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Coluna 'person_urn' já existe.</p>";
    }
    
    // Verificar se a coluna refresh_token existe
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'redes_sociais_config' AND column_name = 'refresh_token'
    ");
    
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN refresh_token TEXT DEFAULT ''");
        echo "<p style='color: green;'>✅ Coluna 'refresh_token' adicionada!</p>";
    }
    
    // Verificar se a coluna token_expires_at existe
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'redes_sociais_config' AND column_name = 'token_expires_at'
    ");
    
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN token_expires_at TIMESTAMP DEFAULT NULL");
        echo "<p style='color: green;'>✅ Coluna 'token_expires_at' adicionada!</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>✅ Migration concluída com sucesso!</p>";
    echo "<p><a href='../admin/redes-sociais.php'>Voltar para Redes Sociais</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

