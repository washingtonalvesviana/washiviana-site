<?php
/**
 * Script para corrigir tabela artigos - FORÇA RECRIAÇÃO
 */
require_once __DIR__ . '/api/config.php';

echo "<h1>Corrigindo tabelas de artigos</h1><pre>";

try {
    // 1. Primeiro criar categorias_artigos se não existir
    echo "1. Verificando categorias_artigos...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categorias_artigos (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            ordem INT DEFAULT 0,
            ativo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela categorias_artigos OK!\n";
    
    // Inserir categorias se tabela vazia
    $count = $pdo->query("SELECT COUNT(*) FROM categorias_artigos")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("
            INSERT INTO categorias_artigos (nome, slug, ordem) VALUES
            ('Tecnologia', 'tecnologia', 1),
            ('Inteligência Artificial', 'inteligencia-artificial', 2),
            ('Automação', 'automacao', 3),
            ('Desenvolvimento', 'desenvolvimento', 4),
            ('Inovação', 'inovacao', 5)
        ");
        echo "✅ Categorias inseridas!\n";
    }
    
    // 2. Dropar tabela artigos antiga e recriar
    echo "\n2. Recriando tabela artigos...\n";
    $pdo->exec("DROP TABLE IF EXISTS artigos CASCADE");
    echo "✅ Tabela antiga removida!\n";
    
    $pdo->exec("
        CREATE TABLE artigos (
            id SERIAL PRIMARY KEY,
            titulo VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            resumo TEXT,
            conteudo TEXT,
            categoria_id INT REFERENCES categorias_artigos(id) ON DELETE SET NULL,
            imagem_principal VARCHAR(255),
            autor VARCHAR(100),
            destaque BOOLEAN DEFAULT FALSE,
            ativo BOOLEAN DEFAULT TRUE,
            fonte_ia VARCHAR(100),
            prompt_usado TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela artigos criada com todas as colunas!\n";
    
    // Criar índices
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_categoria ON artigos(categoria_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_slug ON artigos(slug)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_ativo ON artigos(ativo)");
    echo "✅ Índices criados!\n";
    
    // 3. Verificar estrutura final
    echo "\n3. Estrutura final da tabela artigos:\n";
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'artigos' ORDER BY ordinal_position");
    while ($row = $stmt->fetch()) {
        echo "   - {$row['column_name']}: {$row['data_type']}\n";
    }
    
    echo "\n🎉 CORREÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "\n⚠️ Delete este arquivo após o uso.\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<br><br><a href='admin/artigos.php' style='background:#00b894;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>✅ Ir para Conteúdos</a>";
?>

