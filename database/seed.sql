-- ============================================================
-- Washiviana Site - Dados iniciais (seed) - PostgreSQL
-- Aplicar APOS database/schema_postgres.sql em uma instalacao nova.
-- Idempotente: usa ON CONFLICT DO NOTHING.
--
--   psql -d <db> -f database/seed.sql
--
-- ATENCAO: o usuario admin abaixo usa uma senha padrao conhecida.
-- Troque a senha imediatamente apos o primeiro login.
-- ============================================================

-- Usuario administrador padrao
-- Email: contact@washiviana.com
-- Senha: definida no hash abaixo (trocar no primeiro acesso)
INSERT INTO usuarios (nome, email, senha) VALUES
('Washington Viana', 'contact@washiviana.com', '$2y$10$ZnwTH38/SFtd6XSw8cp2seUFKyzg3AZVhn3HkSMLnR6ewS/hevy5i')
ON CONFLICT (email) DO NOTHING;

-- Categorias de projetos
INSERT INTO categorias (nome, slug, ordem) VALUES
('Web Design & Development', 'web-design-development', 1),
('3D Modeling & Animation', '3d-modeling-animation', 2),
('Artificial Intelligence', 'artificial-intelligence', 3),
('AR/VR Experiences', 'ar-vr-experiences', 4),
('UI/UX Design', 'ui-ux-design', 5),
('Branding & Identity', 'branding-identity', 6),
('Motion Design', 'motion-design', 7)
ON CONFLICT (slug) DO NOTHING;

-- Categorias de artigos
INSERT INTO categorias_artigos (nome, slug, descricao, cor, ordem) VALUES
('Inteligência Artificial', 'inteligencia-artificial', 'Artigos sobre IA, Machine Learning e automação', '#607AFB', 1),
('Tech Insights', 'tech-insights', 'Notícias e análises sobre tecnologia', '#10B981', 2),
('Automação', 'automacao', 'Dicas e tutoriais sobre automação de processos', '#F59E0B', 3),
('Desenvolvimento', 'desenvolvimento', 'Artigos sobre programação e desenvolvimento', '#8B5CF6', 4),
('Design', 'design', 'UX/UI, Design Gráfico e tendências visuais', '#EC4899', 5)
ON CONFLICT (slug) DO NOTHING;

-- Configuracoes iniciais do site
INSERT INTO configuracoes (chave, valor) VALUES
('site_titulo', 'Washington Viana'),
('site_subtitulo', 'Tech & IA com linguagem humana'),
('home_frase_impacto', 'Crio soluções que unem tecnologia, criatividade e automação para transformar negócios e pessoas.'),
('site_email', 'contato@washiviana.com'),
('site_telefone', '+55 19 9 9942 2907'),
('site_linkedin', 'https://linkedin.com/in/washingtonviana'),
('site_instagram', 'https://instagram.com/washiviana'),
('site_github', 'https://github.com/washiviana'),
('mini_bio', '+10 anos criando soluções com tecnologia para empresas. Atuo com IA aplicada, automação, design tecnológico e desenvolvimento com foco em produtividade e governança.'),
('openai_api_key', ''),
('openai_model', 'gpt-4'),
('openai_max_tokens', '800')
ON CONFLICT (chave) DO NOTHING;
