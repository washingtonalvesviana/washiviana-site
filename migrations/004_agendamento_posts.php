<?php
/**
 * Migration: Módulo de Agendamento de Posts
 * - Campos de prompt separados (texto e imagem)
 * - Suporte a múltiplos formatos de imagem
 * - Agendamento com recorrência
 * - Status de publicação
 */
require_once __DIR__ . '/../api/config.php';

echo "<h2>Migration: Módulo de Agendamento de Posts</h2>";
echo "<pre>";

try {
    // =============================================
    // 1. NOVOS CAMPOS NA TABELA ARTIGOS
    // =============================================
    
    $novosCampos = [
        // Prompts separados
        ['prompt_texto', 'TEXT', "Prompt para geração de texto"],
        ['prompt_imagem', 'TEXT', "Prompt para geração de imagem"],
        
        // Imagens em múltiplos formatos
        ['imagem_1x1', 'VARCHAR(255)', "Imagem formato 1:1 (quadrado)"],
        ['imagem_9x16', 'VARCHAR(255)', "Imagem formato 9:16 (vertical)"],
        
        // Agendamento
        ['data_agendamento', 'TIMESTAMP', "Data/hora da publicação agendada"],
        ['recorrencia_tipo', "VARCHAR(20) DEFAULT 'nenhuma'", "Tipo: nenhuma, diaria, semanal, mensal"],
        ['recorrencia_dias', 'VARCHAR(50)', "Dias da semana (para semanal): 0,1,2,3,4,5,6"],
        ['recorrencia_dia_mes', 'INTEGER', "Dia do mês (para mensal)"],
        ['recorrencia_fim', 'DATE', "Data de término da recorrência"],
        
        // Redes sociais destino
        ['redes_destino', 'JSONB', "Redes sociais selecionadas com formatos"],
        
        // Status melhorado
        ['status_publicacao', "VARCHAR(20) DEFAULT 'rascunho'", "Status: rascunho, agendado, publicado, falha"],
        ['erro_publicacao', 'TEXT', "Mensagem de erro se falhou"],
        ['ultima_tentativa', 'TIMESTAMP', "Última tentativa de publicação"],
    ];
    
    foreach ($novosCampos as $campo) {
        $nome = $campo[0];
        $tipo = $campo[1];
        $desc = $campo[2];
        
        // Verificar se coluna existe
        $stmt = $pdo->query("
            SELECT column_name FROM information_schema.columns 
            WHERE table_name = 'artigos' AND column_name = '{$nome}'
        ");
        
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE artigos ADD COLUMN {$nome} {$tipo}");
            echo "✅ Coluna '{$nome}' adicionada ({$desc})\n";
        } else {
            echo "⚠️ Coluna '{$nome}' já existe\n";
        }
    }
    
    // =============================================
    // 2. TABELA DE HISTÓRICO DE IMAGENS
    // =============================================
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS historico_imagens (
            id SERIAL PRIMARY KEY,
            artigo_id INTEGER REFERENCES artigos(id) ON DELETE CASCADE,
            formato VARCHAR(10) NOT NULL, -- '1x1' ou '9x16'
            arquivo VARCHAR(255) NOT NULL,
            prompt_usado TEXT,
            modelo_ia VARCHAR(100),
            ativo BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela 'historico_imagens' criada/verificada\n";
    
    // =============================================
    // 3. TABELA DE AGENDAMENTOS (para recorrência)
    // =============================================
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agendamentos_posts (
            id SERIAL PRIMARY KEY,
            artigo_id INTEGER REFERENCES artigos(id) ON DELETE CASCADE,
            data_publicacao TIMESTAMP NOT NULL,
            rede_social VARCHAR(50) NOT NULL,
            formato_imagem VARCHAR(10), -- '1x1' ou '9x16'
            status VARCHAR(20) DEFAULT 'pendente', -- pendente, publicado, falha, cancelado
            post_id VARCHAR(255), -- ID do post na rede social
            erro TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            executed_at TIMESTAMP
        )
    ");
    echo "✅ Tabela 'agendamentos_posts' criada/verificada\n";
    
    // Índices
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agendamentos_data ON agendamentos_posts(data_publicacao)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_agendamentos_status ON agendamentos_posts(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_status_pub ON artigos(status_publicacao)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_artigos_agendamento ON artigos(data_agendamento)");
    echo "✅ Índices criados\n";
    
    // =============================================
    // 4. TABELA DE CONFIGURAÇÃO DE REDES
    // =============================================
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS config_redes_formatos (
            id SERIAL PRIMARY KEY,
            rede VARCHAR(50) NOT NULL UNIQUE,
            nome_exibicao VARCHAR(100) NOT NULL,
            formato_padrao VARCHAR(10) NOT NULL, -- '1x1', '9x16', '16x9'
            formatos_aceitos VARCHAR(50) NOT NULL, -- '1x1,9x16'
            icone VARCHAR(10),
            ativo BOOLEAN DEFAULT true,
            ordem INTEGER DEFAULT 0
        )
    ");
    echo "✅ Tabela 'config_redes_formatos' criada/verificada\n";
    
    // Inserir configurações padrão
    $redesPadrao = [
        ['instagram_feed', 'Instagram Feed', '1x1', '1x1,16x9', '📸', 1],
        ['instagram_reels', 'Instagram Reels', '9x16', '9x16', '🎬', 2],
        ['instagram_stories', 'Instagram Stories', '9x16', '9x16', '📱', 3],
        ['tiktok', 'TikTok', '9x16', '9x16', '🎵', 4],
        ['youtube_shorts', 'YouTube Shorts', '9x16', '9x16', '▶️', 5],
        ['linkedin', 'LinkedIn', '1x1', '1x1,16x9', '💼', 6],
        ['facebook', 'Facebook', '1x1', '1x1,16x9', '📘', 7],
        ['twitter', 'Twitter/X', '16x9', '1x1,16x9', '🐦', 8],
    ];
    
    foreach ($redesPadrao as $rede) {
        $stmt = $pdo->prepare("
            INSERT INTO config_redes_formatos (rede, nome_exibicao, formato_padrao, formatos_aceitos, icone, ordem)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (rede) DO NOTHING
        ");
        $stmt->execute($rede);
    }
    echo "✅ Configurações de redes sociais inseridas\n";
    
    echo "\n</pre>";
    echo "<p style='color: green; font-weight: bold;'>✅ Migration concluída com sucesso!</p>";
    echo "<p><a href='../admin/artigos.php'>Ir para Conteúdos</a></p>";
    
} catch (Exception $e) {
    echo "\n</pre>";
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
}

