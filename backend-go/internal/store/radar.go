package store

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"

	"github.com/jackc/pgx/v5"
)

// ------- topics -------

// RadarTopics lista temas.
func (s *Store) RadarTopics(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, `
		SELECT t.*, ca.nome AS categoria_nome
		FROM radar_topics t
		LEFT JOIN categorias_artigos ca ON ca.id = t.categoria_artigos_id
		ORDER BY t.ativo DESC, t.nome ASC`)
}

// RadarTopicSave cria/atualiza e devolve o id.
func (s *Store) RadarTopicSave(ctx context.Context, id *int, nome, descricao, keywords, idiomas, regioes string, categoriaID *int, ativo bool) (int, error) {
	if id != nil && *id > 0 {
		_, err := s.pool.Exec(ctx, `
			UPDATE radar_topics
			   SET nome=$1, descricao=$2, keywords=$3, idiomas=$4, regioes=$5, categoria_artigos_id=$6, ativo=$7, updated_at=CURRENT_TIMESTAMP
			 WHERE id=$8`,
			nome, nullIfEmpty(descricao), nullIfEmpty(keywords), idiomas, regioes, categoriaID, ativo, *id)
		return *id, err
	}
	var newID int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO radar_topics (nome, descricao, keywords, idiomas, regioes, categoria_artigos_id, ativo)
		VALUES ($1,$2,$3,$4,$5,$6,$7) RETURNING id`,
		nome, nullIfEmpty(descricao), nullIfEmpty(keywords), idiomas, regioes, categoriaID, ativo).Scan(&newID)
	return newID, err
}

// RadarTopicDelete remove tema.
func (s *Store) RadarTopicDelete(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "DELETE FROM radar_topics WHERE id = $1", id)
	return err
}

// ------- sources -------

// RadarSources lista fontes.
func (s *Store) RadarSources(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, "SELECT * FROM radar_sources ORDER BY ativo DESC, tipo ASC, nome ASC")
}

// RadarSourceSave cria/atualiza e devolve o id.
func (s *Store) RadarSourceSave(ctx context.Context, id *int, nome, tipo, url, configJSON string, ativo bool) (int, error) {
	if id != nil && *id > 0 {
		_, err := s.pool.Exec(ctx, `
			UPDATE radar_sources SET nome=$1, tipo=$2, url=$3, config=$4::jsonb, ativo=$5, updated_at=CURRENT_TIMESTAMP WHERE id=$6`,
			nome, tipo, nullIfEmpty(url), nullIfEmpty(configJSON), ativo, *id)
		return *id, err
	}
	var newID int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO radar_sources (nome, tipo, url, config, ativo) VALUES ($1,$2,$3,$4::jsonb,$5) RETURNING id`,
		nome, tipo, nullIfEmpty(url), nullIfEmpty(configJSON), ativo).Scan(&newID)
	return newID, err
}

// RadarSourceDelete remove fonte.
func (s *Store) RadarSourceDelete(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "DELETE FROM radar_sources WHERE id = $1", id)
	return err
}

// ------- topic_sources -------

