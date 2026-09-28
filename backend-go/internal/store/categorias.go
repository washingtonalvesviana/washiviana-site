package store

import (
	"context"
	"errors"

	"github.com/jackc/pgx/v5"
)

// CategoryTables resolve a tabela alvo (igual getCategoryTables do PHP).
type CategoryTables struct {
	CatTable  string
	ItemTable string
	ItemLabel string
	Noun      string
}

// NewCategoryTables devolve as tabelas para "projetos" (default) ou "artigos".
func NewCategoryTables(target string) CategoryTables {
	if target == "artigos" {
		return CategoryTables{CatTable: "categorias_artigos", ItemTable: "artigos", ItemLabel: "artigos", Noun: "categoria de artigos"}
	}
	return CategoryTables{CatTable: "categorias", ItemTable: "projetos", ItemLabel: "projetos", Noun: "categoria"}
}

// SlugExists verifica se já existe categoria com o slug na tabela alvo.
func (s *Store) SlugExists(ctx context.Context, catTable, slug string) (bool, error) {
	var id int
	err := s.pool.QueryRow(ctx, `SELECT id FROM `+catTable+` WHERE slug = $1 LIMIT 1`, slug).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// SlugExistsExcept verifica slug duplicado ignorando um id.
func (s *Store) SlugExistsExcept(ctx context.Context, catTable, slug string, exceptID int) (bool, error) {
	var id int
	err := s.pool.QueryRow(ctx, `SELECT id FROM `+catTable+` WHERE slug = $1 AND id <> $2 LIMIT 1`, slug, exceptID).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// CategoryExists devolve se a categoria existe (e não erro).
func (s *Store) CategoryExists(ctx context.Context, catTable string, id int) (bool, error) {
	var exists bool
	err := s.pool.QueryRow(ctx, `SELECT EXISTS(SELECT 1 FROM `+catTable+` WHERE id = $1)`, id).Scan(&exists)
	return exists, err
}

// NextCategoriaOrdem devolve MAX(ordem)+1.
func (s *Store) NextCategoriaOrdem(ctx context.Context, catTable string) (int, error) {
	var maxOrdem *int
	if err := s.pool.QueryRow(ctx, `SELECT MAX(ordem) FROM `+catTable).Scan(&maxOrdem); err != nil {
		return 0, err
	}
	if maxOrdem == nil {
		return 1, nil
	}
	return *maxOrdem + 1, nil
}

// CreateCategoria insere e devolve o id.
func (s *Store) CreateCategoria(ctx context.Context, catTable, nome, slug string, ordem int, ativo bool) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx,
		`INSERT INTO `+catTable+` (nome, slug, ordem, ativo) VALUES ($1,$2,$3,$4) RETURNING id`,
		nome, slug, ordem, ativo).Scan(&id)
	return id, err
}

// UpdateCategoria atualiza nome/slug/ativo.
func (s *Store) UpdateCategoria(ctx context.Context, catTable string, id int, nome, slug string, ativo bool) error {
	_, err := s.pool.Exec(ctx,
		`UPDATE `+catTable+` SET nome = $1, slug = $2, ativo = $3 WHERE id = $4`,
		nome, slug, ativo, id)
	return err
}

// CountCategoriaItems conta itens associados (para bloquear exclusão).
func (s *Store) CountCategoriaItems(ctx context.Context, itemTable string, id int) (int, error) {
	var total int
	err := s.pool.QueryRow(ctx, `SELECT COUNT(*) FROM `+itemTable+` WHERE categoria_id = $1`, id).Scan(&total)
	return total, err
}

// DeleteCategoria remove a categoria.
func (s *Store) DeleteCategoria(ctx context.Context, catTable string, id int) error {
	_, err := s.pool.Exec(ctx, `DELETE FROM `+catTable+` WHERE id = $1`, id)
	return err
}

// ToggleCategoriaAtivo inverte o campo ativo.
func (s *Store) ToggleCategoriaAtivo(ctx context.Context, catTable string, id int) error {
	_, err := s.pool.Exec(ctx, `UPDATE `+catTable+` SET ativo = NOT ativo WHERE id = $1`, id)
	return err
}

// SetCategoriaOrdem aplica uma nova ordem em transação (ordem = índice).
func (s *Store) SetCategoriaOrdem(ctx context.Context, catTable string, ids []int) error {
	tx, err := s.pool.Begin(ctx)
	if err != nil {
		return err
	}
	defer tx.Rollback(ctx) //nolint:errcheck

	for index, id := range ids {
		if _, err := tx.Exec(ctx, `UPDATE `+catTable+` SET ordem = $1 WHERE id = $2`, index, id); err != nil {
			return err
		}
	}
	return tx.Commit(ctx)
}
