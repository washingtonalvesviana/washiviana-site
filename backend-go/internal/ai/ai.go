// Package ai porta a geração de texto/artigo do api/gemini.php (multi-provedor).
package ai

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"washiviana/backend/internal/store"
	"washiviana/backend/internal/textutil"
)

// Service gera conteúdo via provedores configurados.
type Service struct {
	store *store.Store
	http  *http.Client
}

// New cria o serviço.
func New(st *store.Store) *Service {
	return &Service{store: st, http: &http.Client{Timeout: 180 * time.Second}}
}

var cfgKeys = []string{
	"llm_text_provider", "llm_text_model",
	"gemini_api_key", "gemini_model",
	"openai_api_key", "openai_base_url", "openai_model",
	"deepseek_api_key", "openrouter_api_key", "anthropic_api_key",
	"ollama_api_key", "ollama_base_url", "ollama_model",
	"ia_instrucoes", "ia_instrucoes_social",
	"llm_image_provider", "llm_image_model", "gemini_image_model",
}

func (s *Service) config(ctx context.Context) (map[string]string, error) {
	return s.store.GetConfiguracoes(ctx, cfgKeys)
}

func textProvider(cfg map[string]string) string {
	p := strings.ToLower(strings.TrimSpace(cfg["llm_text_provider"]))
	switch p {
	case "gemini", "openai", "deepseek", "openrouter", "anthropic", "ollama":
		return p
	default:
		return "gemini"
	}
}

func textModel(cfg map[string]string, provider string) string {
	if m := strings.TrimSpace(cfg["llm_text_model"]); m != "" {
		return m
	}
	switch provider {
	case "gemini":
		if m := strings.TrimSpace(cfg["gemini_model"]); m != "" {
			return m
		}
		return "gemini-2.0-flash"
	case "openai":
		if m := strings.TrimSpace(cfg["openai_model"]); m != "" {
			return m
		}
		return "gpt-4o-mini"
	case "deepseek":
		return "deepseek-chat"
	case "openrouter":
		return "openai/gpt-4o-mini"
	case "anthropic":
		return "claude-3-5-sonnet-latest"
	case "ollama":
		if m := strings.TrimSpace(cfg["ollama_model"]); m != "" {
			return m
		}
		return "llama3.1"
	}
	return ""
}

func apiKey(cfg map[string]string, provider string) string {
	switch provider {
	case "openai":
		return strings.TrimSpace(cfg["openai_api_key"])
	case "deepseek":
		return strings.TrimSpace(cfg["deepseek_api_key"])
	case "openrouter":
		return strings.TrimSpace(cfg["openrouter_api_key"])
	case "anthropic":
		return strings.TrimSpace(cfg["anthropic_api_key"])
	case "ollama":
		return strings.TrimSpace(cfg["ollama_api_key"])
	default:
		return strings.TrimSpace(cfg["gemini_api_key"])
	}
}

// GenerateText resolve provedor/modelo do config e gera texto.
func (s *Service) GenerateText(ctx context.Context, prompt string, maxTokens int) (text, fonte string, err error) {
	cfg, err := s.config(ctx)
	if err != nil {
		return "", "", err
	}
	provider := textProvider(cfg)
	model := textModel(cfg, provider)
	if maxTokens <= 0 {
		maxTokens = 8192
	}
	text, err = s.generateWithProvider(ctx, cfg, provider, model, prompt, maxTokens)
	if err != nil {
		return "", provider + ":" + model, err
	}
	return text, provider + ":" + model, nil
}

