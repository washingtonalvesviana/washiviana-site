// Package i18nsite porta a geração de i18n do site (api/i18n_site.php) — UI strings + configs.
package i18nsite

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"

	"washiviana/backend/internal/ai"
	"washiviana/backend/internal/store"
)

// Service gera traduções de UI/config do site.
type Service struct {
	store *store.Store
	ai    *ai.Service
}

// New cria o serviço.
func New(st *store.Store, aiSvc *ai.Service) *Service {
	return &Service{store: st, ai: aiSvc}
}

type uiPair struct{ Key, Text string }

// Fonte PT-BR das strings de UI (mesmo conjunto do PHP).
var uiPairs = []uiPair{
	{"nav.home", "Início"},
	{"nav.contents", "Conteúdos"},
	{"nav.projects", "Projetos"},
	{"nav.about", "Sobre"},
	{"nav.contact", "Contato"},
	{"hero.cta_contents", "Explorar Conteúdos"},
	{"hero.cta_projects", "Ver Projetos"},
	{"home.section_find", "O que você vai encontrar aqui"},
	{"home.latest", "Últimos conteúdos"},
	{"home.soon", "Conteúdos em breve..."},
	{"home.view_all_contents", "Ver todos os conteúdos →"},
	{"home.about_title", "Um pouco sobre mim"},
	{"home.about_cta", "Conheça minha trajetória"},
	{"home.card_1.title", "Automação & IA"},
	{"home.card_1.subtext", "Aplicações reais usando tecnologia para otimizar processos."},
	{"home.card_2.title", "Tech Insights"},
	{"home.card_2.subtext", "Notícias comentadas e análises sobre tecnologia."},
	{"home.card_3.title", "Projetos"},
	{"home.card_3.subtext", "Projetos que realizei e participei ao longo da carreira."},
	{"home.card_4.title", "Design & Experiências Digitais"},
	{"home.card_4.subtext", "VR, 3D e design aplicado em soluções inovadoras."},
	{"contents.kicker", "News & Artigos"},
	{"contents.subtitle", "Insights sobre tecnologia, IA e automação"},
	{"contents.page_title", "Conteúdos"},
	{"search.title", "Busca: {term}"},
	{"filters.all", "Todos"},
	{"footer.menu", "Menu"},
	{"footer.rights", "Todos os direitos reservados."},
	{"footer.admin", "Área Administrativa"},
	{"article.all_contents", "Todos os Conteúdos"},
	{"projects.all", "Todos os Projetos"},
	{"projects.page_title_all", "Todos os Projetos"},
	{"projects.count.one", "{count} projeto encontrado"},
	{"projects.count.other", "{count} projetos encontrados"},
	{"projects.empty_category", "Nenhum projeto encontrado nesta categoria."},
	{"projects.view_all", "Ver Todos"},
	{"project.visit", "Visitar Projeto"},
	{"project.prev", "Projeto Anterior"},
	{"project.next", "Próximo Projeto"},
	{"actions.read", "Ler"},
	{"actions.view", "Ver"},
	{"gallery.title", "Galeria"},
	{"gallery.image_alt", "Galeria"},
	{"gallery.backdrop_close", "Fechar galeria"},
	{"gallery.open", "Abrir"},
	{"gallery.close", "Fechar"},
	{"gallery.close_aria", "Fechar (Esc)"},
	{"gallery.prev", "Imagem anterior (←)"},
	{"gallery.next", "Próxima imagem (→)"},
	{"gallery.image_of", "Imagem {current} de {total}"},
	{"page.about.title", "Sobre"},
	{"page.about.meta_desc", "Conheça mais sobre {name} - {subtitle}"},
	{"about.photo_alt", "Foto de {name}"},
	{"about.kicker", "Sobre Mim"},
	{"about.p1", "Profissional criativo baseado em <strong class=\"text-neutral-800\">Indaiatuba, SP - Brasil</strong>. Minha jornada tem sido moldada por experiências práticas do mundo real, indo além dos limites da teoria acadêmica."},
	{"about.p2", "Com fervor por <strong class=\"text-neutral-800\">criatividade e tecnologia</strong>, explorei os domínios do design, desenvolvimento e inteligência artificial, guiado pela crença de que a verdadeira expertise é aprimorada através da prática e inovação."},
	{"about.expertise.title", "Áreas de Expertise"},
	{"about.expertise.ai.title", "Inteligência Artificial"},
	{"about.expertise.ai.desc", "Integração de IA em projetos digitais."},
	{"about.expertise.automation.title", "Automação"},
	{"about.expertise.automation.desc", "Automação de processos empresariais com foco em produtividade."},
	{"about.expertise.dev.title", "Desenvolvimento"},
	{"about.expertise.dev.desc", "Desenvolvimento completo de websites e aplicativos."},
	{"about.expertise.design3d.title", "Design & 3D"},
	{"about.expertise.design3d.desc", "Design gráfico, motion design e modelagem 3D."},
	{"about.expertise.uxui.title", "UX/UI Design"},
	{"about.expertise.uxui.desc", "Design de experiência e interface focado no usuário."},
	{"about.expertise.cloud.title", "Cloud & DevOps"},
	{"about.expertise.cloud.desc", "Arquitetura cloud e práticas DevOps."},
	{"about.philosophy.title", "Minha Filosofia"},
	{"about.philosophy.p1", "<strong class=\"text-neutral-800\">Minha educação tem sido o próprio mercado,</strong> onde adaptabilidade, resolução de problemas e busca incansável pela excelência têm sido meus princípios orientadores."},
	{"about.philosophy.p2", "<strong class=\"text-neutral-800\">O que realmente me diferencia</strong> é minha capacidade de aplicar esse conhecimento diverso de maneira prática e eficaz."},
	{"about.philosophy.quote", "Seja revitalizando sua presença digital, implementando soluções de IA de ponta ou revolucionando seus processos com automação, estou aqui para trazer excelência prática para cada empreendimento."},
	{"about.cta.title", "Vamos Trabalhar Juntos?"},
	{"about.cta.desc", "Estou sempre aberto a novos projetos e oportunidades de colaboração."},
	{"about.cta.email", "Enviar Email"},
	{"about.cta.whatsapp", "WhatsApp"},
	{"landing.setup_required_title", "Configuração Necessária"},
	{"landing.setup_required_desc", "A tabela de artigos ainda não foi criada/configurada."},
	{"landing.soon_title", "Conteúdos em breve"},
	{"landing.view_all_contents", "Ver Todos os Conteúdos"},
	{"landing.view_all_projects", "Ver Todos os Projetos"},
	{"landing.automation.title", "Automação & IA"},
	{"landing.automation.desc", "Aplicações reais de IA e automação para produtividade, processos e experiências digitais."},
	{"landing.automation.kicker", "Automação & IA"},
	{"landing.automation.soon_desc", "Novos conteúdos de Automação & IA serão publicados em breve."},
	{"landing.tech.title", "Tech Insights"},
	{"landing.tech.desc", "Notícias comentadas, análises e tendências de tecnologia — com opinião e contexto."},
	{"landing.tech.kicker", "Tech Insights"},
	{"landing.tech.soon_desc", "Novos Tech Insights serão publicados em breve."},
	{"landing.design.title", "Design & Experiências Digitais"},
	{"landing.design.desc", "VR, 3D, UI/UX e experiências interativas — projetos com foco em estética, usabilidade e impacto."},
	{"landing.design.kicker", "Design"},
	{"landing.design.empty_title", "Nenhum projeto encontrado"},
	{"landing.design.empty_desc", "Em breve mais projetos de design e experiências digitais por aqui."},
}

