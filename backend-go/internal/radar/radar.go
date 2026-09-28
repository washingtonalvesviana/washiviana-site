// Package radar porta as partes de IA/clustering do radar (api/radar_lib.php).
package radar

import (
	"context"
	"encoding/json"
	"fmt"
	"math"
	"strings"
	"time"
	"unicode/utf8"

	"washiviana/backend/internal/ai"
	"washiviana/backend/internal/store"
)

// Service executa operações do radar.
type Service struct {
	store *store.Store
	ai    *ai.Service
}

// New cria o serviço.
func New(st *store.Store, aiSvc *ai.Service) *Service {
	return &Service{store: st, ai: aiSvc}
}

// GenerateIdeas gera ideias a partir dos itens coletados de um tema.
func (s *Service) GenerateIdeas(ctx context.Context, topicID, limitItems, numIdeas int) (int, []int, string, error) {
	if limitItems <= 0 {
		limitItems = 20
	}
	if numIdeas <= 0 {
		numIdeas = 8
	}
	topic, err := s.store.RadarTopicAtivo(ctx, topicID)
	if err != nil {
		return 0, nil, "", err
	}
	if topic == nil {
		return 0, nil, "", fmt.Errorf("Tema não encontrado/ativo")
	}
	items, err := s.store.RadarItemsForTopic(ctx, topicID, limitItems)
	if err != nil {
		return 0, nil, "", err
	}
	if len(items) == 0 {
		return 0, nil, "", fmt.Errorf("Sem itens coletados para este tema")
	}

	ids := make([]int, 0, len(items))
	refs := make([]string, 0, len(items))
	for _, it := range items {
		id := asInt(it["id"])
		ids = append(ids, id)
		refs = append(refs, "- "+strings.TrimSpace(asString(it["titulo"]))+" | "+asString(it["url"]))
	}
	idsJSON, _ := json.Marshal(ids)

	idiomas := asString(topic["idiomas"])
	if idiomas == "" {
		idiomas = "pt,en"
	}
	regioes := asString(topic["regioes"])
	if regioes == "" {
		regioes = "br,us,eu"
	}
	forcePt := strings.Contains(strings.ToLower(idiomas), "pt")

	prompt := "Você é um editor de conteúdo.\n" +
		"Objetivo: sugerir ideias de artigos ORIGINAIS a partir de tendências e links coletados.\n" +
		"Regras:\n" +
		"- NÃO copie texto das fontes.\n" +
		"- Gere ideias com ângulo próprio e valor prático.\n" +
		"- Idiomas-alvo: " + idiomas + ".\n" +
		"- Regiões-alvo: " + regioes + " (priorize Brasil, EUA e Europa).\n\n"
	if forcePt {
		prompt += "ATENÇÃO: RETORNE APENAS EM PORTUGUÊS (PT-BR). TODOS OS CAMPOS (title, angle, summary, outline, tags) DEVEM SER RESPONDIDOS EM PT-BR.\n\n"
	}
	prompt += "Tema: " + asString(topic["nome"]) + "\n" +
		"Palavras-chave: " + asString(topic["keywords"]) + "\n\n" +
		"Fontes (links coletados):\n" + strings.Join(refs, "\n") + "\n\n" +
		"Retorne APENAS JSON válido no formato:\n" +
		"{\n  \"ideas\": [\n    {\n      \"title\": \"...\",\n      \"angle\": \"...\",\n      \"summary\": \"...\",\n      \"outline\": [\"...\",\"...\"],\n      \"tags\": [\"...\"],\n      \"priority\": \"hype|medium|evergreen\"\n    }\n  ]\n}\n" +
		fmt.Sprintf("Gere exatamente %d ideias. Seja conciso no resumo e outline para evitar cortes no JSON.\n", numIdeas)

	out, err := s.ai.GenerateJSON(ctx, prompt, 4096)
	if err != nil {
		return 0, nil, "", err
	}
	ideasAny, ok := out["ideas"].([]any)
	if !ok || len(ideasAny) == 0 {
		return 0, nil, "", fmt.Errorf("IA não retornou JSON válido")
	}

	model := s.modelLabel(ctx)

	saved := 0
	ideaIDs := []int{}
	for _, raw := range ideasAny {
		idea, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		title := strings.TrimSpace(asString(idea["title"]))
		if title == "" {
			continue
		}
		outlineStr := ""
		switch ov := idea["outline"].(type) {
		case []any:
			lines := make([]string, 0, len(ov))
			for _, x := range ov {
				lines = append(lines, "- "+strings.TrimSpace(asString(x)))
			}
			outlineStr = strings.Join(lines, "\n")
		default:
			outlineStr = strings.TrimSpace(asString(idea["outline"]))
		}
		tagsStr := ""
		switch tv := idea["tags"].(type) {
		case []any:
			parts := make([]string, 0, len(tv))
			for _, x := range tv {
				parts = append(parts, asString(x))
			}
			tagsStr = strings.Join(parts, ", ")
		default:
			tagsStr = strings.TrimSpace(asString(idea["tags"]))
		}
		id, err := s.store.InsertRadarIdea(ctx, store.RadarIdeaInput{
			TopicID: topicID, Titulo: title,
			Angulo:  strings.TrimSpace(asString(idea["angle"])),
			Resumo:  strings.TrimSpace(asString(idea["summary"])),
			Outline: outlineStr, Tags: tagsStr,
			SourceItemIDs: string(idsJSON), AIModel: model, AIPrompt: prompt,
		})
		if err != nil {
			return saved, ideaIDs, model, err
		}
		ideaIDs = append(ideaIDs, id)
		saved++
	}
	return saved, ideaIDs, model, nil
}

