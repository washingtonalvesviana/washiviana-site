// Package i18n porta a geração de traduções/SEO (api/i18n_seo.php).
package i18n

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	"washiviana/backend/internal/ai"
	"washiviana/backend/internal/store"
	"washiviana/backend/internal/textutil"
)

// Service gera traduções/SEO para artigos e projetos.
type Service struct {
	store *store.Store
	ai    *ai.Service
}

// New cria o serviço.
func New(st *store.Store, aiSvc *ai.Service) *Service {
	return &Service{store: st, ai: aiSvc}
}

func truncate(s string, max int) string {
	if len([]rune(s)) > max {
		return string([]rune(s)[:max]) + "\n\n[TRUNCADO]"
	}
	return s
}

func str(v *string) string {
	if v == nil {
		return ""
	}
	return *v
}

// GenerateArtigo gera e grava artigos_i18n para os idiomas pedidos.
func (s *Service) GenerateArtigo(ctx context.Context, id int, langs []string) ([]string, error) {
	a, err := s.store.GetArtigoI18nSource(ctx, id)
	if err != nil {
		return nil, err
	}
	promptLangs := strings.Join(langs, ", ")
	resumo := truncate(str(a.Resumo), 3000)
	conteudo := truncate(str(a.Conteudo), 12000)

	var b strings.Builder
	b.WriteString("Você é especialista em SEO e tradução.\n")
	b.WriteString("Responda APENAS com JSON válido (sem markdown, sem texto extra).\n")
	b.WriteString("Não inclua pensamentos/raciocínio: retorne diretamente o JSON.\n\n")
	b.WriteString("A partir do conteúdo em PT-BR abaixo, gere versões em " + promptLangs + ".\n\n")
	b.WriteString("Regras:\n")
	b.WriteString("- Para Português (pt), OTIMIZE os metadados (como meta_title e meta_description) focando em SEO, mas mantenha o texto do conteúdo original.\n")
	b.WriteString("- Para os outros idiomas (en, es), TRADUZA mantendo o sentido e tom profissional.\n")
	b.WriteString("- Gere slug em cada idioma (minúsculo, hífen, sem acentos).\n")
	b.WriteString("- Gere meta_title (<= 60 chars) e meta_description (<= 160 chars) por idioma.\n")
	b.WriteString("- Gere og_title e og_description coerentes.\n")
	b.WriteString("- Gere keywords como lista (5 a 12 itens).\n")
	b.WriteString("- Retorne este JSON:\n")
	b.WriteString(jsonExample(langs, true) + "\n\n")
	b.WriteString("PT-BR:\ntitulo: " + a.Titulo + "\nresumo: " + resumo + "\nconteudo: " + conteudo)

	out, err := s.ai.GenerateJSON(ctx, b.String(), 8192)
	if err != nil {
		return nil, err
	}

	var saved []string
	for _, lang := range langs {
		t, ok := out[lang].(map[string]any)
		if !ok {
			continue
		}
		titulo := getStr(t, "titulo")
		resumoOut := getStr(t, "resumo")
		conteudoOut := getStr(t, "conteudo")
		slug := getStr(t, "slug")
		if slug == "" {
			base := titulo
			if base == "" {
				base = a.Titulo + " " + lang
			}
			slug = textutil.SlugifyI18n(base)
		}
		slug, err = s.uniqueSlug(ctx, "artigos_i18n", "artigo_id", id, lang, slug)
		if err != nil {
			return saved, err
		}
		kw := keywordsString(t["keywords"])
		if err := s.store.UpsertArtigoI18n(ctx, id, lang, titulo, slug, resumoOut, conteudoOut,
			getStr(t, "meta_title"), getStr(t, "meta_description"), getStr(t, "og_title"), getStr(t, "og_description"), kw, "go"); err != nil {
			return saved, err
		}
		saved = append(saved, lang)
	}
	return saved, nil
}

