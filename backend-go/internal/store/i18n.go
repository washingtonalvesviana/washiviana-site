package store

import (
	"context"
	"errors"

	"github.com/jackc/pgx/v5"
)

// ArtigoI18nSource é a origem PT-BR para tradução.
type ArtigoI18nSource struct {
	ID       int
	Titulo   string
	Resumo   *string
	Conteudo *string
}

// ProjetoI18nSource é a origem PT-BR para tradução.
type ProjetoI18nSource struct {
	ID          int
	Titulo      string
	Descricao   *string
	Tecnologias *string
	URLProjeto  *string
}

// GetArtigoI18nSource busca o artigo base.
func (s *Store) GetArtigoI18nSource(ctx context.Context, id int) (*ArtigoI18nSource, error) {
	var a ArtigoI18nSource
	err := s.pool.QueryRow(ctx, "SELECT id, titulo, resumo, conteudo FROM artigos WHERE id = $1 LIMIT 1", id).
		Scan(&a.ID, &a.Titulo, &a.Resumo, &a.Conteudo)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, err
	}
	return &a, nil
}

// GetProjetoI18nSource busca o projeto base.
func (s *Store) GetProjetoI18nSource(ctx context.Context, id int) (*ProjetoI18nSource, error) {
	var p ProjetoI18nSource
	err := s.pool.QueryRow(ctx,
		"SELECT id, titulo, descricao, tecnologias, url_projeto FROM projetos WHERE id = $1 LIMIT 1", id).
		Scan(&p.ID, &p.Titulo, &p.Descricao, &p.Tecnologias, &p.URLProjeto)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, err
	}
	return &p, nil
}

// I18nSlugExists verifica slug duplicado no idioma (ignorando o próprio id).
func (s *Store) I18nSlugExists(ctx context.Context, table, idCol string, id int, lang, slug string) (bool, error) {
	var one int
	err := s.pool.QueryRow(ctx,
		"SELECT 1 FROM "+table+" WHERE lang = $1 AND slug = $2 AND "+idCol+" <> $3 LIMIT 1",
		lang, slug, id).Scan(&one)
	if errors.Is(err, pgx.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// UpsertArtigoI18n grava/replace a tradução do artigo.
func (s *Store) UpsertArtigoI18n(ctx context.Context, id int, lang, titulo, slug, resumo, conteudo, metaTitle, metaDesc, ogTitle, ogDesc string, keywords *string, provider string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO artigos_i18n
		  (artigo_id, lang, titulo, slug, resumo, conteudo, meta_title, meta_description, og_title, og_description, keywords, status_traducao, generated_by, generated_at)
		VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,'generated',$12, CURRENT_TIMESTAMP)
		ON CONFLICT (artigo_id, lang) DO UPDATE SET
		  titulo = EXCLUDED.titulo, slug = EXCLUDED.slug, resumo = EXCLUDED.resumo, conteudo = EXCLUDED.conteudo,
		  meta_title = EXCLUDED.meta_title, meta_description = EXCLUDED.meta_description,
		  og_title = EXCLUDED.og_title, og_description = EXCLUDED.og_description, keywords = EXCLUDED.keywords,
		  status_traducao = EXCLUDED.status_traducao, generated_by = EXCLUDED.generated_by,
		  generated_at = EXCLUDED.generated_at, updated_at = CURRENT_TIMESTAMP`,
		id, lang, titulo, slug, resumo, conteudo, metaTitle, metaDesc, ogTitle, ogDesc, keywords, provider)
	return err
}

// UpdateI18nStatus atualiza status_traducao e devolve linhas afetadas.
func (s *Store) UpdateI18nStatus(ctx context.Context, entity string, id int, lang, status string) (int64, error) {
	table, fk := "artigos_i18n", "artigo_id"
	if entity == "projeto" {
		table, fk = "projetos_i18n", "projeto_id"
	}
	tag, err := s.pool.Exec(ctx,
		"UPDATE "+table+" SET status_traducao = $1, updated_at = CURRENT_TIMESTAMP WHERE "+fk+" = $2 AND lang = $3",
		status, id, lang)
	if err != nil {
		return 0, err
	}
	return tag.RowsAffected(), nil
}

// UpsertProjetoI18n grava/replace a tradução do projeto.
func (s *Store) UpsertProjetoI18n(ctx context.Context, id int, lang, titulo, slug, descricao, metaTitle, metaDesc, ogTitle, ogDesc string, keywords *string, provider string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO projetos_i18n
		  (projeto_id, lang, titulo, slug, descricao, meta_title, meta_description, og_title, og_description, keywords, status_traducao, generated_by, generated_at)
		VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,'generated',$11, CURRENT_TIMESTAMP)
		ON CONFLICT (projeto_id, lang) DO UPDATE SET
		  titulo = EXCLUDED.titulo, slug = EXCLUDED.slug, descricao = EXCLUDED.descricao,
		  meta_title = EXCLUDED.meta_title, meta_description = EXCLUDED.meta_description,
		  og_title = EXCLUDED.og_title, og_description = EXCLUDED.og_description, keywords = EXCLUDED.keywords,
		  status_traducao = EXCLUDED.status_traducao, generated_by = EXCLUDED.generated_by,
		  generated_at = EXCLUDED.generated_at, updated_at = CURRENT_TIMESTAMP`,
		id, lang, titulo, slug, descricao, metaTitle, metaDesc, ogTitle, ogDesc, keywords, provider)
	return err
}
