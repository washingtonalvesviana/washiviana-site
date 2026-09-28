package auth

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"time"

	"golang.org/x/crypto/bcrypt"

	"washiviana/backend/internal/store"
)

var (
	// ErrInvalidCredentials indica e-mail/senha incorretos.
	ErrInvalidCredentials = errors.New("credenciais inválidas")
	// ErrUnauthorized indica sessão ausente/ inválida.
	ErrUnauthorized = errors.New("não autenticado")
)

// Service implementa autenticação com sessões server-side (tabela `sessions`).
type Service struct {
	store *store.Store
	ttl   time.Duration
}

// New cria o serviço de autenticação.
func New(st *store.Store, ttl time.Duration) *Service {
	if ttl <= 0 {
		ttl = 7 * 24 * time.Hour
	}
	return &Service{store: st, ttl: ttl}
}

// SessionInfo é o resultado de login/autenticação.
type SessionInfo struct {
	Token     string
	CSRFToken string
	ExpiresAt time.Time
	User      *store.User
}

func randomHex(nBytes int) (string, error) {
	b := make([]byte, nBytes)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return hex.EncodeToString(b), nil
}

// HashToken devolve o SHA-256 (hex) do token — o banco nunca guarda o token cru.
func HashToken(token string) string {
	sum := sha256.Sum256([]byte(token))
	return hex.EncodeToString(sum[:])
}

// Login valida as credenciais (bcrypt, compatível com o PHP) e cria uma sessão.
func (s *Service) Login(ctx context.Context, email, password, ua, ip string) (*SessionInfo, error) {
	user, err := s.store.GetUserByEmail(ctx, email)
	if err != nil {
		if errors.Is(err, store.ErrNotFound) {
			return nil, ErrInvalidCredentials
		}
		return nil, err
	}
	if bcrypt.CompareHashAndPassword([]byte(user.Senha), []byte(password)) != nil {
		return nil, ErrInvalidCredentials
	}

	token, err := randomHex(32)
	if err != nil {
		return nil, err
	}
	csrf, err := randomHex(32)
	if err != nil {
		return nil, err
	}
	sessionID, err := randomHex(16)
	if err != nil {
		return nil, err
	}
	expires := time.Now().Add(s.ttl)

	if err := s.store.CreateSession(ctx, sessionID, user.ID, HashToken(token), csrf, ua, ip, expires); err != nil {
		return nil, err
	}
	return &SessionInfo{Token: token, CSRFToken: csrf, ExpiresAt: expires, User: user}, nil
}

// Authenticate valida um token de sessão e devolve o usuário.
func (s *Service) Authenticate(ctx context.Context, token string) (*SessionInfo, error) {
	if token == "" {
		return nil, ErrUnauthorized
	}
	sess, err := s.store.GetSessionByTokenHash(ctx, HashToken(token))
	if err != nil {
		if errors.Is(err, store.ErrNotFound) {
			return nil, ErrUnauthorized
		}
		return nil, err
	}
	user, err := s.store.GetUserByID(ctx, sess.UserID)
	if err != nil {
		if errors.Is(err, store.ErrNotFound) {
			return nil, ErrUnauthorized
		}
		return nil, err
	}
	return &SessionInfo{CSRFToken: sess.CSRFToken, ExpiresAt: sess.ExpiresAt, User: user}, nil
}

// Logout remove a sessão.
func (s *Service) Logout(ctx context.Context, token string) error {
	if token == "" {
		return nil
	}
	return s.store.DeleteSessionByTokenHash(ctx, HashToken(token))
}

// TTL devolve a duração configurada da sessão.
func (s *Service) TTL() time.Duration { return s.ttl }
