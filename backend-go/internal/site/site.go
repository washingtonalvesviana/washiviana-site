// Package site renderiza páginas públicas (migração strangler do site PHP).
package site

import (
	"context"
	"fmt"
	"html"
	"strings"
	"time"

	"washiviana/backend/internal/config"
	"washiviana/backend/internal/store"
)

// Service renderiza o site público.
type Service struct {
	store *store.Store
	cfg   config.Config
}

// New cria o serviço.
func New(st *store.Store, cfg config.Config) *Service {
	return &Service{store: st, cfg: cfg}
}

var siteConfigKeys = []string{
	"site_titulo", "site_subtitulo", "home_frase_impacto", "mini_bio",
	"site_email", "site_linkedin", "site_instagram", "site_github",
	"home_card_1_icon", "home_card_1_titulo", "home_card_1_subtexto", "home_card_1_link",
	"home_card_2_icon", "home_card_2_titulo", "home_card_2_subtexto", "home_card_2_link",
	"home_card_3_icon", "home_card_3_titulo", "home_card_3_subtexto", "home_card_3_link",
	"home_card_4_icon", "home_card_4_titulo", "home_card_4_subtexto", "home_card_4_link",
}

// ptDefaults são os textos PT-BR de fallback (espelham i18n_site.php).
var ptDefaults = map[string]string{
	"nav.home": "Início", "nav.contents": "Conteúdos", "nav.projects": "Projetos",
	"nav.about": "Sobre", "nav.contact": "Contato",
	"hero.cta_contents": "Explorar Conteúdos", "hero.cta_projects": "Ver Projetos",
	"home.section_find": "O que você vai encontrar aqui",
	"home.latest":       "Últimos conteúdos", "home.soon": "Conteúdos em breve...",
	"home.view_all_contents": "Ver todos os conteúdos →",
	"home.about_title":       "Um pouco sobre mim", "home.about_cta": "Conheça minha trajetória",
	"footer.menu": "Menu", "footer.rights": "Todos os direitos reservados.", "footer.admin": "Área Administrativa",
	"contents.page_title": "Conteúdos", "projects.page_title_all": "Todos os Projetos",
}

func (s *Service) strings(ctx context.Context, lang string) (map[string]string, map[string]string) {
	out := map[string]string{}
	for k, v := range ptDefaults {
		out[k] = v
	}
	if db, err := s.store.SiteUIStrings(ctx, lang); err == nil {
		for k, v := range db {
			if strings.TrimSpace(v) != "" {
				out[k] = v
			}
		}
	}
	cfgI18n := map[string]string{}
	if m, err := s.store.SiteConfigI18n(ctx, lang); err == nil {
		cfgI18n = m
	}
	return out, cfgI18n
}

