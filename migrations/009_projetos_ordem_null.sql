-- Ajusta a coluna `ordem` de projetos para ser opcional (NULL),
-- garantindo fallback por data quando não definida.

ALTER TABLE projetos ALTER COLUMN ordem DROP DEFAULT;

UPDATE projetos
SET ordem = NULL
WHERE ordem = 0;

