<?php
/**
 * Script para criar tabelas de artigos faltantes
 */
require_once __DIR__ . '/api/config.php';

echo "<h1>Criando tabelas de artigos</h1><pre>";

try {
    // Verificar se tabela categorias_artigos existe
    $result = $pdo->query("SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_name = 'categorias_artigos'
    )");
    $exists = $result->fetchColumn();
    
    if (!$exists) {
        // Criar tabela categorias_artigos
        $pdo->exec("
            CREATE TABLE categorias_artigos (
                id SERIAL PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                ordem INT DEFAULT 0,
                ativo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "✅ Tabela categorias_artigos criada!\n";
        
        // Inserir categorias padrão
        $pdo->exec("
            INSERT INTO categorias_artigos (nome, slug, ordem) VALUES
            ('Tecnologia', 'tecnologia', 1),
            ('Inteligência Artificial', 'inteligencia-artificial', 2),
            ('Automação', 'automacao', 3),
            ('Desenvolvimento', 'desenvolvimento', 4),
            ('Inovação', 'inovacao', 5)
        ");
        echo "✅ Categorias padrão inseridas!\n";
    } else {
        echo "ℹ️ Tabela categorias_artigos já existe.\n";
    }
    
    // Verificar se tabela artigos existe
    $result = $pdo->query("SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_name = 'artigos'
    )");
    $exists = $result->fetchColumn();
    
    if (!$exists) {
        // Criar tabela artigos
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
        echo "✅ Tabela artigos criada!\n";
        
        // Criar índices
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_categoria ON artigos(categoria_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_slug ON artigos(slug)");
        echo "✅ Índices criados!\n";
    } else {
        echo "ℹ️ Tabela artigos já existe.\n";
    }
    
    echo "\n🎉 Configuração concluída!\n";
    echo "\n⚠️ Delete este arquivo após o uso.\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<br><a href='admin/artigos.php'>Ir para Conteúdos</a>";
?>