func (s *Service) modelLabel(ctx context.Context) string {
	cfg, err := s.store.GetConfiguracoes(ctx, []string{"llm_text_provider", "llm_text_model"})
	if err != nil {
		return ""
	}
	p := cfg["llm_text_provider"]
	if p == "" {
		p = "gemini"
	}
	return p + ":" + cfg["llm_text_model"]
}

// AnalyzeHype agrupa itens similares e grava métricas de "hype" no raw.
func (s *Service) AnalyzeHype(ctx context.Context, hours int, threshold float64) (int, int, error) {
	if hours <= 0 {
		hours = 48
	}
	if threshold <= 0 {
		threshold = 0.55
	}
	items, err := s.store.RadarHypeItems(ctx, hours)
	if err != nil {
		return 0, 0, err
	}
	if len(items) < 2 {
		return 0, 0, nil
	}

	processed := map[int]bool{}
	var clusters [][]store.RadarHypeItem
	for i := range items {
		if processed[items[i].ID] {
			continue
		}
		processed[items[i].ID] = true
		cluster := []store.RadarHypeItem{items[i]}
		a := truncate255(strings.ToLower(strings.TrimSpace(items[i].Titulo)))
		for j := i + 1; j < len(items); j++ {
			if processed[items[j].ID] {
				continue
			}
			b := truncate255(strings.ToLower(strings.TrimSpace(items[j].Titulo)))
			maxLen := maxInt(utf8.RuneCountInString(a), utf8.RuneCountInString(b))
			if maxLen == 0 {
				continue
			}
			ratio := float64(levenshtein(a, b)) / float64(maxLen)
			if ratio <= threshold {
				cluster = append(cluster, items[j])
				processed[items[j].ID] = true
			}
		}
		if len(cluster) > 1 {
			clusters = append(clusters, cluster)
		}
	}

	updated := 0
	for _, cluster := range clusters {
		var times []int64
		for _, it := range cluster {
			if t := parseRadarTime(it.PubAt); t > 0 {
				times = append(times, t)
			} else if t := parseRadarTime(it.FetchedAt); t > 0 {
				times = append(times, t)
			}
		}
		if len(times) == 0 {
			continue
		}
		minT, maxT := times[0], times[0]
		for _, t := range times {
			if t < minT {
				minT = t
			}
			if t > maxT {
				maxT = t
			}
		}
		spanHours := float64(maxT-minT) / 3600.0
		if spanHours < 0.1 {
			spanHours = 0.1
		}
		velocity := float64(len(cluster)) / spanHours
		hypeScore := velocity * math.Log(float64(len(cluster))+1)
		isTrending := velocity >= 2.0 && len(cluster) >= 3

		for _, it := range cluster {
			rawStr, err := s.store.RadarItemRaw(ctx, it.ID)
			if err != nil {
				return len(clusters), updated, err
			}
			m := map[string]any{}
			_ = json.Unmarshal([]byte(rawStr), &m)
			m["velocity"] = round2(velocity)
			m["hype_score"] = round2(hypeScore)
			m["is_trending"] = isTrending
			m["cluster_size"] = len(cluster)
			newRaw, _ := json.Marshal(m)
			if err := s.store.UpdateRadarItemRaw(ctx, it.ID, string(newRaw)); err != nil {
				return len(clusters), updated, err
			}
			updated++
		}
	}
	return len(clusters), updated, nil
}

// --- helpers ---

func asString(v any) string {
	if s, ok := v.(string); ok {
		return s
	}
	return ""
}

func asInt(v any) int {
	switch n := v.(type) {
	case int:
		return n
	case int64:
		return int(n)
	case float64:
		return int(n)
	}
	return 0
}

func maxInt(a, b int) int {
	if a > b {
		return a
	}
	return b
}

func truncate255(s string) string {
	if len(s) > 255 {
		return s[:255]
	}
	return s
}

// levenshtein byte-based (igual ao PHP).
func levenshtein(a, b string) int {
	la, lb := len(a), len(b)
	if la == 0 {
		return lb
	}
	if lb == 0 {
		return la
	}
	prev := make([]int, lb+1)
	curr := make([]int, lb+1)
	for j := 0; j <= lb; j++ {
		prev[j] = j
	}
	for i := 1; i <= la; i++ {
		curr[0] = i
		for j := 1; j <= lb; j++ {
			cost := 1
			if a[i-1] == b[j-1] {
				cost = 0
			}
			curr[j] = minInt(minInt(curr[j-1]+1, prev[j]+1), prev[j-1]+cost)
		}
		prev, curr = curr, prev
	}
	return prev[lb]
}

func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func round2(f float64) float64 {
	return math.Round(f*100) / 100
}

func parseRadarTime(p *string) int64 {
	if p == nil {
		return 0
	}
	s := strings.TrimSpace(*p)
	if s == "" {
		return 0
	}
	for _, layout := range []string{"2006-01-02 15:04:05.999999", "2006-01-02 15:04:05", time.RFC3339} {
		if t, err := time.ParseInLocation(layout, s, time.Local); err == nil {
			return t.Unix()
		}
	}
	return 0
}
