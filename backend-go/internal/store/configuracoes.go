package store

import "context"

// SetConfiguracao faz upsert de uma configuração (paridade com setConfig do PHP).
func (s *Store) SetConfiguracao(ctx context.Context, chave, valor string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO configuracoes (chave, valor) VALUES ($1, $2)
		ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP`,
		chave, valor)
	return err
}

// GetConfiguracoes devolve as chaves existentes dentre as solicitadas.
func (s *Store) GetConfiguracoes(ctx context.Context, chaves []string) (map[string]string, error) {
	out := map[string]string{}
	if len(chaves) == 0 {
		return out, nil
	}
	rows, err := s.pool.Query(ctx, "SELECT chave, valor FROM configuracoes WHERE chave = ANY($1)", chaves)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	for rows.Next() {
		var k string
		var v *string
		if err := rows.Scan(&k, &v); err != nil {
			return nil, err
		}
		if v != nil {
			out[k] = *v
		} else {
			out[k] = ""
		}
	}
	return out, rows.Err()
}
