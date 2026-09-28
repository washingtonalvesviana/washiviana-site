package radar

import (
	"context"
	"encoding/json"
	"encoding/xml"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"
)

// Config é passado ao coletor.
type CollectConfig struct {
	AllowLoopback bool
}

func (s *Service) allowLoopback() bool {
	return s.collect.AllowLoopback
}

var (
	tagRe         = regexp.MustCompile(`<[^>]*>`)
	wsRe          = regexp.MustCompile(`\s+`)
	titleTagRe    = regexp.MustCompile(`(?is)<title[^>]*>(.*?)</title>`)
	ogTitleRe     = regexp.MustCompile(`(?is)<meta[^>]+(?:property|name)=["'](?:og:title|twitter:title)["'][^>]*content=["']([^"']*)["']`)
	ogDescRe      = regexp.MustCompile(`(?is)<meta[^>]+(?:property|name)=["'](?:og:description|description|twitter:description)["'][^>]*content=["']([^"']*)["']`)
	metaContentRe = regexp.MustCompile(`(?is)<meta[^>]+content=["']([^"']*)["'][^>]*(?:property|name)=["'](?:og:title|twitter:title)["']`)
	metaDescRe    = regexp.MustCompile(`(?is)<meta[^>]+content=["']([^"']*)["'][^>]*(?:property|name)=["'](?:og:description|description|twitter:description)["']`)
)

type rssItem struct {
	URL         string
	Title       string
	Description string
	PublishedAt *string
	Raw         map[string]any
}

// CollectRun coleta todas as fontes ativas de um tema e registra o run.
func (s *Service) CollectRun(ctx context.Context, topicID int) (int, int, []map[string]any, []string, error) {
	meta, _ := json.Marshal(map[string]any{"topic_id": topicID})
	runID, err := s.store.InsertRadarRun(ctx, string(meta))
	if err != nil {
		return 0, 0, nil, nil, err
	}
	sourceIDs, err := s.store.RadarSourceTopicIDs(ctx, topicID)
	if err != nil {
		return runID, 0, nil, nil, err
	}
	if len(sourceIDs) == 0 {
		_ = s.store.FinishRadarRun(ctx, runID, "error", "Sem fontes ativas para este tema")
		return runID, 0, nil, nil, fmt.Errorf("Sem fontes ativas para este tema")
	}

	results := []map[string]any{}
	total := 0
	errors := []string{}
	for _, sid := range sourceIDs {
		saved, errs, err := s.CollectSource(ctx, sid, []int{topicID})
		r := map[string]any{"source_id": sid, "saved": saved}
		if err != nil {
			r["success"] = false
			r["error"] = err.Error()
			errors = append(errors, err.Error())
		} else {
			r["success"] = true
			r["errors"] = errs
			errors = append(errors, errs...)
		}
		total += saved
		results = append(results, r)
	}
	errors = uniqueStrings(errors)
	log := fmt.Sprintf("saved_total=%d\n", total)
	if len(errors) > 0 {
		log += "errors:\n- " + strings.Join(errors, "\n- ")
	}
	status := "success"
	if len(errors) > 0 {
		status = "error"
	}
	_ = s.store.FinishRadarRun(ctx, runID, status, log)
	return runID, total, results, errors, nil
}

