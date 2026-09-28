package store

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgtype"
	"github.com/jackc/pgx/v5/pgxpool"
)

// OIDs dos tipos JSON do PostgreSQL.
const (
	oidJSON  = 114
	oidJSONB = 3802
)

// phpTimeLayout replica o formato de timestamp devolvido pelo PDO/pgsql do PHP
// (ex.: "2025-12-06 19:00:12.961968"), necessário para paridade no modo stranger.
const phpTimeLayout = "2006-01-02 15:04:05.999999"

// Store encapsula o acesso ao PostgreSQL (mesmo banco do PHP).
type Store struct {
	pool *pgxpool.Pool
}

// New cria o pool de conexões.
func New(ctx context.Context, dsn string, maxConns int32) (*Store, error) {
	cfg, err := pgxpool.ParseConfig(dsn)
	if err != nil {
		return nil, fmt.Errorf("dsn inválido: %w", err)
	}
	if maxConns > 0 {
		cfg.MaxConns = maxConns
	}
	// Entrega json/jsonb como texto cru, igual ao PDO/pgsql do PHP (paridade no
	// modo strangler). Escritas futuras devem tratar jsonb explicitamente.
	cfg.AfterConnect = func(ctx context.Context, conn *pgx.Conn) error {
		tm := conn.TypeMap()
		tm.RegisterType(&pgtype.Type{Name: "json", OID: oidJSON, Codec: &pgtype.TextCodec{}})
		tm.RegisterType(&pgtype.Type{Name: "jsonb", OID: oidJSONB, Codec: &pgtype.TextCodec{}})
		return nil
	}
	pool, err := pgxpool.NewWithConfig(ctx, cfg)
	if err != nil {
		return nil, fmt.Errorf("criar pool: %w", err)
	}
	return &Store{pool: pool}, nil
}

// Ping verifica a conectividade.
func (s *Store) Ping(ctx context.Context) error { return s.pool.Ping(ctx) }

// Close encerra o pool.
func (s *Store) Close() { s.pool.Close() }

// --- helpers ---

// normalizeRow alinha tipos ao PDO/pgsql do PHP: time.Time -> formato PHP e
// []byte (jsonb/bytea) -> string crua (o PHP devolve jsonb como texto JSON).
func normalizeRow(m map[string]any) {
	for k, v := range m {
		switch val := v.(type) {
		case time.Time:
			m[k] = val.Format(phpTimeLayout)
		case []byte:
			m[k] = string(val)
		}
	}
}

