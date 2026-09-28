package httpapi

import (
	"context"
	"crypto/subtle"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"math"
	"net"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"washiviana/backend/internal/ai"
	"washiviana/backend/internal/auth"
	"washiviana/backend/internal/config"
	"washiviana/backend/internal/i18n"
	"washiviana/backend/internal/i18nsite"
	"washiviana/backend/internal/radar"
	"washiviana/backend/internal/site"
	"washiviana/backend/internal/store"
	"washiviana/backend/internal/textutil"

	"golang.org/x/crypto/bcrypt"
)

const sessionCookieName = "wv_go_sess"

type ctxKey string

const ctxKeySession ctxKey = "session"

// Server expõe a API HTTP do backend Go.
type Server struct {
	cfg     config.Config
	store   *store.Store
	auth    *auth.Service
	ai      *ai.Service
	i18n    *i18n.Service
	i18nSvc *i18nsite.Service
	radar   *radar.Service
	site    *site.Service
	log     *slog.Logger
}

// New cria o servidor.
func New(cfg config.Config, st *store.Store, authSvc *auth.Service, log *slog.Logger) *Server {
	aiSvc := ai.New(st)
	return &Server{
		cfg: cfg, store: st, auth: authSvc, ai: aiSvc,
		i18n: i18n.New(st, aiSvc), i18nSvc: i18nsite.New(st, aiSvc),
		radar: radar.New(st, aiSvc, radar.CollectConfig{AllowLoopback: cfg.AllowLoopbackFetch}),
		site:  site.New(st, cfg), log: log,
	}
}

// Routes registra as rotas.
func (s *Server) Routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", s.handleHealth)

	// Auth
	mux.HandleFunc("POST /api/v1/auth/login", s.handleLogin)
	mux.HandleFunc("POST /api/v1/auth/logout", s.handleLogout)
	mux.HandleFunc("GET /api/v1/auth/me", s.handleMe)
	mux.HandleFunc("POST /api/v1/auth/change-password", s.writeGuard(s.handleChangePassword))

	// Categorias (leitura)
	mux.HandleFunc("GET /api/v1/categorias", s.handleCategorias)
	// Categorias (escrita — sessão + CSRF)
	mux.HandleFunc("POST /api/v1/categorias", s.writeGuard(s.handleCategoriaCreate))
	mux.HandleFunc("POST /api/v1/categorias/ordenar", s.writeGuard(s.handleCategoriasOrdenar))
	mux.HandleFunc("PUT /api/v1/categorias/{id}", s.writeGuard(s.handleCategoriaUpdate))
	mux.HandleFunc("DELETE /api/v1/categorias/{id}", s.writeGuard(s.handleCategoriaDelete))
	mux.HandleFunc("POST /api/v1/categorias/{id}/toggle", s.writeGuard(s.handleCategoriaToggle))

	// Artigos (leitura)
	mux.HandleFunc("GET /api/v1/artigos", s.handleArtigosList)
	mux.HandleFunc("GET /api/v1/artigos/{id}", s.handleArtigoGet)
	// Artigos (escrita — sessão + CSRF)
	mux.HandleFunc("POST /api/v1/artigos", s.writeGuard(s.handleArtigoCreate))
	mux.HandleFunc("PUT /api/v1/artigos/{id}", s.writeGuard(s.handleArtigoUpdate))
	mux.HandleFunc("DELETE /api/v1/artigos/{id}", s.writeGuard(s.handleArtigoDelete))

	// Projetos (leitura)
	mux.HandleFunc("GET /api/v1/projetos", s.handleProjetosList)
	mux.HandleFunc("GET /api/v1/projetos/{id}", s.handleProjetoGet)
	mux.HandleFunc("GET /api/v1/projetos/slug/{slug}", s.handleProjetoGetBySlug)
	// Projetos (escrita — sessão + CSRF)
	mux.HandleFunc("POST /api/v1/projetos", s.writeGuard(s.handleProjetoCreate))
	mux.HandleFunc("POST /api/v1/projetos/ordenar", s.writeGuard(s.handleProjetosOrdenar))
	mux.HandleFunc("PUT /api/v1/projetos/{id}", s.writeGuard(s.handleProjetoUpdate))
	mux.HandleFunc("DELETE /api/v1/projetos/{id}", s.writeGuard(s.handleProjetoDelete))
	mux.HandleFunc("POST /api/v1/projetos/{id}/toggle-status", s.writeGuard(s.handleProjetoToggleStatus))
	mux.HandleFunc("POST /api/v1/projetos/{id}/toggle-destaque", s.writeGuard(s.handleProjetoToggleDestaque))
	mux.HandleFunc("POST /api/v1/projetos/{id}/linkedin-post", s.writeGuard(s.handleProjetoLinkedinPost))
	mux.HandleFunc("POST /api/v1/projetos/{id}/media/delete", s.writeGuard(s.handleProjetoMediaDelete))
	mux.HandleFunc("GET /api/v1/linkedin-posts/{id}", s.handleLinkedinPostGet)

	// i18n/SEO
	mux.HandleFunc("GET /api/v1/i18n-seo", s.handleI18nSeo)

	// IA (geração de texto/artigo)
	mux.HandleFunc("POST /api/v1/ai/text", s.writeGuard(s.handleAIText))
	mux.HandleFunc("POST /api/v1/ai/article", s.writeGuard(s.handleAIArticle))
	mux.HandleFunc("POST /api/v1/ai/image", s.writeGuard(s.handleAIImage))
	mux.HandleFunc("POST /api/v1/ai/social-agent", s.writeGuard(s.handleAISocialAgent))
	mux.HandleFunc("GET /api/v1/ai/gemini-models", s.handleGeminiModels)
	mux.HandleFunc("POST /api/v1/ai/images-multi", s.writeGuard(s.handleAIImagesMulti))

	// Redes sociais (config)
	mux.HandleFunc("GET /api/v1/redes-sociais", s.handleRedesList)
	mux.HandleFunc("POST /api/v1/redes-sociais", s.writeGuard(s.handleRedeSave))

	// Vídeos (fila)
	mux.HandleFunc("POST /api/v1/videos/enqueue", s.writeGuard(s.handleVideoEnqueue))
	mux.HandleFunc("GET /api/v1/videos/jobs/{id}", s.handleVideoJobStatus)

	// Beacon de acessos (público, same-origin)
	mux.HandleFunc("POST /api/v1/metrics/beacon", s.handleMetricsBeacon)

	// Site público (strangler; sem cutover ainda)
	mux.HandleFunc("GET /site", s.handleSiteRoot)
	mux.HandleFunc("GET /site/", s.handleSiteRoot)
	mux.HandleFunc("GET /site/{lang}/", s.handleSiteHome)
	mux.HandleFunc("GET /site/{lang}/conteudos", s.handleSiteConteudos)
	mux.HandleFunc("GET /site/{lang}/projetos", s.handleSiteProjetos)
	mux.HandleFunc("GET /site/{lang}/sobre", s.handleSiteSobre)
	mux.HandleFunc("GET /site/{lang}/artigo/{slug}", s.handleSiteArtigo)
	mux.HandleFunc("GET /site/{lang}/projeto/{slug}", s.handleSiteProjeto)
	mux.HandleFunc("GET /site/sitemap.xml", s.handleSiteSitemap)

	// Radar (temas, fontes, itens, ideias — CRUD/listas)
	mux.HandleFunc("GET /api/v1/radar/topics", s.handleRadarTopicsList)
	mux.HandleFunc("POST /api/v1/radar/topics", s.writeGuard(s.handleRadarTopicSave))
	mux.HandleFunc("DELETE /api/v1/radar/topics/{id}", s.writeGuard(s.handleRadarTopicDelete))
	mux.HandleFunc("GET /api/v1/radar/sources", s.handleRadarSourcesList)
	mux.HandleFunc("POST /api/v1/radar/sources", s.writeGuard(s.handleRadarSourceSave))
	mux.HandleFunc("DELETE /api/v1/radar/sources/{id}", s.writeGuard(s.handleRadarSourceDelete))
	mux.HandleFunc("GET /api/v1/radar/topics/{id}/sources", s.handleRadarTopicSourcesGet)
	mux.HandleFunc("POST /api/v1/radar/topics/{id}/sources", s.writeGuard(s.handleRadarTopicSourcesSet))
	mux.HandleFunc("GET /api/v1/radar/items", s.handleRadarItemsList)
	mux.HandleFunc("POST /api/v1/radar/items/delete", s.writeGuard(s.handleRadarItemsDelete))
	mux.HandleFunc("GET /api/v1/radar/ideas", s.handleRadarIdeasList)
	mux.HandleFunc("POST /api/v1/radar/ideas/{id}/discard", s.writeGuard(s.handleRadarIdeaDiscard))
	mux.HandleFunc("GET /api/v1/radar/ideas/{id}/sources", s.handleRadarIdeaSources)
	mux.HandleFunc("POST /api/v1/radar/ideas/generate", s.writeGuard(s.handleRadarIdeasGenerate))
	mux.HandleFunc("POST /api/v1/radar/hype", s.writeGuard(s.handleRadarHype))
	mux.HandleFunc("POST /api/v1/radar/ideas/{id}/to-draft", s.writeGuard(s.handleRadarIdeaToDraft))
	mux.HandleFunc("POST /api/v1/radar/collect", s.writeGuard(s.handleRadarCollect))

	// SEO/Traduções (escrita)
	mux.HandleFunc("POST /api/v1/i18n/generate", s.writeGuard(s.handleI18nGenerate))
	mux.HandleFunc("POST /api/v1/i18n/status", s.writeGuard(s.handleI18nStatus))
	mux.HandleFunc("POST /api/v1/i18n/site", s.writeGuard(s.handleI18nSite))

	// Upload de mídia
	mux.HandleFunc("POST /api/v1/upload", s.writeGuard(s.handleUpload))
	mux.HandleFunc("POST /api/v1/upload/delete", s.writeGuard(s.handleUploadDelete))

	// Configurações
	mux.HandleFunc("GET /api/v1/configuracoes", s.handleConfiguracoesList)
	mux.HandleFunc("POST /api/v1/configuracoes", s.writeGuard(s.handleConfiguracoesBulkSave))
	mux.HandleFunc("POST /api/v1/configuracoes/item", s.writeGuard(s.handleConfiguracaoItemSave))

	return s.recover(s.logRequests(s.authenticate(mux)))
}

// --- autenticação ---

