package config

import (
	"fmt"
	"os"
	"strconv"
	"time"
)

// Config contém a configuração do serviço lida do ambiente.
// Nenhum segredo é hardcoded: em produção use EnvironmentFile do systemd.
type Config struct {
	Env           string
	Host          string
	Port          string
	DatabaseURL   string
	MaxConns      int32
	Version       string
	InternalToken string
	CookieSecure  bool
	SessionTTL    time.Duration
	UploadDir     string
	UploadURL     string
	SiteBaseURL   string
}

// Load lê a configuração do ambiente. DATABASE_URL tem prioridade; se ausente,
// monta o DSN a partir das variáveis DB_* (compatível com api/config.local.php).
func Load() (Config, error) {
	c := Config{
		Env:           getenv("APP_ENV", "development"),
		Host:          getenv("HOST", "127.0.0.1"),
		Port:          getenv("PORT", "8081"),
		Version:       getenv("APP_VERSION", "0.0.1"),
		InternalToken: os.Getenv("INTERNAL_TOKEN"),
	}

	c.DatabaseURL = os.Getenv("DATABASE_URL")
	if c.DatabaseURL == "" {
		c.DatabaseURL = buildDSN()
	}
	if c.DatabaseURL == "" {
		return c, fmt.Errorf("DATABASE_URL não definido e variáveis DB_HOST/DB_NAME/DB_USER ausentes")
	}

	maxConns, err := strconv.Atoi(getenv("DB_MAX_CONNS", "5"))
	if err != nil || maxConns <= 0 {
		maxConns = 5
	}
	c.MaxConns = int32(maxConns)

	c.CookieSecure = getenv("COOKIE_SECURE", "0") == "1"
	ttlHours, err := strconv.Atoi(getenv("SESSION_TTL_HOURS", "168"))
	if err != nil || ttlHours <= 0 {
		ttlHours = 168
	}
	c.SessionTTL = time.Duration(ttlHours) * time.Hour

	c.UploadDir = getenv("UPLOAD_DIR", "/var/www/washiviana.com/uploads")
	c.UploadURL = getenv("UPLOAD_URL", "/uploads/")
	c.SiteBaseURL = getenv("SITE_BASE_URL", "https://washiviana.com")

	return c, nil
}

func getenv(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

// buildDSN usa formato keyword/value do pgx para evitar problemas de
// escape com caracteres especiais (@, #) na senha.
func buildDSN() string {
	host := getenv("DB_HOST", "")
	name := getenv("DB_NAME", "")
	user := getenv("DB_USER", "")
	pass := os.Getenv("DB_PASS")
	if host == "" || name == "" || user == "" {
		return ""
	}
	port := getenv("DB_PORT", "5432")
	return fmt.Sprintf("host=%s port=%s dbname=%s user=%s password=%s sslmode=disable",
		host, port, name, user, pass)
}
