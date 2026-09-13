-- ============================================
-- MIGRATION 015 - Remover coluna morta token_expira_em
-- Data: 2026-09-13
-- ============================================
-- A coluna token_expira_em foi criada junto da tabela (migrations 001/002),
-- mas nunca foi usada pelo codigo: o OAuth do LinkedIn grava token_expires_at.
-- Todos os valores de token_expira_em estao NULL. Remocao segura.
-- Idempotente.

ALTER TABLE redes_sociais_config DROP COLUMN IF EXISTS token_expira_em;
