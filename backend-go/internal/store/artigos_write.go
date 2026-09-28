package store

import (
	"context"
	"errors"
	"time"

	"github.com/jackc/pgx/v5"
)

// ArtigoInput reúne os campos graváveis de um artigo (paridade com o PHP).
type ArtigoInput struct {
	Titulo            string
	Slug              string
	Resumo            string
	Conteudo          string
	CategoriaID       *int
	Autor             string
	TipoMidia         string
	Destaque          bool
	PromptTexto       string
	PromptImagem      string
	Imagem1x1         string
	Imagem9x16        string
	StatusPublicacao  string
	DataAgendamento   *time.Time
	RecorrenciaTipo   string
	RecorrenciaDias   *string
	RecorrenciaDiaMes *int
	RecorrenciaFim    *time.Time
	RedesDestino      string
	ImagemPrincipal   *string
	VideoURL          *string
	DataPublicacao    *time.Time
}

// ArtigoRow representa o artigo existente (para defaults no update).
type ArtigoRow struct {
	ID                int
	Titulo            string
	Slug              string
	Resumo            *string
	Conteudo          *string
	CategoriaID       *int
	Autor             *string
	TipoMidia         *string
	Destaque          *bool
	PromptTexto       *string
	PromptImagem      *string
	Imagem1x1         *string
	Imagem9x16        *string
	StatusPublicacao  *string
	DataAgendamento   *time.Time
	RecorrenciaTipo   *string
	RecorrenciaDias   *string
	RecorrenciaDiaMes *int
	RecorrenciaFim    *time.Time
	RedesDestino      *string
	ImagemPrincipal   *string
	VideoURL          *string
	LinkedinPostID    *string
	DataPublicacao    *time.Time
}

func nullIfEmpty(s string) *string {
	if s == "" {
		return nil
	}
	return &s
}

// ArtigoSlugExists verifica existência de slug.
func (s *Store) ArtigoSlugExists(ctx context.Context, slug string) (bool, error) {
	var id int
	err := s.pool.QueryRow(ctx, "SELECT id FROM artigos WHERE slug = $1 LIMIT 1", slug).Scan(&id)
	if errors.Is(err, pgx.ErrNoRows) {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// GetArtigoRow busca os campos usados como default no update.
func (s *Store) GetArtigoRow(ctx context.Context, id int) (*ArtigoRow, error) {
	var a ArtigoRow
	err := s.pool.QueryRow(ctx, `
		SELECT id, titulo, slug, resumo, conteudo, categoria_id, autor, tipo_midia, destaque,
		       prompt_texto, prompt_imagem, imagem_1x1, imagem_9x16, status_publicacao,
		       data_agendamento, recorrencia_tipo, recorrencia_dias, recorrencia_dia_mes, recorrencia_fim,
		       redes_destino, imagem_principal, video_url, linkedin_post_id, data_publicacao
		  FROM artigos WHERE id = $1`, id).Scan(
		&a.ID, &a.Titulo, &a.Slug, &a.Resumo, &a.Conteudo, &a.CategoriaID, &a.Autor, &a.TipoMidia, &a.Destaque,
		&a.PromptTexto, &a.PromptImagem, &a.Imagem1x1, &a.Imagem9x16, &a.StatusPublicacao,
		&a.DataAgendamento, &a.RecorrenciaTipo, &a.RecorrenciaDias, &a.RecorrenciaDiaMes, &a.RecorrenciaFim,
		&a.RedesDestino, &a.ImagemPrincipal, &a.VideoURL, &a.LinkedinPostID, &a.DataPublicacao,
	)
	if errors.Is(err, pgx.ErrNoRows) {
		return nil, nil
	}
	if err != nil {
		return nil, err
	}
	return &a, nil
}

// CreateArtigo insere e devolve o id.
func (s *Store) CreateArtigo(ctx context.Context, in ArtigoInput) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO artigos (
			titulo, slug, resumo, conteudo, categoria_id, autor,
			tipo_midia, imagem_principal, video_url, destaque,
			prompt_texto, prompt_imagem, imagem_1x1, imagem_9x16,
			status_publicacao, data_agendamento,
			recorrencia_tipo, recorrencia_dias, recorrencia_dia_mes, recorrencia_fim,
			redes_destino, data_publicacao
		) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21,$22)
		RETURNING id`,
		in.Titulo, in.Slug, in.Resumo, in.Conteudo, in.CategoriaID, in.Autor,
		in.TipoMidia, in.ImagemPrincipal, in.VideoURL, in.Destaque,
		nullIfEmpty(in.PromptTexto), nullIfEmpty(in.PromptImagem), nullIfEmpty(in.Imagem1x1), nullIfEmpty(in.Imagem9x16),
		in.StatusPublicacao, in.DataAgendamento,
		nullIfEmpty(in.RecorrenciaTipo), in.RecorrenciaDias, in.RecorrenciaDiaMes, in.RecorrenciaFim,
		in.RedesDestino, in.DataPublicacao,
	).Scan(&id)
	return id, err
}

// UpdateArtigo atualiza o artigo.
func (s *Store) UpdateArtigo(ctx context.Context, id int, in ArtigoInput) error {
	_, err := s.pool.Exec(ctx, `
		UPDATE artigos SET
			titulo = $1, slug = $2, resumo = $3, conteudo = $4, categoria_id = $5, autor = $6,
			tipo_midia = $7, imagem_principal = $8, video_url = $9, destaque = $10,
			prompt_texto = $11, prompt_imagem = $12, imagem_1x1 = $13, imagem_9x16 = $14,
			status_publicacao = $15, data_agendamento = $16,
			recorrencia_tipo = $17, recorrencia_dias = $18, recorrencia_dia_mes = $19, recorrencia_fim = $20,
			redes_destino = $21, data_publicacao = $22, updated_at = CURRENT_TIMESTAMP
		WHERE id = $23`,
		in.Titulo, in.Slug, in.Resumo, in.Conteudo, in.CategoriaID, in.Autor,
		in.TipoMidia, in.ImagemPrincipal, in.VideoURL, in.Destaque,
		nullIfEmpty(in.PromptTexto), nullIfEmpty(in.PromptImagem), nullIfEmpty(in.Imagem1x1), nullIfEmpty(in.Imagem9x16),
		in.StatusPublicacao, in.DataAgendamento,
		nullIfEmpty(in.RecorrenciaTipo), in.RecorrenciaDias, in.RecorrenciaDiaMes, in.RecorrenciaFim,
		in.RedesDestino, in.DataPublicacao, id)
	return err
}

// SoftDeletePublicacoes marca publicações do artigo como deletadas (paridade PHP).
func (s *Store) SoftDeletePublicacoes(ctx context.Context, artigoID int) error {
	_, err := s.pool.Exec(ctx, "UPDATE publicacoes_redes SET status = 'deletado' WHERE artigo_id = $1", artigoID)
	return err
}

// DeleteArtigo remove o artigo (filhos caem por ON DELETE CASCADE).
func (s *Store) DeleteArtigo(ctx context.Context, id int) error {
	_, err := s.pool.Exec(ctx, "DELETE FROM artigos WHERE id = $1", id)
	return err
}