// GenerateProjeto gera e grava projetos_i18n para os idiomas pedidos.
func (s *Service) GenerateProjeto(ctx context.Context, id int, langs []string) ([]string, error) {
	p, err := s.store.GetProjetoI18nSource(ctx, id)
	if err != nil {
		return nil, err
	}
	promptLangs := strings.Join(langs, ", ")
	descricao := truncate(str(p.Descricao), 8000)

	var b strings.Builder
	b.WriteString("Você é especialista em SEO e tradução.\n")
	b.WriteString("Responda APENAS com JSON válido (sem markdown, sem texto extra).\n")
	b.WriteString("Não inclua pensamentos/raciocínio: retorne diretamente o JSON.\n\n")
	b.WriteString("A partir do conteúdo do PROJETO em PT-BR abaixo, gere versões em " + promptLangs + ".\n\n")
	b.WriteString("Regras:\n")
	b.WriteString("- Para Português (pt), OTIMIZE os metadados (como meta_title e meta_description) focando em SEO, mas mantenha o texto da descrição original.\n")
	b.WriteString("- Para os outros idiomas (en, es), TRADUZA mantendo o sentido e tom profissional.\n")
	b.WriteString("- Gere slug em cada idioma (minúsculo, hífen, sem acentos).\n")
	b.WriteString("- Gere meta_title (<= 60 chars) e meta_description (<= 160 chars) por idioma.\n")
	b.WriteString("- Gere og_title e og_description coerentes.\n")
	b.WriteString("- Gere keywords como lista (5 a 12 itens).\n")
	b.WriteString("- Retorne este JSON:\n")
	b.WriteString(jsonExample(langs, false) + "\n\n")
	b.WriteString("PT-BR:\ntitulo: " + p.Titulo + "\ndescricao: " + descricao + "\ntecnologias: " + str(p.Tecnologias) + "\nurl: " + str(p.URLProjeto))

	out, err := s.ai.GenerateJSON(ctx, b.String(), 8192)
	if err != nil {
		return nil, err
	}

	var saved []string
	for _, lang := range langs {
		t, ok := out[lang].(map[string]any)
		if !ok {
			continue
		}
		titulo := getStr(t, "titulo")
		descricaoOut := getStr(t, "descricao")
		slug := getStr(t, "slug")
		if slug == "" {
			base := titulo
			if base == "" {
				base = p.Titulo + " " + lang
			}
			slug = textutil.SlugifyI18n(base)
		}
		slug, err = s.uniqueSlug(ctx, "projetos_i18n", "projeto_id", id, lang, slug)
		if err != nil {
			return saved, err
		}
		kw := keywordsString(t["keywords"])
		if err := s.store.UpsertProjetoI18n(ctx, id, lang, titulo, slug, descricaoOut,
			getStr(t, "meta_title"), getStr(t, "meta_description"), getStr(t, "og_title"), getStr(t, "og_description"), kw, "go"); err != nil {
			return saved, err
		}
		saved = append(saved, lang)
	}
	return saved, nil
}

func (s *Service) uniqueSlug(ctx context.Context, table, idCol string, id int, lang, slug string) (string, error) {
	slug = textutil.SlugifyI18n(slug)
	base := slug
	for n := 0; ; {
		exists, err := s.store.I18nSlugExists(ctx, table, idCol, id, lang, slug)
		if err != nil {
			return "", err
		}
		if !exists {
			return slug, nil
		}
		n++
		slug = fmt.Sprintf("%s-%d", base, id)
		if n > 1 {
			slug = fmt.Sprintf("%s-%d", slug, n)
		}
	}
}

func jsonExample(langs []string, artigo bool) string {
	obj := map[string]map[string]any{}
	for _, l := range langs {
		entry := map[string]any{
			"titulo": "", "slug": "", "meta_title": "", "meta_description": "",
			"og_title": "", "og_description": "", "keywords": []string{"..."},
		}
		if artigo {
			entry["resumo"] = ""
			entry["conteudo"] = ""
		} else {
			entry["descricao"] = ""
		}
		obj[l] = entry
	}
	b, _ := json.MarshalIndent(obj, "", "    ")
	return string(b)
}

func getStr(m map[string]any, key string) string {
	if v, ok := m[key].(string); ok {
		return strings.TrimSpace(v)
	}
	return ""
}

func keywordsString(v any) *string {
	arr, ok := v.([]any)
	if !ok {
		return nil
	}
	parts := make([]string, 0, len(arr))
	for _, item := range arr {
		if s, ok := item.(string); ok {
			parts = append(parts, strings.TrimSpace(s))
		}
	}
	if len(parts) > 20 {
		parts = parts[:20]
	}
	out := strings.Join(parts, ", ")
	return &out
}