// authenticate protege /api/v1/* por sessão (cookie) OU X-Internal-Token
// (uso serviço-a-serviço/testes). Login é aberto; /auth/me exige sessão.
func (s *Server) authenticate(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		if path == "/health" || path == "/api/v1/auth/login" {
			next.ServeHTTP(w, r)
			return
		}
		if !strings.HasPrefix(path, "/api/v1/") {
			next.ServeHTTP(w, r)
			return
		}

		// Token interno só vale para loopback (serviço-a-serviço/testes locais).
		// Requisições vindas de fora (via nginx, com X-Real-IP) exigem sessão.
		if s.cfg.InternalToken != "" && path != "/api/v1/auth/me" && isLoopback(r) {
			provided := r.Header.Get("X-Internal-Token")
			if subtle.ConstantTimeCompare([]byte(provided), []byte(s.cfg.InternalToken)) == 1 {
				next.ServeHTTP(w, r)
				return
			}
		}

		info, err := s.auth.Authenticate(r.Context(), sessionToken(r))
		if err != nil {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"success": false, "message": "Não autorizado."})
			return
		}
		next.ServeHTTP(w, r.WithContext(context.WithValue(r.Context(), ctxKeySession, info)))
	})
}

func sessionToken(r *http.Request) string {
	c, err := r.Cookie(sessionCookieName)
	if err != nil {
		return ""
	}
	return c.Value
}

func sessionFrom(r *http.Request) *auth.SessionInfo {
	if v, ok := r.Context().Value(ctxKeySession).(*auth.SessionInfo); ok {
		return v
	}
	return nil
}

// csrfValid valida o header X-CSRF-Token contra o token da sessão (futuras escritas).
func csrfValid(r *http.Request, info *auth.SessionInfo) bool {
	if info == nil {
		return false
	}
	provided := r.Header.Get("X-CSRF-Token")
	return provided != "" && subtle.ConstantTimeCompare([]byte(provided), []byte(info.CSRFToken)) == 1
}

// writeGuard exige sessão real + CSRF válido (escritas).
func (s *Server) writeGuard(h http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		info := sessionFrom(r)
		if info == nil || info.User == nil {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"success": false, "message": "Não autorizado."})
			return
		}
		if !csrfValid(r, info) {
			writeJSON(w, http.StatusForbidden, map[string]any{"success": false, "message": "Sessão expirada. Atualize a página e tente novamente."})
			return
		}
		h(w, r)
	}
}

func (s *Server) setSessionCookie(w http.ResponseWriter, token string, expires time.Time) {
	http.SetCookie(w, &http.Cookie{
		Name:     sessionCookieName,
		Value:    token,
		Path:     "/",
		HttpOnly: true,
		Secure:   s.cfg.CookieSecure,
		SameSite: http.SameSiteLaxMode,
		Expires:  expires,
	})
}

func (s *Server) clearSessionCookie(w http.ResponseWriter) {
	http.SetCookie(w, &http.Cookie{
		Name:     sessionCookieName,
		Value:    "",
		Path:     "/",
		HttpOnly: true,
		Secure:   s.cfg.CookieSecure,
		SameSite: http.SameSiteLaxMode,
		MaxAge:   -1,
	})
}

// --- handlers de auth ---

func (s *Server) handleLogin(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Email    string `json:"email"`
		Password string `json:"password"`
	}
	if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 4096)).Decode(&body); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "JSON inválido."})
		return
	}
	if body.Email == "" || body.Password == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Informe e-mail e senha."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	info, err := s.auth.Login(ctx, body.Email, body.Password, r.UserAgent(), clientIP(r))
	if err != nil {
		if errors.Is(err, auth.ErrInvalidCredentials) {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"success": false, "message": "Credenciais inválidas."})
			return
		}
		s.fail(w, "login", err, "Erro ao autenticar.")
		return
	}

	s.setSessionCookie(w, info.Token, info.ExpiresAt)
	writeJSON(w, http.StatusOK, map[string]any{
		"success":    true,
		"csrf_token": info.CSRFToken,
		"expires_at": info.ExpiresAt.UTC().Format(time.RFC3339),
		"user": map[string]any{
			"id":    info.User.ID,
			"nome":  info.User.Nome,
			"email": info.User.Email,
		},
	})
}

func (s *Server) handleLogout(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 5*time.Second)
	defer cancel()

	if err := s.auth.Logout(ctx, sessionToken(r)); err != nil {
		s.fail(w, "logout", err, "Erro ao encerrar sessão.")
		return
	}
	s.clearSessionCookie(w)
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

func (s *Server) handleMe(w http.ResponseWriter, r *http.Request) {
	info := sessionFrom(r)
	if info == nil || info.User == nil {
		writeJSON(w, http.StatusUnauthorized, map[string]any{"success": false, "message": "Não autorizado."})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":    true,
		"csrf_token": info.CSRFToken,
		"expires_at": info.ExpiresAt.UTC().Format(time.RFC3339),
		"user": map[string]any{
			"id":    info.User.ID,
			"nome":  info.User.Nome,
			"email": info.User.Email,
		},
	})
}

func isLoopback(r *http.Request) bool {
	ip := net.ParseIP(clientIP(r))
	return ip != nil && ip.IsLoopback()
}

func (s *Server) handleChangePassword(w http.ResponseWriter, r *http.Request) {
	info := sessionFrom(r)
	if info == nil || info.User == nil {
		writeJSON(w, http.StatusUnauthorized, map[string]any{"success": false, "message": "Não autorizado."})
		return
	}
	var body struct {
		CurrentPassword string `json:"current_password"`
		NewPassword     string `json:"new_password"`
		Password        string `json:"password"`
		ConfirmPassword string `json:"confirm_password"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if body.NewPassword == "" {
		body.NewPassword = body.Password
	}
	if body.CurrentPassword == "" || body.NewPassword == "" || body.ConfirmPassword == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Preencha todos os campos."})
		return
	}
	if body.NewPassword != body.ConfirmPassword {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "A confirmação não confere com a nova senha."})
		return
	}
	if len(body.NewPassword) < 8 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "A nova senha deve ter ao menos 8 caracteres."})
		return
	}
	if body.NewPassword == body.CurrentPassword {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "A nova senha deve ser diferente da atual."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	user, err := s.store.GetUserByID(ctx, info.User.ID)
	if err != nil {
		s.fail(w, "change password user", err, "Erro ao alterar senha.")
		return
	}
	if bcrypt.CompareHashAndPassword([]byte(user.Senha), []byte(body.CurrentPassword)) != nil {
		writeJSON(w, http.StatusUnauthorized, map[string]any{"success": false, "message": "Senha atual incorreta."})
		return
	}
	hash, err := bcrypt.GenerateFromPassword([]byte(body.NewPassword), bcrypt.DefaultCost)
	if err != nil {
		s.fail(w, "change password hash", err, "Erro ao alterar senha.")
		return
	}
	if err := s.store.UpdateUserPassword(ctx, info.User.ID, string(hash)); err != nil {
		s.fail(w, "change password update", err, "Erro ao alterar senha.")
		return
	}
	_, _ = s.store.DeleteOtherSessions(ctx, info.User.ID, auth.HashToken(sessionToken(r)))
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Senha alterada com sucesso."})
}

func clientIP(r *http.Request) string {
	if ip := r.Header.Get("X-Real-IP"); ip != "" {
		return ip
	}
	host := r.RemoteAddr
	if i := strings.LastIndex(host, ":"); i > 0 {
		host = host[:i]
	}
	return host
}

// --- middlewares ---

func (s *Server) logRequests(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		s.log.Info("http",
			"method", r.Method,
			"path", r.URL.Path,
			"query", r.URL.RawQuery,
			"dur_ms", time.Since(start).Milliseconds(),
		)
	})
}

func (s *Server) recover(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if rec := recover(); rec != nil {
				s.log.Error("panic", "recover", rec, "path", r.URL.Path)
				writeJSON(w, http.StatusInternalServerError, map[string]any{
					"success": false, "message": "Erro interno.",
				})
			}
		}()
		next.ServeHTTP(w, r)
	})
}

// --- handlers de leitura ---

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
	defer cancel()

	dbStatus := "ok"
	if err := s.store.Ping(ctx); err != nil {
		dbStatus = "error"
		s.log.Error("health: db ping falhou", "error", err)
	}

	status := http.StatusOK
	statusText := "ok"
	if dbStatus != "ok" {
		status = http.StatusServiceUnavailable
		statusText = "degraded"
	}
	writeJSON(w, status, map[string]any{
		"status":  statusText,
		"db":      dbStatus,
		"env":     s.cfg.Env,
		"version": s.cfg.Version,
		"time":    time.Now().UTC().Format(time.RFC3339),
	})
}

func (s *Server) handleCategorias(w http.ResponseWriter, r *http.Request) {
	target := r.URL.Query().Get("target")
	if target == "" {
		target = "projetos"
	}
	if target != "projetos" && target != "artigos" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "target inválido."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 6*time.Second)
	defer cancel()

	list, err := s.store.ListCategorias(ctx, target, r.URL.Query().Get("ativas") == "1")
	if err != nil {
		s.fail(w, "listar categorias", err, "Erro ao listar categorias.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "categorias": list})
}

// --- categorias: escrita ---

func (s *Server) handleCategoriaCreate(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Nome   string `json:"nome"`
		Target string `json:"target"`
		Ativo  *bool  `json:"ativo"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	t := store.NewCategoryTables(body.Target)
	nome := textutil.Sanitize(body.Nome)
	if nome == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nome é obrigatório."})
		return
	}
	slug := textutil.Slugify(nome)

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	exists, err := s.store.SlugExists(ctx, t.CatTable, slug)
	if err != nil {
		s.fail(w, "categoria slug", err, "Erro ao criar categoria.")
		return
	}
	if exists {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Já existe uma categoria com este nome."})
		return
	}
	ordem, err := s.store.NextCategoriaOrdem(ctx, t.CatTable)
	if err != nil {
		s.fail(w, "categoria ordem", err, "Erro ao criar categoria.")
		return
	}
	ativo := true
	if body.Ativo != nil {
		ativo = *body.Ativo
	}
	id, err := s.store.CreateCategoria(ctx, t.CatTable, nome, slug, ordem, ativo)
	if err != nil {
		s.fail(w, "criar categoria", err, "Erro ao criar categoria.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":      true,
		"message":      textutil.Ucfirst(t.Noun) + " criada com sucesso!",
		"categoria_id": id,
		"slug":         slug,
	})
}