// RenderHome devolve o HTML da home para o idioma.
func (s *Service) RenderHome(ctx context.Context, lang string) (string, error) {
	cfg, err := s.store.GetConfiguracoes(ctx, siteConfigKeys)
	if err != nil {
		return "", err
	}
	tr, cfgI18n := s.strings(ctx, lang)

	pick := func(key string) string {
		if lang != "pt" {
			if v, ok := cfgI18n[key]; ok && strings.TrimSpace(v) != "" {
				return v
			}
		}
		return cfg[key]
	}
	t := func(key string) string { return tr[key] }

	titulo := cfg["site_titulo"]
	if titulo == "" {
		titulo = "Washiviana"
	}
	subtitulo := pick("site_subtitulo")
	frase := pick("home_frase_impacto")
	bio := pick("mini_bio")

	articles, _ := s.store.SiteLatestArticles(ctx, 6)
	projects, _ := s.store.SiteFeaturedProjects(ctx, 6)

	base := strings.TrimRight(s.cfg.SiteBaseURL, "/")
	homePath := "/site/" + lang + "/"
	canonical := base + homePath

	var b strings.Builder
	b.WriteString("<!doctype html>\n<html lang=\"" + htmlLang(lang) + "\">\n<head>\n")
	b.WriteString("<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n")
	b.WriteString("<title>" + esc(titulo) + (subtituloOf(subtitulo)) + "</title>\n")
	b.WriteString("<meta name=\"description\" content=\"" + esc(frase) + "\">\n")
	b.WriteString("<link rel=\"canonical\" href=\"" + esc(canonical) + "\">\n")
	for _, l := range []string{"pt", "en", "es"} {
		b.WriteString("<link rel=\"alternate\" hreflang=\"" + l + "\" href=\"" + esc(base+"/site/"+l+"/") + "\">\n")
	}
	b.WriteString("<meta property=\"og:type\" content=\"website\">\n")
	b.WriteString("<meta property=\"og:title\" content=\"" + esc(titulo+" - "+subtitulo) + "\">\n")
	b.WriteString("<meta property=\"og:description\" content=\"" + esc(frase) + "\">\n")
	b.WriteString("<meta property=\"og:url\" content=\"" + esc(canonical) + "\">\n")
	b.WriteString("<link rel=\"stylesheet\" href=\"/assets/css/tailwind.min.css\">\n")
	b.WriteString("</head>\n<body class=\"bg-white text-neutral-800 antialiased\">\n")

	// Header / nav
	b.WriteString("<header class=\"max-w-5xl mx-auto flex items-center justify-between px-4 py-5\">\n")
	b.WriteString("<a href=\"/site/" + lang + "/\" class=\"font-bold text-lg\">" + esc(titulo) + "</a>\n")
	b.WriteString("<nav class=\"flex items-center gap-5 text-sm\">")
	b.WriteString(nav(lang, "/conteudos", t("nav.contents")))
	b.WriteString(nav(lang, "/projetos", t("nav.projects")))
	b.WriteString(nav(lang, "/sobre", t("nav.about")))
	b.WriteString("<span class=\"text-neutral-300\">|</span>")
	for _, l := range []string{"pt", "en", "es"} {
		cls := "hover:underline"
		if l == lang {
			cls = "font-semibold underline"
		}
		b.WriteString("<a class=\"" + cls + "\" href=\"/site/" + l + "/\">" + strings.ToUpper(l) + "</a>")
	}
	b.WriteString("</nav></header>\n")

	// Hero
	b.WriteString("<section class=\"max-w-5xl mx-auto px-4 py-14\">\n")
	b.WriteString("<h1 class=\"text-4xl sm:text-5xl font-black tracking-tight\">" + esc(titulo) + "</h1>\n")
	b.WriteString("<p class=\"mt-3 text-xl text-neutral-600\">" + esc(subtitulo) + "</p>\n")
	b.WriteString("<p class=\"mt-6 max-w-3xl text-neutral-700\">" + esc(frase) + "</p>\n")
	b.WriteString("<div class=\"mt-8 flex gap-3\">")
	b.WriteString(btn(lang, "/conteudos", t("hero.cta_contents"), "primary"))
	b.WriteString(btn(lang, "/projetos", t("hero.cta_projects"), ""))
	b.WriteString("</div></section>\n")

	// Cards
	b.WriteString("<section class=\"max-w-5xl mx-auto px-4 py-10\">\n")
	b.WriteString("<h2 class=\"text-2xl font-bold mb-6\">" + esc(t("home.section_find")) + "</h2>\n")
	b.WriteString("<div class=\"grid sm:grid-cols-2 lg:grid-cols-4 gap-4\">\n")
	for i := 1; i <= 4; i++ {
		key := fmt.Sprintf("home_card_%d", i)
		ct := pick(key + "_titulo")
		if ct == "" {
			continue
		}
		cs := pick(key + "_subtexto")
		cl := pick(key + "_link")
		href := cl
		if href == "" {
			href = "/" + lang + "/"
		}
		b.WriteString("<a href=\"" + esc(href) + "\" class=\"block rounded-xl border border-neutral-200 p-5 hover:shadow-sm\">")
		b.WriteString("<div class=\"font-semibold\">" + esc(ct) + "</div>")
		b.WriteString("<p class=\"text-sm text-neutral-600 mt-2\">" + esc(cs) + "</p></a>\n")
	}
	b.WriteString("</div></section>\n")

	// Latest articles
	b.WriteString("<section class=\"max-w-5xl mx-auto px-4 py-10\">\n")
	b.WriteString("<div class=\"flex items-center justify-between mb-6\"><h2 class=\"text-2xl font-bold\">" + esc(t("home.latest")) + "</h2>")
	b.WriteString("<a class=\"text-sm text-emerald-700 hover:underline\" href=\"/" + lang + "/conteudos\">" + esc(t("home.view_all_contents")) + "</a></div>\n")
	if len(articles) == 0 {
		b.WriteString("<p class=\"text-neutral-500\">" + esc(t("home.soon")) + "</p>\n")
	} else {
		b.WriteString("<ul class=\"grid sm:grid-cols-2 gap-5\">\n")
		for _, a := range articles {
			slug := asStr(a["slug"])
			b.WriteString("<li><a class=\"block hover:underline\" href=\"/" + lang + "/artigo/" + esc(slug) + "\">")
			b.WriteString("<span class=\"text-lg font-semibold\">" + esc(asStr(a["titulo"])) + "</span>")
			if r := asStr(a["resumo"]); r != "" {
				b.WriteString("<p class=\"text-sm text-neutral-600 mt-1\">" + esc(truncate(r, 160)) + "</p>")
			}
			b.WriteString("</a></li>\n")
		}
		b.WriteString("</ul>\n")
	}
	b.WriteString("</section>\n")

	// Projects
	b.WriteString("<section class=\"max-w-5xl mx-auto px-4 py-10\">\n")
	b.WriteString("<h2 class=\"text-2xl font-bold mb-6\">" + esc(t("projects.page_title_all")) + "</h2>\n")
	if len(projects) > 0 {
		b.WriteString("<ul class=\"grid sm:grid-cols-2 lg:grid-cols-3 gap-5\">\n")
		for _, p := range projects {
			slug := asStr(p["slug"])
			b.WriteString("<li class=\"rounded-xl border border-neutral-200 p-5\">")
			b.WriteString("<a class=\"font-semibold hover:underline\" href=\"/" + lang + "/projeto/" + esc(slug) + "\">" + esc(asStr(p["titulo"])) + "</a>")
			if d := asStr(p["descricao"]); d != "" {
				b.WriteString("<p class=\"text-sm text-neutral-600 mt-2\">" + esc(truncate(d, 140)) + "</p>")
			}
			b.WriteString("</li>\n")
		}
		b.WriteString("</ul>\n")
	}
	b.WriteString("</section>\n")

	// About
	if strings.TrimSpace(bio) != "" {
		b.WriteString("<section class=\"max-w-5xl mx-auto px-4 py-10\">\n")
		b.WriteString("<h2 class=\"text-2xl font-bold mb-4\">" + esc(t("home.about_title")) + "</h2>\n")
		b.WriteString("<div class=\"prose max-w-3xl text-neutral-700\">" + bio + "</div>\n")
		b.WriteString("<a class=\"inline-block mt-4 text-emerald-700 hover:underline\" href=\"/" + lang + "/sobre\">" + esc(t("home.about_cta")) + "</a>\n")
		b.WriteString("</section>\n")
	}

	// Footer
	b.WriteString("<footer class=\"border-t border-neutral-200 mt-10\">\n<div class=\"max-w-5xl mx-auto px-4 py-8 text-sm text-neutral-600 flex flex-wrap items-center gap-4 justify-between\">\n")
	b.WriteString("<span>© " + esc(titulo) + " — " + esc(t("footer.rights")) + "</span>\n")
	var social []string
	if v := cfg["site_linkedin"]; v != "" {
		social = append(social, "<a class=\"hover:underline\" href=\""+esc(v)+"\">LinkedIn</a>")
	}
	if v := cfg["site_instagram"]; v != "" {
		social = append(social, "<a class=\"hover:underline\" href=\""+esc(v)+"\">Instagram</a>")
	}
	if v := cfg["site_github"]; v != "" {
		social = append(social, "<a class=\"hover:underline\" href=\""+esc(v)+"\">GitHub</a>")
	}
	if v := cfg["site_email"]; v != "" {
		social = append(social, "<a class=\"hover:underline\" href=\"mailto:"+esc(v)+"\">E-mail</a>")
	}
	b.WriteString("<span class=\"flex gap-4\">" + strings.Join(social, "") + "</span>\n")
	b.WriteString("</div></footer>\n")

	b.WriteString("</body></html>\n")
	return b.String(), nil
}

