-- ============================================
-- WASHIVIANA PORTFOLIO - i18n + SEO tables
-- Languages: pt (source), en, es
-- ============================================

-- Artigos i18n + SEO
CREATE TABLE IF NOT EXISTS artigos_i18n (
    id SERIAL PRIMARY KEY,
    artigo_id INT NOT NULL REFERENCES artigos(id) ON DELETE CASCADE,
    lang VARCHAR(5) NOT NULL CHECK (lang IN ('pt', 'en', 'es')),

    titulo VARCHAR(255),
    slug VARCHAR(255),
    resumo TEXT,
    conteudo TEXT,

    meta_title VARCHAR(255),
    meta_description VARCHAR(255),
    og_title VARCHAR(255),
    og_description VARCHAR(255),
    og_image VARCHAR(255),
    keywords TEXT,
    schema_jsonld JSONB,

    status_traducao VARCHAR(20) NOT NULL DEFAULT 'generated' CHECK (status_traducao IN ('draft', 'generated', 'reviewed')),
    generated_by VARCHAR(50),
    generated_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (artigo_id, lang),
    UNIQUE (lang, slug)
);

CREATE INDEX IF NOT EXISTS idx_artigos_i18n_artigo_lang ON artigos_i18n(artigo_id, lang);
CREATE INDEX IF NOT EXISTS idx_artigos_i18n_slug ON artigos_i18n(slug);

-- Projetos i18n + SEO
CREATE TABLE IF NOT EXISTS projetos_i18n (
    id SERIAL PRIMARY KEY,
    projeto_id INT NOT NULL REFERENCES projetos(id) ON DELETE CASCADE,
    lang VARCHAR(5) NOT NULL CHECK (lang IN ('pt', 'en', 'es')),

    titulo VARCHAR(255),
    slug VARCHAR(255),
    descricao TEXT,

    meta_title VARCHAR(255),
    meta_description VARCHAR(255),
    og_title VARCHAR(255),
    og_description VARCHAR(255),
    og_image VARCHAR(255),
    keywords TEXT,
    schema_jsonld JSONB,

    status_traducao VARCHAR(20) NOT NULL DEFAULT 'generated' CHECK (status_traducao IN ('draft', 'generated', 'reviewed')),
    generated_by VARCHAR(50),
    generated_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (projeto_id, lang),
    UNIQUE (lang, slug)
);

CREATE INDEX IF NOT EXISTS idx_projetos_i18n_projeto_lang ON projetos_i18n(projeto_id, lang);
CREATE INDEX IF NOT EXISTS idx_projetos_i18n_slug ON projetos_i18n(slug);

-- Categorias (projetos) i18n (nome/slug por idioma)
CREATE TABLE IF NOT EXISTS categorias_i18n (
    id SERIAL PRIMARY KEY,
    categoria_id INT NOT NULL REFERENCES categorias(id) ON DELETE CASCADE,
    lang VARCHAR(5) NOT NULL CHECK (lang IN ('pt', 'en', 'es')),
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (categoria_id, lang),
    UNIQUE (lang, slug)
);

CREATE INDEX IF NOT EXISTS idx_categorias_i18n_categoria_lang ON categorias_i18n(categoria_id, lang);

-- Categorias (artigos) i18n
CREATE TABLE IF NOT EXISTS categorias_artigos_i18n (
    id SERIAL PRIMARY KEY,
    categoria_id INT NOT NULL REFERENCES categorias_artigos(id) ON DELETE CASCADE,
    lang VARCHAR(5) NOT NULL CHECK (lang IN ('pt', 'en', 'es')),
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    descricao VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (categoria_id, lang),
    UNIQUE (lang, slug)
);

CREATE INDEX IF NOT EXISTS idx_cat_art_i18n_categoria_lang ON categorias_artigos_i18n(categoria_id, lang);

-- Triggers updated_at (reuse existing function if present)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'update_updated_at_column') THEN
        DROP TRIGGER IF EXISTS update_artigos_i18n_updated_at ON artigos_i18n;
        CREATE TRIGGER update_artigos_i18n_updated_at
            BEFORE UPDATE ON artigos_i18n
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();

        DROP TRIGGER IF EXISTS update_projetos_i18n_updated_at ON projetos_i18n;
        CREATE TRIGGER update_projetos_i18n_updated_at
            BEFORE UPDATE ON projetos_i18n
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();

        DROP TRIGGER IF EXISTS update_categorias_i18n_updated_at ON categorias_i18n;
        CREATE TRIGGER update_categorias_i18n_updated_at
            BEFORE UPDATE ON categorias_i18n
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();

        DROP TRIGGER IF EXISTS update_cat_art_i18n_updated_at ON categorias_artigos_i18n;
        CREATE TRIGGER update_cat_art_i18n_updated_at
            BEFORE UPDATE ON categorias_artigos_i18n
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();
    END IF;
END $$;

