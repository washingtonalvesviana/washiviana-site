-- 013_artigos_social_variants.sql
-- Criar tabela para armazenar variantes de conteúdo por rede (Instagram/Facebook/etc)
-- Data: 2026-01-18

CREATE TABLE IF NOT EXISTS artigos_social_variants (
    id SERIAL PRIMARY KEY,
    artigo_id INT NOT NULL REFERENCES artigos(id) ON DELETE CASCADE,
    rede VARCHAR(50) NOT NULL, -- e.g. 'instagram', 'facebook'
    titulo VARCHAR(255),
    caption TEXT,
    hashtags TEXT,
    media_type VARCHAR(20) DEFAULT 'imagem', -- imagem|video
    image_1x1 VARCHAR(255), -- caminho da imagem 1:1 (feed)
    image_9x16 VARCHAR(255), -- caminho da imagem 9:16 (stories/reels)
    video_file VARCHAR(255), -- caminho do video gerado/feito upload
    video_meta JSONB, -- informações do video (duracao, largura, altura etc)
    status VARCHAR(20) DEFAULT 'rascunho', -- rascunho|pronto|publicado
    scheduled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_artigos_social_variants_artigo ON artigos_social_variants(artigo_id);
CREATE INDEX IF NOT EXISTS idx_artigos_social_variants_rede ON artigos_social_variants(rede);
