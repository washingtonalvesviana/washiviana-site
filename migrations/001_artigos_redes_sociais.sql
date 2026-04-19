-- ============================================
-- MIGRAÇÃO: Sistema de Conteúdos com Redes Sociais
-- Data: 2025-12-06
-- ============================================

-- 1. Adicionar novos campos na tabela artigos
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS tipo_midia VARCHAR(10) DEFAULT 'imagem';
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS video_url VARCHAR(255);
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'rascunho';
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS categoria_id INT REFERENCES categorias_artigos(id) ON DELETE SET NULL;
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS publicar_linkedin BOOLEAN DEFAULT FALSE;
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS publicar_instagram BOOLEAN DEFAULT FALSE;
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS linkedin_post_id VARCHAR(100);
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS instagram_post_id VARCHAR(100);
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS data_publicacao TIMESTAMP;
ALTER TABLE artigos ADD COLUMN IF NOT EXISTS data_agendamento TIMESTAMP;

-- Renomear imagem_capa para imagem_principal (consistência)
ALTER TABLE artigos RENAME COLUMN imagem_capa TO imagem_principal;

-- 2. Tabela de credenciais de redes sociais
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
);

-- 3. Tabela de publicações nas redes sociais
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
);

-- 4. Tabela de métricas das publicações
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
);

-- 5. Índices para performance
CREATE INDEX IF NOT EXISTS idx_artigos_status ON artigos(status);
CREATE INDEX IF NOT EXISTS idx_artigos_tipo_midia ON artigos(tipo_midia);
CREATE INDEX IF NOT EXISTS idx_artigos_categoria ON artigos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_publicacoes_artigo ON publicacoes_redes(artigo_id);
CREATE INDEX IF NOT EXISTS idx_publicacoes_rede ON publicacoes_redes(rede);
CREATE INDEX IF NOT EXISTS idx_metricas_publicacao ON metricas_publicacoes(publicacao_id);
CREATE INDEX IF NOT EXISTS idx_metricas_data ON metricas_publicacoes(data_coleta);

-- 6. Inserir configurações padrão das redes
INSERT INTO redes_sociais_config (rede, ativo) VALUES 
('linkedin', FALSE),
('instagram', FALSE)
ON CONFLICT (rede) DO NOTHING;

-- 7. Trigger para updated_at na tabela redes_sociais_config
DROP TRIGGER IF EXISTS update_redes_sociais_config_updated_at ON redes_sociais_config;
CREATE TRIGGER update_redes_sociais_config_updated_at
    BEFORE UPDATE ON redes_sociais_config
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