// RadarTopicSourceIDs lista source_ids do tema.
func (s *Store) RadarTopicSourceIDs(ctx context.Context, topicID int) ([]int, error) {
	rows, err := s.pool.Query(ctx, "SELECT source_id FROM radar_topic_sources WHERE topic_id = $1", topicID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	ids := []int{}
	for rows.Next() {
		var id int
		if err := rows.Scan(&id); err != nil {
			return nil, err
		}
		ids = append(ids, id)
	}
	return ids, rows.Err()
}

// RadarTopicSourcesSet substitui as fontes do tema (transação).
func (s *Store) RadarTopicSourcesSet(ctx context.Context, topicID int, ids []int) error {
	tx, err := s.pool.Begin(ctx)
	if err != nil {
		return err
	}
	defer tx.Rollback(ctx) //nolint:errcheck

	if _, err := tx.Exec(ctx, "DELETE FROM radar_topic_sources WHERE topic_id = $1", topicID); err != nil {
		return err
	}
	for _, sid := range ids {
		if sid <= 0 {
			continue
		}
		if _, err := tx.Exec(ctx, "INSERT INTO radar_topic_sources (topic_id, source_id) VALUES ($1,$2) ON CONFLICT DO NOTHING", topicID, sid); err != nil {
			return err
		}
	}
	return tx.Commit(ctx)
}

// ------- items -------

// RadarItems lista itens (por tema ou geral).
func (s *Store) RadarItems(ctx context.Context, topicID, limit int) ([]map[string]any, error) {
	if limit <= 0 || limit > 200 {
		limit = 50
	}
	if topicID > 0 {
		return s.queryMaps(ctx, `
			SELECT i.*, s.nome AS source_nome, s.tipo AS source_tipo
			FROM radar_items i
			LEFT JOIN radar_sources s ON s.id = i.source_id
			JOIN radar_item_topics it ON it.item_id = i.id
			WHERE it.topic_id = $1
			ORDER BY i.score DESC, i.fetched_at DESC
			LIMIT $2`, topicID, limit)
	}
	return s.queryMaps(ctx, `
		SELECT i.*, s.nome AS source_nome, s.tipo AS source_tipo
		FROM radar_items i
		LEFT JOIN radar_sources s ON s.id = i.source_id
		ORDER BY i.fetched_at DESC
		LIMIT $1`, limit)
}

// RadarItemsDeleteByIDs remove itens por ids e devolve quantos existiam.
func (s *Store) RadarItemsDeleteByIDs(ctx context.Context, ids []int) (int, error) {
	if len(ids) == 0 {
		return 0, nil
	}
	rows, err := s.queryMaps(ctx, "SELECT id FROM radar_items WHERE id = ANY($1)", ids)
	if err != nil {
		return 0, err
	}
	_, err = s.pool.Exec(ctx, "DELETE FROM radar_items WHERE id = ANY($1)", ids)
	return len(rows), err
}

// RadarItemsDeleteByURL remove itens por filtro de URL.
func (s *Store) RadarItemsDeleteByURL(ctx context.Context, urlLike string) (int, error) {
	rows, err := s.queryMaps(ctx, "SELECT id FROM radar_items WHERE url LIKE $1", "%"+urlLike+"%")
	if err != nil {
		return 0, err
	}
	_, err = s.pool.Exec(ctx, "DELETE FROM radar_items WHERE url LIKE $1", "%"+urlLike+"%")
	return len(rows), err
}

// ------- ideas -------

// RadarIdeas lista ideias.
func (s *Store) RadarIdeas(ctx context.Context, topicID int, status string, limit int) ([]map[string]any, error) {
	if limit <= 0 || limit > 200 {
		limit = 50
	}
	sql := "SELECT i.*, t.nome AS topic_nome FROM radar_ideas i JOIN radar_topics t ON t.id = i.topic_id WHERE 1=1"
	args := []any{}
	if topicID > 0 {
		args = append(args, topicID)
		sql += fmt.Sprintf(" AND i.topic_id = $%d", len(args))
	}
	if status != "" {
		args = append(args, status)
		sql += fmt.Sprintf(" AND i.status = $%d", len(args))
	}
	sql += " ORDER BY t.nome ASC, i.created_at DESC"
	args = append(args, limit)
	sql += fmt.Sprintf(" LIMIT $%d", len(args))
	return s.queryMaps(ctx, sql, args...)
}

// RadarIdeaDelete remove ideia.
func (s *Store) RadarIdeaDelete(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "DELETE FROM radar_ideas WHERE id = $1", id)
	return err
}

// RadarIdeaSourceItems devolve os itens-fonte de uma ideia.
func (s *Store) RadarIdeaSourceItems(ctx context.Context, ideaID int) ([]map[string]any, error) {
	var raw *string
	err := s.pool.QueryRow(ctx, "SELECT source_item_ids FROM radar_ideas WHERE id = $1", ideaID).Scan(&raw)
	if errors.Is(err, pgx.ErrNoRows) {
		return []map[string]any{}, nil
	}
	if err != nil {
		return nil, err
	}
	ids := parseJSONIntArray(raw)
	if len(ids) == 0 {
		return []map[string]any{}, nil
	}
	return s.queryMaps(ctx, `SELECT id, titulo, url, score, fetched_at FROM radar_items WHERE id = ANY($1) ORDER BY score DESC LIMIT 10`, ids)
}

func parseJSONIntArray(raw *string) []int {
	if raw == nil || *raw == "" {
		return nil
	}
	var anyArr []any
	if json.Unmarshal([]byte(*raw), &anyArr) != nil {
		return nil
	}
	out := []int{}
	for _, v := range anyArr {
		if f, ok := v.(float64); ok && f > 0 {
			out = append(out, int(f))
		}
	}
	return out
}