// GenerateArticle monta o prompt do artigo e gera o texto (com fallback Gemini por cota).
func (s *Service) GenerateArticle(ctx context.Context, tema string) (text, fonte string, maxTokens int, err error) {
	if strings.TrimSpace(tema) == "" {
		return "", "", 0, fmt.Errorf("Prompt não fornecido")
	}
	cfg, err := s.config(ctx)
	if err != nil {
		return "", "", 0, err
	}
	provider := textProvider(cfg)
	model := textModel(cfg, provider)
	maxTokens = 8192
	prompt := BuildArticlePrompt(cfg["ia_instrucoes"], tema)

	if provider != "gemini" {
		text, err = s.generateWithProvider(ctx, cfg, provider, model, prompt, maxTokens)
		return text, provider + ":" + model, maxTokens, err
	}

	key := apiKey(cfg, "gemini")
	if key == "" {
		return "", "", maxTokens, fmt.Errorf("API Key não configurada para GEMINI")
	}
	text, err = s.geminiGenerate(ctx, model, prompt, maxTokens, key)
	if err != nil {
		msg := err.Error()
		if (strings.Contains(msg, "429") || strings.Contains(strings.ToLower(msg), "quota")) && model != "gemini-2.0-flash" {
			if t2, err2 := s.geminiGenerate(ctx, "gemini-2.0-flash", prompt, maxTokens, key); err2 == nil {
				return t2, "gemini-2.0-flash", maxTokens, nil
			}
		}
		return "", model, maxTokens, err
	}
	return text, model, maxTokens, nil
}

// BuildArticlePrompt replica a montagem do prompt de gerarArtigo().
func BuildArticlePrompt(instrucoes, tema string) string {
	var b strings.Builder
	if strings.TrimSpace(instrucoes) != "" {
		b.WriteString(instrucoes)
		b.WriteString("\n\n")
	}
	b.WriteString("SOLICITAÇÃO: ")
	b.WriteString(tema)
	b.WriteString("\n\n")
	b.WriteString("FORMATO DE SAÍDA OBRIGATÓRIO:\n")
	b.WriteString("- Comece DIRETAMENTE com os campos, sem introdução ou comentários\n")
	b.WriteString("- Use EXATAMENTE estas labels no início de cada linha\n")
	b.WriteString("- O CONTEÚDO deve ser EXTENSO e COMPLETO conforme solicitado nas instruções\n")
	b.WriteString("- O CONTEÚDO deve estar em formato HTML com tags apropriadas\n\n")
	b.WriteString("Título: [título aqui]\n")
	b.WriteString("Slug: [slug-aqui-em-minusculas-sem-acentos]\n")
	b.WriteString("Categoria: [categoria]\n")
	b.WriteString("Resumo: [resumo até 320 caracteres, texto puro sem HTML]\n")
	b.WriteString("Conteúdo: [ARTIGO COMPLETO EM HTML usando:\n")
	b.WriteString("  - <p> para parágrafos\n")
	b.WriteString("  - <h2> e <h3> para subtítulos\n")
	b.WriteString("  - <strong> para negrito\n")
	b.WriteString("  - <em> para itálico\n")
	b.WriteString("  - <ul><li> para listas\n")
	b.WriteString("  - <blockquote> para citações\n")
	b.WriteString("  - Cada parágrafo deve ter pelo menos 3-4 frases\n")
	b.WriteString("  - Use subtítulos (h2) para dividir seções\n")
	b.WriteString("  - NÃO use markdown (**, ##, etc), APENAS HTML\n")
	b.WriteString("]")
	return b.String()
}