func (s *Server) handleCategoriaUpdate(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID e nome são obrigatórios."})
		return
	}
	var body struct {
		Nome   string `json:"nome"`
		Target string `json:"target"`
		Ativo  *bool  `json:"ativo"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	t := store.NewCategoryTables(body.Target)
	nome := textutil.Sanitize(body.Nome)
	if nome == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID e nome são obrigatórios."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	exists, err := s.store.CategoryExists(ctx, t.CatTable, id)
	if err != nil {
		s.fail(w, "categoria exists", err, "Erro ao atualizar categoria.")
		return
	}
	if !exists {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": textutil.Ucfirst(t.Noun) + " não encontrada."})
		return
	}
	slug := textutil.Slugify(nome)
	dup, err := s.store.SlugExistsExcept(ctx, t.CatTable, slug, id)
	if err != nil {
		s.fail(w, "categoria slug dup", err, "Erro ao atualizar categoria.")
		return
	}
	if dup {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Já existe uma categoria com este nome."})
		return
	}
	ativo := true
	if body.Ativo != nil {
		ativo = *body.Ativo
	}
	if err := s.store.UpdateCategoria(ctx, t.CatTable, id, nome, slug, ativo); err != nil {
		s.fail(w, "atualizar categoria", err, "Erro ao atualizar categoria.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": textutil.Ucfirst(t.Noun) + " atualizada com sucesso!",
		"slug":    slug,
	})
}

func (s *Server) handleCategoriaDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID é obrigatório."})
		return
	}
	t := store.NewCategoryTables(r.URL.Query().Get("target"))

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	total, err := s.store.CountCategoriaItems(ctx, t.ItemTable, id)
	if err != nil {
		s.fail(w, "categoria count", err, "Erro ao deletar categoria.")
		return
	}
	if total > 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{
			"success": false,
			"message": "Não é possível deletar categoria com " + t.ItemLabel + " associados. Remova os " + t.ItemLabel + " primeiro.",
		})
		return
	}
	if err := s.store.DeleteCategoria(ctx, t.CatTable, id); err != nil {
		s.fail(w, "deletar categoria", err, "Erro ao deletar categoria.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": textutil.Ucfirst(t.Noun) + " deletada com sucesso!",
	})
}

func (s *Server) handleCategoriasOrdenar(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Target string `json:"target"`
		Ordem  []int  `json:"ordem"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if len(body.Ordem) == 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Ordem inválida."})
		return
	}
	t := store.NewCategoryTables(body.Target)

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	if err := s.store.SetCategoriaOrdem(ctx, t.CatTable, body.Ordem); err != nil {
		s.fail(w, "ordenar categorias", err, "Erro ao ordenar categorias.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Ordem atualizada com sucesso!"})
}

func (s *Server) handleCategoriaToggle(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID é obrigatório."})
		return
	}
	t := store.NewCategoryTables(r.URL.Query().Get("target"))

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	if err := s.store.ToggleCategoriaAtivo(ctx, t.CatTable, id); err != nil {
		s.fail(w, "toggle categoria", err, "Erro ao atualizar status.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Status atualizado com sucesso!"})
}

func (s *Server) decodeJSON(w http.ResponseWriter, r *http.Request, dst any) bool {
	if err := json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20)).Decode(dst); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "JSON inválido."})
		return false
	}
	return true
}

func (s *Server) handleArtigosList(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 10*time.Second)
	defer cancel()

	list, err := s.store.ListArtigos(ctx, r.URL.Query().Get("status"), r.URL.Query().Get("categoria"))
	if err != nil {
		s.fail(w, "listar artigos", err, "Erro ao listar artigos.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "artigos": list})
}

func (s *Server) handleArtigoGet(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "ID não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 6*time.Second)
	defer cancel()

	artigo, err := s.store.GetArtigo(ctx, id)
	if err != nil {
		s.fail(w, "buscar artigo", err, "Erro ao buscar artigo.")
		return
	}
	if artigo == nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Artigo não encontrado"})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "artigo": artigo})
}

// --- artigos: escrita ---

type artigoBody struct {
	Titulo            *string         `json:"titulo"`
	Slug              *string         `json:"slug"`
	Resumo            *string         `json:"resumo"`
	Conteudo          *string         `json:"conteudo"`
	CategoriaID       *int            `json:"categoria_id"`
	Autor             *string         `json:"autor"`
	TipoMidia         *string         `json:"tipo_midia"`
	Destaque          *bool           `json:"destaque"`
	PromptTexto       *string         `json:"prompt_texto"`
	PromptImagem      *string         `json:"prompt_imagem"`
	Imagem1x1         *string         `json:"imagem_1x1"`
	Imagem9x16        *string         `json:"imagem_9x16"`
	StatusPublicacao  *string         `json:"status_publicacao"`
	DataAgendamento   *string         `json:"data_agendamento"`
	RecorrenciaTipo   *string         `json:"recorrencia_tipo"`
	RecorrenciaDias   *string         `json:"recorrencia_dias"`
	RecorrenciaDiaMes *int            `json:"recorrencia_dia_mes"`
	RecorrenciaFim    *string         `json:"recorrencia_fim"`
	RedesDestino      json.RawMessage `json:"redes_destino"`
	ImagemPrincipal   *string         `json:"imagem_principal"`
	VideoURL          *string         `json:"video_url"`
}

func (s *Server) handleArtigoCreate(w http.ResponseWriter, r *http.Request) {
	var body artigoBody
	if !s.decodeJSON(w, r, &body) {
		return
	}
	titulo := strings.TrimSpace(derefStr(body.Titulo))
	if titulo == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Título é obrigatório"})
		return
	}
	conteudo := strings.TrimSpace(derefStr(body.Conteudo))
	if conteudo == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Conteúdo é obrigatório"})
		return
	}
	redes, err := redesDestinoValue(body.RedesDestino)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "redes_destino inválido."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()

	slug := strings.TrimSpace(derefStr(body.Slug))
	if slug == "" {
		slug = textutil.SlugifyArticle(titulo)
	}
	if exists, err := s.store.ArtigoSlugExists(ctx, slug); err != nil {
		s.fail(w, "artigo slug", err, "Erro ao criar conteúdo.")
		return
	} else if exists {
		slug = slug + "-" + strconv.FormatInt(time.Now().Unix(), 10)
	}

	status := optStr(body.StatusPublicacao, "rascunho")
	in := store.ArtigoInput{
		Titulo:            titulo,
		Slug:              slug,
		Resumo:            strings.TrimSpace(derefStr(body.Resumo)),
		Conteudo:          conteudo,
		CategoriaID:       body.CategoriaID,
		Autor:             optStr(body.Autor, "Washington Viana"),
		TipoMidia:         optStr(body.TipoMidia, "imagem"),
		Destaque:          body.Destaque != nil && *body.Destaque,
		PromptTexto:       strings.TrimSpace(derefStr(body.PromptTexto)),
		PromptImagem:      strings.TrimSpace(derefStr(body.PromptImagem)),
		Imagem1x1:         strings.TrimSpace(derefStr(body.Imagem1x1)),
		Imagem9x16:        strings.TrimSpace(derefStr(body.Imagem9x16)),
		StatusPublicacao:  status,
		DataAgendamento:   parseFlexibleDate(body.DataAgendamento),
		RecorrenciaTipo:   optStr(body.RecorrenciaTipo, "nenhuma"),
		RecorrenciaDias:   body.RecorrenciaDias,
		RecorrenciaDiaMes: body.RecorrenciaDiaMes,
		RecorrenciaFim:    parseFlexibleDate(body.RecorrenciaFim),
		RedesDestino:      redes,
		ImagemPrincipal:   body.ImagemPrincipal,
		VideoURL:          body.VideoURL,
	}
	if status == "publicado" {
		now := time.Now()
		in.DataPublicacao = &now
	}

	id, err := s.store.CreateArtigo(ctx, in)
	if err != nil {
		s.fail(w, "criar artigo", err, "Erro ao criar conteúdo.")
		return
	}
	// Publicação automática no LinkedIn NÃO implementada nesta fase (evita efeitos externos).
	writeJSON(w, http.StatusOK, map[string]any{
		"success":     true,
		"message":     "Conteúdo criado com sucesso!",
		"artigo_id":   id,
		"publicacoes": []any{},
	})
}

func (s *Server) handleArtigoUpdate(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "ID não fornecido"})
		return
	}
	var body artigoBody
	if !s.decodeJSON(w, r, &body) {
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()

	row, err := s.store.GetArtigoRow(ctx, id)
	if err != nil {
		s.fail(w, "buscar artigo (update)", err, "Erro ao atualizar conteúdo.")
		return
	}
	if row == nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Artigo não encontrado"})
		return
	}

	titulo := row.Titulo
	if body.Titulo != nil {
		titulo = strings.TrimSpace(*body.Titulo)
	}
	slug := row.Slug
	if body.Slug != nil && strings.TrimSpace(*body.Slug) != "" {
		slug = strings.TrimSpace(*body.Slug)
	}
	status := derefStr(row.StatusPublicacao)
	if body.StatusPublicacao != nil {
		status = *body.StatusPublicacao
	}
	oldStatus := derefStr(row.StatusPublicacao)

	redes := derefStr(row.RedesDestino)
	if len(body.RedesDestino) > 0 {
		v, err := redesDestinoValue(body.RedesDestino)
		if err != nil {
			writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "redes_destino inválido."})
			return
		}
		redes = v
	}

	in := store.ArtigoInput{
		Titulo:            titulo,
		Slug:              slug,
		Resumo:            overrideStr(body.Resumo, row.Resumo, true),
		Conteudo:          overrideStr(body.Conteudo, row.Conteudo, true),
		CategoriaID:       overrideInt(body.CategoriaID, row.CategoriaID),
		Autor:             overrideStr(body.Autor, row.Autor, true),
		TipoMidia:         overrideStr(body.TipoMidia, row.TipoMidia, false),
		Destaque:          body.Destaque != nil && *body.Destaque,
		PromptTexto:       overrideStr(body.PromptTexto, row.PromptTexto, true),
		PromptImagem:      overrideStr(body.PromptImagem, row.PromptImagem, true),
		Imagem1x1:         overrideStr(body.Imagem1x1, row.Imagem1x1, true),
		Imagem9x16:        overrideStr(body.Imagem9x16, row.Imagem9x16, true),
		StatusPublicacao:  status,
		DataAgendamento:   overrideDate(body.DataAgendamento, row.DataAgendamento),
		RecorrenciaTipo:   overrideStr(body.RecorrenciaTipo, row.RecorrenciaTipo, false),
		RecorrenciaDias:   overridePtrStr(body.RecorrenciaDias, row.RecorrenciaDias),
		RecorrenciaDiaMes: overrideInt(body.RecorrenciaDiaMes, row.RecorrenciaDiaMes),
		RecorrenciaFim:    overrideDate(body.RecorrenciaFim, row.RecorrenciaFim),
		RedesDestino:      redes,
		ImagemPrincipal:   overridePtrStr(body.ImagemPrincipal, row.ImagemPrincipal),
		VideoURL:          overridePtrStr(body.VideoURL, row.VideoURL),
		DataPublicacao:    row.DataPublicacao,
	}
	if status == "publicado" && oldStatus != "publicado" {
		now := time.Now()
		in.DataPublicacao = &now
	}

	if err := s.store.UpdateArtigo(ctx, id, in); err != nil {
		s.fail(w, "atualizar artigo", err, "Erro ao atualizar conteúdo.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":     true,
		"message":     "Conteúdo atualizado com sucesso!",
		"publicacoes": []any{},
	})
}

