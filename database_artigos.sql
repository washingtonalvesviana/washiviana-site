-- ============================================
-- WASHIVIANA PORTFOLIO - TABELA DE ARTIGOS/NEWS
-- Execute este script para adicionar a tabela de artigos
-- ============================================

USE washiviana_portfolio;

-- Tabela de artigos/news (gerados por IA)
CREATE TABLE IF NOT EXISTS artigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    resumo TEXT,
    conteudo LONGTEXT,
    imagem_capa VARCHAR(255),
    tags VARCHAR(500),
    autor VARCHAR(100) DEFAULT 'Washington Viana',
    fonte_ia BOOLEAN DEFAULT 0 COMMENT 'Se foi gerado por IA',
    prompt_usado TEXT COMMENT 'Prompt usado para gerar o artigo',
    destaque BOOLEAN DEFAULT 0,
    ativo BOOLEAN DEFAULT 1,
    visualizacoes INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias de artigos (separadas das categorias de projetos)
CREATE TABLE IF NOT EXISTS categorias_artigos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descricao VARCHAR(255),
    cor VARCHAR(7) DEFAULT '#607AFB',
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relacionamento artigos <-> categorias (muitos para muitos)
CREATE TABLE IF NOT EXISTS artigos_categorias (
    artigo_id INT NOT NULL,
    categoria_id INT NOT NULL,
    PRIMARY KEY (artigo_id, categoria_id),
    FOREIGN KEY (artigo_id) REFERENCES artigos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias_artigos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices para performance
CREATE INDEX idx_artigos_slug ON artigos(slug);
CREATE INDEX idx_artigos_ativo ON artigos(ativo);
CREATE INDEX idx_artigos_destaque ON artigos(destaque);
CREATE INDEX idx_artigos_created ON artigos(created_at);
CREATE INDEX idx_categorias_artigos_slug ON categorias_artigos(slug);

-- Categorias iniciais de artigos
INSERT INTO categorias_artigos (nome, slug, descricao, cor, ordem) VALUES
('Inteligência Artificial', 'inteligencia-artificial', 'Artigos sobre IA, Machine Learning e automação', '#607AFB', 1),
('Tech Insights', 'tech-insights', 'Notícias e análises sobre tecnologia', '#10B981', 2),
('Automação', 'automacao', 'Dicas e tutoriais sobre automação de processos', '#F59E0B', 3),
('Desenvolvimento', 'desenvolvimento', 'Artigos sobre programação e desenvolvimento', '#8B5CF6', 4),
('Design', 'design', 'UX/UI, Design Gráfico e tendências visuais', '#EC4899', 5);