func (s *Service) generateWithProvider(ctx context.Context, cfg map[string]string, provider, model, prompt string, maxTokens int) (string, error) {
	key := apiKey(cfg, provider)
	if provider != "ollama" && key == "" {
		return "", fmt.Errorf("API Key não configurada para %s", strings.ToUpper(provider))
	}

	switch provider {
	case "openai", "deepseek", "openrouter":
		baseURL := "https://api.openai.com/v1"
		headers := map[string]string{"Authorization": "Bearer " + key}
		switch provider {
		case "openai":
			if cb := strings.TrimSpace(cfg["openai_base_url"]); cb != "" {
				baseURL = strings.TrimRight(cb, "/")
			}
		case "deepseek":
			baseURL = "https://api.deepseek.com/v1"
		case "openrouter":
			baseURL = "https://openrouter.ai/api/v1"
			headers["HTTP-Referer"] = "https://washiviana.com"
			headers["X-Title"] = "Washiviana Admin"
		}
		payload := map[string]any{
			"model":       model,
			"messages":    []map[string]any{{"role": "user", "content": prompt}},
			"max_tokens":  maxTokens,
			"temperature": 0.7,
		}
		if provider == "openai" && strings.TrimSpace(cfg["openai_base_url"]) != "" {
			payload["chat_template_kwargs"] = map[string]any{"enable_thinking": false}
		}
		body, err := s.postJSON(ctx, baseURL+"/chat/completions", payload, headers)
		if err != nil {
			return "", err
		}
		var d struct {
			Choices []struct {
				Message struct {
					Content string `json:"content"`
				} `json:"message"`
			} `json:"choices"`
		}
		_ = json.Unmarshal(body, &d)
		if len(d.Choices) == 0 {
			return "", fmt.Errorf("a IA não retornou texto")
		}
		text := strings.TrimSpace(d.Choices[0].Message.Content)
		if text == "" {
			return "", fmt.Errorf("a IA não retornou texto")
		}
		return text, nil

	case "anthropic":
		body, err := s.postJSON(ctx, "https://api.anthropic.com/v1/messages", map[string]any{
			"model":      model,
			"max_tokens": maxTokens,
			"messages":   []map[string]any{{"role": "user", "content": prompt}},
		}, map[string]string{"x-api-key": key, "anthropic-version": "2023-06-01"})
		if err != nil {
			return "", err
		}
		var d struct {
			Content []struct {
				Type string `json:"type"`
				Text string `json:"text"`
			} `json:"content"`
		}
		_ = json.Unmarshal(body, &d)
		var sb strings.Builder
		for _, part := range d.Content {
			if part.Type == "text" {
				sb.WriteString(part.Text)
			}
		}
		text := strings.TrimSpace(sb.String())
		if text == "" {
			return "", fmt.Errorf("a IA não retornou texto")
		}
		return text, nil

	case "ollama":
		baseURL := strings.TrimRight(strings.TrimSpace(cfg["ollama_base_url"]), "/")
		if baseURL == "" {
			baseURL = "http://localhost:11434"
		}
		headers := map[string]string{}
		if key != "" {
			headers["Authorization"] = "Bearer " + key
		}
		body, err := s.postJSON(ctx, baseURL+"/api/chat", map[string]any{
			"model":    model,
			"messages": []map[string]any{{"role": "user", "content": prompt}},
			"stream":   false,
		}, headers)
		if err != nil {
			return "", err
		}
		var d struct {
			Message struct {
				Content string `json:"content"`
			} `json:"message"`
		}
		_ = json.Unmarshal(body, &d)
		text := strings.TrimSpace(d.Message.Content)
		if text == "" {
			return "", fmt.Errorf("o Ollama não retornou texto")
		}
		return text, nil

	case "gemini":
		return s.geminiGenerate(ctx, model, prompt, maxTokens, key)
	}
	return "", fmt.Errorf("provedor não suportado para texto")
}

// ModelInfo descreve um modelo listado.
type ModelInfo struct {
	ID          string `json:"id"`
	Name        string `json:"name"`
	Description string `json:"description"`
}