func (s *Server) handleArtigoDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "ID não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()

	_ = s.store.SoftDeletePublicacoes(ctx, id)
	if err := s.store.DeleteArtigo(ctx, id); err != nil {
		s.fail(w, "deletar artigo", err, "Erro ao excluir conteúdo.")
		return
	}
	// Remoção de arquivos e de posts em redes sociais NÃO implementada nesta fase.
	writeJSON(w, http.StatusOK, map[string]any{
		"success":           true,
		"message":           "Conteúdo excluído com sucesso!",
		"redes_deletadas":   []any{},
		"linkedin_deletado": false,
	})
}

// --- helpers de conversão ---

func derefStr(p *string) string {
	if p == nil {
		return ""
	}
	return *p
}

func optStr(p *string, def string) string {
	if p == nil {
		return def
	}
	return strings.TrimSpace(*p)
}

func overrideStr(p *string, existing *string, trim bool) string {
	if p == nil {
		return derefStr(existing)
	}
	if trim {
		return strings.TrimSpace(*p)
	}
	return *p
}

func overridePtrStr(p *string, existing *string) *string {
	if p != nil {
		return p
	}
	return existing
}

func overrideInt(p *int, existing *int) *int {
	if p != nil {
		return p
	}
	return existing
}

func overrideDate(p *string, existing *time.Time) *time.Time {
	if p != nil {
		if strings.TrimSpace(*p) == "" {
			return existing
		}
		return parseFlexibleDate(p)
	}
	return existing
}

func parseFlexibleDate(p *string) *time.Time {
	if p == nil {
		return nil
	}
	s := strings.TrimSpace(*p)
	if s == "" {
		return nil
	}
	for _, layout := range []string{"2006-01-02T15:04", "2006-01-02T15:04:05", "2006-01-02 15:04:05", "2006-01-02"} {
		if t, err := time.ParseInLocation(layout, s, time.Local); err == nil {
			tt := t
			return &tt
		}
	}
	return nil
}

// redesDestinoValue aceita string JSON ou array/objeto JSON e devolve o texto JSON.
func redesDestinoValue(raw json.RawMessage) (string, error) {
	if len(raw) == 0 {
		return "[]", nil
	}
	trimmed := strings.TrimSpace(string(raw))
	if trimmed == "" || trimmed == "null" {
		return "[]", nil
	}
	if strings.HasPrefix(trimmed, "\"") {
		var s string
		if err := json.Unmarshal(raw, &s); err != nil {
			return "", err
		}
		if strings.TrimSpace(s) == "" {
			return "[]", nil
		}
		if !json.Valid([]byte(s)) {
			return "", errors.New("redes_destino não é JSON válido")
		}
		return s, nil
	}
	if !json.Valid(raw) {
		return "", errors.New("redes_destino não é JSON válido")
	}
	return trimmed, nil
}

func (s *Server) handleProjetosList(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()
	limit := atoiDefault(q.Get("limit"), 100)
	offset := atoiDefault(q.Get("offset"), 0)

	ctx, cancel := context.WithTimeout(r.Context(), 10*time.Second)
	defer cancel()

	list, err := s.store.ListProjetos(ctx, q.Get("categoria_id"), q.Get("status"), q.Get("destaque"), limit, offset)
	if err != nil {
		s.fail(w, "listar projetos", err, "Erro ao listar projetos.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "projetos": list})
}

func (s *Server) handleProjetoGet(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID ou slug é obrigatório."})
		return
	}
	s.projetoResponse(w, r, id, "")
}

func (s *Server) handleProjetoGetBySlug(w http.ResponseWriter, r *http.Request) {
	slug := r.PathValue("slug")
	if slug == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID ou slug é obrigatório."})
		return
	}
	s.projetoResponse(w, r, 0, slug)
}

func (s *Server) projetoResponse(w http.ResponseWriter, r *http.Request, id int, slug string) {
	ctx, cancel := context.WithTimeout(r.Context(), 6*time.Second)
	defer cancel()

	projeto, err := s.store.GetProjeto(ctx, id, slug)
	if err != nil {
		s.fail(w, "buscar projeto", err, "Erro ao buscar projeto.")
		return
	}
	if projeto == nil {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Projeto não encontrado."})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "projeto": projeto})
}

// --- projetos: escrita ---

type projetoBody struct {
	Titulo          *string  `json:"titulo"`
	Slug            *string  `json:"slug"`
	Descricao       *string  `json:"descricao"`
	CategoriaID     *int     `json:"categoria_id"`
	ImagemPrincipal *string  `json:"imagem_principal"`
	ImagensGaleria  []string `json:"imagens_galeria"`
	Tecnologias     *string  `json:"tecnologias"`
	URLProjeto      *string  `json:"url_projeto"`
	Destaque        *bool    `json:"destaque"`
	Ativo           *bool    `json:"ativo"`
	Ordem           *int     `json:"ordem"`
	PromptDescricao *string  `json:"prompt_descricao"`
	PromptLinkedin  *string  `json:"prompt_linkedin"`
}

func (s *Server) handleProjetoCreate(w http.ResponseWriter, r *http.Request) {
	var body projetoBody
	if !s.decodeJSON(w, r, &body) {
		return
	}
	titulo := textutil.Sanitize(derefStr(body.Titulo))
	categoriaID := 0
	if body.CategoriaID != nil {
		categoriaID = *body.CategoriaID
	}
	if titulo == "" || categoriaID <= 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Título e categoria são obrigatórios."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()

	rawSlug := strings.TrimSpace(derefStr(body.Slug))
	var slug string
	if rawSlug == "" {
		slug = textutil.Slugify(titulo)
	} else {
		slug = textutil.Slugify(textutil.Sanitize(rawSlug))
	}
	if exists, err := s.store.ProjetoSlugExists(ctx, slug); err != nil {
		s.fail(w, "projeto slug", err, "Erro ao criar projeto.")
		return
	} else if exists {
		slug = slug + "-" + textutil.Uniqid()
	}

	destaque := body.Destaque != nil && *body.Destaque
	ativo := true
	if body.Ativo != nil {
		ativo = *body.Ativo
	}
	in := store.ProjetoInput{
		Titulo:          titulo,
		Slug:            slug,
		Descricao:       derefStr(body.Descricao),
		CategoriaID:     categoriaID,
		ImagemPrincipal: body.ImagemPrincipal,
		ImagensGaleria:  body.ImagensGaleria,
		Tecnologias:     textutil.Sanitize(derefStr(body.Tecnologias)),
		URLProjeto:      textutil.Sanitize(derefStr(body.URLProjeto)),
		Destaque:        destaque,
		Ativo:           ativo,
		Ordem:           positivoOuNulo(body.Ordem),
		PromptDescricao: body.PromptDescricao,
		PromptLinkedin:  body.PromptLinkedin,
	}
	id, err := s.store.CreateProjeto(ctx, in)
	if err != nil {
		s.fail(w, "criar projeto", err, "Erro ao criar projeto.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":    true,
		"message":    "Projeto criado com sucesso!",
		"projeto_id": id,
		"slug":       slug,
	})
}

func (s *Server) handleProjetoUpdate(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID é obrigatório."})
		return
	}
	var body projetoBody
	if !s.decodeJSON(w, r, &body) {
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()

	row, err := s.store.GetProjetoRow(ctx, id)
	if err != nil {
		s.fail(w, "buscar projeto (update)", err, "Erro ao atualizar projeto.")
		return
	}
	if row == nil {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Projeto não encontrado."})
		return
	}

	titulo := row.Titulo
	if body.Titulo != nil {
		titulo = textutil.Sanitize(*body.Titulo)
	}
	categoriaID := derefInt(row.CategoriaID)
	if body.CategoriaID != nil {
		categoriaID = *body.CategoriaID
	}
	destaque := derefBool(row.Destaque)
	if body.Destaque != nil {
		destaque = *body.Destaque
	}
	ativo := derefBoolDefault(row.Ativo, true)
	if body.Ativo != nil {
		ativo = *body.Ativo
	}
	ordem := row.Ordem
	if body.Ordem != nil {
		ordem = positivoOuNulo(body.Ordem)
	}

	// slug
	rawSlug := ""
	if body.Slug != nil {
		rawSlug = strings.TrimSpace(*body.Slug)
	}
	var slug string
	if rawSlug == "" {
		if titulo != row.Titulo {
			slug = textutil.Slugify(titulo)
		} else {
			slug = row.Slug
		}
	} else {
		slug = textutil.Slugify(textutil.Sanitize(rawSlug))
	}
	if slug != row.Slug {
		if dup, err := s.store.ProjetoSlugExistsExcept(ctx, slug, id); err != nil {
			s.fail(w, "projeto slug dup", err, "Erro ao atualizar projeto.")
			return
		} else if dup {
			slug = slug + "-" + textutil.Uniqid()
		}
	}

	var galeria []string
	if body.ImagensGaleria != nil {
		galeria = body.ImagensGaleria
	} else {
		existing, err := s.store.GetProjetoGaleria(ctx, id)
		if err != nil {
			s.fail(w, "projeto galeria", err, "Erro ao atualizar projeto.")
			return
		}
		galeria = existing
	}

	in := store.ProjetoInput{
		Titulo:          titulo,
		Slug:            slug,
		Descricao:       overrideStr(body.Descricao, row.Descricao, false),
		CategoriaID:     categoriaID,
		ImagemPrincipal: overridePtrStr(body.ImagemPrincipal, row.ImagemPrincipal),
		ImagensGaleria:  galeria,
		Tecnologias:     textutil.Sanitize(overrideStr(body.Tecnologias, row.Tecnologias, false)),
		URLProjeto:      textutil.Sanitize(overrideStr(body.URLProjeto, row.URLProjeto, false)),
		Destaque:        destaque,
		Ativo:           ativo,
		Ordem:           ordem,
		PromptDescricao: overridePtrStr(body.PromptDescricao, row.PromptDescricao),
		PromptLinkedin:  overridePtrStr(body.PromptLinkedin, row.PromptLinkedin),
	}
	if err := s.store.UpdateProjeto(ctx, id, in); err != nil {
		s.fail(w, "atualizar projeto", err, "Erro ao atualizar projeto.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": "Projeto atualizado com sucesso!",
		"slug":    slug,
	})
}

func (s *Server) handleProjetoDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID é obrigatório."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()

	row, err := s.store.GetProjetoRow(ctx, id)
	if err != nil {
		s.fail(w, "buscar projeto (delete)", err, "Erro ao deletar projeto.")
		return
	}
	if row == nil {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Projeto não encontrado."})
		return
	}
	// Remoção de arquivos do disco adiada conscientemente.
	if err := s.store.DeleteProjeto(ctx, id); err != nil {
		s.fail(w, "deletar projeto", err, "Erro ao deletar projeto.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Projeto deletado com sucesso!"})
}

