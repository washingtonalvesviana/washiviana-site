// Package video porta o pipeline FFmpeg do scripts/video_worker.php.
package video

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"washiviana/backend/internal/store"
)

// Config do worker de vídeo.
type Config struct {
	UploadDir    string
	FFmpeg       string
	MaxAttempts  int
	SleepBetween int
}

// Service processa jobs de vídeo.
type Service struct {
	store *store.Store
	cfg   Config
	log   func(string, ...any)
}

// New cria o serviço.
func New(st *store.Store, cfg Config, log func(string, ...any)) *Service {
	if cfg.FFmpeg == "" {
		cfg.FFmpeg = "ffmpeg"
	}
	if cfg.MaxAttempts <= 0 {
		cfg.MaxAttempts = 3
	}
	if cfg.SleepBetween <= 0 {
		cfg.SleepBetween = 2
	}
	return &Service{store: st, cfg: cfg, log: log}
}

// Process reclamа e processa no máximo um job. Devolve o job e o status final.
func (s *Service) Process(ctx context.Context) (int, string, error) {
	if _, err := exec.LookPath(s.cfg.FFmpeg); err != nil {
		return 0, "", fmt.Errorf("ffmpeg não encontrado no PATH")
	}
	jobID, ok, err := s.store.ClaimVideoJob(ctx)
	if err != nil {
		return 0, "", err
	}
	if !ok {
		return 0, "", nil
	}
	job, err := s.store.GetVideoJob(ctx, jobID)
	if err != nil {
		return jobID, "", err
	}
	variantID := asInt(job["variant_id"])
	attempts := asInt(job["attempts"])
	params := map[string]any{}
	if raw, ok := job["params"].(string); ok && raw != "" {
		_ = json.Unmarshal([]byte(raw), &params)
	}

	s.logf("processando job #%d (variante=%d) attempt=%d", jobID, variantID, attempts)

	if err := s.build(ctx, jobID, variantID, params); err != nil {
		return jobID, "error", s.fail(ctx, jobID, attempts, err)
	}
	return jobID, "success", nil
}

func (s *Service) build(ctx context.Context, jobID, variantID int, params map[string]any) error {
	duration := intParam(params, "duration_per_image", 3)
	if duration < 1 {
		duration = 1
	}
	music := strParam(params, "music_file")
	resolution := strParam(params, "resolution")
	if resolution == "" {
		resolution = "1080x1920"
	}
	_ = resolution // usado apenas no meta (segmentos fixos em 1080x1920, como o PHP)

	images, err := s.imagesFor(variantID)
	if err != nil {
		return err
	}
	if len(images) == 0 {
		return fmt.Errorf("Nenhuma imagem disponível para gerar vídeo")
	}

	tmpDir, err := os.MkdirTemp("", fmt.Sprintf("video_job_%d_", jobID))
	if err != nil {
		return err
	}
	defer os.RemoveAll(tmpDir)

	var parts []string
	for i, img := range images {
		if _, err := os.Stat(img); err != nil {
			s.logf("aviso: imagem não encontrada: %s", img)
			continue
		}
		out := filepath.Join(tmpDir, fmt.Sprintf("part_%d.mp4", i))
		vf := "scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2:black," +
			"fade=t=in:st=0:d=0.5,fade=t=out:st=" + strconv.FormatFloat(float64(duration)-0.6, 'f', -1, 64) + ":d=0.5"
		args := []string{"-y", "-loop", "1", "-i", img, "-vf", vf,
			"-c:v", "libx264", "-t", strconv.Itoa(duration), "-pix_fmt", "yuv420p", "-preset", "fast", out}
		if err := s.run(ctx, args...); err != nil || !fileExists(out) {
			s.logf("erro ao gerar segmento para imagem %s: %v", img, err)
			continue
		}
		parts = append(parts, out)
	}
	if len(parts) == 0 {
		return fmt.Errorf("Falha ao gerar segmentos de vídeo (todos falharam)")
	}

	finalName := fmt.Sprintf("video_job_%d_%d.mp4", jobID, time.Now().Unix())
	outFinal := filepath.Join(s.cfg.UploadDir, finalName)

	concatFile := filepath.Join(tmpDir, "files.txt")
	var sb strings.Builder
	for _, p := range parts {
		sb.WriteString("file '" + strings.ReplaceAll(p, "'", "\\'") + "'\n")
	}
	if err := os.WriteFile(concatFile, []byte(sb.String()), 0o644); err != nil {
		return err
	}
	if err := s.run(ctx, "-y", "-f", "concat", "-safe", "0", "-i", concatFile, "-c:v", "libx264", "-pix_fmt", "yuv420p", outFinal); err != nil || !fileExists(outFinal) {
		return fmt.Errorf("Erro ao concatenar segmentos (verificar ffmpeg).")
	}

	if music != "" {
		musicPath := filepath.Join(s.cfg.UploadDir, music)
		if fileExists(musicPath) {
			tmpOut := filepath.Join(tmpDir, fmt.Sprintf("final_audio_%d.mp4", time.Now().UnixNano()))
			if err := s.run(ctx, "-y", "-i", outFinal, "-i", musicPath, "-c:v", "copy", "-c:a", "aac", "-b:a", "192k", "-shortest", tmpOut); err == nil && fileExists(tmpOut) {
				_ = os.Rename(tmpOut, outFinal)
			} else {
				s.logf("aviso: falha ao adicionar música ao vídeo")
			}
		}
	}

	if err := s.store.SetVideoJobSuccess(ctx, jobID, finalName); err != nil {
		return err
	}
	meta := map[string]any{
		"generated_at":       time.Now().Format(time.RFC3339),
		"job_id":             jobID,
		"provider_requested": "ffmpeg",
		"model_requested":    "",
		"engine_requested":   "auto",
		"engine_used":        "ffmpeg",
		"resolution":         resolution,
		"duration_per_image": duration,
		"note":               "go_worker_ffmpeg",
	}
	metaJSON, _ := json.Marshal(meta)
	if err := s.store.SetVariantVideo(ctx, variantID, finalName, string(metaJSON)); err != nil {
		return err
	}
	s.logf("job %d concluído: %s", jobID, finalName)
	return nil
}

