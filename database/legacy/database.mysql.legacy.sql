-- ============================================
-- WASHIVIANA PORTFOLIO - DATABASE SCHEMA
-- ============================================

CREATE DATABASE IF NOT EXISTS washiviana_portfolio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE washiviana_portfolio;

-- Tabela de usuários (administradores)
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de categorias
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de projetos
CREATE TABLE IF NOT EXISTS projetos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    descricao TEXT,
    categoria_id INT NOT NULL,
    imagem_principal VARCHAR(255),
    imagens_galeria TEXT,
    tecnologias TEXT,
    url_projeto VARCHAR(255),
    destaque BOOLEAN DEFAULT 0,
    ativo BOOLEAN DEFAULT 1,
    ordem INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações do site
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de posts do LinkedIn gerados pela IA
CREATE TABLE IF NOT EXISTS posts_linkedin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    projeto_id INT NOT NULL,
    conteudo TEXT NOT NULL,
    prompt_usado TEXT,
    gerado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DADOS INICIAIS
-- ============================================

-- Usuário administrador padrão
-- Email: contact@washiviana.com
-- Senha: Washiviana@2026 (alterar após primeiro login!)
INSERT INTO usuarios (nome, email, senha) VALUES 
('Washington Viana', 'contact@washiviana.com', '$2y$10$ZnwTH38/SFtd6XSw8cp2seUFKyzg3AZVhn3HkSMLnR6ewS/hevy5i');

-- Categorias iniciais
INSERT INTO categorias (nome, slug, ordem) VALUES
('Web Design & Development', 'web-design-development', 1),
('3D Modeling & Animation', '3d-modeling-animation', 2),
('Artificial Intelligence', 'artificial-intelligence', 3),
('AR/VR Experiences', 'ar-vr-experiences', 4),
('UI/UX Design', 'ui-ux-design', 5),
('Branding & Identity', 'branding-identity', 6),
('Motion Design', 'motion-design', 7);

-- Configurações iniciais do site
INSERT INTO configuracoes (chave, valor) VALUES
('site_titulo', 'Washington Viana'),
('site_subtitulo', 'Interdisciplinary Creative & Developer'),
('home_frase_impacto', 'Transformando visões em realidade através de tecnologia e criatividade'),
('site_email', 'contact@washiviana.com'),
('site_telefone', '+55 19 9 9942 2907'),
('site_linkedin', 'https://linkedin.com/in/washingtonviana'),
('openai_api_key', ''),
('openai_model', 'gpt-4'),
('openai_max_tokens', '800');

-- ============================================
-- ÍNDICES PARA PERFORMANCE
-- ============================================

CREATE INDEX idx_projetos_categoria ON projetos(categoria_id);
CREATE INDEX idx_projetos_slug ON projetos(slug);
CREATE INDEX idx_projetos_ativo ON projetos(ativo);
CREATE INDEX idx_projetos_destaque ON projetos(destaque);
CREATE INDEX idx_categorias_slug ON categorias(slug);
CREATE INDEX idx_categorias_ativo ON categorias(ativo);