func (s *Server) handleProjetosOrdenar(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Ordem []int `json:"ordem"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if len(body.Ordem) == 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Ordem inválida."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 10*time.Second)
	defer cancel()
	if err := s.store.SetProjetoOrdem(ctx, body.Ordem); err != nil {
		s.fail(w, "ordenar projetos", err, "Erro ao ordenar projetos.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Ordem atualizada com sucesso!"})
}

func (s *Server) handleProjetoToggleStatus(w http.ResponseWriter, r *http.Request) {
	s.projetoToggle(w, r, s.store.ToggleProjetoAtivo, "Status atualizado com sucesso!", "Erro ao atualizar status.")
}

func (s *Server) handleProjetoToggleDestaque(w http.ResponseWriter, r *http.Request) {
	s.projetoToggle(w, r, s.store.ToggleProjetoDestaque, "Destaque atualizado com sucesso!", "Erro ao atualizar destaque.")
}

func (s *Server) projetoToggle(w http.ResponseWriter, r *http.Request, op func(context.Context, int) error, okMsg, errMsg string) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID é obrigatório."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	if err := op(ctx, id); err != nil {
		s.fail(w, "toggle projeto", err, errMsg)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": okMsg})
}

func (s *Server) handleProjetoLinkedinPost(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Projeto ID e conteúdo são obrigatórios."})
		return
	}
	var body struct {
		Conteudo string `json:"conteudo"`
		Prompt   string `json:"prompt"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Conteudo) == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Projeto ID e conteúdo são obrigatórios."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	postID, err := s.store.InsertPostLinkedin(ctx, id, body.Conteudo, body.Prompt)
	if err != nil {
		s.fail(w, "salvar post linkedin", err, "Erro ao salvar post.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Post salvo com sucesso!", "post_id": postID})
}

func (s *Server) handleLinkedinPostGet(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID é obrigatório."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	post, err := s.store.GetPostLinkedin(ctx, id)
	if err != nil {
		s.fail(w, "buscar post linkedin", err, "Erro ao buscar post.")
		return
	}
	if post == nil {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Post não encontrado."})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "post": post})
}

func (s *Server) handleProjetoMediaDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Projeto ID e nome de arquivo são obrigatórios."})
		return
	}
	var body struct {
		Filename string `json:"filename"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Filename) == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Projeto ID e nome de arquivo são obrigatórios."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	galeria, err := s.store.GetProjetoGaleria(ctx, id)
	if err != nil {
		if errors.Is(err, store.ErrNotFound) {
			writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Projeto não encontrado."})
			return
		}
		s.fail(w, "projeto galeria (media delete)", err, "Erro ao deletar mídia.")
		return
	}
	found := false
	filtered := make([]string, 0, len(galeria))
	for _, item := range galeria {
		if item == body.Filename {
			found = true
			continue
		}
		filtered = append(filtered, item)
	}
	if !found {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Arquivo não pertence a este projeto."})
		return
	}
	// Remoção do arquivo no disco adiada conscientemente.
	if err := s.store.SetProjetoGaleria(ctx, id, filtered); err != nil {
		s.fail(w, "projeto galeria update", err, "Erro ao deletar mídia.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Mídia deletada com sucesso."})
}

func positivoOuNulo(p *int) *int {
	if p == nil || *p <= 0 {
		return nil
	}
	v := *p
	return &v
}

func derefInt(p *int) int {
	if p == nil {
		return 0
	}
	return *p
}

func derefBool(p *bool) bool {
	if p == nil {
		return false
	}
	return *p
}

func derefBoolDefault(p *bool, def bool) bool {
	if p == nil {
		return def
	}
	return *p
}

func (s *Server) handleI18nSeo(w http.ResponseWriter, r *http.Request) {
	q := r.URL.Query()
	entity := q.Get("entity")
	id := atoiDefault(q.Get("id"), 0)
	lang := q.Get("lang")

	if (entity != "artigo" && entity != "projeto") || id <= 0 || lang == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Parâmetros inválidos."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 6*time.Second)
	defer cancel()

	data, err := s.store.GetI18nSeo(ctx, entity, id, lang)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{"success": false, "message": "Erro ao buscar dados."})
		return
	}
	if data == nil {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Dados de SEO não encontrados."})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "data": data})
}

// --- IA ---

func (s *Server) handleAIText(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Prompt    string `json:"prompt"`
		MaxTokens int    `json:"max_tokens"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Prompt) == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Prompt não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 200*time.Second)
	defer cancel()

	text, fonte, err := s.ai.GenerateText(ctx, body.Prompt, body.MaxTokens)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":       true,
		"texto":         text,
		"fonte_ia":      fonte,
		"chars_gerados": len(text),
	})
}

func (s *Server) handleAIArticle(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Tema string `json:"tema"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Tema) == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Prompt não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 220*time.Second)
	defer cancel()

	text, fonte, maxTokens, err := s.ai.GenerateArticle(ctx, body.Tema)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":          true,
		"texto":            text,
		"fonte_ia":         fonte,
		"chars_gerados":    len(text),
		"max_tokens_usado": maxTokens,
	})
}

func (s *Server) handleAIImage(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Prompt string `json:"prompt"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Prompt) == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Prompt não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 280*time.Second)
	defer cancel()

	img, err := s.ai.GenerateImage(ctx, body.Prompt, s.cfg.UploadDir, s.cfg.UploadURL)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":   true,
		"filename":  img.Filename,
		"url":       img.URL,
		"image_url": img.URL,
		"model":     img.Model,
	})
}

func (s *Server) handleAISocialAgent(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Prompt string `json:"prompt"`
		Rede   string `json:"rede"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Prompt) == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Prompt não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 200*time.Second)
	defer cancel()

	text, err := s.ai.GenerateSocialAgent(ctx, body.Prompt, body.Rede)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "text": text, "chars_gerados": len(text)})
}

func (s *Server) handleGeminiModels(w http.ResponseWriter, r *http.Request) {
	key := strings.TrimSpace(r.URL.Query().Get("api_key"))
	ctx, cancel := context.WithTimeout(r.Context(), 40*time.Second)
	defer cancel()

	if key == "" {
		cfg, err := s.store.GetConfiguracoes(ctx, []string{"gemini_api_key"})
		if err == nil {
			key = strings.TrimSpace(cfg["gemini_api_key"])
		}
	}
	if key == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "API Key não configurada"})
		return
	}
	text, image, total, err := s.ai.GeminiModels(ctx, key)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":              true,
		"textModels":           text,
		"imageModels":          image,
		"imageModelsAvailable": len(image) > 0,
		"totalModels":          total,
	})
}

// --- redes sociais (config) ---

func (s *Server) handleRedesList(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	redes, err := s.store.ListRedesSociais(ctx)
	if err != nil {
		s.fail(w, "listar redes", err, "Erro ao listar redes sociais.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "redes": redes})
}

func (s *Server) handleRedeSave(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Rede            string  `json:"rede"`
		Ativo           *bool   `json:"ativo"`
		ClientID        *string `json:"client_id"`
		ClientSecret    *string `json:"client_secret"`
		AccessToken     *string `json:"access_token"`
		PageID          *string `json:"page_id"`
		PersonURN       *string `json:"person_urn"`
		OrganizationURN *string `json:"organization_urn"`
		PublishTarget   *string `json:"publish_target"`
		DadosExtras     *string `json:"dados_extras"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Rede) == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "rede é obrigatória."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	if _, err := s.store.SaveRedeSocial(ctx, store.RedeSocialInput{
		Rede: body.Rede, Ativo: body.Ativo, ClientID: body.ClientID, ClientSecret: body.ClientSecret,
		AccessToken: body.AccessToken, PageID: body.PageID, PersonURN: body.PersonURN,
		OrganizationURN: body.OrganizationURN, PublishTarget: body.PublishTarget, DadosExtras: body.DadosExtras,
	}); err != nil {
		s.fail(w, "salvar rede", err, "Erro ao salvar configuração.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Configuração salva."})
}

// --- site público (strangler) ---

func (s *Server) handleSiteRoot(w http.ResponseWriter, r *http.Request) {
	http.Redirect(w, r, "/site/pt/", http.StatusFound)
}

func (s *Server) handleSiteHome(w http.ResponseWriter, r *http.Request) {
	lang := site.NormalizeLang(r.PathValue("lang"))
	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()

	body, err := s.site.RenderHome(ctx, lang)
	if err != nil {
		s.log.Error("render site home", "error", err)
		http.Error(w, "Erro ao renderizar a página.", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(body))
}

func (s *Server) writeHTML(w http.ResponseWriter, status int, body string) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(status)
	_, _ = w.Write([]byte(body))
}