func (s *Service) fail(ctx context.Context, jobID, attempts int, buildErr error) error {
	msg := buildErr.Error()
	s.logf("erro no job %d: %s", jobID, msg)
	if attempts >= s.cfg.MaxAttempts {
		_ = s.store.SetVideoJobFailed(ctx, jobID, msg)
		s.logf("job %d marcado como failed após %d tentativas", jobID, attempts)
		return nil
	}
	time.Sleep(time.Duration(s.cfg.SleepBetween*attempts) * time.Second)
	_ = s.store.SetVideoJobPending(ctx, jobID, msg)
	s.logf("job %d reagendado (attempts=%d)", jobID, attempts)
	return nil
}

func (s *Service) imagesFor(variantID int) ([]string, error) {
	imgs, err := s.store.GetVariantImages(context.Background(), variantID)
	if err != nil {
		return nil, err
	}
	var out []string
	if imgs.Image9x16 != nil && *imgs.Image9x16 != "" {
		out = append(out, filepath.Join(s.cfg.UploadDir, *imgs.Image9x16))
	}
	if imgs.Image1x1 != nil && *imgs.Image1x1 != "" {
		out = append(out, filepath.Join(s.cfg.UploadDir, *imgs.Image1x1))
	}
	if len(out) == 0 {
		main, err := s.store.VideoArticleMainImage(context.Background(), variantID)
		if err != nil {
			return nil, err
		}
		if main != "" {
			out = append(out, filepath.Join(s.cfg.UploadDir, main))
		}
	}
	return out, nil
}

func (s *Service) run(ctx context.Context, args ...string) error {
	cmd := exec.CommandContext(ctx, s.cfg.FFmpeg, args...)
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("%v: %s", err, strings.TrimSpace(string(out)))
	}
	return nil
}

func (s *Service) logf(format string, args ...any) {
	if s.log != nil {
		s.log(fmt.Sprintf(format, args...))
	}
}

func fileExists(p string) bool {
	st, err := os.Stat(p)
	return err == nil && !st.IsDir()
}

func asInt(v any) int {
	switch n := v.(type) {
	case int:
		return n
	case int32:
		return int(n)
	case int64:
		return int(n)
	case float64:
		return int(n)
	}
	return 0
}

func intParam(m map[string]any, key string, def int) int {
	if v, ok := m[key]; ok {
		if n := asInt(v); n != 0 {
			return n
		}
	}
	return def
}

func strParam(m map[string]any, key string) string {
	if v, ok := m[key].(string); ok {
		return strings.TrimSpace(v)
	}
	return ""
}
