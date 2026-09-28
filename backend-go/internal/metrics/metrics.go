// Package metrics porta api/metrics_lib.php (coleta best-effort por rede).
package metrics

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"time"

	"washiviana/backend/internal/store"
)

// Fetcher coleta métricas de publicações.
type Fetcher struct {
	store *store.Store
	http  *http.Client
}

// New cria o fetcher.
func New(st *store.Store) *Fetcher {
	return &Fetcher{store: st, http: &http.Client{Timeout: 20 * time.Second}}
}

// Collect despacha pela rede; nunca entra em pânico (erros retornados).
func (f *Fetcher) Collect(ctx context.Context, pub store.PublicacaoMetrica) (store.MetricsRow, error) {
	if pub.PostID == "" {
		return store.MetricsRow{}, fmt.Errorf("publicacao sem post_id")
	}
	switch pub.Rede {
	case "linkedin":
		return f.fetchLinkedIn(ctx, pub.PostID)
	case "instagram":
		return f.fetchInstagram(ctx, pub.PostID)
	case "facebook":
		return f.fetchFacebook(ctx, pub.PostID)
	default:
		return store.MetricsRow{}, fmt.Errorf("rede sem coletor: %s", pub.Rede)
	}
}

func (f *Fetcher) getJSON(ctx context.Context, rawURL string, headers map[string]string) (int, []byte, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, rawURL, nil)
	if err != nil {
		return 0, nil, err
	}
	for k, v := range headers {
		req.Header.Set(k, v)
	}
	resp, err := f.http.Do(req)
	if err != nil {
		return 0, nil, err
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	return resp.StatusCode, body, nil
}

func apiErrorMessage(code int, body []byte) string {
	var d map[string]any
	if json.Unmarshal(body, &d) == nil {
		if errObj, ok := d["error"].(map[string]any); ok {
			if m, ok := errObj["message"].(string); ok && m != "" {
				return m
			}
		}
		if m, ok := d["message"].(string); ok && m != "" {
			return m
		}
	}
	return fmt.Sprintf("HTTP %d", code)
}

func (f *Fetcher) fetchLinkedIn(ctx context.Context, postID string) (store.MetricsRow, error) {
	token, err := f.store.GetRedeAccessToken(ctx, "linkedin")
	if err != nil {
		return store.MetricsRow{}, err
	}
	if token == "" {
		return store.MetricsRow{}, fmt.Errorf("LinkedIn sem access_token")
	}
	headers := map[string]string{
		"Authorization":              "Bearer " + token,
		"X-Restli-Protocol-Version":  "2.0.0",
		"LinkedIn-Version":           time.Now().Format("200601"),
	}
	u := "https://api.linkedin.com/rest/socialActions/" + url.PathEscape(postID)
	code, body, err := f.getJSON(ctx, u, headers)
	if err != nil {
		return store.MetricsRow{}, err
	}
	if code < 200 || code >= 300 {
		return store.MetricsRow{}, fmt.Errorf("LinkedIn: %s", apiErrorMessage(code, body))
	}
	var d map[string]any
	_ = json.Unmarshal(body, &d)
	likes := intAt(d, "likesSummary", "totalLikes")
	comments := intAt(d, "commentsSummary", "totalComments")
	return store.MetricsRow{
		Curtidas:    likes,
		Comentarios: comments,
		Engajamento: float64(likes + comments),
		Extras:      json.RawMessage(body),
	}, nil
}

func (f *Fetcher) fetchInstagram(ctx context.Context, mediaID string) (store.MetricsRow, error) {
	token, err := f.store.GetRedeAccessToken(ctx, "instagram")
	if err != nil {
		return store.MetricsRow{}, err
	}
	if token == "" {
		return store.MetricsRow{}, fmt.Errorf("Instagram sem access_token")
	}
	v := f.facebookVersion(ctx)
	u := "https://graph.facebook.com/" + url.PathEscape(v) + "/" + url.PathEscape(mediaID) + "/insights" +
		"?metric=" + "impressions,reach,likes,comments,shares,saved" + "&access_token=" + url.QueryEscape(token)
	code, body, err := f.getJSON(ctx, u, nil)
	if err != nil {
		return store.MetricsRow{}, err
	}
	if code < 200 || code >= 300 {
		return store.MetricsRow{}, fmt.Errorf("Instagram: %s", apiErrorMessage(code, body))
	}
	var d struct {
		Data []struct {
			Name   string `json:"name"`
			Values []struct {
				Value float64 `json:"value"`
			} `json:"values"`
			Value *float64 `json:"value"`
		} `json:"data"`
	}
	_ = json.Unmarshal(body, &d)
	m := map[string]int{}
	for _, item := range d.Data {
		if item.Name == "" {
			continue
		}
		if len(item.Values) > 0 {
			m[item.Name] = int(item.Values[0].Value)
		} else if item.Value != nil {
			m[item.Name] = int(*item.Value)
		}
	}
	return store.MetricsRow{
		Visualizacoes:     m["impressions"],
		Curtidas:          m["likes"],
		Comentarios:       m["comments"],
		Compartilhamentos: m["shares"],
		Alcance:           m["reach"],
		Engajamento:       float64(m["likes"] + m["comments"] + m["shares"] + m["saved"]),
		Extras:            json.RawMessage(body),
	}, nil
}

func (f *Fetcher) fetchFacebook(ctx context.Context, postID string) (store.MetricsRow, error) {
	token, err := f.store.GetRedeAccessToken(ctx, "facebook")
	if err != nil {
		return store.MetricsRow{}, err
	}
	if token == "" {
		return store.MetricsRow{}, fmt.Errorf("Facebook sem access_token")
	}
	v := f.facebookVersion(ctx)
	fields := "likes.summary(true),comments.summary(true),shares"
	u := "https://graph.facebook.com/" + url.PathEscape(v) + "/" + url.PathEscape(postID) +
		"?fields=" + url.QueryEscape(fields) + "&access_token=" + url.QueryEscape(token)
	code, body, err := f.getJSON(ctx, u, nil)
	if err != nil {
		return store.MetricsRow{}, err
	}
	if code < 200 || code >= 300 {
		return store.MetricsRow{}, fmt.Errorf("Facebook: %s", apiErrorMessage(code, body))
	}
	var d map[string]any
	_ = json.Unmarshal(body, &d)
	likes := intAt(d, "likes", "summary", "total_count")
	comments := intAt(d, "comments", "summary", "total_count")
	shares := intAt(d, "shares", "count")
	return store.MetricsRow{
		Curtidas:          likes,
		Comentarios:       comments,
		Compartilhamentos: shares,
		Engajamento:       float64(likes + comments + shares),
		Extras:            json.RawMessage(body),
	}, nil
}

func (f *Fetcher) facebookVersion(ctx context.Context) string {
	vals, err := f.store.GetConfiguracoes(ctx, []string{"facebook_api_version"})
	if err == nil && vals["facebook_api_version"] != "" {
		return vals["facebook_api_version"]
	}
	return "v17.0"
}

// intAt navega em mapas aninhados convertendo o valor final para int.
func intAt(m map[string]any, path ...string) int {
	var cur any = m
	for _, key := range path {
		obj, ok := cur.(map[string]any)
		if !ok {
			return 0
		}
		cur, ok = obj[key]
		if !ok {
			return 0
		}
	}
	switch v := cur.(type) {
	case float64:
		return int(v)
	case int:
		return v
	case json.Number:
		i, _ := v.Int64()
		return int(i)
	default:
		return 0
	}
}
