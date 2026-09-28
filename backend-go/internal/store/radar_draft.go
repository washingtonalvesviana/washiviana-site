package store

import "context"

// RadarIdeaWithTopic busca uma ideia com dados do tema.
func (s *Store) RadarIdeaWithTopic(ctx context.Context, id int) (map[string]any, error) {
	return s.queryOne(ctx, `
		SELECT i.*, t.nome AS topic_nome, t.categoria_artigos_id
		FROM radar_ideas i
		JOIN radar_topics t ON t.id = i.topic_id
		WHERE i.id = $1 LIMIT 1`, id)
}

// RadarItemRefs devolve refs (titulo|url) para os ids informados.
func (s *Store) RadarItemRefs(ctx context.Context, ids []int) ([]string, error) {
	if len(ids) == 0 {
		return nil, nil
	}
	rows, err := s.queryMaps(ctx,
		`SELECT titulo, url FROM radar_items WHERE id = ANY($1) ORDER BY score DESC, fetched_at DESC LIMIT 20`, ids)
	if err != nil {
		return nil, err
	}
	refs := make([]string, 0, len(rows))
	for _, r := range rows {
		refs = append(refs, "- "+trimAny(r["titulo"])+" | "+trimAny(r["url"]))
	}
	return refs, nil
}

func trimAny(v any) string {
	if s, ok := v.(string); ok {
		return s
	}
	return ""
}

// UpdateRadarIdeaStatus altera o status da ideia.
func (s *Store) UpdateRadarIdeaStatus(ctx context.Context, id int, status string) error {
	_, err := s.pool.Exec(ctx, "UPDATE radar_ideas SET status = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2", status, id)
	return err
}

// InsertArtigoDraft cria um artigo rascunho e devolve o id.
func (s *Store) InsertArtigoDraft(ctx context.Context, titulo, slug, resumo, conteudo string, categoriaID *int, promptTexto *string) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO artigos (titulo, slug, resumo, conteudo, autor, status_publicacao, ativo, categoria_id, prompt_texto)
		VALUES ($1,$2,$3,$4,'Washington Viana','rascunho',true,$5,$6)
		RETURNING id`,
		titulo, slug, resumo, conteudo, categoriaID, promptTexto).Scan(&id)
	return id, err
}
