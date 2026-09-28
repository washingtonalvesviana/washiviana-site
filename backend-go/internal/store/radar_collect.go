package store

import "context"

// RadarSourceAtivo busca fonte ativa.
func (s *Store) RadarSourceAtivo(ctx context.Context, id int) (map[string]any, error) {
	return s.queryOne(ctx, "SELECT * FROM radar_sources WHERE id = $1 AND ativo = true", id)
}

// RadarSourceTopicIDs lista source_ids ativos do tema.
func (s *Store) RadarSourceTopicIDs(ctx context.Context, topicID int) ([]int, error) {
	rows, err := s.pool.Query(ctx, `
		SELECT ts.source_id FROM radar_topic_sources ts
		JOIN radar_sources s ON s.id = ts.source_id
		WHERE ts.topic_id = $1 AND s.ativo = true`, topicID)
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

// UpsertRadarItem insere/atualiza item coletado e vincula aos temas.
func (s *Store) UpsertRadarItem(ctx context.Context, sourceID *int, url, urlNorm, titulo, descricao string, publishedAt *string, score float64, rawJSON *string, topicIDs []int) (int, error) {
	tx, err := s.pool.Begin(ctx)
	if err != nil {
		return 0, err
	}
	defer tx.Rollback(ctx) //nolint:errcheck

	var itemID int
	err = tx.QueryRow(ctx, `
		INSERT INTO radar_items (source_id, url, url_norm, titulo, descricao, published_at, score, raw)
		VALUES ($1,$2,$3,$4,$5,$6::timestamp,$7,$8::jsonb)
		ON CONFLICT (url_norm) DO UPDATE SET
			source_id = COALESCE(EXCLUDED.source_id, radar_items.source_id),
			titulo = COALESCE(NULLIF(EXCLUDED.titulo,''), radar_items.titulo),
			descricao = COALESCE(NULLIF(EXCLUDED.descricao,''), radar_items.descricao),
			published_at = COALESCE(EXCLUDED.published_at, radar_items.published_at),
			fetched_at = CURRENT_TIMESTAMP,
			score = GREATEST(radar_items.score, EXCLUDED.score),
			raw = COALESCE(EXCLUDED.raw, radar_items.raw)
		RETURNING id`,
		sourceID, url, urlNorm, titulo, descricao, publishedAt, score, rawJSON).Scan(&itemID)
	if err != nil {
		return 0, err
	}
	for _, tid := range topicIDs {
		if tid <= 0 {
			continue
		}
		if _, err := tx.Exec(ctx, "INSERT INTO radar_item_topics (item_id, topic_id) VALUES ($1,$2) ON CONFLICT DO NOTHING", itemID, tid); err != nil {
			return 0, err
		}
	}
	if err := tx.Commit(ctx); err != nil {
		return 0, err
	}
	return itemID, nil
}

// RadarItemsFullByIDs devolve linhas completas por ids (para backup).
func (s *Store) RadarItemsFullByIDs(ctx context.Context, ids []int) ([]map[string]any, error) {
	if len(ids) == 0 {
		return nil, nil
	}
	return s.queryMaps(ctx, "SELECT * FROM radar_items WHERE id = ANY($1)", ids)
}

// RadarItemsFullByURL devolve linhas completas por filtro de URL (para backup).
func (s *Store) RadarItemsFullByURL(ctx context.Context, urlLike string) ([]map[string]any, error) {
	return s.queryMaps(ctx, "SELECT * FROM radar_items WHERE url LIKE $1", "%"+urlLike+"%")
}

// InsertRadarRun cria um run.
func (s *Store) InsertRadarRun(ctx context.Context, metaJSON string) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx, "INSERT INTO radar_runs (status, triggered_by, meta) VALUES ('running','manual',$1::jsonb) RETURNING id", metaJSON).Scan(&id)
	return id, err
}

// FinishRadarRun finaliza um run.
func (s *Store) FinishRadarRun(ctx context.Context, id int, status, log string) error {
	_, err := s.pool.Exec(ctx, "UPDATE radar_runs SET status=$1, finished_at=CURRENT_TIMESTAMP, log=$2 WHERE id=$3", status, log, id)
	return err
}
