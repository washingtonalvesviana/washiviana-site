-- 016_sessions.sql
-- Sessões do novo backend Go (Fase 2).
-- ADITIVA: não altera nenhuma tabela existente e não afeta o site/admin PHP.
-- Aplicar primeiro em staging; em produção, aplicar apenas quando o Go for usado.

CREATE TABLE IF NOT EXISTS sessions (
    id           text        PRIMARY KEY,
    user_id      integer     NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token_hash   text        NOT NULL UNIQUE,
    csrf_token   text        NOT NULL,
    user_agent   text        NULL,
    ip           varchar(45) NULL,
    created_at   timestamptz NOT NULL DEFAULT now(),
    last_seen_at timestamptz NOT NULL DEFAULT now(),
    expires_at   timestamptz NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id   ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at);
