<?php
/**
 * WASHIVIANA PORTFOLIO - i18n status updater
 * Marca traduções como reviewed/draft/generated.
 */

require_once __DIR__ . '/config.php';

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
}

$entity = $_POST['entity'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$lang = normalizeLang($_POST['lang'] ?? '');
$status = $_POST['status'] ?? 'reviewed';

if (!in_array($entity, ['artigo', 'projeto'], true)) {
    jsonResponse(['success' => false, 'message' => 'Entity inválida.'], 400);
}
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID inválido.'], 400);
}
if (!in_array($lang, ['pt', 'en', 'es'], true)) {
    jsonResponse(['success' => false, 'message' => 'Idioma inválido.'], 400);
}
if (!in_array($status, ['draft', 'generated', 'reviewed'], true)) {
    jsonResponse(['success' => false, 'message' => 'Status inválido.'], 400);
}

try {
    if ($entity === 'artigo') {
        $stmt = $pdo->prepare("UPDATE artigos_i18n SET status_traducao = ?, updated_at = CURRENT_TIMESTAMP WHERE artigo_id = ? AND lang = ?");
        $stmt->execute([$status, $id, $lang]);
    } else {
        $stmt = $pdo->prepare("UPDATE projetos_i18n SET status_traducao = ?, updated_at = CURRENT_TIMESTAMP WHERE projeto_id = ? AND lang = ?");
        $stmt->execute([$status, $id, $lang]);
    }

    if ($stmt->rowCount() === 0) {
        jsonResponse(['success' => false, 'message' => 'Tradução não encontrada.'], 404);
    }

    jsonResponse(['success' => true, 'message' => 'Status atualizado.', 'status' => $status]);
} catch (Exception $e) {
    error_log("i18n_status error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao atualizar status.'], 500);
}