func (s *Server) renderSitePage(w http.ResponseWriter, r *http.Request, render func(ctx context.Context, lang string) (string, error), notFound bool) {
	_ = notFound
	lang := site.NormalizeLang(r.PathValue("lang"))
	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()
	body, err := render(ctx, lang)
	if err != nil {
		if errors.Is(err, site.ErrNotFound) {
			s.writeHTML(w, http.StatusNotFound, "<!doctype html><meta charset=utf-8><h1>404</h1><p>Página não encontrada.</p>")
			return
		}
		s.log.Error("render site page", "error", err)
		http.Error(w, "Erro ao renderizar a página.", http.StatusInternalServerError)
		return
	}
	s.writeHTML(w, http.StatusOK, body)
}

func (s *Server) handleSiteSitemap(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()
	body, err := s.site.RenderSitemap(ctx)
	if err != nil {
		s.log.Error("render sitemap", "error", err)
		http.Error(w, "Erro ao gerar sitemap.", http.StatusInternalServerError)
		return
	}
	w.Header().Set("Content-Type", "application/xml; charset=utf-8")
	_, _ = w.Write([]byte(body))
}

func (s *Server) handleSiteConteudos(w http.ResponseWriter, r *http.Request) {
	s.renderSitePage(w, r, s.site.RenderConteudos, false)
}

func (s *Server) handleSiteProjetos(w http.ResponseWriter, r *http.Request) {
	s.renderSitePage(w, r, s.site.RenderProjetos, false)
}

func (s *Server) handleSiteSobre(w http.ResponseWriter, r *http.Request) {
	s.renderSitePage(w, r, s.site.RenderSobre, false)
}

func (s *Server) handleSiteArtigo(w http.ResponseWriter, r *http.Request) {
	lang := site.NormalizeLang(r.PathValue("lang"))
	slug := r.PathValue("slug")
	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()
	body, err := s.site.RenderArtigo(ctx, lang, slug)
	if err != nil {
		if errors.Is(err, site.ErrNotFound) {
			s.writeHTML(w, http.StatusNotFound, "<!doctype html><meta charset=utf-8><h1>404</h1><p>Conteúdo não encontrado.</p>")
			return
		}
		s.log.Error("render artigo", "error", err)
		http.Error(w, "Erro ao renderizar a página.", http.StatusInternalServerError)
		return
	}
	s.writeHTML(w, http.StatusOK, body)
}

func (s *Server) handleSiteProjeto(w http.ResponseWriter, r *http.Request) {
	lang := site.NormalizeLang(r.PathValue("lang"))
	slug := r.PathValue("slug")
	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()
	body, err := s.site.RenderProjeto(ctx, lang, slug)
	if err != nil {
		if errors.Is(err, site.ErrNotFound) {
			s.writeHTML(w, http.StatusNotFound, "<!doctype html><meta charset=utf-8><h1>404</h1><p>Projeto não encontrado.</p>")
			return
		}
		s.log.Error("render projeto", "error", err)
		http.Error(w, "Erro ao renderizar a página.", http.StatusInternalServerError)
		return
	}
	s.writeHTML(w, http.StatusOK, body)
}

// --- radar (CRUD/listas) ---

func (s *Server) handleRadarTopicsList(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	topics, err := s.store.RadarTopics(ctx)
	if err != nil {
		s.fail(w, "radar topics", err, "Erro ao listar temas.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "topics": topics})
}

func (s *Server) handleRadarTopicSave(w http.ResponseWriter, r *http.Request) {
	var body struct {
		ID               *int   `json:"id"`
		Nome             string `json:"nome"`
		Descricao        string `json:"descricao"`
		Keywords         string `json:"keywords"`
		Idiomas          string `json:"idiomas"`
		Regioes          string `json:"regioes"`
		CategoriaArtigos *int   `json:"categoria_artigos_id"`
		Ativo            *bool  `json:"ativo"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Nome) == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nome é obrigatório"})
		return
	}
	idiomas := strings.TrimSpace(body.Idiomas)
	if idiomas == "" {
		idiomas = "pt,en"
	}
	regioes := strings.TrimSpace(body.Regioes)
	if regioes == "" {
		regioes = "br,us,eu"
	}
	ativo := true
	if body.Ativo != nil {
		ativo = *body.Ativo
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	id, err := s.store.RadarTopicSave(ctx, body.ID, strings.TrimSpace(body.Nome), body.Descricao, body.Keywords, idiomas, regioes, body.CategoriaArtigos, ativo)
	if err != nil {
		s.fail(w, "radar topic save", err, "Erro ao salvar tema.")
		return
	}
	msg := "Tema atualizado"
	if body.ID == nil || *body.ID <= 0 {
		msg = "Tema criado"
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg, "id": id})
}

func (s *Server) handleRadarTopicDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	if err := s.store.RadarTopicDelete(ctx, id); err != nil {
		s.fail(w, "radar topic delete", err, "Erro ao remover tema.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Tema removido"})
}

func (s *Server) handleRadarSourcesList(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	sources, err := s.store.RadarSources(ctx)
	if err != nil {
		s.fail(w, "radar sources", err, "Erro ao listar fontes.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "sources": sources})
}

func (s *Server) handleRadarSourceSave(w http.ResponseWriter, r *http.Request) {
	var body struct {
		ID     *int   `json:"id"`
		Nome   string `json:"nome"`
		Tipo   string `json:"tipo"`
		URL    string `json:"url"`
		Config string `json:"config"`
		Ativo  *bool  `json:"ativo"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	nome := strings.TrimSpace(body.Nome)
	tipo := strings.TrimSpace(body.Tipo)
	if tipo == "" {
		tipo = "rss"
	}
	urlv := strings.TrimSpace(body.URL)
	if nome == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nome é obrigatório"})
		return
	}
	if tipo != "rss" && tipo != "api" && tipo != "scrape" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Tipo inválido"})
		return
	}
	if (tipo == "rss" || tipo == "scrape") && urlv == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "URL é obrigatória"})
		return
	}
	cfg := strings.TrimSpace(body.Config)
	if cfg != "" && !json.Valid([]byte(cfg)) {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Config JSON inválido"})
		return
	}
	ativo := true
	if body.Ativo != nil {
		ativo = *body.Ativo
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	id, err := s.store.RadarSourceSave(ctx, body.ID, nome, tipo, urlv, cfg, ativo)
	if err != nil {
		s.fail(w, "radar source save", err, "Erro ao salvar fonte.")
		return
	}
	msg := "Fonte atualizada"
	if body.ID == nil || *body.ID <= 0 {
		msg = "Fonte criada"
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": msg, "id": id})
}

func (s *Server) handleRadarSourceDelete(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	if err := s.store.RadarSourceDelete(ctx, id); err != nil {
		s.fail(w, "radar source delete", err, "Erro ao remover fonte.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Fonte removida"})
}

func (s *Server) handleRadarTopicSourcesGet(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "topic_id inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	ids, err := s.store.RadarTopicSourceIDs(ctx, id)
	if err != nil {
		s.fail(w, "radar topic sources", err, "Erro ao listar fontes do tema.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "source_ids": ids})
}

func (s *Server) handleRadarTopicSourcesSet(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "topic_id inválido"})
		return
	}
	var body struct {
		SourceIDs []int `json:"source_ids"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 10*time.Second)
	defer cancel()
	if err := s.store.RadarTopicSourcesSet(ctx, id, body.SourceIDs); err != nil {
		s.fail(w, "radar topic sources set", err, "Erro ao atualizar fontes do tema.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Fontes do tema atualizadas"})
}

func (s *Server) handleRadarItemsList(w http.ResponseWriter, r *http.Request) {
	topicID := atoiDefault(r.URL.Query().Get("topic_id"), 0)
	limit := atoiDefault(r.URL.Query().Get("limit"), 50)
	ctx, cancel := context.WithTimeout(r.Context(), 12*time.Second)
	defer cancel()
	items, err := s.store.RadarItems(ctx, topicID, limit)
	if err != nil {
		s.fail(w, "radar items", err, "Erro ao listar itens.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "items": items})
}

func (s *Server) handleRadarItemsDelete(w http.ResponseWriter, r *http.Request) {
	var body struct {
		IDs     []int  `json:"ids"`
		URLLike string `json:"url_like"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	urlLike := strings.TrimSpace(body.URLLike)
	if len(body.IDs) == 0 && urlLike == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Parâmetros inválidos: envie ids (JSON) ou url_like"})
		return
	}
	if len(body.IDs) > 1000 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Limite de exclusão por requisição: 1000"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 30*time.Second)
	defer cancel()

	if len(body.IDs) == 0 && len(urlLike) < 3 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Filtro muito curto, informe ao menos 3 caracteres"})
		return
	}

	// Backup CSV antes de apagar (como o PHP).
	var rows []map[string]any
	var err error
	if len(body.IDs) > 0 {
		rows, err = s.store.RadarItemsFullByIDs(ctx, body.IDs)
	} else {
		rows, err = s.store.RadarItemsFullByURL(ctx, urlLike)
	}
	if err != nil {
		s.fail(w, "radar items backup", err, "Erro ao remover itens.")
		return
	}
	if len(rows) == 0 {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Nenhum item encontrado"})
		return
	}
	backupFile, csvErr := writeRadarBackupCSV(rows)
	if csvErr != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{"success": false, "message": "Erro ao criar backup"})
		return
	}

	var deleted int
	if len(body.IDs) > 0 {
		deleted, err = s.store.RadarItemsDeleteByIDs(ctx, body.IDs)
	} else {
		deleted, err = s.store.RadarItemsDeleteByURL(ctx, urlLike)
	}
	if err != nil {
		s.fail(w, "radar items delete", err, "Erro ao remover itens.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true, "message": "Itens removidos", "deleted_count": deleted, "backup_file": backupFile,
	})
}

