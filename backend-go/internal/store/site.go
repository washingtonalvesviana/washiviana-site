package store

import "context"

// SiteUIStrings devolve as strings de UI de um idioma (chave→texto).
func (s *Store) SiteUIStrings(ctx context.Context, lang string) (map[string]string, error) {
	rows, err := s.queryMaps(ctx, "SELECT chave, texto FROM ui_strings WHERE lang = $1", lang)
	if err != nil {
		return nil, err
	}
	out := map[string]string{}
	for _, r := range rows {
		if k, ok := r["chave"].(string); ok {
			if t, ok := r["texto"].(string); ok {
				out[k] = t
			}
		}
	}
	return out, nil
}

// SiteConfigI18n devolve configs traduzidas de um idioma (chave→valor).
func (s *Store) SiteConfigI18n(ctx context.Context, lang string) (map[string]string, error) {
	rows, err := s.queryMaps(ctx, "SELECT chave, valor FROM configuracoes_i18n WHERE lang = $1", lang)
	if err != nil {
		return nil, err
	}
	out := map[string]string{}
	for _, r := range rows {
		if k, ok := r["chave"].(string); ok {
			if v, ok := r["valor"].(string); ok {
				out[k] = v
			}
		}
	}
	return out, nil
}

// SiteLatestArticles lista artigos publicados/ativos.
func (s *Store) SiteLatestArticles(ctx context.Context, limit int) ([]map[string]any, error) {
	if limit <= 0 {
		limit = 6
	}
	return s.queryMaps(ctx, `
		SELECT id, titulo, slug, resumo, imagem_principal, created_at
		FROM artigos
		WHERE ativo = true AND status_publicacao = 'publicado'
		ORDER BY COALESCE(data_publicacao, created_at) DESC
		LIMIT $1`, limit)
}

// SiteArticleBySlug busca um artigo publicado por slug.
func (s *Store) SiteArticleBySlug(ctx context.Context, slug string) (map[string]any, error) {
	return s.queryOne(ctx, `
		SELECT * FROM artigos
		WHERE slug = $1 AND ativo = true AND status_publicacao = 'publicado'
		LIMIT 1`, slug)
}

// ArtigoI18nRow busca a tradução de um artigo.
func (s *Store) ArtigoI18nRow(ctx context.Context, artigoID int, lang string) (map[string]any, error) {
	return s.queryOne(ctx, `
		SELECT titulo, slug, resumo, conteudo, meta_title, meta_description, og_title, og_description
		FROM artigos_i18n WHERE artigo_id = $1 AND lang = $2 LIMIT 1`, artigoID, lang)
}

// SiteProjectBySlug busca um projeto ativo por slug.
func (s *Store) SiteProjectBySlug(ctx context.Context, slug string) (map[string]any, error) {
	return s.queryOne(ctx, "SELECT * FROM projetos WHERE slug = $1 AND ativo = true LIMIT 1", slug)
}

// ProjetoI18nRow busca a tradução de um projeto.
func (s *Store) ProjetoI18nRow(ctx context.Context, projetoID int, lang string) (map[string]any, error) {
	return s.queryOne(ctx, `
		SELECT titulo, slug, descricao, meta_title, meta_description, og_title, og_description
		FROM projetos_i18n WHERE projeto_id = $1 AND lang = $2 LIMIT 1`, projetoID, lang)
}

// SiteFeaturedProjects lista projetos ativos (destaques primeiro).
func (s *Store) SiteFeaturedProjects(ctx context.Context, limit int) ([]map[string]any, error) {
	if limit <= 0 {
		limit = 6
	}
	return s.queryMaps(ctx, `
		SELECT id, titulo, slug, descricao, imagem_principal
		FROM projetos
		WHERE ativo = true
		ORDER BY destaque DESC, COALESCE(NULLIF(ordem,0), 999999) ASC, created_at DESC
		LIMIT $1`, limit)
}
