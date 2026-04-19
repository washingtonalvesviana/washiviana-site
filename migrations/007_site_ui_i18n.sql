-- ============================================
-- WASHIVIANA PORTFOLIO - Site UI + Config i18n
-- ============================================

-- Configurações i18n (valores traduzidos por chave/idioma)
CREATE TABLE IF NOT EXISTS configuracoes_i18n (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(100) NOT NULL,
    lang VARCHAR(5) NOT NULL CHECK (lang IN ('pt', 'en', 'es')),
    valor TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (chave, lang)
);

CREATE INDEX IF NOT EXISTS idx_config_i18n_chave_lang ON configuracoes_i18n(chave, lang);

-- Strings de UI do site (textos fixos do template)
CREATE TABLE IF NOT EXISTS ui_strings (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(150) NOT NULL,
    lang VARCHAR(5) NOT NULL CHECK (lang IN ('pt', 'en', 'es')),
    texto TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (chave, lang)
);

CREATE INDEX IF NOT EXISTS idx_ui_strings_chave_lang ON ui_strings(chave, lang);

-- Triggers updated_at (reuse existing function if present)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_proc WHERE proname = 'update_updated_at_column') THEN
        DROP TRIGGER IF EXISTS update_configuracoes_i18n_updated_at ON configuracoes_i18n;
        CREATE TRIGGER update_configuracoes_i18n_updated_at
            BEFORE UPDATE ON configuracoes_i18n
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();

        DROP TRIGGER IF EXISTS update_ui_strings_updated_at ON ui_strings;
        CREATE TRIGGER update_ui_strings_updated_at
            BEFORE UPDATE ON ui_strings
            FOR EACH ROW
            EXECUTE FUNCTION update_updated_at_column();
    END IF;
END $$;