func nav(lang, path, label string) string {
	return "<a class=\"hover:underline\" href=\"/" + lang + path + "\">" + html.EscapeString(label) + "</a>"
}

func btn(lang, path, label, kind string) string {
	cls := "inline-block rounded-lg border border-neutral-300 px-5 py-2.5 text-sm hover:bg-neutral-50"
	if kind == "primary" {
		cls = "inline-block rounded-lg bg-emerald-700 text-white px-5 py-2.5 text-sm hover:bg-emerald-800"
	}
	return "<a class=\"" + cls + "\" href=\"/" + lang + path + "\">" + html.EscapeString(label) + "</a>"
}

func esc(s string) string { return html.EscapeString(s) }

func htmlLang(lang string) string {
	switch lang {
	case "pt":
		return "pt-BR"
	case "en":
		return "en"
	case "es":
		return "es"
	}
	return "pt-BR"
}

func subtituloOf(sub string) string {
	if strings.TrimSpace(sub) == "" {
		return ""
	}
	return " — " + esc(sub)
}

func asStr(v any) string {
	if s, ok := v.(string); ok {
		return s
	}
	return ""
}

func truncate(s string, max int) string {
	r := []rune(s)
	if len(r) <= max {
		return s
	}
	return string(r[:max]) + "…"
}

