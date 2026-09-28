package store

import (
	"context"
	"errors"

	"github.com/jackc/pgx/v5"
)

// ClaimVideoJob reclama um job pendente (atômico) e marca como processing.
func (s *Store) ClaimVideoJob(ctx context.Context) (int, bool, error) {
	var id int
	err := s.pool.QueryRow(ctx, `
		UPDATE video_jobs
		   SET status = 'processing', attempts = attempts + 1, updated_at = now()
		 WHERE id = (
		       SELECT id FROM video_jobs WHERE status = 'pending'
		       ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED
		 )
		RETURNING id`).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return 0, false, nil
	}
	if err != nil {
		return 0, false, err
	}
	return id, true, nil
}

// VideoVariantInfo dados da variante para o vídeo.
type VideoVariantInfo struct {
	Caption string
	Titulo  string
}

// VideoVariantInfoForJob busca caption/titulo da variante.
func (s *Store) VideoVariantInfoForJob(ctx context.Context, variantID int) (VideoVariantInfo, error) {
	var v VideoVariantInfo
	err := s.pool.QueryRow(ctx, "SELECT COALESCE(caption,''), COALESCE(titulo,'') FROM artigos_social_variants WHERE id = $1", variantID).
		Scan(&v.Caption, &v.Titulo)
	if errors.Is(err, pgx.ErrNoRows) {
		return v, nil
	}
	return v, err
}

// VideoArticleMainImage devolve a imagem principal do artigo da variante.
func (s *Store) VideoArticleMainImage(ctx context.Context, variantID int) (string, error) {
	var img *string
	err := s.pool.QueryRow(ctx, `
		SELECT imagem_principal FROM artigos
		WHERE id = (SELECT artigo_id FROM artigos_social_variants WHERE id = $1)`, variantID).Scan(&img)
	if errors.Is(err, pgx.ErrNoRows) {
		return "", nil
	}
	if err != nil {
		return "", err
	}
	if img == nil {
		return "", nil
	}
	return *img, nil
}

// SetVideoJobSuccess marca o job como concluído.
func (s *Store) SetVideoJobSuccess(ctx context.Context, id int, outputFile string) error {
	_, err := s.pool.Exec(ctx,
		"UPDATE video_jobs SET status='success', output_file=$1, last_error=NULL, updated_at=now() WHERE id=$2",
		outputFile, id)
	return err
}

// SetVideoJobFailed marca o job como falho.
func (s *Store) SetVideoJobFailed(ctx context.Context, id int, errMsg string) error {
	_, err := s.pool.Exec(ctx,
		"UPDATE video_jobs SET status='failed', last_error=$1, updated_at=now() WHERE id=$2", errMsg, id)
	return err
}

// SetVideoJobPending volta o job para a fila (retry).
func (s *Store) SetVideoJobPending(ctx context.Context, id int, errMsg string) error {
	_, err := s.pool.Exec(ctx,
		"UPDATE video_jobs SET status='pending', last_error=$1, updated_at=now() WHERE id=$2", errMsg, id)
	return err
}

// SetVariantVideo grava o vídeo e metadados na variante.
func (s *Store) SetVariantVideo(ctx context.Context, variantID int, file, metaJSON string) error {
	_, err := s.pool.Exec(ctx,
		"UPDATE artigos_social_variants SET video_file=$1, video_meta=$2::jsonb, updated_at=now() WHERE id=$3",
		file, metaJSON, variantID)
	return err
}