// GeminiModels lista modelos do Gemini (v1 + v1beta), separando texto e imagem.
func (s *Service) GeminiModels(ctx context.Context, apiKey string) (text, image []ModelInfo, total int, err error) {
	seen := map[string]map[string]any{}
	for _, base := range []string{"https://generativelanguage.googleapis.com/v1/models?key=", "https://generativelanguage.googleapis.com/v1beta/models?key="} {
		status, body, gerr := s.getJSON(ctx, base+url.QueryEscape(apiKey), nil)
		if gerr != nil || status != 200 {
			continue
		}
		var d struct {
			Models []map[string]any `json:"models"`
		}
		if json.Unmarshal(body, &d) != nil {
			continue
		}
		for _, m := range d.Models {
			name, _ := m["name"].(string)
			id := strings.TrimPrefix(name, "models/")
			if id == "" {
				continue
			}
			if _, ok := seen[id]; !ok {
				seen[id] = m
			}
		}
	}
	if len(seen) == 0 {
		return nil, nil, 0, fmt.Errorf("Resposta inválida da API ou chave sem modelos disponíveis")
	}
	text = []ModelInfo{}
	image = []ModelInfo{}
	for id, m := range seen {
		name, _ := m["name"].(string)
		displayName, _ := m["displayName"].(string)
		if displayName == "" {
			displayName = name
		}
		description, _ := m["description"].(string)
		info := ModelInfo{ID: id, Name: displayName, Description: description}

		if strings.Contains(id, "gemini") && !strings.Contains(id, "embedding") {
			if methodsContains(m["supportedGenerationMethods"], "generateContent") {
				text = append(text, info)
			}
		}
		lowerID := strings.ToLower(id)
		lowerDisp := strings.ToLower(displayName)
		if strings.Contains(lowerID, "imagen") || strings.Contains(lowerID, "image") || strings.Contains(lowerDisp, "image") {
			image = append(image, info)
		}
	}
	sort.Slice(text, func(i, j int) bool { return text[i].ID > text[j].ID })
	sort.Slice(image, func(i, j int) bool { return image[i].ID > image[j].ID })
	return text, image, len(seen), nil
}

func methodsContains(v any, want string) bool {
	arr, ok := v.([]any)
	if !ok {
		return false
	}
	for _, item := range arr {
		if s, ok := item.(string); ok && s == want {
			return true
		}
	}
	return false
}

// GenerateSocialAgent gera a legenda de rede social (porta gerarSocialAgent).
// Divergência consciente: usa o provedor de texto configurado (o PHP força Gemini).
func (s *Service) GenerateSocialAgent(ctx context.Context, prompt, rede string) (string, error) {
	cfg, err := s.config(ctx)
	if err != nil {
		return "", err
	}
	rede = strings.ToLower(strings.TrimSpace(rede))
	if rede == "" {
		rede = "instagram"
	}
	instrucoes := s.store.RedeDadosExtrasField(ctx, rede, "social_agent_instruction")
	if strings.TrimSpace(instrucoes) == "" {
		instrucoes = cfg["ia_instrucoes_social"]
	}

	tone := "Use tom leve e direto, com emojis e 2-3 hashtags relevantes."
	if rede == "facebook" {
		tone = "Use tom mais informal e engajador, adaptado para Facebook."
	}

	var b strings.Builder
	if strings.TrimSpace(instrucoes) != "" {
		b.WriteString(instrucoes)
		b.WriteString("\n\n")
	}
	b.WriteString("Context: Rede=" + rede + ". " + tone + "\n\n")
	b.WriteString("Objetivo: gere a legenda final pronta para publicar, com 2 ou 3 frases completas, emojis e 2-3 hashtags no final (sem explicar o processo). Seja direto, mencione o tema do prompt e use linguagem adaptada à rede.\n\n")
	b.WriteString(prompt)
	promptFinal := b.String()

	text, err := s.generateWithProvider(ctx, cfg, textProvider(cfg), textModel(cfg, textProvider(cfg)), promptFinal, 512)
	if err != nil {
		return "", err
	}
	clean := sanitizeSocial(text)

	if needsSocialRetry(clean) {
		retryPrompt := promptFinal + "\n\nINSTRUCAO FINAL: responda APENAS com a legenda pronta para publicar, em texto corrido, 2 ou 3 frases completas, emojis e 2-3 hashtags ao final. NAO use titulos, listas, markdown, separadores ou introducoes. NAO comece com 'Certo', 'Entendido', 'Aqui esta' ou frases meta. Termine com pontuacao. Escreva no minimo 140 caracteres."
		if t2, err2 := s.generateWithProvider(ctx, cfg, textProvider(cfg), textModel(cfg, textProvider(cfg)), retryPrompt, 512); err2 == nil {
			if c2 := sanitizeSocial(t2); c2 != "" {
				clean = c2
			}
		}
	}
	if strings.TrimSpace(clean) == "" {
		return "", fmt.Errorf("a IA não retornou texto")
	}
	return clean, nil
}

