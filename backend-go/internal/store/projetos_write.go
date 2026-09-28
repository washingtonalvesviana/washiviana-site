package store

import (
	"context"
	"encoding/json"
	"errors"

	"github.com/jackc/pgx/v5"
)

// ProjetoInput reúne os campos graváveis de um projeto.
type ProjetoInput struct {
	Titulo          string
	Slug            string
	Descricao       string
	CategoriaID     int
	ImagemPrincipal *string
	ImagensGaleria  []string
	Tecnologias     string
	URLProjeto      string
	Destaque        bool
	Ativo           bool
	Ordem           *int
	PromptDescricao *string
	PromptLinkedin  *string
}

// ProjetoRow são os campos usados como default no update.
type ProjetoRow struct {
	ID              int
	Titulo          string
	Slug            string
	Descricao       *string
	CategoriaID     *int
	ImagemPrincipal *string
	ImagensGaleria  *string
	Tecnologias     *string
	URLProjeto      *string
	Destaque        *bool
	Ativo           *bool
	Ordem           *int
	PromptDescricao *string
	PromptLinkedin  *string
}

func galeriaJSON(items []string) string {
	if items == nil {
		items = []string{}
	}
	b, _ := json.Marshal(items)
	return string(b)
}

// ProjetoSlugExists verifica slug.
func (s *Store) ProjetoSlugExists(ctx context.Context, slug string) (bool, error) {
	var id int
	err := s.pool.QueryRow(ctx, "SELECT id FROM projetos WHERE slug = $1 LIMIT 1", slug).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// ProjetoSlugExistsExcept verifica slug ignorando um id.
func (s *Store) ProjetoSlugExistsExcept(ctx context.Context, slug string, exceptID int) (bool, error) {
	var id int
	err := s.pool.QueryRow(ctx, "SELECT id FROM projetos WHERE slug = $1 AND id <> $2 LIMIT 1", slug, exceptID).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// GetProjetoRow busca o projeto para defaults.
func (s *Store) GetProjetoRow(ctx context.Context, id int) (*ProjetoRow, error) {
	var p ProjetoRow
	err := s.pool.QueryRow(ctx, `
		SELECT id, titulo, slug, descricao, categoria_id, imagem_principal, imagens_galeria,
		       tecnologias, url_projeto, destaque, ativo, ordem, prompt_descricao, prompt_linkedin
		  FROM projetos WHERE id = $1`, id).Scan(
		&p.ID, &p.Titulo, &p.Slug, &p.Descricao, &p.CategoriaID, &p.ImagemPrincipal, &p.ImagensGaleria,
		&p.Tecnologias, &p.URLProjeto, &p.Destaque, &p.Ativo, &p.Ordem, &p.PromptDescricao, &p.PromptLinkedin,
	)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &p, nil
}

// CreateProjeto insere e devolve o id.
func (s *Store) CreateProjeto(ctx context.Context, in ProjetoInput) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO projetos
			(titulo, slug, descricao, categoria_id, imagem_principal, imagens_galeria,
			 tecnologias, url_projeto, destaque, ativo, ordem, prompt_descricao, prompt_linkedin)
		VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13)
		RETURNING id`,
		in.Titulo, in.Slug, in.Descricao, in.CategoriaID, in.ImagemPrincipal, galeriaJSON(in.ImagensGaleria),
		in.Tecnologias, in.URLProjeto, in.Destaque, in.Ativo, in.Ordem, in.PromptDescricao, in.PromptLinkedin,
	).Scan(&id)
	return id, err
}

// UpdateProjeto atualiza o projeto.
func (s *Store) UpdateProjeto(ctx context.Context, id int, in ProjetoInput) error {
	_, err := s.pool.Exec(ctx, `
		UPDATE projetos SET
			titulo = $1, slug = $2, descricao = $3, categoria_id = $4,
			imagem_principal = $5, imagens_galeria = $6, tecnologias = $7,
			url_projeto = $8, destaque = $9, ativo = $10, ordem = $11,
			prompt_descricao = $12, prompt_linkedin = $13, updated_at = CURRENT_TIMESTAMP
		WHERE id = $14`,
		in.Titulo, in.Slug, in.Descricao, in.CategoriaID, in.ImagemPrincipal, galeriaJSON(in.ImagensGaleria),
		in.Tecnologias, in.URLProjeto, in.Destaque, in.Ativo, in.Ordem, in.PromptDescricao, in.PromptLinkedin, id)
	return err
}

// DeleteProjeto remove o projeto (i18n/posts caem por CASCADE).
func (s *Store) DeleteProjeto(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "DELETE FROM projetos WHERE id = $1", id)
	return err
}

// SetProjetoOrdem aplica ordem = índice em transação.
func (s *Store) SetProjetoOrdem(ctx context.Context, ids []int) error {
	tx, err := s.pool.Begin(ctx)
	if err != nil {
		return err
	}
	defer tx.Rollback(ctx) //nolint:errcheck
	for index, id := range ids {
		if _, err := tx.Exec(ctx, "UPDATE projetos SET ordem = $1 WHERE id = $2", index, id); err != nil {
			return err
		}
	}
	return tx.Commit(ctx)
}

// ToggleProjetoAtivo inverte ativo.
func (s *Store) ToggleProjetoAtivo(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "UPDATE projetos SET ativo = NOT ativo WHERE id = $1", id)
	return err
}

// ToggleProjetoDestaque inverte destaque.
func (s *Store) ToggleProjetoDestaque(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "UPDATE projetos SET destaque = NOT destaque WHERE id = $1", id)
	return err
}

// ------- posts LinkedIn (projetos) -------

// InsertPostLinkedin grava um post gerado por IA.
func (s *Store) InsertPostLinkedin(ctx context.Context, projetoID int, conteudo, prompt string) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx,
		"INSERT INTO posts_linkedin (projeto_id, conteudo, prompt_usado) VALUES ($1,$2,$3) RETURNING id",
		projetoID, conteudo, nullIfEmpty(prompt)).Scan(&id)
	return id, err
}

// GetPostLinkedin busca post por id.
func (s *Store) GetPostLinkedin(ctx context.Context, id int) (map[string]any, error) {
	return s.queryOne(ctx, "SELECT * FROM posts_linkedin WHERE id = $1", id)
}

// ------- mídia da galeria -------

// GetProjetoGaleria devolve a galeria atual (lista de arquivos).
func (s *Store) GetProjetoGaleria(ctx context.Context, id int) ([]string, error) {
	var raw *string
	err := s.pool.QueryRow(ctx, "SELECT imagens_galeria FROM projetos WHERE id = $1", id).Scan(&raw)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, ErrNotFound
	}
	if err != nil {
		return nil, err
	}
	if raw == nil || *raw == "" {
		return []string{}, nil
	}
	var items []string
	if err := json.Unmarshal([]byte(*raw), &items); err != nil {
		return []string{}, nil
	}
	return items, nil
}

// SetProjetoGaleria atualiza a galeria.
func (s *Store) SetProjetoGaleria(ctx context.Context, id int, items []string) error {
	_, err := s.pool.Exec(ctx, "UPDATE projetos SET imagens_galeria = $1 WHERE id = $2", galeriaJSON(items), id)
	return err
}