// ErrNotFound indica página/recurso inexistente.
var ErrNotFound = fmt.Errorf("não encontrado")

// pageCtx reúne dados comuns de configuração/i18n.
type pageCtx struct {
	cfg       map[string]string
	tr        map[string]string
	cfgI18n   map[string]string
	titulo    string
	subtitulo string
	base      string
}

func (s *Service) pageCtx(ctx context.Context, lang string) (*pageCtx, error) {
	cfg, err := s.store.GetConfiguracoes(ctx, siteConfigKeys)
	if err != nil {
		return nil, err
	}
	tr, cfgI18n := s.strings(ctx, lang)
	titulo := cfg["site_titulo"]
	if titulo == "" {
		titulo = "Washiviana"
	}
	subt := cfg["site_subtitulo"]
	if lang != "pt" {
		if v := strings.TrimSpace(cfgI18n["site_subtitulo"]); v != "" {
			subt = v
		}
	}
	return &pageCtx{cfg: cfg, tr: tr, cfgI18n: cfgI18n, titulo: titulo, subtitulo: subt, base: strings.TrimRight(s.cfg.SiteBaseURL, "/")}, nil
}

func (p *pageCtx) t(key string) string { return p.tr[key] }

func (p *pageCtx) pick(key string) string {
	if v := strings.TrimSpace(p.cfgI18n[key]); v != "" {
		return v
	}
	return p.cfg[key]
}

