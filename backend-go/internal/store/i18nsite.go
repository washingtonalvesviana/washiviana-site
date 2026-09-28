package store

import "context"

// UpsertUIString grava/replace uma string de UI.
func (s *Store) UpsertUIString(ctx context.Context, chave, lang, texto string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO ui_strings (chave, lang, texto) VALUES ($1,$2,$3)
		ON CONFLICT (chave, lang) DO UPDATE SET texto = EXCLUDED.texto, updated_at = CURRENT_TIMESTAMP`,
		chave, lang, texto)
	return err
}

// CategoriasAtivasProjetos lista categorias de projetos ativas.
func (s *Store) CategoriasAtivasProjetos(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, "SELECT id, nome, slug FROM categorias WHERE ativo = true ORDER BY ordem ASC")
}

// CategoriasAtivasArtigos lista categorias de artigos ativas.
func (s *Store) CategoriasAtivasArtigos(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, "SELECT id, nome, slug, descricao FROM categorias_artigos WHERE ativo = true ORDER BY ordem ASC")
}

// UpsertCategoriaI18n grava/replace categoria (projetos) i18n.
func (s *Store) UpsertCategoriaI18n(ctx context.Context, id int, lang, nome, slug string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO categorias_i18n (categoria_id, lang, nome, slug) VALUES ($1,$2,$3,$4)
		ON CONFLICT (categoria_id, lang) DO UPDATE SET nome = EXCLUDED.nome, slug = EXCLUDED.slug, updated_at = CURRENT_TIMESTAMP`,
		id, lang, nome, slug)
	return err
}

// UpsertCategoriaArtigoI18n grava/replace categoria (artigos) i18n.
func (s *Store) UpsertCategoriaArtigoI18n(ctx context.Context, id int, lang, nome, slug string, descricao *string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO categorias_artigos_i18n (categoria_id, lang, nome, slug, descricao) VALUES ($1,$2,$3,$4,$5)
		ON CONFLICT (categoria_id, lang) DO UPDATE SET nome = EXCLUDED.nome, slug = EXCLUDED.slug, descricao = EXCLUDED.descricao, updated_at = CURRENT_TIMESTAMP`,
		id, lang, nome, slug, descricao)
	return err
}

// UpsertConfigI18n grava/replace uma config i18n.
func (s *Store) UpsertConfigI18n(ctx context.Context, chave, lang, valor string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO configuracoes_i18n (chave, lang, valor) VALUES ($1,$2,$3)
		ON CONFLICT (chave, lang) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP`,
		chave, lang, valor)
	return err
}
