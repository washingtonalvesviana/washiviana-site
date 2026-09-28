package main

import (
	"context"
	"errors"
	"log/slog"
	"net"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"washiviana/backend/internal/auth"
	"washiviana/backend/internal/config"
	"washiviana/backend/internal/httpapi"
	"washiviana/backend/internal/store"
)

// version pode ser injetada no build: go build -ldflags "-X main.version=0.1.0"
var version = "dev"

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)

	cfg, err := config.Load()
	if err != nil {
		logger.Error("configuração inválida", "error", err)
		os.Exit(1)
	}
	if version != "dev" {
		cfg.Version = version
	}

	ctx := context.Background()
	st, err := store.New(ctx, cfg.DatabaseURL, cfg.MaxConns)
	if err != nil {
		logger.Error("falha ao criar pool de conexões", "error", err)
		os.Exit(1)
	}
	defer st.Close()

	pingCtx, cancelPing := context.WithTimeout(ctx, 5*time.Second)
	if err := st.Ping(pingCtx); err != nil {
		cancelPing()
		logger.Error("banco inacessível no start", "error", err)
		os.Exit(1)
	}
	cancelPing()

	authSvc := auth.New(st, cfg.SessionTTL)
	srv := httpapi.New(cfg, st, authSvc, logger)
	httpServer := &http.Server{
		Addr:              net.JoinHostPort(cfg.Host, cfg.Port),
		Handler:           srv.Routes(),
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       30 * time.Second,
		WriteTimeout:      60 * time.Second,
		IdleTimeout:       120 * time.Second,
	}

	go func() {
		logger.Info("servidor iniciado",
			"addr", httpServer.Addr,
			"env", cfg.Env,
			"version", cfg.Version,
		)
		if err := httpServer.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			logger.Error("servidor falhou", "error", err)
			os.Exit(1)
		}
	}()

	stop := make(chan os.Signal, 1)
	signal.Notify(stop, syscall.SIGINT, syscall.SIGTERM)
	<-stop

	shutdownCtx, cancelShutdown := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancelShutdown()
	if err := httpServer.Shutdown(shutdownCtx); err != nil {
		logger.Error("shutdown com erro", "error", err)
	}
	logger.Info("servidor encerrado")
}