// shell monta a página completa (head + header + inner + footer).
func (s *Service) shell(lang string, p *pageCtx, title, description, canonicalPath, inner string) string {
	canonical := p.base + canonicalPath
	var b strings.Builder
	b.WriteString("<!doctype html>\n<html lang=\"" + htmlLang(lang) + "\">\n<head>\n")
	b.WriteString("<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n")
	b.WriteString("<title>" + esc(title) + "</title>\n")
	if description != "" {
		b.WriteString("<meta name=\"description\" content=\"" + esc(description) + "\">\n")
	}
	b.WriteString("<link rel=\"canonical\" href=\"" + esc(canonical) + "\">\n")
	b.WriteString("<meta property=\"og:type\" content=\"website\">\n")
	b.WriteString("<meta property=\"og:title\" content=\"" + esc(title) + "\">\n")
	b.WriteString("<meta property=\"og:description\" content=\"" + esc(description) + "\">\n")
	b.WriteString("<meta property=\"og:url\" content=\"" + esc(canonical) + "\">\n")
	b.WriteString("<link rel=\"stylesheet\" href=\"/assets/css/tailwind.min.css\">\n</head>\n")

	b.WriteString("<body class=\"bg-white text-neutral-800 antialiased\">\n")
	b.WriteString("<header class=\"max-w-5xl mx-auto flex items-center justify-between px-4 py-5\">\n")
	b.WriteString("<a href=\"/site/" + lang + "/\" class=\"font-bold text-lg\">" + esc(p.titulo) + "</a>\n")
	b.WriteString("<nav class=\"flex items-center gap-5 text-sm\">")
	b.WriteString(nav(lang, "/conteudos", p.t("nav.contents")))
	b.WriteString(nav(lang, "/projetos", p.t("nav.projects")))
	b.WriteString(nav(lang, "/sobre", p.t("nav.about")))
	b.WriteString("</nav></header>\n")
	b.WriteString("<main class=\"max-w-5xl mx-auto px-4 py-10\">\n" + inner + "</main>\n")
	b.WriteString("<footer class=\"border-t border-neutral-200 mt-10\">\n<div class=\"max-w-5xl mx-auto px-4 py-8 text-sm text-neutral-600\">")
	b.WriteString("<a class=\"hover:underline\" href=\"/site/" + lang + "/\">" + esc(p.titulo) + "</a> — " + esc(p.t("footer.rights")))
	b.WriteString("</div></footer>\n</body></html>\n")
	return b.String()
}

// RenderConteudos lista os conteúdos publicados.
func (s *Service) RenderConteudos(ctx context.Context, lang string) (string, error) {
	p, err := s.pageCtx(ctx, lang)
	if err != nil {
		return "", err
	}
	articles, err := s.store.SiteLatestArticles(ctx, 50)
	if err != nil {
		return "", err
	}
	var b strings.Builder
	b.WriteString("<h1 class=\"text-3xl font-black mb-8\">" + esc(p.t("contents.page_title")) + "</h1>\n")
	if len(articles) == 0 {
		b.WriteString("<p class=\"text-neutral-500\">" + esc(p.t("home.soon")) + "</p>")
	} else {
		b.WriteString("<ul class=\"grid sm:grid-cols-2 gap-6\">\n")
		for _, a := range articles {
			b.WriteString("<li><a class=\"block hover:underline\" href=\"/" + lang + "/artigo/" + esc(asStr(a["slug"])) + "\">")
			b.WriteString("<span class=\"text-lg font-semibold\">" + esc(asStr(a["titulo"])) + "</span>")
			if r := asStr(a["resumo"]); r != "" {
				b.WriteString("<p class=\"text-sm text-neutral-600 mt-1\">" + esc(truncate(r, 180)) + "</p>")
			}
			b.WriteString("</a></li>\n")
		}
		b.WriteString("</ul>\n")
	}
	return s.shell(lang, p, p.titulo+" — "+p.t("contents.page_title"), p.pick("home_frase_impacto"), "/site/"+lang+"/conteudos", b.String()), nil
}