// CollectSource coleta uma fonte.
func (s *Service) CollectSource(ctx context.Context, sourceID int, topicIDs []int) (int, []string, error) {
	src, err := s.store.RadarSourceAtivo(ctx, sourceID)
	if err != nil {
		return 0, nil, err
	}
	if src == nil {
		return 0, nil, fmt.Errorf("Fonte não encontrada/ativa")
	}
	tipo := asString(src["tipo"])
	rawURL := asString(src["url"])
	cfg := map[string]any{}
	if c, ok := src["config"].(string); ok && c != "" {
		_ = json.Unmarshal([]byte(c), &cfg)
	}

	var items []rssItem
	switch tipo {
	case "rss":
		if rawURL == "" {
			return 0, nil, fmt.Errorf("RSS sem URL")
		}
		body, _, err := s.httpGet(ctx, rawURL, intCfg(cfg, "timeout", 20), intCfg(cfg, "maxBytes", 2000000))
		if err != nil {
			return 0, nil, fmt.Errorf("RSS: %v", err)
		}
		items, err = parseRSS(body)
		if err != nil {
			return 0, nil, fmt.Errorf("RSS parse: %v", err)
		}
	case "scrape":
		if rawURL == "" {
			return 0, nil, fmt.Errorf("Scrape sem URL")
		}
		body, ct, err := s.httpGet(ctx, rawURL, intCfg(cfg, "timeout", 20), intCfg(cfg, "maxBytes", 2000000))
		if err != nil {
			return 0, nil, fmt.Errorf("Scrape: %v", err)
		}
		title, desc := scrapeMetadata(body)
		items = []rssItem{{URL: rawURL, Title: title, Description: desc, Raw: map[string]any{"content_type": ct}}}
	case "api":
		if rawURL == "" {
			return 0, nil, fmt.Errorf("API sem URL")
		}
		body, _, err := s.httpRequest(ctx, rawURL, strings.ToUpper(strCfg(cfg, "method", "GET")), cfg, intCfg(cfg, "timeout", 20), intCfg(cfg, "maxBytes", 2000000))
		if err != nil {
			return 0, nil, fmt.Errorf("API: %v", err)
		}
		var doc any
		if json.Unmarshal([]byte(body), &doc) != nil {
			return 0, nil, fmt.Errorf("Resposta API não é JSON válido")
		}
		itemsPath := strCfg(cfg, "items_path", "")
		urlPath := strCfg(cfg, "url_path", "url")
		titlePath := strCfg(cfg, "title_path", "title")
		descPath := strCfg(cfg, "description_path", "description")
		datePath := strCfg(cfg, "date_path", "")

		var list []any
		if itemsPath != "" {
			if found, ok := getValueByPath(doc, itemsPath).([]any); ok {
				list = found
			}
		} else if m, ok := doc.(map[string]any); ok {
			if v, ok := m["items"].([]any); ok {
				list = v
			} else if v, ok := m["data"].([]any); ok {
				list = v
			}
		}
		if len(list) == 0 {
			return 0, nil, fmt.Errorf("API: nenhum item encontrado (ver items_path)")
		}
		for _, raw := range list {
			u := asPathString(getValueByPath(raw, urlPath))
			t := asPathString(getValueByPath(raw, titlePath))
			d := asPathString(getValueByPath(raw, descPath))
			var pub *string
			if datePath != "" {
				if dv := getValueByPath(raw, datePath); dv != nil {
					switch n := dv.(type) {
					case float64:
						s := time.Unix(int64(n), 0).Format("2006-01-02 15:04:05")
						pub = &s
					case string:
						s := n
						pub = &s
					}
				}
			}
			items = append(items, rssItem{URL: u, Title: t, Description: d, PublishedAt: pub, Raw: map[string]any{}})
		}
	default:
		return 0, nil, fmt.Errorf("Tipo inválido")
	}

	saved := 0
	errors := []string{}
	for _, it := range items {
		if err := s.upsertItem(ctx, sourceID, it, topicIDs); err != nil {
			errors = append(errors, err.Error())
			continue
		}
		saved++
	}
	return saved, uniqueStrings(errors), nil
}

func (s *Service) upsertItem(ctx context.Context, sourceID int, it rssItem, topicIDs []int) error {
	u := strings.TrimSpace(it.URL)
	if u == "" {
		return fmt.Errorf("Item sem URL")
	}
	norm := normalizeURL(u)
	if norm == "" {
		return fmt.Errorf("URL inválida")
	}
	var rawJSON *string
	if it.Raw != nil {
		b, _ := json.Marshal(it.Raw)
		s := string(b)
		rawJSON = &s
	}
	score := computeScore(it.PublishedAt)
	_, err := s.store.UpsertRadarItem(ctx, &sourceID, u, norm, strings.TrimSpace(it.Title), strings.TrimSpace(it.Description), it.PublishedAt, score, rawJSON, topicIDs)
	return err
}

// --- HTTP ---

func (s *Service) httpGet(ctx context.Context, rawURL string, timeout, maxBytes int) (string, string, error) {
	return s.httpRequest(ctx, rawURL, "GET", nil, timeout, maxBytes)
}