func (s *Server) handleRadarIdeasList(w http.ResponseWriter, r *http.Request) {
	topicID := atoiDefault(r.URL.Query().Get("topic_id"), 0)
	limit := atoiDefault(r.URL.Query().Get("limit"), 50)
	status := strings.TrimSpace(r.URL.Query().Get("status"))
	ctx, cancel := context.WithTimeout(r.Context(), 10*time.Second)
	defer cancel()
	ideas, err := s.store.RadarIdeas(ctx, topicID, status, limit)
	if err != nil {
		s.fail(w, "radar ideas", err, "Erro ao listar ideias.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "ideas": ideas})
}

func (s *Server) handleRadarIdeaDiscard(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	if err := s.store.RadarIdeaDelete(ctx, id); err != nil {
		s.fail(w, "radar idea discard", err, "Erro ao excluir ideia.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Ideia excluída"})
}

func (s *Server) handleRadarIdeaSources(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	sources, err := s.store.RadarIdeaSourceItems(ctx, id)
	if err != nil {
		s.fail(w, "radar idea sources", err, "Erro ao buscar fontes da ideia.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "sources": sources})
}

func (s *Server) handleRadarIdeasGenerate(w http.ResponseWriter, r *http.Request) {
	var body struct {
		TopicID    int `json:"topic_id"`
		LimitItems int `json:"limit_items"`
		NumIdeas   int `json:"num_ideas"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if body.TopicID <= 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "topic_id inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 220*time.Second)
	defer cancel()

	saved, ids, model, err := s.radar.GenerateIdeas(ctx, body.TopicID, body.LimitItems, body.NumIdeas)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": "Ideias geradas",
		"result":  map[string]any{"success": true, "saved": saved, "idea_ids": ids, "model": model},
	})
}

func (s *Server) handleRadarHype(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Hours     int     `json:"hours"`
		Threshold float64 `json:"threshold"`
	}
	_ = json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20)).Decode(&body)

	ctx, cancel := context.WithTimeout(r.Context(), 120*time.Second)
	defer cancel()

	clusters, updated, err := s.radar.AnalyzeHype(ctx, body.Hours, body.Threshold)
	if err != nil {
		s.fail(w, "radar hype", err, "Erro ao analisar hype.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "clusters_found": clusters, "items_updated": updated})
}

func (s *Server) handleRadarIdeaToDraft(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID inválido"})
		return
	}
	var body struct {
		Mode string `json:"mode"`
	}
	_ = json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20)).Decode(&body)

	ctx, cancel := context.WithTimeout(r.Context(), 240*time.Second)
	defer cancel()

	artigoID, slug, err := s.radar.IdeaToDraft(ctx, id, body.Mode)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": "Rascunho criado em Conteúdos",
		"result":  map[string]any{"success": true, "artigo_id": artigoID, "slug": slug},
	})
}

func (s *Server) handleRadarCollect(w http.ResponseWriter, r *http.Request) {
	var body struct {
		TopicID int `json:"topic_id"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if body.TopicID <= 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "topic_id inválido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 220*time.Second)
	defer cancel()

	runID, total, results, errors, err := s.radar.CollectRun(ctx, body.TopicID)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error(), "run_id": runID})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":     true,
		"run_id":      runID,
		"saved_total": total,
		"results":     results,
		"errors":      errors,
	})
}

// --- vídeos ---

func (s *Server) handleVideoEnqueue(w http.ResponseWriter, r *http.Request) {
	var body struct {
		ID               int     `json:"id"`
		DurationPerImage int     `json:"duration_per_image"`
		MusicFile        *string `json:"music_file"`
		Resolution       string  `json:"resolution"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if body.ID <= 0 {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "id não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 20*time.Second)
	defer cancel()

	imgs, err := s.store.GetVariantImages(ctx, body.ID)
	if err != nil {
		s.fail(w, "variant images", err, "Erro ao enfileirar vídeo.")
		return
	}
	if !imgs.Found {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Variante não encontrada"})
		return
	}
	if !imgs.HasImages() {
		// Divergência consciente: o Go não executa o script PHP de geração automática de imagens.
		writeJSON(w, http.StatusOK, map[string]any{
			"success": false,
			"message": "Variante sem imagens. Gere as imagens (IA) antes de enfileirar o vídeo.",
		})
		return
	}

	cfg, err := s.store.GetConfiguracoes(ctx, []string{"llm_video_provider", "llm_video_model"})
	if err != nil {
		s.fail(w, "video config", err, "Erro ao enfileirar vídeo.")
		return
	}
	provider := strings.TrimSpace(cfg["llm_video_provider"])
	if provider == "" {
		provider = "gemini"
	}
	model := strings.TrimSpace(cfg["llm_video_model"])
	resolution := strings.TrimSpace(body.Resolution)
	if resolution == "" {
		resolution = "1080x1920"
	}
	duration := body.DurationPerImage
	if duration <= 0 {
		duration = 3
	}
	params := map[string]any{
		"duration_per_image": duration,
		"music_file":         body.MusicFile,
		"resolution":         resolution,
		"video_provider":     provider,
		"video_model":        model,
		"video_engine":       "auto",
	}
	paramsJSON, _ := json.Marshal(params)

	jobID, err := s.store.EnqueueVideoJob(ctx, body.ID, string(paramsJSON))
	if err != nil {
		s.fail(w, "enqueue video", err, "Erro ao enfileirar vídeo.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":  true,
		"job_id":   jobID,
		"provider": provider,
		"model":    model,
		"engine":   "auto",
		"message":  "Job enfileirado",
	})
}

func (s *Server) handleVideoJobStatus(w http.ResponseWriter, r *http.Request) {
	id, ok := parseID(r.PathValue("id"))
	if !ok {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "job_id não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	job, err := s.store.GetVideoJob(ctx, id)
	if err != nil {
		s.fail(w, "video job", err, "Erro ao buscar job.")
		return
	}
	if job == nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Job não encontrado"})
		return
	}
	if of, ok := job["output_file"].(string); ok && of != "" {
		job["output_url"] = strings.TrimRight(s.cfg.UploadURL, "/") + "/" + of
	}
	provider, model, engine := "", "", "ffmpeg"
	if raw, ok := job["params"].(string); ok && raw != "" {
		var p map[string]any
		if json.Unmarshal([]byte(raw), &p) == nil {
			if v, ok := p["video_provider"].(string); ok {
				provider = v
			}
			if v, ok := p["video_model"].(string); ok {
				model = v
			}
			if v, ok := p["video_engine"].(string); ok && v != "" {
				engine = v
			}
		}
	}
	job["provider"] = provider
	job["model"] = model
	job["engine"] = engine
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "job": job})
}

// --- beacon de acessos ---

func (s *Server) handleMetricsBeacon(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"success": false, "message": "Método não permitido."})
		return
	}
	if !sameOrigin(r, s.cfg.SiteBaseURL) {
		writeJSON(w, http.StatusForbidden, map[string]any{"success": false, "message": "Origem não permitida."})
		return
	}
	var body struct {
		Path string `json:"path"`
		UA   string `json:"ua"`
	}
	_ = json.NewDecoder(r.Body).Decode(&body) // corpo é opcional

	path := strings.TrimSpace(body.Path)
	if path == "" {
		path = r.URL.RequestURI()
		if path == "" {
			path = "/"
		}
	}
	if strings.HasPrefix(path, "http://") || strings.HasPrefix(path, "https://") {
		if u, err := url.Parse(path); err == nil {
			path = u.Path
		}
	}
	if len(path) > 500 {
		path = path[:500]
	}
	ua := strings.TrimSpace(body.UA)
	if ua == "" {
		ua = r.UserAgent()
	}
	if len(ua) > 500 {
		ua = ua[:500]
	}
	ipStr := clientIP(r)
	var ip *string
	if ipStr != "" {
		ip = &ipStr
	}

	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	if ip != nil {
		last, err := s.store.RecentSiteAccess(ctx, *ip, path)
		if err == nil && last != nil && time.Since(*last) < 60*time.Second {
			writeJSON(w, http.StatusOK, map[string]any{"success": true, "skipped": true})
			return
		}
	}
	if err := s.store.InsertSiteAccess(ctx, path, ua, ip); err != nil {
		s.fail(w, "metrics beacon", err, "Não foi possível registrar acesso.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true})
}

func sameOrigin(r *http.Request, baseURL string) bool {
	base, err := url.Parse(baseURL)
	if err != nil || base.Hostname() == "" {
		return false
	}
	host := base.Hostname()
	origin := r.Header.Get("Origin")
	if origin == "" {
		origin = r.Referer()
	}
	if origin == "" {
		return true // sem Origin/Referer: aceita (como o PHP)
	}
	u, err := url.Parse(origin)
	if err != nil {
		return false
	}
	return strings.EqualFold(u.Hostname(), host)
}

func (s *Server) handleAIImagesMulti(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Prompt string `json:"prompt"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Prompt) == "" {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Prompt não fornecido"})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 280*time.Second)
	defer cancel()

	res, err := s.ai.GenerateImagesMulti(ctx, body.Prompt, s.cfg.UploadDir, s.cfg.UploadURL)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, res)
}

// --- SEO/traduções ---

func (s *Server) handleI18nGenerate(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Entity string   `json:"entity"`
		ID     int      `json:"id"`
		Langs  []string `json:"langs"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if (body.Entity != "artigo" && body.Entity != "projeto") || body.ID <= 0 || len(body.Langs) == 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Parâmetros inválidos."})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), 220*time.Second)
	defer cancel()

	var (
		saved []string
		err   error
	)
	if body.Entity == "artigo" {
		saved, err = s.i18n.GenerateArtigo(ctx, body.ID, body.Langs)
	} else {
		saved, err = s.i18n.GenerateProjeto(ctx, body.ID, body.Langs)
	}
	if err != nil {
		if errors.Is(err, store.ErrNotFound) {
			msg := "Artigo não encontrado."
			if body.Entity == "projeto" {
				msg = "Projeto não encontrado."
			}
			writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": msg})
			return
		}
		s.fail(w, "gerar i18n", err, "Erro ao gerar traduções/SEO.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success": true,
		"message": "Traduções/SEO gerados.",
		"saved":   saved,
	})
}

func (s *Server) handleI18nStatus(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Entity string `json:"entity"`
		ID     int    `json:"id"`
		Lang   string `json:"lang"`
		Status string `json:"status"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if body.Entity != "artigo" && body.Entity != "projeto" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Entity inválida."})
		return
	}
	if body.ID <= 0 {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "ID inválido."})
		return
	}
	if body.Lang != "pt" && body.Lang != "en" && body.Lang != "es" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Idioma inválido."})
		return
	}
	if body.Status != "draft" && body.Status != "generated" && body.Status != "reviewed" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Status inválido."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	affected, err := s.store.UpdateI18nStatus(ctx, body.Entity, body.ID, body.Lang, body.Status)
	if err != nil {
		s.fail(w, "i18n status", err, "Erro ao atualizar status.")
		return
	}
	if affected == 0 {
		writeJSON(w, http.StatusNotFound, map[string]any{"success": false, "message": "Tradução não encontrada."})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Status atualizado.", "status": body.Status})
}