// RenderProjetos lista os projetos ativos.
func (s *Service) RenderProjetos(ctx context.Context, lang string) (string, error) {
	p, err := s.pageCtx(ctx, lang)
	if err != nil {
		return "", err
	}
	projects, err := s.store.SiteFeaturedProjects(ctx, 50)
	if err != nil {
		return "", err
	}
	var b strings.Builder
	b.WriteString("<h1 class=\"text-3xl font-black mb-8\">" + esc(p.t("projects.page_title_all")) + "</h1>\n")
	b.WriteString("<ul class=\"grid sm:grid-cols-2 lg:grid-cols-3 gap-6\">\n")
	for _, pr := range projects {
		b.WriteString("<li class=\"rounded-xl border border-neutral-200 p-5\">")
		b.WriteString("<a class=\"font-semibold hover:underline\" href=\"/" + lang + "/projeto/" + esc(asStr(pr["slug"])) + "\">" + esc(asStr(pr["titulo"])) + "</a>")
		if d := asStr(pr["descricao"]); d != "" {
			b.WriteString("<p class=\"text-sm text-neutral-600 mt-2\">" + esc(truncate(d, 160)) + "</p>")
		}
		b.WriteString("</li>\n")
	}
	b.WriteString("</ul>\n")
	return s.shell(lang, p, p.titulo+" — "+p.t("projects.page_title_all"), p.pick("home_frase_impacto"), "/site/"+lang+"/projetos", b.String()), nil
}

// RenderArtigo renderiza o detalhe do artigo por slug.
func (s *Service) RenderArtigo(ctx context.Context, lang, slug string) (string, error) {
	p, err := s.pageCtx(ctx, lang)
	if err != nil {
		return "", err
	}
	a, err := s.store.SiteArticleBySlug(ctx, slug)
	if err != nil {
		return "", err
	}
	if a == nil {
		return "", ErrNotFound
	}
	titulo := asStr(a["titulo"])
	resumo := asStr(a["resumo"])
	conteudo := asStr(a["conteudo"])
	metaDesc := resumo
	if lang != "pt" {
		if id := asIntAnyLocal(a["id"]); id > 0 {
			if row, _ := s.store.ArtigoI18nRow(ctx, id, lang); row != nil {
				if v := asStr(row["titulo"]); v != "" {
					titulo = v
				}
				if v := asStr(row["resumo"]); v != "" {
					resumo = v
					metaDesc = v
				}
				if v := asStr(row["conteudo"]); v != "" {
					conteudo = v
				}
				if v := asStr(row["meta_description"]); v != "" {
					metaDesc = v
				}
			}
		}
	}
	var b strings.Builder
	b.WriteString("<a class=\"text-sm text-emerald-700 hover:underline\" href=\"/" + lang + "/conteudos\">← " + esc(p.t("nav.contents")) + "</a>\n")
	b.WriteString("<article class=\"mt-4 max-w-3xl\"><h1 class=\"text-3xl font-black mb-4\">" + esc(titulo) + "</h1>\n")
	b.WriteString("<div class=\"prose max-w-none text-neutral-800\">" + conteudo + "</div></article>\n")
	return s.shell(lang, p, titulo+" — "+p.titulo, metaDesc, "/site/"+lang+"/artigo/"+slug, b.String()), nil
}

