-- Migration: Add prompt columns to projetos
ALTER TABLE projetos
    ADD COLUMN IF NOT EXISTS prompt_descricao TEXT,
    ADD COLUMN IF NOT EXISTS prompt_linkedin TEXT;

-- Run this migration in production (Postgres):
-- psql -h <host> -p <port> -U <user> -d <db> -f migrations/011_add_project_prompts.sql
