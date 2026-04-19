<?php
/**
 * Teste rápido para CRUD da tabela artigos_social_variants
 * Uso:
 * 1) Configure as credenciais no arquivo .env ou exporte as variáveis: PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD
 * 2) php tests/test_social_variants.php
 *
 * ATENÇÃO: este script faz INSERT/DELETE em banco. Use em ambiente de staging/test.
 */

require_once __DIR__ . '/../api/config.php'; // aproveita a configuração do projeto

$pdo = $pdo ?? null;
if (!$pdo) {
    echo "Não foi possível conectar ao banco via config.php. Verifique configuração.\n";
    exit(1);
}

try {
    echo "Rodando testes de artigos_social_variants...\n";

    // 1) Criar artigo de teste
    $stmt = $pdo->prepare("INSERT INTO artigos (titulo, slug, resumo, conteudo, status_publicacao, created_at) VALUES (?, ?, ?, ?, 'rascunho', NOW()) RETURNING id");
    $titulo = 'TESTE_VARIANT_' . bin2hex(random_bytes(4));
    $slug = 'teste-variant-' . bin2hex(random_bytes(4));
    $stmt->execute([$titulo, $slug, 'resumo', 'conteudo']);
    $artigoId = $stmt->fetchColumn();

    if (!$artigoId) throw new Exception('Falha ao criar artigo de teste');
    echo "Artigo criado: $artigoId\n";

    // 2) Inserir variante social
    $stmt = $pdo->prepare("INSERT INTO artigos_social_variants (artigo_id, rede, titulo, caption, hashtags, media_type, image_1x1, image_9x16, status, created_at) VALUES (?, 'instagram', ?, ?, ?, 'imagem', '', '', 'rascunho', NOW()) RETURNING id");
    $caption = 'Legenda de teste #teste';
    $stmt->execute([$artigoId, 'Titulo Teste', $caption, '#teste']);
    $variantId = $stmt->fetchColumn();

    if (!$variantId) throw new Exception('Falha ao criar variante social');
    echo "Variante criada: $variantId\n";

    // 3) Buscar variante
    $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE id = ?");
    $stmt->execute([$variantId]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception('Variante não encontrada após inserção');
    echo "Variante encontrada: rede={$row['rede']} caption='{$row['caption']}'\n";

    // 4) Teste: tentativa de publicar (espera erro por configuração ausente)
    require_once __DIR__ . '/../api/instagram.php';
    $pubRes = publicarNoInstagramViaAPI($row);
    if ($pubRes['success']) {
        echo "Aviso: publicação Instagram retornou sucesso (inesperado em ambiente sem credenciais).\n";
    } else {
        echo "Publicação Instagram não configurada (esperado): " . ($pubRes['message'] ?? '') . "\n";
    }

    // 5) Atualizar variante
    $stmt = $pdo->prepare("UPDATE artigos_social_variants SET caption = ? WHERE id = ?");
    $newCaption = 'Atualizado ' . time();
    $stmt->execute([$newCaption, $variantId]);

    $stmt = $pdo->prepare("SELECT caption FROM artigos_social_variants WHERE id = ?");
    $stmt->execute([$variantId]);
    $updated = $stmt->fetchColumn();
    if ($updated !== $newCaption) throw new Exception('Atualização falhou');
    echo "Atualização confirmada: $updated\n";

    // 5) Deletar variante e artigo (cleanup)
    $stmt = $pdo->prepare("DELETE FROM artigos_social_variants WHERE id = ?");
    $stmt->execute([$variantId]);
    echo "Variante deletada.\n";

    $stmt = $pdo->prepare("DELETE FROM artigos WHERE id = ?");
    $stmt->execute([$artigoId]);
    echo "Artigo deletado.\n";

    echo "Todos os testes passaram.\n";
} catch (Exception $e) {
    echo "Erro durante testes: " . $e->getMessage() . "\n";
    exit(2);
}