// RenderProjeto renderiza o detalhe do projeto por slug.
func (s *Service) RenderProjeto(ctx context.Context, lang, slug string) (string, error) {
	p, err := s.pageCtx(ctx, lang)
	if err != nil {
		return "", err
	}
	pr, err := s.store.SiteProjectBySlug(ctx, slug)
	if err != nil {
		return "", err
	}
	if pr == nil {
		return "", ErrNotFound
	}
	titulo := asStr(pr["titulo"])
	desc := asStr(pr["descricao"])
	if lang != "pt" {
		if id := asIntAnyLocal(pr["id"]); id > 0 {
			if row, _ := s.store.ProjetoI18nRow(ctx, id, lang); row != nil {
				if v := asStr(row["titulo"]); v != "" {
					titulo = v
				}
				if v := asStr(row["descricao"]); v != "" {
					desc = v
				}
			}
		}
	}
	var b strings.Builder
	b.WriteString("<a class=\"text-sm text-emerald-700 hover:underline\" href=\"/" + lang + "/projetos\">← " + esc(p.t("nav.projects")) + "</a>\n")
	b.WriteString("<h1 class=\"text-3xl font-black mt-4 mb-4\">" + esc(titulo) + "</h1>\n")
	b.WriteString("<div class=\"max-w-3xl text-neutral-700 whitespace-pre-line\">" + esc(desc) + "</div>\n")
	return s.shell(lang, p, titulo+" — "+p.titulo, desc, "/site/"+lang+"/projeto/"+slug, b.String()), nil
}

// RenderSobre renderiza a página Sobre.
func (s *Service) RenderSobre(ctx context.Context, lang string) (string, error) {
	p, err := s.pageCtx(ctx, lang)
	if err != nil {
		return "", err
	}
	bio := p.pick("mini_bio")
	var b strings.Builder
	b.WriteString("<h1 class=\"text-3xl font-black mb-6\">" + esc(p.t("nav.about")) + "</h1>\n")
	b.WriteString("<div class=\"prose max-w-3xl text-neutral-800\">" + bio + "</div>\n")
	return s.shell(lang, p, p.titulo+" — "+p.t("nav.about"), p.pick("home_frase_impacto"), "/site/"+lang+"/sobre", b.String()), nil
}

// RenderSitemap gera o sitemap.xml com URLs finais (/{lang}/...) e hreflang.
func (s *Service) RenderSitemap(ctx context.Context) (string, error) {
	base := strings.TrimRight(s.cfg.SiteBaseURL, "/")
	langs := []string{"pt", "en", "es"}
	hl := func(l string) string {
		if l == "pt" {
			return "pt-BR"
		}
		return l
	}
	static := []struct{ path, prio, freq string }{
		{"/", "1.0", "weekly"},
		{"/conteudos", "0.8", "daily"},
		{"/projetos", "0.8", "weekly"},
		{"/automacao-ia", "0.7", "weekly"},
		{"/tech-insights", "0.7", "weekly"},
		{"/design-experiencias", "0.7", "weekly"},
		{"/sobre", "0.6", "monthly"},
	}

	var b strings.Builder
	b.WriteString("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n")
	b.WriteString("<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n")

	for _, it := range static {
		for _, lang := range langs {
			b.WriteString("  <url>\n    <loc>" + esc(base+langPath(it.path, lang)) + "</loc>\n")
			for _, alt := range langs {
				b.WriteString("    <xhtml:link rel=\"alternate\" hreflang=\"" + hl(alt) + "\" href=\"" + esc(base+langPath(it.path, alt)) + "\" />\n")
			}
			b.WriteString("    <changefreq>" + it.freq + "</changefreq>\n    <priority>" + it.prio + "</priority>\n  </url>\n")
		}
	}

	articles, _ := s.store.SitemapArticles(ctx)
	aSlugs := s.slugMap(ctx, true)
	for _, a := range articles {
		id := asIntAnyLocal(a["id"])
		slugs := aSlugs[id]
		if slugs == nil {
			slugs = map[string]string{}
		}
		slugs["pt"] = asStr(a["slug"])
		lastmod := isoDate(asStr(a["updated_at"]), asStr(a["created_at"]))
		for _, lang := range langs {
			if lang != "pt" && slugs[lang] == "" {
				continue
			}
			b.WriteString("  <url>\n    <loc>" + esc(base+artPath(slugOr(slugs, lang), lang)) + "</loc>\n")
			for _, alt := range langs {
				if alt != "pt" && slugs[alt] == "" {
					continue
				}
				b.WriteString("    <xhtml:link rel=\"alternate\" hreflang=\"" + hl(alt) + "\" href=\"" + esc(base+artPath(slugOr(slugs, alt), alt)) + "\" />\n")
			}
			if lastmod != "" {
				b.WriteString("    <lastmod>" + lastmod + "</lastmod>\n")
			}
			b.WriteString("    <changefreq>monthly</changefreq>\n    <priority>0.7</priority>\n  </url>\n")
		}
	}

	projects, _ := s.store.SitemapProjects(ctx)
	pSlugs := s.slugMap(ctx, false)
	for _, pr := range projects {
		id := asIntAnyLocal(pr["id"])
		slugs := pSlugs[id]
		if slugs == nil {
			slugs = map[string]string{}
		}
		slugs["pt"] = asStr(pr["slug"])
		lastmod := isoDate(asStr(pr["updated_at"]), asStr(pr["created_at"]))
		for _, lang := range langs {
			if lang != "pt" && slugs[lang] == "" {
				continue
			}
			b.WriteString("  <url>\n    <loc>" + esc(base+projPath(slugOr(slugs, lang), lang)) + "</loc>\n")
			for _, alt := range langs {
				if alt != "pt" && slugs[alt] == "" {
					continue
				}
				b.WriteString("    <xhtml:link rel=\"alternate\" hreflang=\"" + hl(alt) + "\" href=\"" + esc(base+projPath(slugOr(slugs, alt), alt)) + "\" />\n")
			}
			if lastmod != "" {
				b.WriteString("    <lastmod>" + lastmod + "</lastmod>\n")
			}
			b.WriteString("    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n")
		}
	}
	b.WriteString("</urlset>\n")
	return b.String(), nil
}