func (s *Server) handleI18nSite(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Langs []string `json:"langs"`
	}
	_ = json.NewDecoder(http.MaxBytesReader(w, r.Body, 1<<20)).Decode(&body)
	langs := i18nsite.NormalizeLangs(body.Langs)

	ctx, cancel := context.WithTimeout(r.Context(), 260*time.Second)
	defer cancel()

	res, err := s.i18nSvc.Generate(ctx, langs)
	if err != nil {
		s.fail(w, "gerar i18n site", err, "Erro ao gerar i18n do site.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"success":          true,
		"message":          "Traduções do site geradas.",
		"saved_ui":         res.SavedUI,
		"saved_config":     res.SavedConfig,
		"saved_categories": res.SavedCategories,
	})
}

// --- upload de mídia ---

var allowedUploadExt = map[string]bool{
	"jpg": true, "jpeg": true, "png": true, "webp": true, "gif": true, "mp4": true, "webm": true,
}

func sanitizePrefix(p string) string {
	var b strings.Builder
	for _, r := range strings.ToLower(strings.TrimSpace(p)) {
		if (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') || r == '_' || r == '-' {
			b.WriteRune(r)
		}
		if b.Len() >= 20 {
			break
		}
	}
	return b.String()
}

func (s *Server) handleUpload(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseMultipartForm(32 << 20); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nenhum arquivo enviado."})
		return
	}
	file, header, err := r.FormFile("file")
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nenhum arquivo enviado."})
		return
	}
	defer file.Close()

	ext := strings.ToLower(strings.TrimPrefix(filepath.Ext(header.Filename), "."))
	if !allowedUploadExt[ext] {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Tipo de arquivo não permitido."})
		return
	}
	isImage := ext == "jpg" || ext == "jpeg" || ext == "png" || ext == "webp" || ext == "gif"
	var maxBytes int64 = 5 * 1024 * 1024
	if !isImage {
		maxBytes = 2 * 1024 * 1024 * 1024
	}
	if header.Size > maxBytes {
		writeJSON(w, http.StatusBadRequest, map[string]any{
			"success": false,
			"message": fmt.Sprintf("Arquivo muito grande. Máximo: %dMB.", maxBytes/(1024*1024)),
		})
		return
	}

	prefix := sanitizePrefix(r.FormValue("prefix"))
	if prefix == "" {
		prefix = "file"
	}
	// Divergência consciente do PHP: o Go NÃO otimiza/força .jpg — preserva a extensão original.
	filename := prefix + "_" + textutil.Uniqid() + "." + ext

	if err := os.MkdirAll(s.cfg.UploadDir, 0o755); err != nil {
		s.fail(w, "upload mkdir", err, "Erro ao salvar arquivo.")
		return
	}
	dst := filepath.Join(s.cfg.UploadDir, filename)
	out, err := os.Create(dst)
	if err != nil {
		s.fail(w, "upload create", err, "Erro ao salvar arquivo.")
		return
	}
	written, copyErr := io.Copy(out, io.LimitReader(file, maxBytes+1))
	closeErr := out.Close()
	if copyErr != nil || closeErr != nil || written > maxBytes {
		_ = os.Remove(dst)
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Erro ao salvar arquivo."})
		return
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"success":  true,
		"message":  "Arquivo enviado com sucesso!",
		"filename": filename,
		"url":      strings.TrimRight(s.cfg.UploadURL, "/") + "/" + filename,
	})
}

func (s *Server) handleUploadDelete(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Filename string `json:"filename"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	filename := strings.TrimSpace(body.Filename)
	if filename == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nome do arquivo é obrigatório."})
		return
	}
	if strings.Contains(filename, "..") || strings.ContainsAny(filename, "/\\") {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Nome de arquivo inválido."})
		return
	}
	if err := os.Remove(filepath.Join(s.cfg.UploadDir, filename)); err != nil {
		s.fail(w, "upload delete", err, "Erro ao deletar arquivo.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Arquivo deletado com sucesso!"})
}

// --- configurações ---

var allowedConfigKeys = []string{
	"site_titulo", "site_subtitulo", "home_frase_impacto",
	"home_card_1_icon", "home_card_1_titulo", "home_card_1_subtexto", "home_card_1_link",
	"home_card_2_icon", "home_card_2_titulo", "home_card_2_subtexto", "home_card_2_link",
	"home_card_3_icon", "home_card_3_titulo", "home_card_3_subtexto", "home_card_3_link",
	"home_card_4_icon", "home_card_4_titulo", "home_card_4_subtexto", "home_card_4_link",
	"site_email", "site_telefone", "site_linkedin", "site_instagram", "site_github", "mini_bio",
	"llm_text_provider", "llm_image_provider", "llm_video_provider",
	"llm_text_model", "llm_image_model", "llm_video_model", "llm_auto_validate_on_load",
	"gemini_api_key", "gemini_model", "gemini_image_model", "gemini_max_tokens",
	"deepseek_api_key", "openrouter_api_key", "anthropic_api_key",
	"ollama_base_url", "ollama_api_key",
	"ia_instrucoes", "ia_instrucoes_projeto", "ia_instrucoes_linkedin",
	"notify_email", "sentry_dsn",
	"openai_api_key", "openai_base_url", "openai_model", "openai_max_tokens",
}

// isSecretConfigKey identifica chaves que não devem ser expostas pela API.
func isSecretConfigKey(k string) bool {
	return strings.Contains(k, "api_key") || strings.Contains(k, "secret") ||
		strings.Contains(k, "token") || k == "sentry_dsn"
}

func allowedConfigSet() map[string]bool {
	m := make(map[string]bool, len(allowedConfigKeys))
	for _, k := range allowedConfigKeys {
		m[k] = true
	}
	return m
}

func (s *Server) handleConfiguracoesList(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()

	vals, err := s.store.GetConfiguracoes(ctx, allowedConfigKeys)
	if err != nil {
		s.fail(w, "listar configurações", err, "Erro ao carregar configurações.")
		return
	}
	out := make(map[string]any, len(allowedConfigKeys))
	for _, k := range allowedConfigKeys {
		if isSecretConfigKey(k) {
			continue // não expõe segredos via API
		}
		out[k] = vals[k] // "" se ausente
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "configuracoes": out})
}

func (s *Server) handleConfiguracoesBulkSave(w http.ResponseWriter, r *http.Request) {
	var body map[string]any
	if !s.decodeJSON(w, r, &body) {
		return
	}
	allowed := allowedConfigSet()

	ctx, cancel := context.WithTimeout(r.Context(), 15*time.Second)
	defer cancel()

	updated := 0
	for k, v := range body {
		if allowed[k] {
			if err := s.store.SetConfiguracao(ctx, k, coerceString(v)); err != nil {
				s.fail(w, "salvar configuração", err, "Erro ao salvar configurações")
				return
			}
			updated++
		}
	}

	// Compatibilidade: provider Gemini reflete modelos legados.
	textProvider := strings.TrimSpace(coerceString(body["llm_text_provider"]))
	imageProvider := strings.TrimSpace(coerceString(body["llm_image_provider"]))
	llmTextModel := strings.TrimSpace(coerceString(body["llm_text_model"]))
	llmImageModel := strings.TrimSpace(coerceString(body["llm_image_model"]))

	if textProvider == "" || imageProvider == "" {
		cur, err := s.store.GetConfiguracoes(ctx, []string{"llm_text_provider", "llm_image_provider"})
		if err == nil {
			if textProvider == "" {
				textProvider = cur["llm_text_provider"]
				if textProvider == "" {
					textProvider = "gemini"
				}
			}
			if imageProvider == "" {
				imageProvider = cur["llm_image_provider"]
				if imageProvider == "" {
					imageProvider = "gemini"
				}
			}
		}
	}
	if textProvider == "gemini" && llmTextModel != "" {
		if err := s.store.SetConfiguracao(ctx, "gemini_model", llmTextModel); err != nil {
			s.fail(w, "salvar gemini_model", err, "Erro ao salvar configurações")
			return
		}
	}
	if imageProvider == "gemini" && llmImageModel != "" {
		if err := s.store.SetConfiguracao(ctx, "gemini_image_model", llmImageModel); err != nil {
			s.fail(w, "salvar gemini_image_model", err, "Erro ao salvar configurações")
			return
		}
	}

	if updated > 0 {
		writeJSON(w, http.StatusOK, map[string]any{
			"success": true,
			"message": "Configurações salvas com sucesso! (" + strconv.Itoa(updated) + " campos atualizados)",
		})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": false, "message": "Nenhuma configuração para salvar"})
}

func (s *Server) handleConfiguracaoItemSave(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Chave string `json:"chave"`
		Valor string `json:"valor"`
	}
	if !s.decodeJSON(w, r, &body) {
		return
	}
	if strings.TrimSpace(body.Chave) == "" {
		writeJSON(w, http.StatusBadRequest, map[string]any{"success": false, "message": "Chave é obrigatória."})
		return
	}
	ctx, cancel := context.WithTimeout(r.Context(), 8*time.Second)
	defer cancel()
	if err := s.store.SetConfiguracao(ctx, body.Chave, body.Valor); err != nil {
		s.fail(w, "salvar configuração (item)", err, "Erro ao salvar configuração.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"success": true, "message": "Configuração salva."})
}

func coerceString(v any) string {
	switch t := v.(type) {
	case nil:
		return ""
	case string:
		return strings.TrimSpace(t)
	case bool:
		if t {
			return "1"
		}
		return "0"
	case float64:
		if t == math.Trunc(t) {
			return strconv.FormatInt(int64(t), 10)
		}
		return strconv.FormatFloat(t, 'f', -1, 64)
	default:
		return strings.TrimSpace(fmt.Sprintf("%v", t))
	}
}

// --- helpers ---

func (s *Server) fail(w http.ResponseWriter, op string, err error, msg string) {
	s.log.Error(op, "error", err)
	writeJSON(w, http.StatusInternalServerError, map[string]any{"success": false, "message": msg})
}

func parseID(raw string) (int, bool) {
	id, err := strconv.Atoi(raw)
	if err != nil || id <= 0 {
		return 0, false
	}
	return id, true
}

func atoiDefault(raw string, def int) int {
	if raw == "" {
		return def
	}
	v, err := strconv.Atoi(raw)
	if err != nil {
		return def
	}
	return v
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}
