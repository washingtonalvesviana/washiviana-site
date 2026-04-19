<?php
/**
 * WASHIVIANA PORTFOLIO - Get i18n/SEO details for review
 */
require_once __DIR__ . '/config.php';

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

header('Content-Type: application/json; charset=utf-8');

$entity = $_GET['entity'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$lang = $_GET['lang'] ?? '';

if (!in_array($entity, ['artigo', 'projeto'], true) || $id <= 0 || empty($lang)) {
    jsonResponse(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$table = ($entity === 'artigo') ? 'artigos_i18n' : 'projetos_i18n';
$fk = ($entity === 'artigo') ? 'artigo_id' : 'projeto_id';

try {
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$fk} = ? AND lang = ?");
    $stmt->execute([$id, $lang]);
    $data = $stmt->fetch();

    if (!$data) {
        jsonResponse(['success' => false, 'message' => 'Dados de SEO não encontrados.'], 404);
    }

    // Clean up unnecessary fields for JSON response
    unset($data['conteudo']);
    unset($data['descricao']);

    jsonResponse(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Erro ao buscar dados.', 'details' => $e->getMessage()], 500);
}