func needsSocialRetry(text string) bool {
	if len([]rune(text)) < 140 {
		return true
	}
	lower := strings.ToLower(text)
	for _, p := range []string{"certo", "a i sim", "aí sim", "ok", "entendido", "aqui est"} {
		if strings.HasPrefix(lower, p) {
			return true
		}
	}
	last := strings.TrimSpace(text)
	if last == "" {
		return true
	}
	r := []rune(last)[len([]rune(last))-1]
	if !strings.ContainsRune(".!?…", r) {
		return true
	}
	return false
}

func sanitizeSocial(raw string) string {
	lines := strings.Split(strings.ReplaceAll(raw, "\r\n", "\n"), "\n")
	var clean []string
	for _, line := range lines {
		l := strings.TrimSpace(line)
		if l == "" || strings.HasPrefix(l, "```") || l == "---" {
			continue
		}
		l = trimLineMarkers(l)
		clean = append(clean, l)
	}
	return strings.TrimSpace(strings.Join(clean, " "))
}

func trimLineMarkers(l string) string {
	// remove heading (#..) e marcadores de lista (-, *, •, 1.)
	for strings.HasPrefix(l, "#") {
		l = strings.TrimPrefix(l, "#")
	}
	l = strings.TrimSpace(l)
	for _, p := range []string{"- ", "* ", "• "} {
		if strings.HasPrefix(l, p) {
			l = strings.TrimSpace(strings.TrimPrefix(l, p))
		}
	}
	// remove "N. " no início
	i := 0
	for i < len(l) && l[i] >= '0' && l[i] <= '9' {
		i++
	}
	if i > 0 && i+1 < len(l) && l[i] == '.' && l[i+1] == ' ' {
		l = strings.TrimSpace(l[i+2:])
	}
	return l
}

// GeneratedImage é o resultado de uma geração de imagem.
type GeneratedImage struct {
	Filename string
	URL      string
	Model    string
}

// GenerateImage gera uma imagem (openai-compatível ou gemini) e salva em uploads/.
func (s *Service) GenerateImage(ctx context.Context, prompt, uploadDir, uploadURL string) (*GeneratedImage, error) {
	if strings.TrimSpace(prompt) == "" {
		return nil, fmt.Errorf("Prompt não fornecido")
	}
	cfg, err := s.config(ctx)
	if err != nil {
		return nil, err
	}
	provider := strings.ToLower(strings.TrimSpace(cfg["llm_image_provider"]))
	if provider == "" {
		provider = "gemini"
	}
	model := strings.TrimSpace(cfg["llm_image_model"])

	switch provider {
	case "openai":
		if model == "" {
			model = "gpt-image-1"
		}
		key := strings.TrimSpace(cfg["openai_api_key"])
		if key == "" {
			return nil, fmt.Errorf("API Key da OpenAI não configurada")
		}
		baseURL := "https://api.openai.com/v1"
		if cb := strings.TrimSpace(cfg["openai_base_url"]); cb != "" {
			baseURL = strings.TrimRight(cb, "/")
		}
		body, err := s.postJSON(ctx, baseURL+"/images/generations", map[string]any{
			"model": model, "prompt": prompt, "size": "1536x1024",
		}, map[string]string{"Authorization": "Bearer " + key})
		if err != nil {
			return nil, err
		}
		var d struct {
			Data []struct {
				B64JSON string `json:"b64_json"`
				URL     string `json:"url"`
			} `json:"data"`
		}
		_ = json.Unmarshal(body, &d)
		if len(d.Data) == 0 {
			return nil, fmt.Errorf("resposta de imagem inválida da OpenAI")
		}
		if d.Data[0].B64JSON != "" {
			fn, err := saveBase64PNG(uploadDir, d.Data[0].B64JSON)
			if err != nil {
				return nil, err
			}
			return &GeneratedImage{Filename: fn, URL: strings.TrimRight(uploadURL, "/") + "/" + fn, Model: model}, nil
		}
		if d.Data[0].URL != "" {
			fn, err := s.downloadToUploads(ctx, d.Data[0].URL, uploadDir)
			if err != nil {
				return nil, err
			}
			return &GeneratedImage{Filename: fn, URL: strings.TrimRight(uploadURL, "/") + "/" + fn, Model: model}, nil
		}
		return nil, fmt.Errorf("resposta de imagem inválida da OpenAI")

	default: // gemini
		key := strings.TrimSpace(cfg["gemini_api_key"])
		if key == "" {
			return nil, fmt.Errorf("API Key não configurada")
		}
		if model == "" {
			if m := strings.TrimSpace(cfg["gemini_image_model"]); m != "" {
				model = m
			} else {
				model = "gemini-2.0-flash-exp-image-generation"
			}
		}
		b64, err := s.geminiImage(ctx, model, prompt, key)
		if err != nil {
			return nil, err
		}
		fn, err := saveBase64PNG(uploadDir, b64)
		if err != nil {
			return nil, err
		}
		return &GeneratedImage{Filename: fn, URL: strings.TrimRight(uploadURL, "/") + "/" + fn, Model: model}, nil
	}
}

