<?php
/**
 * Migração: Criar tabelas de redes sociais
 */
require_once __DIR__ . '/../api/config.php';

echo "<h2>Criando tabelas de redes sociais...</h2><pre>";

try {
    // Tabela de credenciais de redes sociais
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS redes_sociais_config (
            id SERIAL PRIMARY KEY,
            rede VARCHAR(50) NOT NULL UNIQUE,
            ativo BOOLEAN DEFAULT FALSE,
            client_id VARCHAR(255),
            client_secret VARCHAR(255),
            access_token TEXT,
            refresh_token TEXT,
            token_expira_em TIMESTAMP,
            page_id VARCHAR(100),
            user_id VARCHAR(100),
            dados_extras JSONB,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela redes_sociais_config criada\n";
    
    // Tabela de publicações nas redes sociais
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS publicacoes_redes (
            id SERIAL PRIMARY KEY,
            artigo_id INT NOT NULL REFERENCES artigos(id) ON DELETE CASCADE,
            rede VARCHAR(50) NOT NULL,
            post_id VARCHAR(100),
            url_post VARCHAR(500),
            status VARCHAR(20) DEFAULT 'pendente',
            erro_mensagem TEXT,
            publicado_em TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela publicacoes_redes criada\n";
    
    // Tabela de métricas das publicações
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS metricas_publicacoes (
            id SERIAL PRIMARY KEY,
            publicacao_id INT NOT NULL REFERENCES publicacoes_redes(id) ON DELETE CASCADE,
            data_coleta TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            visualizacoes INT DEFAULT 0,
            curtidas INT DEFAULT 0,
            comentarios INT DEFAULT 0,
            compartilhamentos INT DEFAULT 0,
            cliques INT DEFAULT 0,
            alcance INT DEFAULT 0,
            engajamento DECIMAL(5,2) DEFAULT 0,
            dados_extras JSONB
        )
    ");
    echo "✅ Tabela metricas_publicacoes criada\n";
    
    // Índices
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_publicacoes_artigo ON publicacoes_redes(artigo_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_publicacoes_rede ON publicacoes_redes(rede)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_metricas_publicacao ON metricas_publicacoes(publicacao_id)");
    echo "✅ Índices criados\n";
    
    // Inserir configurações padrão das redes
    $pdo->exec("INSERT INTO redes_sociais_config (rede, ativo) VALUES ('linkedin', FALSE) ON CONFLICT (rede) DO NOTHING");
    $pdo->exec("INSERT INTO redes_sociais_config (rede, ativo) VALUES ('instagram', FALSE) ON CONFLICT (rede) DO NOTHING");
    echo "✅ Configurações padrão inseridas\n";
    
    // Adicionar coluna tipo_midia se não existir
    $pdo->exec("ALTER TABLE artigos ADD COLUMN IF NOT EXISTS tipo_midia VARCHAR(10) DEFAULT 'imagem'");
    echo "✅ Coluna tipo_midia adicionada\n";
    
    // Verificar tabelas
    echo "\n<h3>Tabelas no banco:</h3>\n";
    $stmt = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\n✅ Migração concluída com sucesso!\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='../admin/artigos.php'>Ir para Gerenciar Conteúdos</a></p>";