var configKeys = []string{
	"site_subtitulo", "home_frase_impacto", "mini_bio",
	"home_card_1_titulo", "home_card_1_subtexto",
	"home_card_2_titulo", "home_card_2_subtexto",
	"home_card_3_titulo", "home_card_3_subtexto",
	"home_card_4_titulo", "home_card_4_subtexto",
}

// Result agrega o que foi salvo.
type Result struct {
	SavedUI         map[string][]string         `json:"saved_ui"`
	SavedConfig     map[string][]string         `json:"saved_config"`
	SavedCategories map[string]map[string][]int `json:"saved_categories"`
}

// Generate traduz UI/config para os idiomas pedidos.
func (s *Service) Generate(ctx context.Context, langs []string) (*Result, error) {
	cfgVals, err := s.store.GetConfiguracoes(ctx, configKeys)
	if err != nil {
		return nil, err
	}

	res := &Result{
		SavedUI:         map[string][]string{},
		SavedConfig:     map[string][]string{},
		SavedCategories: map[string]map[string][]int{},
	}
	cfgJSON, _ := json.Marshal(cfgVals)

	catProj, _ := s.store.CategoriasAtivasProjetos(ctx)
	catArt, _ := s.store.CategoriasAtivasArtigos(ctx)
	catPayload := map[string]any{
		"categorias_projetos": catProj,
		"categorias_artigos":  catArt,
	}
	catJSON, _ := json.Marshal(catPayload)

	for _, lang := range langs {
		// UI em chunks de 24
		merged := map[string]string{}
		for start := 0; start < len(uiPairs); start += 24 {
			end := start + 24
			if end > len(uiPairs) {
				end = len(uiPairs)
			}
			chunk := map[string]string{}
			for _, p := range uiPairs[start:end] {
				chunk[p.Key] = p.Text
			}
			chunkJSON, _ := json.Marshal(chunk)
			prompt := "Você é especialista em localização (i18n) e UX writing.\n" +
				"Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" +
				"Não inclua pensamentos/raciocínio.\n\n" +
				"Traduza do PT-BR para " + lang + " mantendo o mesmo sentido.\n" +
				"- Para UI: mantenha curto (botões/menus), preserve setas e pontuação.\n" +
				"- Preserve placeholders exatamente como estão: {name}, {subtitle}, {count}, {term}, {current}, {total}.\n" +
				"- Preserve tags HTML já existentes sem remover/alterar atributos.\n\n" +
				"Retorne neste formato:\n{ \"ui\": {\"key\":\"text\"...} }\n\n" +
				"UI (PT-BR):\n" + string(chunkJSON)

			out, err := s.ai.GenerateJSON(ctx, prompt, 4096)
			if err != nil {
				return res, err
			}
			for k, v := range langMap(out["ui"], lang) {
				merged[k] = v
			}
		}

		// Configs (1 chamada)
		promptCfg := "Você é especialista em localização (i18n) e UX writing.\n" +
			"Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" +
			"Não inclua pensamentos/raciocínio.\n\n" +
			"Traduza do PT-BR para " + lang + " mantendo o mesmo sentido.\n" +
			"- Mantenha tom profissional.\n" +
			"- Preserve placeholders exatamente como estão.\n\n" +
			"Retorne neste formato:\n{ \"config\": {\"key\":\"text\"...} }\n\n" +
			"CONFIG (PT-BR):\n" + string(cfgJSON)
		outCfg, err := s.ai.GenerateJSON(ctx, promptCfg, 4096)
		if err != nil {
			return res, err
		}
		cfgTranslated := langMap(outCfg["config"], lang)

		for _, p := range uiPairs {
			if v, ok := merged[p.Key]; ok && strings.TrimSpace(v) != "" {
				if err := s.store.UpsertUIString(ctx, p.Key, lang, strings.TrimSpace(v)); err != nil {
					return res, err
				}
				res.SavedUI[lang] = append(res.SavedUI[lang], p.Key)
			}
		}
		for _, k := range configKeys {
			if v, ok := cfgTranslated[k]; ok && strings.TrimSpace(v) != "" {
				if err := s.store.UpsertConfigI18n(ctx, k, lang, strings.TrimSpace(v)); err != nil {
					return res, err
				}
				res.SavedConfig[lang] = append(res.SavedConfig[lang], k)
			}
		}

		// 3) Categorias (nome/descricao) por idioma.
		if len(catProj) > 0 || len(catArt) > 0 {
			promptCats := "Você é especialista em localização (i18n).\n" +
				"Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" +
				"Não inclua pensamentos/raciocínio.\n\n" +
				"Traduza do PT-BR para " + lang + " mantendo o mesmo sentido.\n" +
				"- NÃO traduza nem altere o campo slug.\n" +
				"- Retorne neste formato:\n" +
				"{ \"categorias_projetos\": {\"id\": {\"nome\":\"...\"}}, \"categorias_artigos\": {\"id\": {\"nome\":\"...\",\"descricao\":\"...\"}} }\n\n" +
				"DADOS (PT-BR):\n" + string(catJSON)
			outCats, err := s.ai.GenerateJSON(ctx, promptCats, 4096)
			if err != nil {
				return res, err
			}
			savedProj := []int{}
			projOut := childMap(outCats["categorias_projetos"])
			for _, c := range catProj {
				id := asIntAny(c["id"])
				entry := childMap(projOut[strID(id)])
				nome := strings.TrimSpace(asStrAny(entry["nome"]))
				if id <= 0 || nome == "" {
					continue
				}
				if err := s.store.UpsertCategoriaI18n(ctx, id, lang, nome, asStrAny(c["slug"])); err != nil {
					return res, err
				}
				savedProj = append(savedProj, id)
			}
			savedArt := []int{}
			artOut := childMap(outCats["categorias_artigos"])
			for _, c := range catArt {
				id := asIntAny(c["id"])
				entry := childMap(artOut[strID(id)])
				nome := strings.TrimSpace(asStrAny(entry["nome"]))
				if id <= 0 || nome == "" {
					continue
				}
				var desc *string
				if d := strings.TrimSpace(asStrAny(entry["descricao"])); d != "" {
					desc = &d
				}
				if err := s.store.UpsertCategoriaArtigoI18n(ctx, id, lang, nome, asStrAny(c["slug"]), desc); err != nil {
					return res, err
				}
				savedArt = append(savedArt, id)
			}
			if len(savedProj) > 0 || len(savedArt) > 0 {
				res.SavedCategories[lang] = map[string][]int{
					"categorias_projetos": savedProj,
					"categorias_artigos":  savedArt,
				}
			}
		}
	}
	return res, nil
}