func (s *Service) geminiImage(ctx context.Context, model, prompt, key string) (string, error) {
	u := fmt.Sprintf("https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s", url.PathEscape(model), url.QueryEscape(key))
	body, err := s.postJSON(ctx, u, map[string]any{
		"contents": []map[string]any{{"parts": []map[string]any{{"text": prompt}}}},
	}, nil)
	if err != nil {
		return "", err
	}
	var d struct {
		Candidates []struct {
			Content struct {
				Parts []struct {
					InlineData struct {
						Data string `json:"data"`
					} `json:"inlineData"`
				} `json:"parts"`
			} `json:"content"`
		} `json:"candidates"`
	}
	_ = json.Unmarshal(body, &d)
	if len(d.Candidates) > 0 {
		for _, p := range d.Candidates[0].Content.Parts {
			if p.InlineData.Data != "" {
				return p.InlineData.Data, nil
			}
		}
	}
	return "", fmt.Errorf("a IA não retornou imagem")
}

func (s *Service) downloadToUploads(ctx context.Context, rawURL, uploadDir string) (string, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, rawURL, nil)
	if err != nil {
		return "", err
	}
	resp, err := s.http.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return "", fmt.Errorf("HTTP %d ao baixar imagem", resp.StatusCode)
	}
	data, _ := io.ReadAll(io.LimitReader(resp.Body, 32<<20))
	return saveBase64PNG(uploadDir, base64.StdEncoding.EncodeToString(data))
}

func saveBase64PNG(uploadDir, b64 string) (string, error) {
	raw, err := base64.StdEncoding.DecodeString(strings.TrimSpace(b64))
	if err != nil {
		return "", fmt.Errorf("base64 inválido: %w", err)
	}
	if err := os.MkdirAll(uploadDir, 0o755); err != nil {
		return "", err
	}
	filename := "ai_" + textutil.Uniqid() + ".png"
	if err := os.WriteFile(filepath.Join(uploadDir, filename), raw, 0o644); err != nil {
		return "", err
	}
	return filename, nil
}