func (s *Service) slugMap(ctx context.Context, artigo bool) map[int]map[string]string {
	out := map[int]map[string]string{}
	var rows []map[string]any
	var err error
	var idKey string
	if artigo {
		rows, err = s.store.SitemapArticleSlugs(ctx)
		idKey = "artigo_id"
	} else {
		rows, err = s.store.SitemapProjectSlugs(ctx)
		idKey = "projeto_id"
	}
	if err != nil {
		return out
	}
	for _, r := range rows {
		id := asIntAnyLocal(r[idKey])
		lang := asStr(r["lang"])
		slug := asStr(r["slug"])
		if id <= 0 || lang == "" || slug == "" {
			continue
		}
		if out[id] == nil {
			out[id] = map[string]string{}
		}
		out[id][lang] = slug
	}
	return out
}

func slugOr(m map[string]string, lang string) string {
	if v := m[lang]; v != "" {
		return v
	}
	return m["pt"]
}

func langPath(path, lang string) string {
	if path == "/" {
		return "/" + lang + "/"
	}
	return "/" + lang + path
}

func artPath(slug, lang string) string  { return "/" + lang + "/artigo/" + slug }
func projPath(slug, lang string) string { return "/" + lang + "/projeto/" + slug }

func isoDate(updated, created string) string {
	v := updated
	if v == "" {
		v = created
	}
	for _, layout := range []string{"2006-01-02 15:04:05.999999", "2006-01-02 15:04:05", "2006-01-02"} {
		if t, err := time.ParseInLocation(layout, v, time.Local); err == nil {
			return t.UTC().Format("2006-01-02T15:04:05Z")
		}
	}
	return ""
}

func asIntAnyLocal(v any) int {
	switch n := v.(type) {
	case int:
		return n
	case int32:
		return int(n)
	case int64:
		return int(n)
	case float64:
		return int(n)
	}
	return 0
}

// NormalizeLang valida o idioma (default pt).
func NormalizeLang(l string) string {
	switch strings.ToLower(strings.TrimSpace(l)) {
	case "en":
		return "en"
	case "es":
		return "es"
	default:
		return "pt"
	}
}
