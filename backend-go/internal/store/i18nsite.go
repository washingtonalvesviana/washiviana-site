package store

import "context"

// UpsertUIString grava/replace uma string de UI.
func (s *Store) UpsertUIString(ctx context.Context, chave, lang, texto string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO ui_strings (chave, lang, texto) VALUES ($1,$2,$3)
		ON CONFLICT (chave, lang) DO UPDATE SET texto = EXCLUDED.texto, updated_at = CURRENT_TIMESTAMP`,
		chave, lang, texto)
	return err
}

// UpsertConfigI18n grava/replace uma config i18n.
func (s *Store) UpsertConfigI18n(ctx context.Context, chave, lang, valor string) error {
	_, err := s.pool.Exec(ctx, `
		INSERT INTO configuracoes_i18n (chave, lang, valor) VALUES ($1,$2,$3)
		ON CONFLICT (chave, lang) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP`,
		chave, lang, valor)
	return err
}