// GenerateJSON gera e já parseia um objeto JSON (usado por i18n/SEO).
func (s *Service) GenerateJSON(ctx context.Context, prompt string, maxTokens int) (map[string]any, error) {
	cfg, err := s.config(ctx)
	if err != nil {
		return nil, err
	}
	provider := textProvider(cfg)
	model := textModel(cfg, provider)
	key := apiKey(cfg, provider)
	if maxTokens <= 0 {
		maxTokens = 8192
	}

	var content string
	switch provider {
	case "openai", "deepseek", "openrouter":
		if key == "" {
			return nil, fmt.Errorf("API Key não configurada para %s", strings.ToUpper(provider))
		}
		baseURL := "https://api.openai.com/v1"
		headers := map[string]string{"Authorization": "Bearer " + key}
		switch provider {
		case "openai":
			if cb := strings.TrimSpace(cfg["openai_base_url"]); cb != "" {
				baseURL = strings.TrimRight(cb, "/")
			}
		case "deepseek":
			baseURL = "https://api.deepseek.com/v1"
		case "openrouter":
			baseURL = "https://openrouter.ai/api/v1"
		}
		msgs := []map[string]any{
			{"role": "system", "content": "Retorne apenas JSON válido, sem markdown e sem texto extra."},
			{"role": "user", "content": prompt},
		}
		payload := map[string]any{"model": model, "messages": msgs, "max_tokens": maxTokens, "temperature": 0.2, "response_format": map[string]any{"type": "json_object"}}
		if provider == "openai" && strings.TrimSpace(cfg["openai_base_url"]) != "" {
			payload["chat_template_kwargs"] = map[string]any{"enable_thinking": false}
		}
		body, err := s.postJSON(ctx, baseURL+"/chat/completions", payload, headers)
		if err != nil {
			// Retry com max_completion_tokens (alguns endpoints rejeitam max_tokens).
			payload["max_completion_tokens"] = payload["max_tokens"]
			delete(payload, "max_tokens")
			body, err = s.postJSON(ctx, baseURL+"/chat/completions", payload, headers)
			if err != nil {
				return nil, err
			}
		}
		content = openAIContent(body)

	case "anthropic":
		if key == "" {
			return nil, fmt.Errorf("API Key não configurada para ANTHROPIC")
		}
		body, err := s.postJSON(ctx, "https://api.anthropic.com/v1/messages", map[string]any{
			"model": model, "max_tokens": maxTokens,
			"messages": []map[string]any{{"role": "user", "content": prompt}},
		}, map[string]string{"x-api-key": key, "anthropic-version": "2023-06-01"})
		if err != nil {
			return nil, err
		}
		content = anthropicContent(body)

	case "ollama":
		baseURL := strings.TrimRight(strings.TrimSpace(cfg["ollama_base_url"]), "/")
		if baseURL == "" {
			baseURL = "http://localhost:11434"
		}
		body, err := s.postJSON(ctx, baseURL+"/api/chat", map[string]any{
			"model":    model,
			"messages": []map[string]any{{"role": "system", "content": "Retorne apenas JSON válido, sem markdown e sem texto extra."}, {"role": "user", "content": prompt}},
			"stream":   false,
		}, nil)
		if err != nil {
			return nil, err
		}
		var d struct {
			Message struct {
				Content string `json:"content"`
			} `json:"message"`
		}
		_ = json.Unmarshal(body, &d)
		content = d.Message.Content

	default: // gemini
		if key == "" {
			return nil, fmt.Errorf("API Key não configurada para GEMINI")
		}
		u := fmt.Sprintf("https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s", url.PathEscape(model), url.QueryEscape(key))
		body, err := s.postJSON(ctx, u, map[string]any{
			"contents":         []map[string]any{{"parts": []map[string]any{{"text": prompt}}}},
			"generationConfig": map[string]any{"maxOutputTokens": maxTokens, "temperature": 0.2, "responseMimeType": "application/json"},
		}, nil)
		if err != nil {
			return nil, err
		}
		var d struct {
			Candidates []struct {
				Content struct {
					Parts []struct {
						Text string `json:"text"`
					} `json:"parts"`
				} `json:"content"`
			} `json:"candidates"`
		}
		_ = json.Unmarshal(body, &d)
		if len(d.Candidates) > 0 {
			for _, part := range d.Candidates[0].Content.Parts {
				content += part.Text
			}
		}
	}

	obj := extractJSONObject(content)
	if obj == nil {
		return nil, fmt.Errorf("a IA retornou resposta não-JSON")
	}
	return obj, nil
}

