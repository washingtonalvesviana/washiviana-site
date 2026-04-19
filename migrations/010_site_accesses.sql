-- ============================================
-- MIGRAÇÃO: Tabela de acessos do site (site_accesses)
-- ============================================

CREATE TABLE IF NOT EXISTS site_accesses (
    id SERIAL PRIMARY KEY,
    path VARCHAR(500) NOT NULL,
    user_agent TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_site_accesses_created_at ON site_accesses(created_at);
CREATE INDEX IF NOT EXISTS idx_site_accesses_path ON site_accesses(path);
