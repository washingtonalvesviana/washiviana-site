package store

import (
	"context"
	"errors"
	"time"

	"github.com/jackc/pgx/v5"
)

// ErrNotFound indica ausência de registro.
var ErrNotFound = errors.New("registro não encontrado")

// User representa um usuário do admin (tabela usuarios).
type User struct {
	ID    int
	Nome  string
	Email string
	Senha string // hash bcrypt
}

// Session representa uma sessão do backend Go (tabela sessions).
type Session struct {
	ID         string
	UserID     int
	CSRFToken  string
	ExpiresAt  time.Time
}

// GetUserByEmail busca usuário por e-mail.
func (s *Store) GetUserByEmail(ctx context.Context, email string) (*User, error) {
	var u User
	err := s.pool.QueryRow(ctx,
		"SELECT id, nome, email, senha FROM usuarios WHERE email = $1 LIMIT 1", email,
	).Scan(&u.ID, &u.Nome, &u.Email, &u.Senha)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, err
	}
	return &u, nil
}

// GetUserByID busca usuário por id.
func (s *Store) GetUserByID(ctx context.Context, id int) (*User, error) {
	var u User
	err := s.pool.QueryRow(ctx,
		"SELECT id, nome, email, senha FROM usuarios WHERE id = $1 LIMIT 1", id,
	).Scan(&u.ID, &u.Nome, &u.Email, &u.Senha)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, err
	}
	return &u, nil
}

// CreateSession grava uma nova sessão.
func (s *Store) CreateSession(ctx context.Context, id string, userID int, tokenHash, csrf, ua, ip string, expires time.Time) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO sessions (id, user_id, token_hash, csrf_token, user_agent, ip, expires_at)
		VALUES ($1, $2, $3, $4, NULLIF($5, ''), NULLIF($6, ''), $7)`,
		id, userID, tokenHash, csrf, ua, ip, expires)
	return err
}

// GetSessionByTokenHash retorna sessão válida (não expirada) e atualiza last_seen_at.
func (s *Store) GetSessionByTokenHash(ctx context.Context, tokenHash string) (*Session, error) {
	var sess Session
	err := s.pool.QueryRow(ctx, `
		UPDATE sessions
		   SET last_seen_at = now()
		 WHERE token_hash = $1 AND expires_at > now()
		RETURNING id, user_id, csrf_token, expires_at`, tokenHash,
	).Scan(&sess.ID, &sess.UserID, &sess.CSRFToken, &sess.ExpiresAt)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, err
	}
	return &sess, nil
}

// DeleteSessionByTokenHash remove uma sessão (logout).
func (s *Store) DeleteSessionByTokenHash(ctx context.Context, tokenHash string) error {
	_, err := s.pool.Exec(ctx, "DELETE FROM sessions WHERE token_hash = $1", tokenHash)
	return err
}

// DeleteExpiredSessions limpa sessões expiradas (manutenção).
func (s *Store) DeleteExpiredSessions(ctx context.Context) (int64, error) {
	tag, err := s.pool.Exec(ctx, "DELETE FROM sessions WHERE expires_at <= now()")
	if err != nil {
		return 0, err
	}
	return tag.RowsAffected(), nil
}