func (s *Store) queryMaps(ctx context.Context, sql string, args ...any) ([]map[string]any, error) {
	rows, err := s.pool.Query(ctx, sql, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	list, err := pgx.CollectRows(rows, pgx.RowToMap)
	if err != nil {
		return nil, err
	}
	if list == nil {
		list = []map[string]any{}
	}
	for _, m := range list {
		normalizeRow(m)
	}
	return list, nil
}

func (s *Store) queryOne(ctx context.Context, sql string, args ...any) (map[string]any, error) {
	list, err := s.queryMaps(ctx, sql, args...)
	if err != nil {
		return nil, err
	}
	if len(list) == 0 {
		return nil, nil
	}
	return list[0], nil
}

// decodeGaleria replica o json_decode(..., true) ?: [] do PHP para imagens_galeria.
func decodeGaleria(m map[string]any) {
	raw, ok := m["imagens_galeria"]
	if !ok {
		return
	}
	s, _ := raw.(string)
	var arr any
	if s != "" {
		if err := json.Unmarshal([]byte(s), &arr); err != nil {
			arr = nil
		}
	}
	if arr == nil {
		m["imagens_galeria"] = []any{}
	} else {
		m["imagens_galeria"] = arr
	}
}

// --- categorias ---

// ListCategorias replica api/categorias.php action=listar.
func (s *Store) ListCategorias(ctx context.Context, target string, apenasAtivas bool) ([]map[string]any, error) {
	catTable, itemTable, label := "categorias", "projetos", "total_projetos"
	if target == "artigos" {
		catTable, itemTable, label = "categorias_artigos", "artigos", "total_artigos"
	}
	where := ""
	if apenasAtivas {
		where = " WHERE ativo = true"
	}
	// Tabelas via whitelist (sem entrada do usuário).
	sql := fmt.Sprintf(`
		SELECT c.*,
		       (SELECT COUNT(*) FROM %s i WHERE i.categoria_id = c.id) AS total_items
		FROM %s c%s
		ORDER BY c.ordem ASC`, itemTable, catTable, where)

	list, err := s.queryMaps(ctx, sql)
	if err != nil {
		return nil, err
	}
	for _, m := range list {
		m[label] = m["total_items"]
	}
	return list, nil
}

// --- artigos ---

// ListArtigos replica api/artigos.php action=list.
func (s *Store) ListArtigos(ctx context.Context, status, categoria string) ([]map[string]any, error) {
	sql := `SELECT a.*, ca.nome as categoria_nome
	        FROM artigos a
	        LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id
	        WHERE 1=1`
	args := []any{}
	if status != "" {
		args = append(args, status)
		sql += fmt.Sprintf(" AND a.status = $%d", len(args))
	}
	if categoria != "" {
		args = append(args, categoria)
		sql += fmt.Sprintf(" AND a.categoria_id = $%d", len(args))
	}
	sql += " ORDER BY a.created_at DESC"
	return s.queryMaps(ctx, sql, args...)
}

// GetArtigo replica api/artigos.php action=get.
func (s *Store) GetArtigo(ctx context.Context, id int) (map[string]any, error) {
	return s.queryOne(ctx, "SELECT * FROM artigos WHERE id = $1", id)
}

// --- projetos ---

// ListProjetos replica api/projetos.php action=list.
func (s *Store) ListProjetos(ctx context.Context, categoriaID, status, destaque string, limit, offset int) ([]map[string]any, error) {
	sql := `SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug
	        FROM projetos p
	        LEFT JOIN categorias c ON p.categoria_id = c.id
	        WHERE 1=1`
	args := []any{}
	if categoriaID != "" {
		args = append(args, categoriaID)
		sql += fmt.Sprintf(" AND p.categoria_id = $%d", len(args))
	}
	if status != "" {
		args = append(args, status)
		sql += fmt.Sprintf(" AND p.ativo = $%d", len(args))
	}
	if destaque != "" {
		args = append(args, destaque)
		sql += fmt.Sprintf(" AND p.destaque = $%d", len(args))
	}
	sql += " ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC"
	args = append(args, limit)
	sql += fmt.Sprintf(" LIMIT $%d", len(args))
	args = append(args, offset)
	sql += fmt.Sprintf(" OFFSET $%d", len(args))

	list, err := s.queryMaps(ctx, sql, args...)
	if err != nil {
		return nil, err
	}
	for _, m := range list {
		decodeGaleria(m)
	}
	return list, nil
}

// GetProjeto replica api/projetos.php action=get (por id ou slug).
func (s *Store) GetProjeto(ctx context.Context, id int, slug string) (map[string]any, error) {
	const base = `SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug
	              FROM projetos p
	              LEFT JOIN categorias c ON p.categoria_id = c.id
	              WHERE %s = $1`
	var (
		m   map[string]any
		err error
	)
	if slug != "" {
		m, err = s.queryOne(ctx, fmt.Sprintf(base, "p.slug"), slug)
	} else {
		m, err = s.queryOne(ctx, fmt.Sprintf(base, "p.id"), id)
	}
	if err != nil || m == nil {
		return m, err
	}
	decodeGaleria(m)
	return m, nil
}

// --- i18n/SEO ---

// GetI18nSeo replica api/get_i18n_seo.php (remove conteudo/descricao).
func (s *Store) GetI18nSeo(ctx context.Context, entity string, id int, lang string) (map[string]any, error) {
	table, fk := "artigos_i18n", "artigo_id"
	if entity == "projeto" {
		table, fk = "projetos_i18n", "projeto_id"
	}
	sql := fmt.Sprintf("SELECT * FROM %s WHERE %s = $1 AND lang = $2", table, fk)
	m, err := s.queryOne(ctx, sql, id, lang)
	if err != nil || m == nil {
		return m, err
	}
	delete(m, "conteudo")
	delete(m, "descricao")
	return m, nil
}
