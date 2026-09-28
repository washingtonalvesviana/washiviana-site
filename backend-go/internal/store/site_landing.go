package store

import "context"

// SiteArticlesByCategorySlugs lista artigos publicados de categorias (com i18n).
func (s *Store) SiteArticlesByCategorySlugs(ctx context.Context, lang string, slugs []string) ([]map[string]any, error) {
	return s.queryMaps(ctx, `
		SELECT a.id, a.created_at,
		       COALESCE(t.titulo, a.titulo) AS titulo_exib,
		       COALESCE(t.resumo, a.resumo) AS resumo_exib,
		       COALESCE(t.slug, a.slug) AS slug_exib,
		       COALESCE(ci.nome, ca.nome) AS categoria_nome
		FROM artigos a
		LEFT JOIN artigos_i18n t ON t.artigo_id = a.id AND t.lang = $1
		LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id
		LEFT JOIN categorias_artigos_i18n ci ON ci.categoria_id = ca.id AND ci.lang = $1
		WHERE a.status_publicacao = 'publicado' AND ca.slug = ANY($2)
		ORDER BY a.created_at DESC`, lang, slugs)
}

// SiteProjectsByTag lista projetos ativos cujo texto contém o termo (com i18n).
func (s *Store) SiteProjectsByTag(ctx context.Context, lang, term string) ([]map[string]any, error) {
	like := "%" + term + "%"
	return s.queryMaps(ctx, `
		SELECT p.id,
		       COALESCE(t.titulo, p.titulo) AS titulo_exib,
		       COALESCE(t.descricao, p.descricao) AS descricao_exib,
		       COALESCE(t.slug, p.slug) AS slug_exib,
		       COALESCE(ci.nome, c.nome) AS categoria_nome
		FROM projetos p
		LEFT JOIN projetos_i18n t ON t.projeto_id = p.id AND t.lang = $1
		LEFT JOIN categorias c ON p.categoria_id = c.id
		LEFT JOIN categorias_i18n ci ON ci.categoria_id = c.id AND ci.lang = $1
		WHERE p.ativo = true
		  AND (COALESCE(t.titulo, p.titulo) ILIKE $2 OR COALESCE(t.descricao, p.descricao) ILIKE $2 OR p.tecnologias ILIKE $2)
		ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC`, lang, like)
}