func childMap(v any) map[string]any {
	if m, ok := v.(map[string]any); ok {
		return m
	}
	return map[string]any{}
}

func asStrAny(v any) string {
	if s, ok := v.(string); ok {
		return s
	}
	return ""
}

func asIntAny(v any) int {
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

func strID(i int) string {
	return fmt.Sprintf("%d", i)
}

// langMap aceita {"en":{...}} ou {key:value}; devolve key->texto.
func langMap(v any, lang string) map[string]string {
	m, ok := v.(map[string]any)
	if !ok {
		return map[string]string{}
	}
	if sub, ok := m[lang].(map[string]any); ok {
		m = sub
	}
	out := map[string]string{}
	for k, val := range m {
		if s, ok := val.(string); ok && strings.TrimSpace(s) != "" {
			out[k] = strings.TrimSpace(s)
		}
	}
	return out
}

// NormalizeLangs filtra/únicos pt/en/es com default en,es.
func NormalizeLangs(in []string) []string {
	seen := map[string]bool{}
	out := []string{}
	for _, l := range in {
		l = strings.ToLower(strings.TrimSpace(l))
		if (l == "pt" || l == "en" || l == "es") && !seen[l] {
			seen[l] = true
			out = append(out, l)
		}
	}
	if len(out) == 0 {
		return []string{"en", "es"}
	}
	return out
}

// ErrNoLangs é retornado quando nenhum idioma válido é informado.
var ErrNoLangs = fmt.Errorf("nenhum idioma válido")
