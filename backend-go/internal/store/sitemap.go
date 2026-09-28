package store

import "context"

// SitemapArticles lista artigos publicados (slug/timestamps).
func (s *Store) SitemapArticles(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, `
		SELECT id, slug, updated_at, created_at FROM artigos
		WHERE status_publicacao = 'publicado' AND ativo = true
		ORDER BY created_at DESC`)
}

// SitemapProjects lista projetos ativos (slug/timestamps).
func (s *Store) SitemapProjects(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, `
		SELECT id, slug, updated_at, created_at FROM projetos
		WHERE ativo = true ORDER BY created_at DESC`)
}

// SitemapArticleSlugs lista slugs i18n de artigos.
func (s *Store) SitemapArticleSlugs(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, "SELECT artigo_id, lang, slug FROM artigos_i18n WHERE slug IS NOT NULL")
}

// SitemapProjectSlugs lista slugs i18n de projetos.
func (s *Store) SitemapProjectSlugs(ctx context.Context) ([]map[string]any, error) {
	return s.queryMaps(ctx, "SELECT projeto_id, lang, slug FROM projetos_i18n WHERE slug IS NOT NULL")
}
