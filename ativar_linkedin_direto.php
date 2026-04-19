<?php
/**
 * Script direto para ativar LinkedIn
 */
require_once __DIR__ . '/api/config.php';

try {
    $stmt = $pdo->prepare("
        UPDATE redes_sociais_config 
        SET ativo = true::boolean, updated_at = CURRENT_TIMESTAMP 
        WHERE rede = 'linkedin'
    ");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ LinkedIn ativado com sucesso!\n";
        
        // Verificar
        $stmt2 = $pdo->prepare("SELECT ativo FROM redes_sociais_config WHERE rede = 'linkedin'");
        $stmt2->execute();
        $result = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        if ($result && ($result['ativo'] === 't' || $result['ativo'] === true)) {
            echo "✅ Confirmação: LinkedIn está ATIVO\n";
        } else {
            echo "⚠️ Atenção: Status pode não ter sido atualizado corretamente\n";
        }
    } else {
        echo "⚠️ Nenhuma linha atualizada. LinkedIn pode não estar configurado.\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
