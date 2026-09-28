package store

import (
	"context"
	"errors"

	"github.com/jackc/pgx/v5"
)

// RadarTopicAtivo busca tema ativo.
func (s *Store) RadarTopicAtivo(ctx context.Context, id int) (map[string]any, error) {
	return s.queryOne(ctx, "SELECT * FROM radar_topics WHERE id = $1 AND ativo = true", id)
}

// RadarItemsForTopic lista itens do tema (ordem por score).
func (s *Store) RadarItemsForTopic(ctx context.Context, topicID, limit int) ([]map[string]any, error) {
	if limit <= 0 {
		limit = 20
	}
	return s.queryMaps(ctx, `
		SELECT i.* FROM radar_items i
		JOIN radar_item_topics it ON it.item_id = i.id
		WHERE it.topic_id = $1
		ORDER BY i.score DESC, i.fetched_at DESC
		LIMIT $2`, topicID, limit)
}

// RadarIdeaInput campos de uma ideia.
type RadarIdeaInput struct {
	TopicID       int
	Titulo        string
	Angulo        string
	Resumo        string
	Outline       string
	Tags          string
	SourceItemIDs string // JSON
	AIModel       string
	AIPrompt      string
	AIRaw         string
}

// InsertRadarIdea grava uma ideia e devolve o id.
func (s *Store) InsertRadarIdea(ctx context.Context, in RadarIdeaInput) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO radar_ideas (topic_id, titulo, angulo, resumo, outline, tags, status, source_item_ids, ai_model, ai_prompt, ai_raw)
		VALUES ($1,$2,$3,$4,$5,$6,'nova',$7::jsonb,$8,$9,$10) RETURNING id`,
		in.TopicID, in.Titulo, in.Angulo, in.Resumo, in.Outline, in.Tags, in.SourceItemIDs, in.AIModel, in.AIPrompt, in.AIRaw).Scan(&id)
	return id, err
}

// RadarHypeItem item usado no analyze_hype.
type RadarHypeItem struct {
	ID        int
	Titulo    string
	PubAt     *string
	FetchedAt *string
}

// RadarHypeItems lista itens recentes (até 500).
func (s *Store) RadarHypeItems(ctx context.Context, hours int) ([]RadarHypeItem, error) {
	if hours <= 0 {
		hours = 48
	}
	rows, err := s.pool.Query(ctx, `
		SELECT id, titulo, published_at::text, fetched_at::text
		FROM radar_items
		WHERE (published_at >= NOW() - make_interval(hours => $1) OR fetched_at >= NOW() - make_interval(hours => $1))
		ORDER BY fetched_at DESC
		LIMIT 500`, hours)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := []RadarHypeItem{}
	for rows.Next() {
		var it RadarHypeItem
		if err := rows.Scan(&it.ID, &it.Titulo, &it.PubAt, &it.FetchedAt); err != nil {
			return nil, err
		}
		out = append(out, it)
	}
	return out, rows.Err()
}

// RadarItemRaw devolve o raw (jsonb como texto) de um item.
func (s *Store) RadarItemRaw(ctx context.Context, id int) (string, error) {
	var raw *string
	err := s.pool.QueryRow(ctx, "SELECT raw::text FROM radar_items WHERE id = $1", id).Scan(&raw)
	if errors.Is(err, pgx.ErrNoRows) {
		return "{}", nil
	}
	if err != nil {
		return "", err
	}
	if raw == nil || *raw == "" {
		return "{}", nil
	}
	return *raw, nil
}

// UpdateRadarItemRaw grava o raw (jsonb) de um item.
func (s *Store) UpdateRadarItemRaw(ctx context.Context, id int, rawJSON string) error {
	_, err := s.pool.Exec(ctx, "UPDATE radar_items SET raw = $1::jsonb WHERE id = $2", rawJSON, id)
	return err
}
