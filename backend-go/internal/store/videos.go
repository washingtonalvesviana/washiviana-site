package store

import (
	"context"
	"errors"
	"time"

	"github.com/jackc/pgx/v5"
)

// VariantImages informa as imagens de uma variante.
type VariantImages struct {
	Found     bool
	Image1x1  *string
	Image9x16 *string
}

// HasImages indica se há alguma imagem na variante.
func (v VariantImages) HasImages() bool {
	return (v.Image1x1 != nil && *v.Image1x1 != "") || (v.Image9x16 != nil && *v.Image9x16 != "")
}

// GetVariantImages busca image_1x1/image_9x16 de uma variante.
func (s *Store) GetVariantImages(ctx context.Context, id int) (VariantImages, error) {
	var v VariantImages
	err := s.pool.QueryRow(ctx, "SELECT image_1x1, image_9x16 FROM artigos_social_variants WHERE id = $1", id).
		Scan(&v.Image1x1, &v.Image9x16)
	if errors.Is(err, pgx.ErrNoRows) {
		return v, nil
	}
	if err != nil {
		return v, err
	}
	v.Found = true
	return v, nil
}

// EnqueueVideoJob insere um job de vídeo e devolve o id.
func (s *Store) EnqueueVideoJob(ctx context.Context, variantID int, paramsJSON string) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx,
		"INSERT INTO video_jobs (variant_id, params, created_at, updated_at) VALUES ($1, $2::jsonb, now(), now()) RETURNING id",
		variantID, paramsJSON).Scan(&id)
	return id, err
}

// GetVideoJob devolve o job (jsonb como texto, timestamps no formato PHP).
func (s *Store) GetVideoJob(ctx context.Context, id int) (map[string]any, error) {
	return s.queryOne(ctx, `SELECT id, variant_id, status, attempts, last_error, output_file, params, created_at, updated_at
	                       FROM video_jobs WHERE id = $1`, id)
}

// InsertSiteAccess registra um acesso (beacon).
func (s *Store) InsertSiteAccess(ctx context.Context, path, ua string, ip *string) error {
	_, err := s.pool.Exec(ctx,
		"INSERT INTO site_accesses (path, user_agent, ip, created_at) VALUES ($1, $2, $3, CURRENT_TIMESTAMP)",
		path, ua, ip)
	return err
}

// RecentSiteAccess devolve o último acesso do ip+path (para rate limit).
func (s *Store) RecentSiteAccess(ctx context.Context, ip, path string) (*time.Time, error) {
	var t time.Time
	err := s.pool.QueryRow(ctx,
		"SELECT created_at FROM site_accesses WHERE ip = $1 AND path = $2 ORDER BY created_at DESC LIMIT 1",
		ip, path).Scan(&t)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &t, nil
}
