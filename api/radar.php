<?php
/**
 * WASHIVIANA - Radar API (admin-only)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/radar_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
}

try {
    switch ($action) {
        case 'topics_list':
            topicsList();
            break;
        case 'topics_save':
            topicsSave();
            break;
        case 'topics_delete':
            topicsDelete();
            break;

        case 'sources_list':
            sourcesList();
            break;
        case 'sources_save':
            sourcesSave();
            break;
        case 'sources_delete':
            sourcesDelete();
            break;

        case 'topic_sources_set':
            topicSourcesSet();
            break;
        case 'topic_sources_get':
            topicSourcesGet();
            break;

        case 'collect_run':
            collectRun();
            break;

        case 'items_list':
            itemsList();
            break;

        case 'ideas_list':
            ideasList();
            break;
        case 'ideas_generate':
            ideasGenerate();
            break;
        case 'idea_discard':
            ideaDiscard();
            break;
        case 'idea_to_draft':
            ideaToDraft();
            break;
        case 'idea_sources':
            ideaSources();
            break;

        case 'items_delete':
            itemsDelete();
            break;
        
        case 'analyze_hype':
            analyzeHype();
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Ação não especificada'], 400);
    }
} catch (Exception $e) {
    error_log("Radar API Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
}

function topicsList(): void {
    global $pdo;
    $rows = $pdo->query("
        SELECT t.*, ca.nome AS categoria_nome
        FROM radar_topics t
        LEFT JOIN categorias_artigos ca ON ca.id = t.categoria_artigos_id
        ORDER BY t.ativo DESC, t.nome ASC
    ")->fetchAll();
    jsonResponse(['success' => true, 'topics' => $rows]);
}

function topicsSave(): void {
    global $pdo;
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $nome = trim((string)($_POST['nome'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $keywords = trim((string)($_POST['keywords'] ?? ''));
    $idiomas = trim((string)($_POST['idiomas'] ?? 'pt,en'));
    $regioes = trim((string)($_POST['regioes'] ?? 'br,us,eu'));
    $categoriaId = !empty($_POST['categoria_artigos_id']) ? (int)$_POST['categoria_artigos_id'] : null;
    $ativo = isset($_POST['ativo']) ? (bool)$_POST['ativo'] : true;

    if ($nome === '') jsonResponse(['success' => false, 'message' => 'Nome é obrigatório'], 400);

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE radar_topics
            SET nome = ?, descricao = ?, keywords = ?, idiomas = ?, regioes = ?, categoria_artigos_id = ?, ativo = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$nome, $descricao ?: null, $keywords ?: null, $idiomas, $regioes, $categoriaId, $ativo ? 't' : 'f', $id]);
        jsonResponse(['success' => true, 'message' => 'Tema atualizado']);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO radar_topics (nome, descricao, keywords, idiomas, regioes, categoria_artigos_id, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([$nome, $descricao ?: null, $keywords ?: null, $idiomas, $regioes, $categoriaId, $ativo ? 't' : 'f']);
        jsonResponse(['success' => true, 'message' => 'Tema criado', 'id' => (int)$stmt->fetchColumn()]);
    }
}

function topicsDelete(): void {
    global $pdo;
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    $pdo->prepare("DELETE FROM radar_topics WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Tema removido']);
}

function sourcesList(): void {
    global $pdo;
    $rows = $pdo->query("SELECT * FROM radar_sources ORDER BY ativo DESC, tipo ASC, nome ASC")->fetchAll();
    jsonResponse(['success' => true, 'sources' => $rows]);
}

function sourcesSave(): void {
    global $pdo;
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $nome = trim((string)($_POST['nome'] ?? ''));
    $tipo = trim((string)($_POST['tipo'] ?? 'rss'));
    $url = trim((string)($_POST['url'] ?? ''));
    $config = trim((string)($_POST['config'] ?? ''));
    $ativo = isset($_POST['ativo']) ? (bool)$_POST['ativo'] : true;

    if ($nome === '') jsonResponse(['success' => false, 'message' => 'Nome é obrigatório'], 400);
    if (!in_array($tipo, ['rss','api','scrape'], true)) jsonResponse(['success' => false, 'message' => 'Tipo inválido'], 400);
    if (($tipo === 'rss' || $tipo === 'scrape') && $url === '') jsonResponse(['success' => false, 'message' => 'URL é obrigatória'], 400);

    $cfg = null;
    if ($config !== '') {
        $decoded = json_decode($config, true);
        if (!is_array($decoded)) jsonResponse(['success' => false, 'message' => 'Config JSON inválido'], 400);
        $cfg = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE radar_sources SET nome = ?, tipo = ?, url = ?, config = ?::jsonb, ativo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$nome, $tipo, $url ?: null, $cfg, $ativo ? 't' : 'f', $id]);
        jsonResponse(['success' => true, 'message' => 'Fonte atualizada']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO radar_sources (nome, tipo, url, config, ativo) VALUES (?, ?, ?, ?::jsonb, ?) RETURNING id");
        $stmt->execute([$nome, $tipo, $url ?: null, $cfg, $ativo ? 't' : 'f']);
        jsonResponse(['success' => true, 'message' => 'Fonte criada', 'id' => (int)$stmt->fetchColumn()]);
    }
}

function sourcesDelete(): void {
    global $pdo;
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    $pdo->prepare("DELETE FROM radar_sources WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Fonte removida']);
}

function topicSourcesGet(): void {
    global $pdo;
    $topicId = !empty($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
    if (!$topicId) jsonResponse(['success' => false, 'message' => 'topic_id inválido'], 400);

    $stmt = $pdo->prepare("SELECT source_id FROM radar_topic_sources WHERE topic_id = ?");
    $stmt->execute([$topicId]);
    $ids = array_map(fn($r) => (int)$r['source_id'], $stmt->fetchAll());
    jsonResponse(['success' => true, 'source_ids' => $ids]);
}

function topicSourcesSet(): void {
    global $pdo;
    $topicId = !empty($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    $sourceIdsRaw = $_POST['source_ids'] ?? '[]';
    $sourceIds = json_decode($sourceIdsRaw, true);
    if (!$topicId) jsonResponse(['success' => false, 'message' => 'topic_id inválido'], 400);
    if (!is_array($sourceIds)) jsonResponse(['success' => false, 'message' => 'source_ids inválido'], 400);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM radar_topic_sources WHERE topic_id = ?")->execute([$topicId]);
        $stmt = $pdo->prepare("INSERT INTO radar_topic_sources (topic_id, source_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
        foreach ($sourceIds as $sid) {
            $sid = (int)$sid;
            if ($sid > 0) $stmt->execute([$topicId, $sid]);
        }
        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Fontes do tema atualizadas']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function collectRun(): void {
    global $pdo;
    $topicId = !empty($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    if (!$topicId) jsonResponse(['success' => false, 'message' => 'topic_id inválido'], 400);

    $runId = null;
    $stmt = $pdo->prepare("INSERT INTO radar_runs (status, triggered_by, meta) VALUES ('running', 'manual', :meta::jsonb) RETURNING id");
    $stmt->execute([':meta' => json_encode(['topic_id' => $topicId], JSON_UNESCAPED_UNICODE)]);
    $runId = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT source_id FROM radar_topic_sources ts JOIN radar_sources s ON s.id = ts.source_id WHERE ts.topic_id = ? AND s.ativo = true");
    $stmt->execute([$topicId]);
    $sourceIds = array_map(fn($r) => (int)$r['source_id'], $stmt->fetchAll());
    if (!$sourceIds) {
        $pdo->prepare("UPDATE radar_runs SET status='error', finished_at=CURRENT_TIMESTAMP, log=? WHERE id=?")->execute(['Sem fontes ativas para este tema', $runId]);
        jsonResponse(['success' => false, 'message' => 'Sem fontes ativas para este tema', 'run_id' => $runId], 400);
    }

    $results = [];
    $savedTotal = 0;
    $errors = [];
    foreach ($sourceIds as $sid) {
        $r = radarCollectSource($sid, [$topicId]);
        $results[] = $r;
        if (!empty($r['saved'])) $savedTotal += (int)$r['saved'];
        foreach (($r['errors'] ?? []) as $e) $errors[] = $e;
        if (!$r['success'] && !empty($r['error'])) $errors[] = $r['error'];
    }
    $errors = array_values(array_unique($errors));
    $log = "saved_total={$savedTotal}\n" . (empty($errors) ? '' : ("errors:\n- " . implode("\n- ", $errors)));

    $pdo->prepare("UPDATE radar_runs SET status=?, finished_at=CURRENT_TIMESTAMP, log=? WHERE id=?")
        ->execute([empty($errors) ? 'success' : 'error', $log, $runId]);

    jsonResponse(['success' => true, 'run_id' => $runId, 'saved_total' => $savedTotal, 'results' => $results, 'errors' => $errors]);
}

function itemsList(): void {
    global $pdo;
    $topicId = !empty($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
    $limit = !empty($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;

    if ($topicId) {
        $stmt = $pdo->prepare("
            SELECT i.*, s.nome AS source_nome, s.tipo AS source_tipo
            FROM radar_items i
            LEFT JOIN radar_sources s ON s.id = i.source_id
            JOIN radar_item_topics it ON it.item_id = i.id
            WHERE it.topic_id = ?
            ORDER BY i.score DESC, i.fetched_at DESC
            LIMIT ?
        ");
        $stmt->execute([$topicId, $limit]);
        jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
    }

    $stmt = $pdo->prepare("
        SELECT i.*, s.nome AS source_nome, s.tipo AS source_tipo
        FROM radar_items i
        LEFT JOIN radar_sources s ON s.id = i.source_id
        ORDER BY i.fetched_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    jsonResponse(['success' => true, 'items' => $stmt->fetchAll()]);
}

function ideasList(): void {
    global $pdo;
    $topicId = !empty($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
    $status = trim((string)($_GET['status'] ?? ''));
    $limit = !empty($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;

    $sql = "SELECT i.*, t.nome AS topic_nome FROM radar_ideas i JOIN radar_topics t ON t.id = i.topic_id WHERE 1=1"; // ordered by topic name then date
    $params = [];
    if ($topicId) { $sql .= " AND i.topic_id = ?"; $params[] = $topicId; }
    if ($status !== '') { $sql .= " AND i.status = ?"; $params[] = $status; }
    // Order by topic name and creation date
    $sql .= " ORDER BY t.nome ASC, i.created_at DESC";
    $sql .= " LIMIT " . (int)$limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'ideas' => $stmt->fetchAll()]);
}

function ideasGenerate(): void {
    $topicId = !empty($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
    $numIdeas = !empty($_POST['num_ideas']) ? max(3, min(15, (int)$_POST['num_ideas'])) : 8;
    if (!$topicId) jsonResponse(['success' => false, 'message' => 'topic_id inválido'], 400);

    $r = radarGenerateIdeasForTopic($topicId, 20, $numIdeas);
    if (!$r['success']) jsonResponse(['success' => false, 'message' => $r['error'] ?? 'Erro ao gerar ideias', 'debug' => $r], 500);
    jsonResponse(['success' => true, 'message' => 'Ideias geradas', 'result' => $r]);
}

function ideaDiscard(): void {
    global $pdo;
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);
    $pdo->prepare("DELETE FROM radar_ideas WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Ideia excluída']);
}

function ideaToDraft(): void {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    $mode = trim((string)($_POST['mode'] ?? 'ai'));
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);

    if ($mode === 'simple') {
        $r = radarIdeaToCreateSimpleDraft($id);
    } else {
        $r = radarIdeaToDraftArticle($id);
    }

    if (!$r['success']) jsonResponse(['success' => false, 'message' => $r['error'] ?? 'Erro ao criar rascunho', 'debug' => $r], 500);
    jsonResponse(['success' => true, 'message' => 'Rascunho criado em Conteúdos', 'result' => $r]);
}

/**
 * Apagar itens coletados (ids ou filtro URL)
 * - POST apenas
 * - Parâmetros: ids (JSON array) ou url_like (string)
 * - Gera backup CSV em /tmp antes de apagar
 */
