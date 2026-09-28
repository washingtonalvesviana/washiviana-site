-- 017_social_variants_status_width.sql
-- Amplia artigos_social_variants.status para comportar 'pronto_para_publicacao' (22 chars),
-- que hoje NÃO cabe em varchar(20) — bug latente que faz o worker PHP falhar.
-- ADITIVA e não-destrutiva (widening de varchar; em PostgreSQL é apenas metadado).
-- Aplicar primeiro em staging; em produção no cutover da Fase 3.

ALTER TABLE artigos_social_variants
    ALTER COLUMN status TYPE varchar(30);