func openAIContent(body []byte) string {
	var d struct {
		Choices []struct {
			Message struct {
				Content string `json:"content"`
			} `json:"message"`
		} `json:"choices"`
	}
	_ = json.Unmarshal(body, &d)
	if len(d.Choices) == 0 {
		return ""
	}
	return d.Choices[0].Message.Content
}

func anthropicContent(body []byte) string {
	var d struct {
		Content []struct {
			Type string `json:"type"`
			Text string `json:"text"`
		} `json:"content"`
	}
	_ = json.Unmarshal(body, &d)
	var sb strings.Builder
	for _, p := range d.Content {
		if p.Type == "text" {
			sb.WriteString(p.Text)
		}
	}
	return sb.String()
}

// extractJSONObject extrai o primeiro objeto JSON do texto (tolerante a cercas markdown).
func extractJSONObject(text string) map[string]any {
	text = strings.TrimSpace(text)
	// remove cercas ```json ... ```
	if i := strings.Index(text, "```"); i >= 0 {
		rest := text[i+3:]
		rest = strings.TrimPrefix(strings.TrimSpace(rest), "json")
		if j := strings.Index(rest, "```"); j >= 0 {
			text = rest[:j]
		} else {
			text = rest
		}
		text = strings.TrimSpace(text)
	}
	start := strings.Index(text, "{")
	end := strings.LastIndex(text, "}")
	if start < 0 || end <= start {
		return nil
	}
	var obj map[string]any
	if json.Unmarshal([]byte(text[start:end+1]), &obj) != nil {
		return nil
	}
	return obj
}

func (s *Service) geminiGenerate(ctx context.Context, model, prompt string, maxTokens int, key string) (string, error) {
	u := fmt.Sprintf("https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s",
		url.PathEscape(model), url.QueryEscape(key))
	payload := map[string]any{
		"contents": []map[string]any{
			{"parts": []map[string]any{{"text": prompt}}},
		},
		"generationConfig": map[string]any{"maxOutputTokens": maxTokens, "temperature": 0.7},
	}
	body, err := s.postJSON(ctx, u, payload, nil)
	if err != nil {
		return "", err
	}
	var d struct {
		Candidates []struct {
			Content struct {
				Parts []struct {
					Text string `json:"text"`
				} `json:"parts"`
			} `json:"content"`
		} `json:"candidates"`
	}
	_ = json.Unmarshal(body, &d)
	var sb strings.Builder
	if len(d.Candidates) > 0 {
		for _, part := range d.Candidates[0].Content.Parts {
			sb.WriteString(part.Text)
		}
	}
	text := strings.TrimSpace(sb.String())
	if text == "" {
		return "", fmt.Errorf("a IA não retornou texto")
	}
	return text, nil
}

func (s *Service) getJSON(ctx context.Context, rawURL string, headers map[string]string) (int, []byte, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, rawURL, nil)
	if err != nil {
		return 0, nil, err
	}
	for k, v := range headers {
		req.Header.Set(k, v)
	}
	resp, err := s.http.Do(req)
	if err != nil {
		return 0, nil, err
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	return resp.StatusCode, body, nil
}

func (s *Service) postJSON(ctx context.Context, rawURL string, payload any, headers map[string]string) ([]byte, error) {
	buf, err := json.Marshal(payload)
	if err != nil {
		return nil, err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, rawURL, bytes.NewReader(buf))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	for k, v := range headers {
		req.Header.Set(k, v)
	}
	resp, err := s.http.Do(req)
	if err != nil {
		return nil, fmt.Errorf("erro de conexão: %w", err)
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(resp.Body, 4<<20))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		var d map[string]any
		msg := fmt.Sprintf("HTTP %d", resp.StatusCode)
		if json.Unmarshal(body, &d) == nil {
			if e, ok := d["error"].(map[string]any); ok {
				if m, ok := e["message"].(string); ok && m != "" {
					msg = m
				}
			} else if m, ok := d["message"].(string); ok && m != "" {
				msg = m
			}
		}
		return nil, fmt.Errorf("%s", msg)
	}
	return body, nil
}
