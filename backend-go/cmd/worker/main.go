// Command worker executa tarefas de background do backend Go.
//
// Uso:
//
//	washiviana-worker publish-scheduled [--limit N]   # reclama variantes agendadas
//	washiviana-worker metrics                         # (não implementado ainda)
//
// Fase 3(d): o claim é atômico (IMPEDE publicação/processamento duplicado).
package main

import (
	"context"
	"encoding/json"
	"log/slog"
	"os"
	"strconv"
	"strings"
	"time"

	"washiviana/backend/internal/config"
	"washiviana/backend/internal/metrics"
	"washiviana/backend/internal/store"
)

var version = "dev"

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)

	if len(os.Args) < 2 {
		logger.Error("subcomando ausente", "uso", "worker publish-scheduled|metrics")
		os.Exit(2)
	}
	cmd := os.Args[1]

	cfg, err := config.Load()
	if err != nil {
		logger.Error("configuração inválida", "error", err)
		os.Exit(1)
	}
	if version != "dev" {
		cfg.Version = version
	}

	ctx, cancel := context.WithTimeout(context.Background(), 60*time.Second)
	defer cancel()

	st, err := store.New(ctx, cfg.DatabaseURL, cfg.MaxConns)
	if err != nil {
		logger.Error("falha ao criar pool", "error", err)
		os.Exit(1)
	}
	defer st.Close()

	switch cmd {
	case "publish-scheduled":
		runPublishScheduled(ctx, logger, st, argLimit(os.Args[2:], 10))
	case "metrics":
		runMetrics(ctx, logger, st, os.Args[2:])
	case "video":
		runVideo(ctx, logger, st, os.Args[2:])
	default:
		logger.Error("subcomando desconhecido", "cmd", cmd)
		os.Exit(2)
	}
}

func runMetrics(ctx context.Context, logger *slog.Logger, st *store.Store, args []string) {
	rede := argValue(args, "--rede=")
	limit := argIntValue(args, "--limit=", 0)
	dryRun := hasFlag(args, "--dry-run")

	pubs, err := st.ListMetricasElegiveis(ctx, rede, limit)
	if err != nil {
		logger.Error("listar publicações elegíveis falhou", "error", err)
		os.Exit(1)
	}
	if dryRun {
		ids := make([]int, 0, len(pubs))
		for _, p := range pubs {
			ids = append(ids, p.ID)
		}
		writeOut(map[string]any{"event": "eligible", "count": len(pubs), "ids": ids})
		return
	}

	fetcher := metrics.New(st)
	ok, fail := 0, 0
	for _, pub := range pubs {
		row, err := fetcher.Collect(ctx, pub)
		if err != nil {
			fail++
			logger.Warn("coleta falhou", "id", pub.ID, "rede", pub.Rede, "error", err.Error())
			continue
		}
		if err := st.InsertMetricsSnapshot(ctx, pub.ID, row); err != nil {
			fail++
			logger.Warn("gravar snapshot falhou", "id", pub.ID, "error", err.Error())
			continue
		}
		ok++
		logger.Info("métricas coletadas", "id", pub.ID, "rede", pub.Rede)
	}
	writeOut(map[string]any{"event": "metrics", "ok": ok, "fail": fail})
	if ok == 0 && fail > 0 {
		os.Exit(1)
	}
}

func runVideo(ctx context.Context, logger *slog.Logger, st *store.Store, args []string) {
	limit := argIntValue(args, "--limit=", 10)
	dryRun := hasFlag(args, "--dry-run")

	if !dryRun {
		// Executor de vídeo (FFmpeg + provedores externos) ainda NÃO portado.
		// Falha explícita para evitar qualquer processamento acidental.
		logger.Error("executor de vídeo não implementado; rode com --dry-run")
		os.Exit(2)
	}

	jobs, err := st.ListVideoJobsPendentes(ctx, limit)
	if err != nil {
		logger.Error("listar video_jobs falhou", "error", err)
		os.Exit(1)
	}
	ids := make([]int, 0, len(jobs))
	for _, j := range jobs {
		ids = append(ids, j.ID)
	}
	writeOut(map[string]any{"event": "video_pending", "count": len(jobs), "ids": ids})
}

func writeOut(v any) {
	b, _ := json.Marshal(v)
	os.Stdout.Write(append(b, '\n'))
}

func hasFlag(args []string, name string) bool {
	for _, a := range args {
		if a == name {
			return true
		}
	}
	return false
}

func argValue(args []string, prefix string) string {
	for _, a := range args {
		if strings.HasPrefix(a, prefix) {
			return strings.TrimSpace(strings.TrimPrefix(a, prefix))
		}
	}
	return ""
}

func argIntValue(args []string, prefix string, def int) int {
	if v := argValue(args, prefix); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func runPublishScheduled(ctx context.Context, logger *slog.Logger, st *store.Store, limit int) {
	ids, err := st.ClaimScheduledVariants(ctx, limit)
	if err != nil {
		logger.Error("claim de variantes falhou", "error", err)
		os.Exit(1)
	}
	if ids == nil {
		ids = []int{}
	}
	// Saída legível por máquina (usada nos testes de concorrência).
	out := map[string]any{"event": "claimed", "count": len(ids), "ids": ids}
	b, _ := json.Marshal(out)
	os.Stdout.Write(append(b, '\n'))
}

func argLimit(args []string, def int) int {
	for i := 0; i < len(args); i++ {
		if args[i] == "--limit" && i+1 < len(args) {
			if v, err := strconv.Atoi(args[i+1]); err == nil && v > 0 {
				return v
			}
		}
	}
	return def
}