function itemsDelete(): void {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);

    $idsRaw = $_POST['ids'] ?? '';
    $urlLike = trim((string)($_POST['url_like'] ?? ''));

    if (!$idsRaw && $urlLike === '') {
        jsonResponse(['success' => false, 'message' => 'Parâmetros inválidos: envie ids (JSON) ou url_like'], 400);
    }

    $rows = [];

    if ($idsRaw) {
        $ids = json_decode($idsRaw, true);
        if (!is_array($ids) || empty($ids)) jsonResponse(['success' => false, 'message' => 'ids inválido'], 400);
        if (count($ids) > 1000) jsonResponse(['success' => false, 'message' => 'Limite de exclusão por requisição: 1000'], 400);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM radar_items WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
    } else {
        // Segurança: exigir ao menos 3 caracteres no filtro para evitar exclusões acidentais massivas
        if (strlen($urlLike) < 3) jsonResponse(['success' => false, 'message' => 'Filtro muito curto, informe ao menos 3 caracteres'], 400);
        $stmt = $pdo->prepare("SELECT * FROM radar_items WHERE url LIKE ?");
        $stmt->execute(["%{$urlLike}%"]);
        $rows = $stmt->fetchAll();
    }

    if (empty($rows)) jsonResponse(['success' => false, 'message' => 'Nenhum item encontrado'], 404);

    // Backup CSV
    $ts = date('YmdHis');
    $file = sys_get_temp_dir() . "/radar_items_delete_backup_{$ts}.csv";

    $fp = fopen($file, 'w');
    if ($fp === false) jsonResponse(['success' => false, 'message' => 'Erro ao criar backup'], 500);

    // Cabeçalho com colunas retornadas
    fputcsv($fp, array_keys($rows[0]));
    foreach ($rows as $r) {
        // Normalizar valores para CSV
        $line = array_map(function($v) {
            if (is_null($v)) return '';
            if (is_array($v) || is_object($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
            return (string)$v;
        }, $r);
        fputcsv($fp, $line);
    }
    fclose($fp);

    // Deletar
    $pdo->beginTransaction();
    try {
        if ($idsRaw) {
            $stmt = $pdo->prepare("DELETE FROM radar_items WHERE id IN ($placeholders)");
            $stmt->execute($ids);
        } else {
            $stmt = $pdo->prepare("DELETE FROM radar_items WHERE url LIKE ?");
            $stmt->execute(["%{$urlLike}%"]);
        }
        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Itens removidos', 'deleted_count' => count($rows), 'backup_file' => $file]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function analyzeHype(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
    
    $result = radarAnalyzeHype();
    jsonResponse($result);
}
function ideaSources(): void {
    global $pdo;
    $id = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) jsonResponse(['success' => false, 'message' => 'ID inválido'], 400);

    $stmt = $pdo->prepare("SELECT source_item_ids FROM radar_ideas WHERE id = ?");
    $stmt->execute([$id]);
    $idsRaw = $stmt->fetchColumn();
    if (!$idsRaw) {
        jsonResponse(['success' => true, 'sources' => []]);
        return;
    }

    $ids = json_decode($idsRaw, true);
    if (!is_array($ids) || empty($ids)) {
        jsonResponse(['success' => true, 'sources' => []]);
        return;
    }

    // Filtrar apenas IDs válidos
    $ids = array_filter($ids, fn($x) => is_numeric($x));
    if (empty($ids)) {
        jsonResponse(['success' => true, 'sources' => []]);
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, titulo, url, score, fetched_at FROM radar_items WHERE id IN ($placeholders) ORDER BY score DESC LIMIT 10");
    $stmt->execute(array_values($ids));
    jsonResponse(['success' => true, 'sources' => $stmt->fetchAll()]);
}
