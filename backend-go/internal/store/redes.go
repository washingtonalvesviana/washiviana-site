package store

import (
	"context"
	"encoding/json"

	"github.com/jackc/pgx/v5"
)

// RedeSocial é a visão segura (sem segredos) de redes_sociais_config.
type RedeSocial struct {
	ID              int     `json:"id"`
	Rede            string  `json:"rede"`
	Ativo           bool    `json:"ativo"`
	ClientID        *string `json:"client_id"`
	PageID          *string `json:"page_id"`
	PersonURN       *string `json:"person_urn"`
	OrganizationURN *string `json:"organization_urn"`
	PublishTarget   *string `json:"publish_target"`
	TokenExpiresAt  *string `json:"token_expires_at"`
	HasSecret       bool    `json:"has_secret"`
	HasToken        bool    `json:"has_token"`
}

// ListRedesSociais devolve as redes sem expor segredos.
func (s *Store) ListRedesSociais(ctx context.Context) ([]RedeSocial, error) {
	rows, err := s.pool.Query(ctx, `
		SELECT id, rede, ativo, client_id, page_id, person_urn, organization_urn, publish_target,
		       token_expires_at::text,
		       (client_secret IS NOT NULL AND client_secret <> '') AS has_secret,
		       (access_token IS NOT NULL AND access_token <> '') AS has_token
		  FROM redes_sociais_config ORDER BY rede`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []RedeSocial
	for rows.Next() {
		var r RedeSocial
		if err := rows.Scan(&r.ID, &r.Rede, &r.Ativo, &r.ClientID, &r.PageID, &r.PersonURN,
			&r.OrganizationURN, &r.PublishTarget, &r.TokenExpiresAt, &r.HasSecret, &r.HasToken); err != nil {
			return nil, err
		}
		out = append(out, r)
	}
	return out, rows.Err()
}

// RedeDadosExtrasField lê um campo de dados_extras (jsonb) de uma rede.
func (s *Store) RedeDadosExtrasField(ctx context.Context, rede, field string) string {
	var raw *string
	err := s.pool.QueryRow(ctx, "SELECT dados_extras::text FROM redes_sociais_config WHERE rede = $1 LIMIT 1", rede).Scan(&raw)
	if err != nil || raw == nil {
		return ""
	}
	var m map[string]any
	if json.Unmarshal([]byte(*raw), &m) != nil {
		return ""
	}
	if v, ok := m[field].(string); ok {
		return v
	}
	return ""
}

// RedeSocialInput são os campos graváveis (segredos opcionais: vazio = manter).
type RedeSocialInput struct {
	Rede            string
	Ativo           *bool
	ClientID        *string
	ClientSecret    *string
	AccessToken     *string
	PageID          *string
	PersonURN       *string
	OrganizationURN *string
	PublishTarget   *string
	DadosExtras     *string
}

// SaveRedeSocial faz upsert por rede. Segredos vazios NÃO sobrescrevem o existente.
func (s *Store) SaveRedeSocial(ctx context.Context, in RedeSocialInput) (int, error) {
	var id int
	err := s.pool.QueryRow(ctx, `
		INSERT INTO redes_sociais_config (rede, ativo, client_id, client_secret, access_token, page_id, person_urn, organization_urn, publish_target, dados_extras)
		VALUES ($1, COALESCE($2, true), $3, NULLIF($4,''), NULLIF($5,''), $6, $7, $8, COALESCE($9,'person'), NULLIF($10,'')::jsonb)
		ON CONFLICT (rede) DO UPDATE SET
			ativo = COALESCE($2, redes_sociais_config.ativo),
			client_id = COALESCE($3, redes_sociais_config.client_id),
			client_secret = COALESCE(NULLIF($4,''), redes_sociais_config.client_secret),
			access_token = COALESCE(NULLIF($5,''), redes_sociais_config.access_token),
			page_id = COALESCE($6, redes_sociais_config.page_id),
			person_urn = COALESCE($7, redes_sociais_config.person_urn),
			organization_urn = COALESCE($8, redes_sociais_config.organization_urn),
			publish_target = COALESCE($9, redes_sociais_config.publish_target),
			dados_extras = COALESCE(NULLIF($10,'')::jsonb, redes_sociais_config.dados_extras),
			updated_at = CURRENT_TIMESTAMP
		RETURNING id`,
		in.Rede, in.Ativo, in.ClientID, derefOrEmpty(in.ClientSecret), derefOrEmpty(in.AccessToken),
		in.PageID, in.PersonURN, in.OrganizationURN, in.PublishTarget, derefOrEmpty(in.DadosExtras)).Scan(&id)
	if err == pgx.ErrNoRows {
		return 0, nil
	}
	return id, err
}

func derefOrEmpty(p *string) string {
	if p == nil {
		return ""
	}
	return *p
}