func (s *Service) httpRequest(ctx context.Context, rawURL, method string, cfg map[string]any, timeout, maxBytes int) (string, string, error) {
	if !s.isSafeURL(rawURL) {
		return "", "", fmt.Errorf("URL bloqueada por segurança (SSRF).")
	}
	if timeout <= 0 || timeout > 120 {
		timeout = 20
	}
	client := &http.Client{Timeout: time.Duration(timeout) * time.Second}
	var bodyReader io.Reader
	if cfg != nil {
		if b, ok := cfg["body"].(string); ok && b != "" {
			bodyReader = strings.NewReader(b)
		}
	}
	req, err := http.NewRequestWithContext(ctx, method, rawURL, bodyReader)
	if err != nil {
		return "", "", err
	}
	req.Header.Set("User-Agent", "WashivianaRadar/1.0 (+https://washiviana.com)")
	req.Header.Set("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8")
	if cfg != nil {
		if hs, ok := cfg["headers"].(map[string]any); ok {
			for k, v := range hs {
				req.Header.Set(k, fmt.Sprintf("%v", v))
			}
		}
	}
	resp, err := client.Do(req)
	if err != nil {
		return "", "", err
	}
	defer resp.Body.Close()
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return "", "", fmt.Errorf("HTTP %d", resp.StatusCode)
	}
	if maxBytes <= 0 {
		maxBytes = 2000000
	}
	data, _ := io.ReadAll(io.LimitReader(resp.Body, int64(maxBytes)))
	return string(data), resp.Header.Get("Content-Type"), nil
}

func (s *Service) isSafeURL(raw string) bool {
	u, err := url.Parse(raw)
	if err != nil || u.Hostname() == "" {
		return false
	}
	if u.Scheme != "http" && u.Scheme != "https" {
		return false
	}
	if s.allowLoopback() {
		return true
	}
	host := u.Hostname()
	if strings.EqualFold(host, "localhost") {
		return false
	}
	if ip := net.ParseIP(host); ip != nil {
		return isPublicIP(ip)
	}
	ips, err := net.LookupIP(host)
	if err != nil || len(ips) == 0 {
		return false
	}
	for _, ip := range ips {
		if !isPublicIP(ip) {
			return false
		}
	}
	return true
}

func isPublicIP(ip net.IP) bool {
	return !(ip.IsPrivate() || ip.IsLoopback() || ip.IsUnspecified() || ip.IsLinkLocalUnicast() || ip.IsLinkLocalMulticast())
}

// --- parsing ---

func parseRSS(xmlStr string) ([]rssItem, error) {
	xmlStr = strings.TrimSpace(xmlStr)
	if xmlStr == "" {
		return nil, fmt.Errorf("RSS vazio")
	}
	var doc struct {
		Channel struct {
			Items []struct {
				Link        string `xml:"link"`
				Title       string `xml:"title"`
				Description string `xml:"description"`
				PubDate     string `xml:"pubDate"`
				GUID        string `xml:"guid"`
			} `xml:"item"`
		} `xml:"channel"`
		Entries []struct {
			Title   string `xml:"title"`
			Summary string `xml:"summary"`
			Content string `xml:"content"`
			Updated string `xml:"updated"`
			Pub     string `xml:"published"`
			Links   []struct {
				Href string `xml:"href,attr"`
				Rel  string `xml:"rel,attr"`
			} `xml:"link"`
		} `xml:"entry"`
	}
	if err := xml.Unmarshal([]byte(xmlStr), &doc); err != nil {
		return nil, fmt.Errorf("XML inválido")
	}

	items := []rssItem{}
	if len(doc.Channel.Items) > 0 {
		for _, it := range doc.Channel.Items {
			items = append(items, rssItem{
				URL:         it.Link,
				Title:       it.Title,
				Description: stripHTML(it.Description),
				PublishedAt: parseDate(it.PubDate),
				Raw:         map[string]any{"guid": it.GUID},
			})
		}
		return items, nil
	}
	for _, it := range doc.Entries {
		link := ""
		for _, l := range it.Links {
			if l.Rel == "" || l.Rel == "alternate" {
				link = l.Href
				break
			}
		}
		summary := it.Summary
		if summary == "" {
			summary = it.Content
		}
		date := it.Updated
		if date == "" {
			date = it.Pub
		}
		items = append(items, rssItem{URL: link, Title: it.Title, Description: stripHTML(summary), PublishedAt: parseDate(date), Raw: map[string]any{}})
	}
	return items, nil
}

