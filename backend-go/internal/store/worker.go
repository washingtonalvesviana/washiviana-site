package store

import (
	"context"
	"encoding/json"
	"errors"

	"github.com/jackc/pgx/v5"
)

// PublicacaoMetrica é uma publicação elegível para coleta de métricas.
type PublicacaoMetrica struct {
	ID       int
	ArtigoID int
	Rede     string
	PostID   string
}

// VideoJob é um job de vídeo na fila.
type VideoJob struct {
	ID        int
	VariantID int
	Status    string
	Attempts  int
}

// ListVideoJobsPendentes lista jobs de vídeo com status 'pending'.
func (s *Store) ListVideoJobsPendentes(ctx context.Context, limit int) ([]VideoJob, error) {
	if limit <= 0 {
		limit = 10
	}
	rows, err := s.pool.Query(ctx,
		`SELECT id, variant_id, status, attempts FROM video_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT $1`,
		limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []VideoJob
	for rows.Next() {
		var j VideoJob
		if err := rows.Scan(&j.ID, &j.VariantID, &j.Status, &j.Attempts); err != nil {
			return nil, err
		}
		out = append(out, j)
	}
	return out, rows.Err()
}

// MetricsRow são os campos de um snapshot.
type MetricsRow struct {
	Visualizacoes     int
	Curtidas          int
	Comentarios       int
	Compartilhamentos int
	Cliques           int
	Alcance           int
	Engajamento       float64
	Extras            json.RawMessage
}

// ListMetricasElegiveis replica a seleção de metrics_worker.php.
func (s *Store) ListMetricasElegiveis(ctx context.Context, rede string, limit int) ([]PublicacaoMetrica, error) {
	sql := `SELECT id, artigo_id, rede, post_id
	          FROM publicacoes_redes
	         WHERE status = 'publicado' AND post_id IS NOT NULL AND post_id <> ''`
	args := []any{}
	if rede != "" {
		args = append(args, rede)
		sql += " AND rede = $1"
	}
	sql += " ORDER BY publicado_em DESC NULLS LAST, id DESC"
	if limit > 0 {
		args = append(args, limit)
		sql += " LIMIT $" + itoa(len(args))
	}
	rows, err := s.pool.Query(ctx, sql, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var out []PublicacaoMetrica
	for rows.Next() {
		var p PublicacaoMetrica
		if err := rows.Scan(&p.ID, &p.ArtigoID, &p.Rede, &p.PostID); err != nil {
			return nil, err
		}
		out = append(out, p)
	}
	return out, rows.Err()
}

// GetRedeAccessToken devolve o access_token de uma rede.
func (s *Store) GetRedeAccessToken(ctx context.Context, rede string) (string, error) {
	var token *string
	err := s.pool.QueryRow(ctx, "SELECT access_token FROM redes_sociais_config WHERE rede = $1 LIMIT 1", rede).Scan(&token)
	if errors.Is(err, pgx.ErrNoRows) {
		return "", nil
	}
	if err != nil {
		return "", err
	}
	if token == nil {
		return "", nil
	}
	return *token, nil
}

// InsertMetricsSnapshot grava um snapshot em metricas_publicacoes.
func (s *Store) InsertMetricsSnapshot(ctx context.Context, publicacaoID int, m MetricsRow) error {
	var extras any
	if len(m.Extras) > 0 {
		extras = string(m.Extras)
	}
	_, err := s.pool.Exec(ctx, `INSERT INTO metricas_publicacoes
		(publicacao_id, data_coleta, visualizacoes, curtidas, comentarios, compartilhamentos, cliques, alcance, engajamento, dados_extras)
		VALUES ($1, CURRENT_TIMESTAMP, $2, $3, $4, $5, $6, $7, $8, $9)`,
		publicacaoID, m.Visualizacoes, m.Curtidas, m.Comentarios, m.Compartilhamentos, m.Cliques, m.Alcance, m.Engajamento, extras)
	return err
}

func itoa(n int) string {
	if n == 0 {
		return "0"
	}
	var b [20]byte
	i := len(b)
	for n > 0 {
		i--
		b[i] = byte('0' + n%10)
		n /= 10
	}
	return string(b[i:])
}

// ClaimScheduledVariants reclama atomicamente variantes 'pronto' vencidas e as
// marca como 'pronto_para_publicacao' (paridade com worker_publish_scheduled.php).
//
// O UPDATE ... WHERE id IN (SELECT ... FOR UPDATE SKIP LOCKED) é atômico: dois
// workers concorrentes nunca reclamam a mesma variante.
func (s *Store) ClaimScheduledVariants(ctx context.Context, limit int) ([]int, error) {
	if limit <= 0 {
		limit = 10
	}
	rows, err := s.pool.Query(ctx, `
		UPDATE artigos_social_variants v
		   SET status = 'pronto_para_publicacao', updated_at = CURRENT_TIMESTAMP
		 WHERE v.id IN (
		       SELECT id FROM artigos_social_variants
		        WHERE scheduled_at IS NOT NULL
		          AND scheduled_at <= NOW()
		          AND status = 'pronto'
		        ORDER BY scheduled_at ASC
		        LIMIT $1
		        FOR UPDATE SKIP LOCKED
		 )
		RETURNING v.id`, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var ids []int
	for rows.Next() {
		var id int
		if err := rows.Scan(&id); err != nil {
			return nil, err
		}
		ids = append(ids, id)
	}
	return ids, rows.Err()
}
