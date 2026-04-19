-- ============================================
-- WASHIVIANA PORTFOLIO - DATABASE SCHEMA (PostgreSQL)
-- Execute este script no banco 'washiviana' do PostgreSQL
-- ============================================

-- Tabela de usuários (administradores)
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de categorias (para projetos)
CREATE TABLE IF NOT EXISTS categorias (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de projetos
CREATE TABLE IF NOT EXISTS projetos (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    descricao TEXT,
    categoria_id INT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    imagem_principal VARCHAR(255),
    imagens_galeria TEXT,
    tecnologias TEXT,
    url_projeto VARCHAR(255),
    destaque BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    ordem INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de configurações do site
CREATE TABLE IF NOT EXISTS configuracoes (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de posts do LinkedIn gerados pela IA
CREATE TABLE IF NOT EXISTS posts_linkedin (
    id SERIAL PRIMARY KEY,
    projeto_id INT NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    conteudo TEXT NOT NULL,
    prompt_usado TEXT,
    gerado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABELAS DE ARTIGOS/NEWS (para conteúdos com IA)
-- ============================================

-- Tabela de artigos/news
CREATE TABLE IF NOT EXISTS artigos (
    id SERIAL PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    resumo TEXT,
    conteudo TEXT,
    imagem_capa VARCHAR(255),
    tags VARCHAR(500),
    autor VARCHAR(100) DEFAULT 'Washington Viana',
    fonte_ia BOOLEAN DEFAULT FALSE,
    prompt_usado TEXT,
    destaque BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    visualizacoes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categorias de artigos (separadas das categorias de projetos)
CREATE TABLE IF NOT EXISTS categorias_artigos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    cor VARCHAR(7) DEFAULT '#607AFB',
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Relacionamento artigos <-> categorias (muitos para muitos)
CREATE TABLE IF NOT EXISTS artigos_categorias (
    artigo_id INT NOT NULL REFERENCES artigos(id) ON DELETE CASCADE,
    categoria_id INT NOT NULL REFERENCES categorias_artigos(id) ON DELETE CASCADE,
    PRIMARY KEY (artigo_id, categoria_id)
);

-- ============================================
-- ÍNDICES PARA PERFORMANCE
-- ============================================

CREATE INDEX IF NOT EXISTS idx_projetos_categoria ON projetos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_projetos_slug ON projetos(slug);
CREATE INDEX IF NOT EXISTS idx_projetos_ativo ON projetos(ativo);
CREATE INDEX IF NOT EXISTS idx_projetos_destaque ON projetos(destaque);
CREATE INDEX IF NOT EXISTS idx_categorias_slug ON categorias(slug);
CREATE INDEX IF NOT EXISTS idx_categorias_ativo ON categorias(ativo);
CREATE INDEX IF NOT EXISTS idx_artigos_slug ON artigos(slug);
CREATE INDEX IF NOT EXISTS idx_artigos_ativo ON artigos(ativo);
CREATE INDEX IF NOT EXISTS idx_artigos_destaque ON artigos(destaque);
CREATE INDEX IF NOT EXISTS idx_artigos_created ON artigos(created_at);
CREATE INDEX IF NOT EXISTS idx_categorias_artigos_slug ON categorias_artigos(slug);

-- ============================================
-- DADOS INICIAIS
-- ============================================

-- Usuário administrador padrão
-- Email: contact@washiviana.com
-- Senha: Washiviana@2026 (hash bcrypt)
INSERT INTO usuarios (nome, email, senha) VALUES 
('Washington Viana', 'contact@washiviana.com', '$2y$10$ZnwTH38/SFtd6XSw8cp2seUFKyzg3AZVhn3HkSMLnR6ewS/hevy5i')
ON CONFLICT (email) DO NOTHING;

-- Categorias de projetos
INSERT INTO categorias (nome, slug, ordem) VALUES
('Web Design & Development', 'web-design-development', 1),
('3D Modeling & Animation', '3d-modeling-animation', 2),
('Artificial Intelligence', 'artificial-intelligence', 3),
('AR/VR Experiences', 'ar-vr-experiences', 4),
('UI/UX Design', 'ui-ux-design', 5),
('Branding & Identity', 'branding-identity', 6),
('Motion Design', 'motion-design', 7)
ON CONFLICT (slug) DO NOTHING;

-- Categorias de artigos
INSERT INTO categorias_artigos (nome, slug, descricao, cor, ordem) VALUES
('Inteligência Artificial', 'inteligencia-artificial', 'Artigos sobre IA, Machine Learning e automação', '#607AFB', 1),
('Tech Insights', 'tech-insights', 'Notícias e análises sobre tecnologia', '#10B981', 2),
('Automação', 'automacao', 'Dicas e tutoriais sobre automação de processos', '#F59E0B', 3),
('Desenvolvimento', 'desenvolvimento', 'Artigos sobre programação e desenvolvimento', '#8B5CF6', 4),
('Design', 'design', 'UX/UI, Design Gráfico e tendências visuais', '#EC4899', 5)
ON CONFLICT (slug) DO NOTHING;

-- Configurações iniciais do site
INSERT INTO configuracoes (chave, valor) VALUES
('site_titulo', 'Washington Viana'),
('site_subtitulo', 'Tech & IA com linguagem humana'),
('home_frase_impacto', 'Crio soluções que unem tecnologia, criatividade e automação para transformar negócios e pessoas.'),
('site_email', 'contato@washiviana.com'),
('site_telefone', '+55 19 9 9942 2907'),
('site_linkedin', 'https://linkedin.com/in/washingtonviana'),
('site_instagram', 'https://instagram.com/washiviana'),
('site_github', 'https://github.com/washiviana'),
('mini_bio', '+10 anos criando soluções com tecnologia para empresas. Atuo com IA aplicada, automação, design tecnológico e desenvolvimento com foco em produtividade e governança.'),
('openai_api_key', ''),
('openai_model', 'gpt-4'),
('openai_max_tokens', '800')
ON CONFLICT (chave) DO NOTHING;

-- ============================================
-- FUNÇÃO PARA ATUALIZAR updated_at AUTOMATICAMENTE
-- ============================================

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers para atualizar updated_at
DROP TRIGGER IF EXISTS update_projetos_updated_at ON projetos;
CREATE TRIGGER update_projetos_updated_at
    BEFORE UPDATE ON projetos
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

DROP TRIGGER IF EXISTS update_configuracoes_updated_at ON configuracoes;
CREATE TRIGGER update_configuracoes_updated_at
    BEFORE UPDATE ON configuracoes
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

DROP TRIGGER IF EXISTS update_artigos_updated_at ON artigos;
CREATE TRIGGER update_artigos_updated_at
    BEFORE UPDATE ON artigos
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

