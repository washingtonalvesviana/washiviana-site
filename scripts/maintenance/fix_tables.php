<?php
/**
 * Script para criar tabelas faltantes
 */
require_once __DIR__ . '/../../api/config.php';

echo "<h1>Criando tabelas faltantes</h1><pre>";

try {
    // Criar tabela posts_linkedin
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts_linkedin (
            id SERIAL PRIMARY KEY,
            projeto_id INT NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
            conteudo TEXT NOT NULL,
            prompt_usado TEXT,
            gerado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela posts_linkedin criada!\n";
    
    // Criar índice
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_posts_linkedin_projeto ON posts_linkedin(projeto_id)");
    echo "✅ Índice criado!\n";
    
    echo "\n🎉 Tabelas criadas com sucesso!\n";
    echo "\n⚠️ Delete este arquivo após o uso.\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<br><a href='admin/projetos.php'>Voltar aos Projetos</a>";
?>