func scrapeMetadata(html string) (string, string) {
	title := ""
	if m := titleTagRe.FindStringSubmatch(html); len(m) >= 2 {
		title = strings.TrimSpace(m[1])
	}
	if m := ogTitleRe.FindStringSubmatch(html); len(m) >= 2 {
		title = strings.TrimSpace(m[1])
	} else if m := metaContentRe.FindStringSubmatch(html); len(m) >= 2 {
		title = strings.TrimSpace(m[1])
	}
	desc := ""
	if m := ogDescRe.FindStringSubmatch(html); len(m) >= 2 {
		desc = strings.TrimSpace(m[1])
	} else if m := metaDescRe.FindStringSubmatch(html); len(m) >= 2 {
		desc = strings.TrimSpace(m[1])
	}
	return title, desc
}

func stripHTML(s string) string {
	return strings.TrimSpace(wsRe.ReplaceAllString(tagRe.ReplaceAllString(s, ""), " "))
}

func parseDate(s string) *string {
	s = strings.TrimSpace(s)
	if s == "" {
		return nil
	}
	for _, layout := range []string{time.RFC1123Z, time.RFC1123, time.RFC3339, "2006-01-02 15:04:05", "Mon, 02 Jan 2006 15:04:05 -0700"} {
		if t, err := time.Parse(layout, s); err == nil {
			out := t.Format("2006-01-02 15:04:05")
			return &out
		}
	}
	return nil
}

func computeScore(publishedAt *string) float64 {
	base := 10.0
	now := float64(time.Now().Unix())
	t := now
	if publishedAt != nil {
		if parsed := parseDate(*publishedAt); parsed != nil {
			if pt, err := time.ParseInLocation("2006-01-02 15:04:05", *parsed, time.Local); err == nil {
				t = float64(pt.Unix())
			}
		}
	}
	ageHours := (now - t) / 3600.0
	if ageHours < 0 {
		ageHours = 0
	}
	score := 100.0 / (1.0 + (ageHours / 24.0))
	if score < base {
		score = base
	}
	if score > 100 {
		score = 100
	}
	return score
}

func normalizeURL(raw string) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return ""
	}
	if i := strings.Index(raw, "#"); i >= 0 {
		raw = raw[:i]
	}
	raw = wsRe.ReplaceAllString(raw, "")
	u, err := url.Parse(raw)
	if err != nil || u.Scheme == "" || u.Host == "" {
		return raw
	}
	u.Scheme = strings.ToLower(u.Scheme)
	u.Host = strings.ToLower(u.Host)
	if u.Path != "/" && strings.HasSuffix(u.Path, "/") {
		u.Path = strings.TrimRight(u.Path, "/")
	}
	drop := map[string]bool{"utm_source": true, "utm_medium": true, "utm_campaign": true, "utm_term": true, "utm_content": true, "gclid": true, "fbclid": true, "igshid": true, "mc_cid": true, "mc_eid": true, "ref": true, "ref_src": true, "s": true}
	q := u.Query()
	for k := range q {
		if drop[k] {
			q.Del(k)
		}
	}
	u.RawQuery = q.Encode()
	return u.String()
}

func getValueByPath(v any, path string) any {
	if path == "" {
		return v
	}
	cur := v
	for _, key := range strings.Split(path, ".") {
		m, ok := cur.(map[string]any)
		if !ok {
			return nil
		}
		cur, ok = m[key]
		if !ok {
			return nil
		}
	}
	return cur
}

func asPathString(v any) string {
	switch t := v.(type) {
	case string:
		return t
	case float64:
		return strconv.FormatFloat(t, 'f', -1, 64)
	default:
		return ""
	}
}

func strCfg(m map[string]any, key, def string) string {
	if m == nil {
		return def
	}
	if v, ok := m[key].(string); ok && v != "" {
		return v
	}
	return def
}

func intCfg(m map[string]any, key string, def int) int {
	if m == nil {
		return def
	}
	switch v := m[key].(type) {
	case float64:
		return int(v)
	case int:
		return v
	}
	return def
}

func uniqueStrings(in []string) []string {
	seen := map[string]bool{}
	out := []string{}
	for _, s := range in {
		if s != "" && !seen[s] {
			seen[s] = true
			out = append(out, s)
		}
	}
	sort.Strings(out)
	return out
}
