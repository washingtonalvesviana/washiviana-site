<?php
/**
 * Migration: Adicionar coluna organization_urn e publish_target na tabela redes_sociais_config
 * Permite escolher entre publicar no perfil pessoal ou página da empresa
 */
require_once __DIR__ . '/../api/config.php';

echo "<h2>Migration: Adicionar organization_urn e publish_target</h2>";

try {
    // Verificar se a coluna organization_urn já existe
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'redes_sociais_config' AND column_name = 'organization_urn'
    ");
    
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN organization_urn VARCHAR(255) DEFAULT ''");
        echo "<p style='color: green;'>✅ Coluna 'organization_urn' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Coluna 'organization_urn' já existe.</p>";
    }
    
    // Verificar se a coluna publish_target existe (person ou organization)
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'redes_sociais_config' AND column_name = 'publish_target'
    ");
    
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN publish_target VARCHAR(20) DEFAULT 'person'");
        echo "<p style='color: green;'>✅ Coluna 'publish_target' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Coluna 'publish_target' já existe.</p>";
    }
    
    // Corrigir Person URN existente (remover slug, deixar apenas ID numérico)
    $stmt = $pdo->query("SELECT person_urn FROM redes_sociais_config WHERE rede = 'linkedin'");
    $row = $stmt->fetch();
    
    if ($row && !empty($row['person_urn'])) {
        $currentUrn = $row['person_urn'];
        
        // Se o URN tem formato urn:li:person:slug-com-numero-38583269
        if (preg_match('/urn:li:person:.*-(\d+)$/', $currentUrn, $matches)) {
            $newUrn = 'urn:li:person:' . $matches[1];
            $pdo->prepare("UPDATE redes_sociais_config SET person_urn = ? WHERE rede = 'linkedin'")->execute([$newUrn]);
            echo "<p style='color: green;'>✅ Person URN corrigido de '$currentUrn' para '$newUrn'</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Person URN atual: $currentUrn (não necessita correção)</p>";
        }
    }
    
    echo "<p style='color: green; font-weight: bold;'>✅ Migration concluída com sucesso!</p>";
    echo "<p><a href='../admin/redes-sociais.php'>Voltar para Redes Sociais</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}
