<?php
/**
 * WASHIVIANA PORTFOLIO - API de Artigos/Conteúdos
 */

// Iniciar buffer de saída para capturar qualquer output indesejado
if (!ob_get_level()) {
    ob_start();
}

// Suprimir erros HTML - retornar apenas JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Garantir header JSON antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Capturar erros fatais e limpar buffer
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean(); // Limpar qualquer output anterior
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/config.php';

// Versão da API LinkedIn REST (deve corresponder à versão em linkedin.php)
// Usar a data atual YYYYMM por padrão para evitar `NONEXISTENT_VERSION` antigo
if (!defined('LINKEDIN_API_VERSION')) define('LINKEDIN_API_VERSION', date('Ym')); // ex: 202601 (pode ser sobrescrito se necessário)

// Verificar autenticação
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF para requisições POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        // Log detalhado para diagnosticar CSRF failures
        $sessionId = session_id();
        $hasUser = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $sessionCsrf = isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 6) . '...' : 'NULL';
        $postedCsrf = $csrf ? (substr($csrf, 0, 6) . '...') : 'NULL';
        $lastAct = isset($_SESSION['last_activity']) ? date('c', $_SESSION['last_activity']) : 'NULL';
        error_log("CSRF_FAIL: session_id={$sessionId} user_id={$hasUser} session_csrf={$sessionCsrf} posted_csrf={$postedCsrf} last_activity={$lastAct}");
        // DEBUG: log full token length and first 40 chars (temporary)
        error_log("CSRF_FAIL_DEBUG: posted_len=" . strlen($csrf) . " posted_prefix=" . substr($csrf,0,40) . "");
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
}

try {
    switch ($action) {
        case 'create':
            criarArtigo();
            break;
            
        case 'update':
            atualizarArtigo();
            break;
            
        case 'delete':
            deletarArtigo();
            break;
            
        case 'get':
            buscarArtigo();
            break;
            
        case 'list':
            listarArtigos();
            break;
            
        case 'get_publicacoes':
            buscarPublicacoes();
            break;

        case 'list_social_variants':
            listarSocialVariants();
            break;

        case 'save_social_variant':
            salvarSocialVariant();
            break;

        case 'delete_social_variant':
            deletarSocialVariant();
            break;

        case 'publish_social_variant':
            publishSocialVariant();
            break;

        case 'delete_linkedin_post':
            deletarPostLinkedIn();
            break;

        case 'get_linkedin_status':
            verificarStatusLinkedIn();
            break;

        case 'publish_now_linkedin':
            publicarAgoraLinkedIn();
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Ação não especificada']);
    }
} catch (Exception $e) {
    error_log("Artigos API Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()], 500);
}

/**
 * Criar novo artigo
 */
function criarArtigo() {
    global $pdo;
    
    $titulo = trim($_POST['titulo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $resumo = trim($_POST['resumo'] ?? '');
    $conteudo = trim($_POST['conteudo'] ?? '');
    $categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
    $autor = trim($_POST['autor'] ?? 'Washington Viana');
    $tipo_midia = $_POST['tipo_midia'] ?? 'imagem';
    $destaque = isset($_POST['destaque']) && $_POST['destaque'] ? true : false;
    
    // Novos campos de prompt
    $prompt_texto = trim($_POST['prompt_texto'] ?? '');
    $prompt_imagem = trim($_POST['prompt_imagem'] ?? '');
    
    // Novos campos de imagem em formatos
    $imagem_1x1 = trim($_POST['imagem_1x1'] ?? '');
    $imagem_9x16 = trim($_POST['imagem_9x16'] ?? '');
    
    // Campos de agendamento
    $status_publicacao = $_POST['status_publicacao'] ?? 'rascunho';
    $data_agendamento = !empty($_POST['data_agendamento']) ? $_POST['data_agendamento'] : null;
    // Normalizar data_agendamento: aceitar formato 'YYYY-MM-DDTHH:MM' (datetime-local) e gravar como 'Y-m-d H:i:s' no fuso do servidor
    if (!empty($data_agendamento)) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $data_agendamento, new DateTimeZone(date_default_timezone_get()));
        if ($dt) {
            $data_agendamento = $dt->format('Y-m-d H:i:s');
        } else {
            // tentar parsear formatos comuns
            $dt2 = date_create($data_agendamento);
            if ($dt2) $data_agendamento = $dt2->format('Y-m-d H:i:s');
            else $data_agendamento = null;
        }
    }
    $recorrencia_tipo = $_POST['recorrencia_tipo'] ?? 'nenhuma';
    $recorrencia_dias = $_POST['recorrencia_dias'] ?? '';
    $recorrencia_dia_mes = !empty($_POST['recorrencia_dia_mes']) ? (int)$_POST['recorrencia_dia_mes'] : null;
    $recorrencia_fim = !empty($_POST['recorrencia_fim']) ? $_POST['recorrencia_fim'] : null;
    
    // Redes sociais destino
    $redes_destino = $_POST['redes_destino'] ?? '[]';
    
    // Validações
    if (empty($titulo)) {
        jsonResponse(['success' => false, 'message' => 'Título é obrigatório']);
    }
    
    if (empty($conteudo)) {
        jsonResponse(['success' => false, 'message' => 'Conteúdo é obrigatório']);
    }
    
    // Gerar slug se não fornecido
    if (empty($slug)) {
        $slug = gerarSlug($titulo);
    }
    
    // Verificar se slug já existe
    $stmt = $pdo->prepare("SELECT id FROM artigos WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . time();
    }
    
    // Upload de imagem principal (fallback)
    $imagem_principal = null;
    if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
        $imagem_principal = uploadArquivo($_FILES['imagem_principal'], 'imagem');
    }
    
    // Upload de vídeo
    $video_url = null;
    if (isset($_FILES['video_arquivo']) && $_FILES['video_arquivo']['error'] === UPLOAD_ERR_OK) {
        $video_url = uploadArquivo($_FILES['video_arquivo'], 'video');
    }
    
    // Inserir no banco
    $stmt = $pdo->prepare("
        INSERT INTO artigos (
            titulo, slug, resumo, conteudo, categoria_id, autor, 
            tipo_midia, imagem_principal, video_url, destaque,
            prompt_texto, prompt_imagem, imagem_1x1, imagem_9x16,
            status_publicacao, data_agendamento, 
            recorrencia_tipo, recorrencia_dias, recorrencia_dia_mes, recorrencia_fim,
            redes_destino, data_publicacao
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $data_publicacao = ($status_publicacao === 'publicado') ? date('Y-m-d H:i:s') : null;
    
    $stmt->execute([
        $titulo, $slug, $resumo, $conteudo, $categoria_id, $autor,
        $tipo_midia, $imagem_principal, $video_url, $destaque ? 't' : 'f',
        $prompt_texto, $prompt_imagem, $imagem_1x1 ?: null, $imagem_9x16 ?: null,
        $status_publicacao, $data_agendamento,
        $recorrencia_tipo, $recorrencia_dias ?: null, $recorrencia_dia_mes, $recorrencia_fim,
        $redes_destino, $data_publicacao
    ]);
    
    $artigo_id = $pdo->lastInsertId();
    
    // Publicação automática no LinkedIn quando criado já como "publicado" e com LinkedIn selecionado.
    $publicacoes = [];
    $redesDestinoArray = json_decode($redes_destino, true) ?? [];
    $redesIds = array_column($redesDestinoArray, 'rede');
    $querPublicarLinkedIn = in_array('linkedin', $redesIds, true);
    $isAgendado = ($status_publicacao === 'agendado' && !empty($data_agendamento));

    if ($status_publicacao === 'publicado' && $querPublicarLinkedIn && !$isAgendado) {
        error_log("criarArtigo: Publicação imediata no LinkedIn para artigo $artigo_id");

        $siteUrl = getConfig('site_url') ?: 'https://washiviana.com';
        $siteUrl = rtrim($siteUrl, '/');
        $urlArtigo = $siteUrl . '/pt/artigo/' . urlencode($slug);
        $textoPost = $titulo . "\n\n" . ($resumo ?: '');

        $dadosArtigo = [
            'titulo' => $titulo,
            'resumo' => $resumo,
            'imagem_1x1' => $imagem_1x1,
            'imagem_principal' => $imagem_principal
        ];

        $resultado = publicarNoLinkedInViaAPI($artigo_id, $textoPost, $urlArtigo, $dadosArtigo);
        $publicacoes['linkedin'] = $resultado;

        if (!empty($resultado['success'])) {
            $linkedinPostId = $resultado['post_id'] ?? $resultado['linkedin_post_id'] ?? null;
            if ($linkedinPostId) {
                $stmtUpdate = $pdo->prepare("UPDATE artigos SET linkedin_post_id = ? WHERE id = ?");
                $stmtUpdate->execute([$linkedinPostId, $artigo_id]);
            }
            error_log("criarArtigo: LinkedIn publicado com sucesso para artigo $artigo_id");
        } else {
            error_log("criarArtigo: Erro ao publicar no LinkedIn: " . ($resultado['message'] ?? 'desconhecido'));
        }
    }

    $mensagem = 'Conteúdo criado com sucesso!';
    if (!empty($publicacoes['linkedin']['success'])) {
        $mensagem .= ' Publicado no LinkedIn!';
    } elseif (!empty($publicacoes['linkedin']['message'])) {
        $mensagem .= ' Erro LinkedIn: ' . $publicacoes['linkedin']['message'];
    }

    jsonResponse([
        'success' => true,
        'message' => $mensagem,
        'artigo_id' => $artigo_id,
        'publicacoes' => $publicacoes
    ]);
}

/**
 * Atualizar artigo existente
 */
function atualizarArtigo() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID não fornecido']);
    }
    
    // Buscar artigo atual
    $stmt = $pdo->prepare("SELECT * FROM artigos WHERE id = ?");
    $stmt->execute([$id]);
    $artigo = $stmt->fetch();
    
    if (!$artigo) {
        jsonResponse(['success' => false, 'message' => 'Artigo não encontrado']);
    }
    
    $titulo = trim($_POST['titulo'] ?? $artigo['titulo']);
    $slug = trim($_POST['slug'] ?? $artigo['slug']);
    $resumo = trim($_POST['resumo'] ?? '');
    $conteudo = trim($_POST['conteudo'] ?? '');
    $categoria_id = $_POST['categoria_id'] ?: null;
    $autor = trim($_POST['autor'] ?? $artigo['autor']);
    $tipo_midia = $_POST['tipo_midia'] ?? $artigo['tipo_midia'];
    $destaque = isset($_POST['destaque']) ? true : false;
    
    // Novos campos de prompt
    $prompt_texto = trim($_POST['prompt_texto'] ?? $artigo['prompt_texto'] ?? '');
    $prompt_imagem = trim($_POST['prompt_imagem'] ?? $artigo['prompt_imagem'] ?? '');
    
    // Novos campos de imagem em formatos
    $imagem_1x1 = trim($_POST['imagem_1x1'] ?? $artigo['imagem_1x1'] ?? '');
    $imagem_9x16 = trim($_POST['imagem_9x16'] ?? $artigo['imagem_9x16'] ?? '');
    
    // Campos de agendamento
    $status_publicacao = $_POST['status_publicacao'] ?? $artigo['status_publicacao'] ?? 'rascunho';
    $data_agendamento = !empty($_POST['data_agendamento']) ? $_POST['data_agendamento'] : $artigo['data_agendamento'];
    // Normalizar data_agendamento como acima (datetime-local -> Y-m-d H:i:s)
    if (!empty($data_agendamento)) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $data_agendamento, new DateTimeZone(date_default_timezone_get()));
        if ($dt) {
            $data_agendamento = $dt->format('Y-m-d H:i:s');
        } else {
            $dt2 = date_create($data_agendamento);
            if ($dt2) $data_agendamento = $dt2->format('Y-m-d H:i:s');
            else $data_agendamento = $artigo['data_agendamento'];
        }
    }
    $recorrencia_tipo = $_POST['recorrencia_tipo'] ?? $artigo['recorrencia_tipo'] ?? 'nenhuma';
    $recorrencia_dias = $_POST['recorrencia_dias'] ?? $artigo['recorrencia_dias'] ?? '';
    $recorrencia_dia_mes = !empty($_POST['recorrencia_dia_mes']) ? (int)$_POST['recorrencia_dia_mes'] : $artigo['recorrencia_dia_mes'];
    $recorrencia_fim = !empty($_POST['recorrencia_fim']) ? $_POST['recorrencia_fim'] : $artigo['recorrencia_fim'];
    
    // Redes sociais destino
    $redes_destino = $_POST['redes_destino'] ?? $artigo['redes_destino'] ?? '[]';
    
    // Upload de imagem principal (fallback)
    $imagem_principal = $artigo['imagem_principal'];
    if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
        $imagem_principal = uploadArquivo($_FILES['imagem_principal'], 'imagem');
    }
    
    // Upload de vídeo
    $video_url = $artigo['video_url'];
    if (isset($_FILES['video_arquivo']) && $_FILES['video_arquivo']['error'] === UPLOAD_ERR_OK) {
        $video_url = uploadArquivo($_FILES['video_arquivo'], 'video');
    }
    
    // Data de publicação
    $data_publicacao = $artigo['data_publicacao'];
    $mudouParaPublicado = ($status_publicacao === 'publicado' && ($artigo['status_publicacao'] ?? '') !== 'publicado');
    if ($mudouParaPublicado) {
        $data_publicacao = date('Y-m-d H:i:s');
    }
    
    // Atualizar no banco
    $stmt = $pdo->prepare("
        UPDATE artigos SET
            titulo = ?, slug = ?, resumo = ?, conteudo = ?, categoria_id = ?, autor = ?,
            tipo_midia = ?, imagem_principal = ?, video_url = ?, destaque = ?,
            prompt_texto = ?, prompt_imagem = ?, imagem_1x1 = ?, imagem_9x16 = ?,
            status_publicacao = ?, data_agendamento = ?,
            recorrencia_tipo = ?, recorrencia_dias = ?, recorrencia_dia_mes = ?, recorrencia_fim = ?,
            redes_destino = ?, data_publicacao = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([
        $titulo, $slug, $resumo, $conteudo, $categoria_id, $autor,
        $tipo_midia, $imagem_principal, $video_url, $destaque ? 't' : 'f',
        $prompt_texto, $prompt_imagem, $imagem_1x1 ?: null, $imagem_9x16 ?: null,
        $status_publicacao, $data_agendamento,
        $recorrencia_tipo, $recorrencia_dias ?: null, $recorrencia_dia_mes, $recorrencia_fim,
        $redes_destino, $data_publicacao, $id
    ]);

    // Publicação automática no LinkedIn quando:
    // 1. Mudou para status "publicado" (não agendado)
    // 2. LinkedIn está nas redes destino
    // 3. Não é agendamento (publicação imediata)
    // 4. Artigo ainda não foi publicado no LinkedIn
    $publicacoes = [];
    $redesDestinoArray = json_decode($redes_destino, true) ?? [];
    $redesIds = array_column($redesDestinoArray, 'rede');
    $querPublicarLinkedIn = in_array('linkedin', $redesIds);
    $isAgendado = ($status_publicacao === 'agendado' && !empty($data_agendamento));
    $jaTemLinkedInPost = !empty($artigo['linkedin_post_id']);

    if ($mudouParaPublicado && $querPublicarLinkedIn && !$isAgendado && !$jaTemLinkedInPost) {
        error_log("atualizarArtigo: Publicação imediata no LinkedIn para artigo $id");

        // Construir URL do artigo
        $siteUrl = getConfig('site_url') ?: 'https://washiviana.com';
        $siteUrl = rtrim($siteUrl, '/');
        $urlArtigo = $siteUrl . '/pt/artigo/' . urlencode($slug);

        // Texto do post (pode ser customizado no futuro)
        $textoPost = $titulo . "\n\n" . ($resumo ?: '');

        // Dados do artigo para a publicação
        $dadosArtigo = [
            'titulo' => $titulo,
            'resumo' => $resumo,
            'imagem_1x1' => $imagem_1x1,
            'imagem_principal' => $imagem_principal
        ];

        // Publicar no LinkedIn
        $resultado = publicarNoLinkedInViaAPI($id, $textoPost, $urlArtigo, $dadosArtigo);
        $publicacoes['linkedin'] = $resultado;

        if ($resultado['success']) {
            // Atualizar linkedin_post_id no banco
            $linkedinPostId = $resultado['post_id'] ?? $resultado['linkedin_post_id'] ?? null;
            if ($linkedinPostId) {
                $stmtUpdate = $pdo->prepare("UPDATE artigos SET linkedin_post_id = ? WHERE id = ?");
                $stmtUpdate->execute([$linkedinPostId, $id]);
            }
            error_log("atualizarArtigo: LinkedIn publicado com sucesso para artigo $id");
        } else {
            error_log("atualizarArtigo: Erro ao publicar no LinkedIn: " . ($resultado['message'] ?? 'desconhecido'));
        }
    }

    $mensagem = 'Conteúdo atualizado com sucesso!';
    if (!empty($publicacoes['linkedin']['success'])) {
        $mensagem .= ' Publicado no LinkedIn!';
    } elseif (!empty($publicacoes['linkedin']['message'])) {
        $mensagem .= ' Erro LinkedIn: ' . $publicacoes['linkedin']['message'];
    }

    jsonResponse([
        'success' => true,
        'message' => $mensagem,
        'publicacoes' => $publicacoes
    ]);
}

/**
 * Deletar artigo
 * Opcionalmente deleta também das redes sociais
 */
function deletarArtigo() {
    global $pdo;

    error_log('deletarArtigo: Inicio chamada. _POST keys: ' . implode(',', array_keys($_POST ?? [])));
    $startTs = microtime(true);

    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    $deletarRedes = $_POST['deletar_redes'] ?? '0';
    $redesDeletar = json_decode($_POST['redes_deletar'] ?? '[]', true);
    $deletarLinkedIn = $_POST['deletar_linkedin'] ?? '0';

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID não fornecido']);
    }

    // Buscar artigo para remover arquivos e linkedin_post_id
    $stmt = $pdo->prepare("SELECT imagem_principal, video_url, linkedin_post_id, imagem_1x1, imagem_9x16 FROM artigos WHERE id = ?");
    $stmt->execute([$id]);
    $artigo = $stmt->fetch();

    $redesDeletadas = [];
    $linkedinDeletado = false;

    // Deletar das redes sociais se solicitado
    if ($deletarRedes === '1' && !empty($redesDeletar)) {
        foreach ($redesDeletar as $rede) {
            $resultado = deletarPostRede($rede['rede'], $rede['post_id'], $rede['id']);
            $redesDeletadas[] = $resultado;
        }
    }

    // Deletar do LinkedIn se solicitado e tiver post vinculado
    if ($deletarLinkedIn === '1' && !empty($artigo['linkedin_post_id'])) {
        $resultado = chamarLinkedInDelete($artigo['linkedin_post_id']);
        $linkedinDeletado = $resultado['success'];
        if ($resultado['success']) {
            error_log("Artigo $id: Post LinkedIn deletado com sucesso: " . $artigo['linkedin_post_id']);
        } else {
            error_log("Artigo $id: Falha ao deletar post LinkedIn: " . ($resultado['message'] ?? 'erro desconhecido'));
        }
    }
    
    if ($artigo) {
        // Remover arquivos
        if ($artigo['imagem_principal']) {
            @unlink(UPLOAD_DIR . $artigo['imagem_principal']);
        }
        if ($artigo['video_url']) {
            @unlink(UPLOAD_DIR . $artigo['video_url']);
        }
    }
    
    // Deletar publicações do banco (marcar como deletadas) - se tabela existir
    try {
        $stmt = $pdo->prepare("UPDATE publicacoes_redes SET status = 'deletado' WHERE artigo_id = ?");
        $stmt->execute([$id]);
    } catch (Exception $e) {
        // Tabela pode não existir, ignorar
    }
    
    // Deletar artigo do banco
    $stmt = $pdo->prepare("DELETE FROM artigos WHERE id = ?");
    $stmt->execute([$id]);

    $elapsed = round(microtime(true) - $startTs, 3);
    error_log("deletarArtigo: DELETE completo. artigo_id={$id} elapsed={$elapsed}s");

    jsonResponse([
        'success' => true,
        'message' => 'Conteúdo excluído com sucesso!',
        'redes_deletadas' => $redesDeletadas,
        'linkedin_deletado' => $linkedinDeletado
    ]);
}

/**
 * Buscar publicações do artigo nas redes sociais
 */
function buscarPublicacoes() {
    global $pdo;

    $id = $_GET['id'] ?? null;

    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID não fornecido', 'publicacoes' => []]);
    }

    $publicacoes = [];
    $linkedinPostId = null;

    try {
        // Buscar linkedin_post_id diretamente do artigo
        $stmt = $pdo->prepare("SELECT linkedin_post_id FROM artigos WHERE id = ?");
        $stmt->execute([$id]);
        $artigo = $stmt->fetch();
        $linkedinPostId = $artigo['linkedin_post_id'] ?? null;

        // Verificar se a tabela publicacoes_redes existe
        $stmt = $pdo->query("SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_name = 'publicacoes_redes'
        )");
        $tabelaExiste = $stmt->fetchColumn();

        if ($tabelaExiste) {
            // Buscar publicações da tabela
            $stmt = $pdo->prepare("
                SELECT id, rede, post_id, status,
                       TO_CHAR(publicado_em, 'DD/MM/YYYY HH24:MI') as publicado_em
                FROM publicacoes_redes
                WHERE artigo_id = ? AND status = 'publicado'
                ORDER BY publicado_em DESC
            ");
            $stmt->execute([$id]);
            $publicacoes = $stmt->fetchAll();
        }

        // Se tiver linkedin_post_id mas não estiver nas publicações, adicionar
        if ($linkedinPostId && !array_filter($publicacoes, fn($p) => $p['rede'] === 'linkedin')) {
            array_unshift($publicacoes, [
                'id' => null,
                'rede' => 'linkedin',
                'post_id' => $linkedinPostId,
                'status' => 'publicado',
                'publicado_em' => null
            ]);
        }

        jsonResponse([
            'success' => true,
            'publicacoes' => $publicacoes,
            'linkedin_post_id' => $linkedinPostId
        ]);

    } catch (Exception $e) {
        error_log("publicacoes_artigo_error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao buscar publicações', 'publicacoes' => []]);
    }
}

// ---------------------------------------------
// Social Variants API (instagram/facebook) - CRUD
// ---------------------------------------------
function listarSocialVariants() {
        global $pdo;

        $artigoId = $_GET['artigo_id'] ?? null;
        if (!$artigoId) {
            jsonResponse(['success' => false, 'message' => 'artigo_id não fornecido']);
        }

        $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE artigo_id = ? ORDER BY created_at DESC");
        $stmt->execute([$artigoId]);
        $rows = $stmt->fetchAll();

        jsonResponse(['success' => true, 'variants' => $rows]);
    }

    function salvarSocialVariant() {
        global $pdo;

        $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
        $artigoId = !empty($_POST['artigo_id']) ? intval($_POST['artigo_id']) : null;
        $rede = trim($_POST['rede'] ?? 'instagram');
        $titulo = trim($_POST['titulo'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $hashtags = trim($_POST['hashtags'] ?? '');
        $media_type = trim($_POST['media_type'] ?? 'imagem');
        $image_1x1 = trim($_POST['image_1x1'] ?? '');
        $image_9x16 = trim($_POST['image_9x16'] ?? '');
        $video_file = trim($_POST['video_file'] ?? '');
        $scheduled_at = trim($_POST['scheduled_at'] ?? null);
        // Normalizar scheduled_at: tratar string vazia como NULL e validar formato se fornecido
        if ($scheduled_at === '' || $scheduled_at === null) {
            $scheduled_at = null;
        } else {
            // Tentar parsear a data (aceita formatos variados) e formatar para TIMESTAMP do Postgres
            $dt = date_create($scheduled_at);
            if ($dt) {
                $scheduled_at = $dt->format('Y-m-d H:i:s');
            } else {
                // Valor inválido -> desconsiderar agendamento
                error_log("salvarSocialVariant: invalid scheduled_at provided: " . substr($scheduled_at, 0, 200));
                $scheduled_at = null;
            }
        }
        $status = trim($_POST['status'] ?? 'rascunho');
        if (!$artigoId) {
            jsonResponse(['success' => false, 'message' => 'artigo_id não fornecido']);
        }

        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE artigos_social_variants SET rede = ?, titulo = ?, caption = ?, hashtags = ?, media_type = ?, image_1x1 = ?, image_9x16 = ?, video_file = ?, scheduled_at = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND artigo_id = ?");
                $stmt->execute([$rede, $titulo, $caption, $hashtags, $media_type, $image_1x1, $image_9x16, $video_file, $scheduled_at, $status, $id, $artigoId]);
                $savedId = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO artigos_social_variants (artigo_id, rede, titulo, caption, hashtags, media_type, image_1x1, image_9x16, video_file, scheduled_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$artigoId, $rede, $titulo, $caption, $hashtags, $media_type, $image_1x1, $image_9x16, $video_file, $scheduled_at, $status]);
                $savedId = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE id = ?");
            $stmt->execute([$savedId]);
            $row = $stmt->fetch();

            jsonResponse(['success' => true, 'variant' => $row]);
        } catch (Exception $e) {
            error_log("Erro ao salvar variante social: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Erro ao salvar variante social: ' . $e->getMessage()]);
        }
    }

    function deletarSocialVariant() {
        global $pdo;

        $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'id não fornecido']);
        }

        try {
            // Garantir que o registro exista
            $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonResponse(['success' => false, 'message' => 'Registro não encontrado']);
            }

            // Apagar arquivos associados (imagem/video) apenas se existirem e parecerem locais
            foreach (['image_1x1', 'image_9x16', 'video_file'] as $field) {
                if (!empty($row[$field])) {
                    $path = UPLOAD_DIR . $row[$field];
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }

            $stmt = $pdo->prepare("DELETE FROM artigos_social_variants WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(['success' => true, 'message' => 'Variante social deletada com sucesso']);
        } catch (Exception $e) {
            error_log("Erro ao deletar variante social: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Erro ao deletar variante social: ' . $e->getMessage()]);
        }
    }

    // Marcar imediatamente uma variante social como pronta/gerada para publicação manual (não publicamos via API automaticamente)
    function publishSocialVariant() {
        global $pdo;

        $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
        if (!$id) {
            jsonResponse(['success' => false, 'message' => 'id não fornecido']);
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE id = ?");
            $stmt->execute([$id]);
            $variant = $stmt->fetch();
            if (!$variant) {
                jsonResponse(['success' => false, 'message' => 'Variante não encontrada']);
            }

            // Somente marcar se estiver com status 'pronto'
            if ((($variant['status'] ?? '') !== 'pronto')) {
                jsonResponse(['success' => false, 'message' => "Esta variante não está com status 'pronto'. Defina-a como 'pronto' antes de marcar."]);
            }

            $artigoId = $variant['artigo_id'];
            $rede = $variant['rede'];

            // Registrar que a variante foi gerada/está pronta para publicação manual
            try {
                $stmt = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, conteudo, status, publicado_em, created_at) VALUES (?, ?, ?, ?, ?, 'gerado', NULL, CURRENT_TIMESTAMP)");
                $stmt->execute([$rede, $artigoId, null, null, $variant['caption'] ?? '']);
            } catch (Exception $e) {
                // Se tabela não existir ou outro erro, apenas continuar
                error_log('publishSocialVariant - não foi possível registrar em publicacoes_redes: ' . $e->getMessage());
            }

            // Marcar variante como pronta para publicação manual
            $stmt = $pdo->prepare("UPDATE artigos_social_variants SET status = 'pronto_para_publicacao', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(['success' => true, 'message' => 'Variante marcada como pronta para publicação manual']);
        } catch (Exception $e) {
            error_log('Erro publishSocialVariant: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

/**
 * Deletar post de uma rede social específica
 */
function deletarPostRede($rede, $postId, $publicacaoId) {
    global $pdo;
    
    $resultado = [
        'rede' => $rede,
        'post_id' => $postId,
        'success' => false,
        'error' => null
    ];
    
    try {
        error_log('deletarPostRede: Iniciando deleção rede=' . $rede . ' postId=' . $postId . ' publicacaoId=' . $publicacaoId);
        if ($rede === 'linkedin') {
            // Chamar API do LinkedIn para deletar
            $response = chamarLinkedInDelete($postId);
            $resultado['success'] = $response['success'];
            $resultado['error'] = $response['message'] ?? ($response['error'] ?? null);
            error_log('deletarPostRede: resultado LinkedIn: ' . json_encode($response));
        } elseif ($rede === 'instagram') {
            // Instagram não permite deletar via API facilmente
            $resultado['success'] = false;
            $resultado['error'] = 'Instagram não suporta exclusão via API. Delete manualmente.';
            error_log('deletarPostRede: instagram deletion not supported');
        }
        
        // Atualizar status no banco
        if ($resultado['success']) {
            $stmt = $pdo->prepare("UPDATE publicacoes_redes SET status = 'deletado' WHERE id = ?");
            $stmt->execute([$publicacaoId]);
        }
        
    } catch (Exception $e) {
        $resultado['error'] = $e->getMessage();
    }
    
    return $resultado;
}

/**
 * Chamar API do LinkedIn para deletar post
 */
function chamarLinkedInDelete($postUrn) {
    global $pdo;
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token'])) {
        return ['success' => false, 'message' => 'LinkedIn não configurado'];
    }
    
    // URL encode do URN
    $encodedUrn = urlencode($postUrn);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/rest/posts/' . $encodedUrn,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        // Timeouts para evitar bloquear indefinidamente
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION
        ]
    ]);
    
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlErr) {
        error_log('chamarLinkedInDelete: cURL error: ' . $curlErr);
    }    
    if ($httpCode === 204 || $httpCode === 200) {
        return ['success' => true];
    } else {
        $errorData = json_decode($response, true);
        return [
            'success' => false,
            'message' => $errorData['message'] ?? 'Erro HTTP ' . $httpCode
        ];
    }
}

/**
 * Verificar se um post ainda existe no LinkedIn.
 * Retorna:
 * - success=true, exists=true/false quando conseguiu consultar
 * - success=false quando não foi possível validar (token/HTTP/cURL)
 */
function verificarPostLinkedInExiste($postUrn) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();

    if (!$config || empty($config['access_token'])) {
        return ['success' => false, 'message' => 'LinkedIn não configurado'];
    }

    $encodedUrn = urlencode($postUrn);

    // Algumas versões mensais podem não estar ativas para todos os endpoints.
    $candidateVersions = array_unique(array_filter([
        LINKEDIN_API_VERSION,
        date('Ym'),
        date('Ym', strtotime('-1 month')),
        date('Ym', strtotime('-2 month')),
        '202601',
        '202501',
        '202412',
        '202411',
        null // tentativa final sem LinkedIn-Version
    ], function($v) { return $v === null || preg_match('/^\d{6}$/', (string)$v); }));

    $lastError = null;

    foreach ($candidateVersions as $version) {
        $headers = [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0'
        ];
        if ($version !== null) {
            $headers[] = 'LinkedIn-Version: ' . $version;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.linkedin.com/rest/posts/' . $encodedUrn,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            $lastError = 'Erro cURL ao verificar post: ' . $curlErr;
            continue;
        }

        if ($httpCode === 200) {
            return ['success' => true, 'exists' => true, 'version_used' => $version];
        }

        if ($httpCode === 404) {
            return ['success' => true, 'exists' => false, 'version_used' => $version];
        }

        $errorData = json_decode((string)$response, true);
        $msg = (string)($errorData['message'] ?? ('HTTP ' . $httpCode));

        // Se versão não ativa, tenta próxima versão automaticamente.
        if (stripos($msg, 'Requested version') !== false || stripos($msg, 'NONEXISTENT_VERSION') !== false) {
            $lastError = $msg;
            continue;
        }

        // Para outros erros, interrompe e retorna imediatamente.
        return [
            'success' => false,
            'message' => 'Não foi possível verificar o post no LinkedIn: ' . $msg,
            'http_code' => $httpCode
        ];
    }

    return [
        'success' => false,
        'message' => 'Não foi possível verificar o post no LinkedIn: ' . ($lastError ?: 'falha de versão da API')
    ];
}

/**
 * Deletar post do LinkedIn (endpoint da API)
 */
function deletarPostLinkedIn() {
    global $pdo;

    $artigoId = $_POST['artigo_id'] ?? null;

    if (!$artigoId) {
        jsonResponse(['success' => false, 'message' => 'ID do artigo não fornecido']);
    }

    // Buscar linkedin_post_id do artigo
    $stmt = $pdo->prepare("SELECT linkedin_post_id FROM artigos WHERE id = ?");
    $stmt->execute([$artigoId]);
    $artigo = $stmt->fetch();

    if (!$artigo) {
        jsonResponse(['success' => false, 'message' => 'Artigo não encontrado']);
    }

    if (empty($artigo['linkedin_post_id'])) {
        jsonResponse(['success' => false, 'message' => 'Este artigo não tem post vinculado no LinkedIn']);
    }

    $postUrn = $artigo['linkedin_post_id'];

    // Tentar deletar do LinkedIn
    $resultado = chamarLinkedInDelete($postUrn);

    if ($resultado['success']) {
        // Limpar linkedin_post_id do artigo
        $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = NULL WHERE id = ?");
        $stmt->execute([$artigoId]);

        // Atualizar status na tabela publicacoes_redes
        $stmt = $pdo->prepare("UPDATE publicacoes_redes SET status = 'deletado' WHERE artigo_id = ? AND rede = 'linkedin'");
        $stmt->execute([$artigoId]);

        jsonResponse([
            'success' => true,
            'message' => 'Post deletado do LinkedIn com sucesso! Agora você pode republicar o artigo.'
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao deletar do LinkedIn: ' . ($resultado['message'] ?? 'Erro desconhecido')
        ]);
    }
}

/**
 * Verificar status de publicação no LinkedIn
 */
function verificarStatusLinkedIn() {
    global $pdo;

    $artigoId = $_GET['artigo_id'] ?? null;

    if (!$artigoId) {
        jsonResponse(['success' => false, 'message' => 'ID do artigo não fornecido']);
    }

    // Buscar linkedin_post_id do artigo
    $stmt = $pdo->prepare("SELECT linkedin_post_id FROM artigos WHERE id = ?");
    $stmt->execute([$artigoId]);
    $artigo = $stmt->fetch();

    if (!$artigo) {
        jsonResponse(['success' => false, 'message' => 'Artigo não encontrado']);
    }

    $jaPublicado = !empty($artigo['linkedin_post_id']);

    jsonResponse([
        'success' => true,
        'publicado' => $jaPublicado,
        'post_urn' => $artigo['linkedin_post_id'] ?? null
    ]);
}

/**
 * Publicar imediatamente no LinkedIn para um artigo já existente.
 * Útil para artigos já publicados no site que ainda não possuem linkedin_post_id.
 */
function publicarAgoraLinkedIn() {
    global $pdo;

    $artigoId = $_POST['artigo_id'] ?? null;
    if (!$artigoId) {
        jsonResponse(['success' => false, 'message' => 'ID do artigo não fornecido']);
    }

    $stmt = $pdo->prepare("SELECT id, titulo, resumo, conteudo, slug, status_publicacao, redes_destino, linkedin_post_id, imagem_1x1, imagem_principal FROM artigos WHERE id = ?");
    $stmt->execute([$artigoId]);
    $artigo = $stmt->fetch();

    if (!$artigo) {
        jsonResponse(['success' => false, 'message' => 'Artigo não encontrado']);
    }

    if (($artigo['status_publicacao'] ?? '') !== 'publicado') {
        jsonResponse(['success' => false, 'message' => 'O artigo precisa estar com status "publicado" para enviar ao LinkedIn.']);
    }

    if (!empty($artigo['linkedin_post_id'])) {
        $postUrn = (string)$artigo['linkedin_post_id'];
        $check = verificarPostLinkedInExiste($postUrn);
        $msgCheck = (string)($check['message'] ?? '');
        $permissionReadDenied = (
            stripos($msgCheck, 'Not enough permissions') !== false ||
            stripos($msgCheck, 'partnerApiPostsExternal.GET') !== false ||
            stripos($msgCheck, 'forbidden') !== false ||
            stripos($msgCheck, '403') !== false
        );

        if (!empty($check['success']) && array_key_exists('exists', $check) && $check['exists'] === false) {
            // ID stale: post removido manualmente no LinkedIn. Limpar para permitir republicação.
            $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = NULL WHERE id = ?");
            $stmt->execute([$artigoId]);
            $artigo['linkedin_post_id'] = null;
            error_log("publicarAgoraLinkedIn: linkedin_post_id stale limpo para artigo {$artigoId} ({$postUrn})");
        } elseif ($permissionReadDenied) {
            // Alguns tokens têm permissão de publicar, mas não de consultar post por GET.
            // Nessa situação, não bloquear republicação manual.
            $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = NULL WHERE id = ?");
            $stmt->execute([$artigoId]);
            $artigo['linkedin_post_id'] = null;
            error_log("publicarAgoraLinkedIn: sem permissão GET para verificar post; limpando linkedin_post_id e seguindo com republicação. artigo={$artigoId} urn={$postUrn}");
        } elseif (!empty($check['success']) && !empty($check['exists'])) {
            jsonResponse([
                'success' => false,
                'message' => 'Este artigo já possui publicação no LinkedIn.',
                'post_urn' => $postUrn
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => ($check['message'] ?? 'Não foi possível validar publicação existente no LinkedIn.'),
                'post_urn' => $postUrn
            ]);
        }
    }

    $redesDestino = json_decode($artigo['redes_destino'] ?? '[]', true) ?? [];
    $redesIds = array_column($redesDestino, 'rede');
    if (!in_array('linkedin', $redesIds, true)) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não está selecionado em "Publicar em" para este artigo.']);
    }

    $siteUrl = getConfig('site_url') ?: 'https://washiviana.com';
    $siteUrl = rtrim($siteUrl, '/');
    $urlArtigo = $siteUrl . '/pt/artigo/' . urlencode($artigo['slug']);

    $textoPost = trim((string)($artigo['titulo'] ?? '')) . "\n\n" . trim((string)($artigo['resumo'] ?? ''));
    if (trim($textoPost) === '') {
        $textoPost = trim((string)($artigo['titulo'] ?? ''));
    }

    $dadosArtigo = [
        'titulo' => $artigo['titulo'] ?? '',
        'resumo' => $artigo['resumo'] ?? '',
        'imagem_1x1' => $artigo['imagem_1x1'] ?? null,
        'imagem_principal' => $artigo['imagem_principal'] ?? null,
    ];

    $resultado = publicarNoLinkedInViaAPI((int)$artigoId, $textoPost, $urlArtigo, $dadosArtigo);

    if (!empty($resultado['success'])) {
        $postId = $resultado['post_id'] ?? $resultado['linkedin_post_id'] ?? null;
        if ($postId) {
            $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = ? WHERE id = ?");
            $stmt->execute([$postId, $artigoId]);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Publicado no LinkedIn com sucesso!',
            'post_urn' => $postId
        ]);
    }

    jsonResponse([
        'success' => false,
        'message' => $resultado['message'] ?? 'Falha ao publicar no LinkedIn.'
    ]);
}

/**
 * Buscar artigo específico
 */
function buscarArtigo() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID não fornecido']);
    }
    
    $stmt = $pdo->prepare("SELECT * FROM artigos WHERE id = ?");
    $stmt->execute([$id]);
    $artigo = $stmt->fetch();
    
    if (!$artigo) {
        jsonResponse(['success' => false, 'message' => 'Artigo não encontrado']);
    }
    
    jsonResponse([
        'success' => true,
        'artigo' => $artigo
    ]);
}

/**
 * Listar artigos
 */
function listarArtigos() {
    global $pdo;
    
    $status = $_GET['status'] ?? null;
    $categoria = $_GET['categoria'] ?? null;
    
    $sql = "SELECT a.*, ca.nome as categoria_nome 
            FROM artigos a 
            LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id 
            WHERE 1=1";
    $params = [];
    
    if ($status) {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    
    if ($categoria) {
        $sql .= " AND a.categoria_id = ?";
        $params[] = $categoria;
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artigos = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'artigos' => $artigos
    ]);
}

/**
 * Publicar artigo nas redes sociais selecionadas
 */
function publicarNasRedesSociais($artigoId, $redesDestinoJson, $dadosArtigo) {
    global $pdo;
    
    // Log para debug
    error_log("Publicar nas redes sociais - Artigo ID: $artigoId, JSON: $redesDestinoJson");
    
    $redesDestino = json_decode($redesDestinoJson, true);
    if (!is_array($redesDestino) || empty($redesDestino)) {
        error_log("Redes destino vazias ou inválidas para artigo $artigoId");
        return [];
    }
    
    error_log("Redes destino encontradas: " . print_r($redesDestino, true));

    $resultados = [];

    // Calcular base URL seguro: preferir site_url do banco para evitar URLs malformadas
    $siteConfig = null;
    try { $siteConfig = getConfig('site_url'); } catch (Exception $e) { $siteConfig = null; }

    if (!empty($siteConfig) && filter_var($siteConfig, FILTER_VALIDATE_URL)) {
        $baseUrl = rtrim($siteConfig, '/');
    } else {
        $baseUrl = BASE_URL;
        // Validar se BASE_URL é válido (em contexto web normalmente é, mas por segurança)
        $isInvalidUrl = !filter_var($baseUrl, FILTER_VALIDATE_URL)
            || preg_match('#localhost.*/home/#i', $baseUrl)
            || preg_match('#localhost.*/scripts/#i', $baseUrl);
        if ($isInvalidUrl) {
            $baseUrl = 'https://washiviana.com';
            error_log("publicarNasRedesSociais: BASE_URL inválido detectado, usando fallback: $baseUrl");
        }
    }

    // Preparar texto do post
    $textoPost = $dadosArtigo['resumo'] ?: substr(strip_tags($dadosArtigo['conteudo']), 0, 300);
    if (strlen($textoPost) < 50) {
        $textoPost = $dadosArtigo['titulo'] . "\n\n" . $textoPost;
    }

    // URL do artigo (construir diretamente para garantir consistência)
    $urlArtigo = $baseUrl . '/pt/artigo/' . urlencode($dadosArtigo['slug']);
    
    foreach ($redesDestino as $rede) {
        $redeId = is_array($rede) ? ($rede['rede'] ?? '') : $rede;
        
        error_log("Processando rede: " . print_r($rede, true) . " -> ID: $redeId");
        
        if (empty($redeId)) {
            error_log("Rede ID vazio, pulando...");
            continue;
        }
        
        try {
            // Verificar se já foi publicado nesta rede (dupla verificação)
            $jaPublicado = false;

            // Verificação 1: tabela publicacoes_redes
            $stmt = $pdo->prepare("
                SELECT id FROM publicacoes_redes
                WHERE artigo_id = ? AND rede = ? AND status = 'publicado'
            ");
            $stmt->execute([$artigoId, $redeId]);
            if ($stmt->fetch()) {
                $jaPublicado = true;
            }

            // Verificação 2: coluna linkedin_post_id na tabela artigos (para LinkedIn)
            if ($redeId === 'linkedin' && !$jaPublicado) {
                $stmt = $pdo->prepare("SELECT linkedin_post_id FROM artigos WHERE id = ?");
                $stmt->execute([$artigoId]);
                $artigoCheck = $stmt->fetch();
                if ($artigoCheck && !empty($artigoCheck['linkedin_post_id'])) {
                    $jaPublicado = true;
                    error_log("LinkedIn - Artigo $artigoId já tem linkedin_post_id: " . $artigoCheck['linkedin_post_id']);
                }
            }

            if ($jaPublicado) {
                $resultados[$redeId] = [
                    'success' => false,
                    'message' => 'Este artigo já foi publicado no LinkedIn. Delete o post do LinkedIn primeiro ou use a opção de republicar.',
                    'skipped' => true,
                    'ja_publicado' => true
                ];
                continue;
            }

                // Publicar no LinkedIn
            if ($redeId === 'linkedin') {
                error_log("Iniciando publicação no LinkedIn para artigo $artigoId");
                $resultado = publicarNoLinkedInViaAPI($artigoId, $textoPost, $urlArtigo, $dadosArtigo);
                error_log("Resultado LinkedIn: " . print_r($resultado, true));
                $resultados[$redeId] = $resultado;
            }
            // Publicar no Instagram
            elseif ($redeId === 'instagram') {
                error_log("Iniciando publicação no Instagram para artigo $artigoId");
                $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE artigo_id = ? AND rede = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$artigoId, 'instagram']);
                $variant = $stmt->fetch();
                if (!$variant) {
                    $resultados[$redeId] = ['success' => false, 'message' => 'Nenhuma variante Instagram encontrada'];
                } else {
                    require_once __DIR__ . '/instagram.php';
                    $resultado = publicarNoInstagramViaAPI($variant);
                    error_log("Resultado Instagram: " . print_r($resultado, true));

                    if (!empty($resultado['success'])) {
                        // registrar publicação
                        try {
                            $stmt = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, conteudo, status, publicado_em, created_at) VALUES (?, ?, ?, ?, ?, 'publicado', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                            $stmt->execute(['instagram', $artigoId, $resultado['post_id'] ?? null, $resultado['url'] ?? null, $variant['caption'] ?? '']);
                        } catch (Exception $e) {
                            error_log('Erro ao registrar publicação Instagram: ' . $e->getMessage());
                        }
                        $resultados[$redeId] = ['success' => true, 'post_id' => $resultado['post_id'] ?? null];
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, status, erro_mensagem, created_at) VALUES (?, ?, 'erro', ?, CURRENT_TIMESTAMP)");
                            $stmt->execute(['instagram', $artigoId, $resultado['message'] ?? 'Erro ao publicar']);
                        } catch (Exception $e) {
                            error_log('Erro ao registrar falha Instagram: ' . $e->getMessage());
                        }
                        $resultados[$redeId] = ['success' => false, 'message' => $resultado['message'] ?? 'Erro ao publicar no Instagram'];
                    }
                }
            }
            // Publicar no Facebook
            elseif ($redeId === 'facebook') {
                error_log("Iniciando publicação no Facebook para artigo $artigoId");
                $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE artigo_id = ? AND rede = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$artigoId, 'facebook']);
                $variant = $stmt->fetch();
                if (!$variant) {
                    $resultados[$redeId] = ['success' => false, 'message' => 'Nenhuma variante Facebook encontrada'];
                } else {
                    require_once __DIR__ . '/facebook.php';
                    $resultado = publicarNoFacebookViaAPI($variant);
                    error_log("Resultado Facebook: " . print_r($resultado, true));

                    if (!empty($resultado['success'])) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, conteudo, status, publicado_em, created_at) VALUES (?, ?, ?, ?, ?, 'publicado', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                            $stmt->execute(['facebook', $artigoId, $resultado['post_id'] ?? null, $resultado['url'] ?? null, $variant['caption'] ?? '']);
                        } catch (Exception $e) {
                            error_log('Erro ao registrar publicação Facebook: ' . $e->getMessage());
                        }
                        $resultados[$redeId] = ['success' => true, 'post_id' => $resultado['post_id'] ?? null];
                    } else {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, status, erro_mensagem, created_at) VALUES (?, ?, 'erro', ?, CURRENT_TIMESTAMP)");
                            $stmt->execute(['facebook', $artigoId, $resultado['message'] ?? 'Erro ao publicar']);
                        } catch (Exception $e) {
                            error_log('Erro ao registrar falha Facebook: ' . $e->getMessage());
                        }
                        $resultados[$redeId] = ['success' => false, 'message' => $resultado['message'] ?? 'Erro ao publicar no Facebook'];
                    }
                }
            } else {
                error_log("Rede $redeId não suportada ainda");
                $resultados[$redeId] = [
                    'success' => false,
                    'message' => "Rede $redeId ainda não implementada"
                ];
            }
            
        } catch (Exception $e) {
            $resultados[$redeId] = [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
            
            // Registrar erro no banco
            try {
                $stmt = $pdo->prepare("
                    UPDATE artigos 
                    SET erro_publicacao = ?, ultima_tentativa = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$e->getMessage(), $artigoId]);
            } catch (Exception $e2) {
                // Ignorar erro ao registrar erro
            }
        }
    }
    
    return $resultados;
}

/**
 * Upload de imagem para o LinkedIn
 * Retorna o URN da imagem ou null em caso de falha
 */
function uploadImagemLinkedIn($imagemPath, $authorUrn, $accessToken) {
    try {
        // Converter caminho relativo para absoluto
        $imagemAbsoluta = $imagemPath;
        
        // Se não for path absoluto, tentar várias localizações
        if (!file_exists($imagemAbsoluta)) {
            $possiveisCaminhos = [
                __DIR__ . '/../uploads/' . basename($imagemPath),
                __DIR__ . '/../' . $imagemPath,
                __DIR__ . '/../' . ltrim($imagemPath, '/'),
                $_SERVER['DOCUMENT_ROOT'] . '/washiviana/uploads/' . basename($imagemPath),
                $_SERVER['DOCUMENT_ROOT'] . '/washiviana/' . $imagemPath,
            ];
            
            foreach ($possiveisCaminhos as $caminho) {
                error_log("LinkedIn Upload - Tentando: $caminho");
                if (file_exists($caminho)) {
                    $imagemAbsoluta = $caminho;
                    break;
                }
            }
        }
        
        // Verificar se arquivo existe
        if (!file_exists($imagemAbsoluta)) {
            error_log("LinkedIn Upload - Arquivo não encontrado em nenhum caminho. Original: $imagemPath");
            return null;
        }
        
        $fileSize = filesize($imagemAbsoluta);
        error_log("LinkedIn Upload - Arquivo encontrado: $imagemAbsoluta, Tamanho: $fileSize bytes");
        error_log("LinkedIn Upload - Arquivo: $imagemAbsoluta, Tamanho: $fileSize bytes");
        
        // Passo 1: Inicializar upload com fallback de versões da API.
        $initPayload = [
            'initializeUploadRequest' => [
                'owner' => $authorUrn
            ]
        ];

        $candidateVersions = buildLinkedInVersionCandidates(true);
        $deadline = microtime(true) + 18;

        $response = null;
        $httpCode = 0;
        $initData = null;
        $initOk = false;

        foreach ($candidateVersions as $ver) {
            if (microtime(true) >= $deadline) {
                error_log('LinkedIn Upload Init - deadline atingido, interrompendo tentativas de versão.');
                break;
            }

            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0'
            ];
            if ($ver !== null) {
                $headers[] = 'LinkedIn-Version: ' . $ver;
            }

            $ch = curl_init('https://api.linkedin.com/rest/images?action=initializeUpload');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($initPayload),
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => $headers
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("LinkedIn Upload Init - version=" . ($ver ?? 'none') . " HTTP: $httpCode, Response: " . substr((string)$response, 0, 600));

            if ($httpCode === 200) {
                $initData = json_decode((string)$response, true);
                $initOk = true;
                break;
            }

            $tmp = json_decode((string)$response, true);
            $msg = (string)($tmp['message'] ?? '');
            if (stripos($msg, 'Requested version') !== false || stripos($msg, 'NONEXISTENT_VERSION') !== false) {
                continue;
            }
        }

        if (!$initOk || !is_array($initData)) {
            error_log("LinkedIn Upload - Falha ao inicializar upload de imagem após fallback de versões.");
            return null;
        }

        $uploadUrl = $initData['value']['uploadUrl'] ?? null;
        $imageUrn = $initData['value']['image'] ?? null;
        
        if (!$uploadUrl || !$imageUrn) {
            error_log("LinkedIn Upload - uploadUrl ou imageUrn não retornado");
            return null;
        }
        
        error_log("LinkedIn Upload - URL: $uploadUrl");
        error_log("LinkedIn Upload - Image URN: $imageUrn");
        
        // Passo 2: Fazer upload do arquivo binário
        $imageData = file_get_contents($imagemAbsoluta);
        
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $imageData,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $fileSize
            ]
        ]);
        
        $uploadResponse = curl_exec($ch);
        $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("LinkedIn Upload Binary - HTTP: $uploadHttpCode");
        
        // HTTP 201 ou 200 significa sucesso
        if ($uploadHttpCode >= 200 && $uploadHttpCode < 300) {
            return $imageUrn;
        }
        
        error_log("LinkedIn Upload - Falha no upload binário: HTTP $uploadHttpCode, Response: $uploadResponse");
        return null;
        
    } catch (Exception $e) {
        error_log("LinkedIn Upload Exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Monta versões candidatas de API do LinkedIn limitando tentativas para evitar timeouts longos.
 */
function buildLinkedInVersionCandidates($includeNoHeader = false) {
    $versions = array_values(array_unique(array_filter([
        LINKEDIN_API_VERSION,
        date('Ym'),
        date('Ym', strtotime('-1 month')),
        date('Ym', strtotime('-2 month')),
    ], function($v) {
        return preg_match('/^\d{6}$/', (string)$v);
    })));

    // Limita para no máximo 3 versões para reduzir latência em cenários de fallback.
    $versions = array_slice($versions, 0, 3);

    if ($includeNoHeader) {
        $versions[] = null;
    }

    return $versions;
}

/**
 * Resolver a melhor imagem disponível para publicação no LinkedIn.
 * Prioriza 1x1 (IA), mas só usa se o arquivo realmente existir.
 */
function resolverImagemParaLinkedIn(array $dadosArtigo): ?string {
    $candidatas = [
        $dadosArtigo['imagem_1x1'] ?? null,
        $dadosArtigo['imagem'] ?? null,
        $dadosArtigo['imagem_principal'] ?? null,
    ];

    foreach ($candidatas as $img) {
        $img = trim((string)$img);
        if ($img === '') continue;

        $possiveisCaminhos = [
            $img,
            __DIR__ . '/../uploads/' . basename($img),
            __DIR__ . '/../' . ltrim($img, '/'),
            $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . basename($img),
            $_SERVER['DOCUMENT_ROOT'] . '/washiviana/uploads/' . basename($img),
        ];

        foreach ($possiveisCaminhos as $caminho) {
            if (is_string($caminho) && $caminho !== '' && file_exists($caminho)) {
                return $img; // mantém formato original para uploadImagemLinkedIn resolver
            }
        }
    }

    return null;
}

/**
 * Publicar no LinkedIn via chamada HTTP interna à API
 */
function publicarNoLinkedInViaAPI($artigoId, $texto, $url, $dadosArtigo) {
    try {
        // LinkedIn permite até 3000 caracteres no commentary
        $titulo = $dadosArtigo['titulo'] ?? '';
        $resumo = $dadosArtigo['resumo'] ?? '';
        
        // Buscar a melhor imagem válida (IA ou upload manual), evitando caminho quebrado.
        $imagem = resolverImagemParaLinkedIn($dadosArtigo) ?: '';
        
        global $pdo;
    
        // Buscar credenciais
        $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config || empty($config['access_token'])) {
            return [
                'success' => false,
                'message' => 'LinkedIn não configurado. Configure Access Token.'
            ];
        }
        
        // Determinar o author URN baseado na configuração (person ou organization)
        $publishTarget = $config['publish_target'] ?? 'person';
        $authorUrn = '';
        
        if ($publishTarget === 'organization' && !empty($config['organization_urn'])) {
            // Publicar na página da empresa
            $authorUrn = trim($config['organization_urn']);
            error_log("LinkedIn - Publicando na PÁGINA DA EMPRESA: $authorUrn");
        } elseif (!empty($config['person_urn'])) {
            // Publicar no perfil pessoal
            $authorUrn = trim($config['person_urn']);
            error_log("LinkedIn - Publicando no PERFIL PESSOAL: $authorUrn");
        } else {
            return [
                'success' => false,
                'message' => 'Person URN ou Organization URN não configurado. Configure em Redes Sociais.'
            ];
        }
        
        // Validar formato do URN (aceita IDs numéricos ou alfanuméricos como qyyIt_9a-B)
        if (!preg_match('/^urn:li:(person|organization):[A-Za-z0-9_-]+$/', $authorUrn)) {
            return [
                'success' => false,
                'message' => 'Formato de URN inválido: ' . $authorUrn . '. Use o formato urn:li:person:ID ou urn:li:organization:ID'
            ];
        }
        
        // Verificar token e tentar obter mais informações sobre permissões
        error_log("LinkedIn - Verificando token antes de publicar...");
        $chTest = curl_init();
        curl_setopt_array($chTest, [
            CURLOPT_URL => 'https://api.linkedin.com/v2/userinfo',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['access_token'],
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0',
                'LinkedIn-Version: ' . LINKEDIN_API_VERSION
            ]
        ]);
        $testResponse = curl_exec($chTest);
        $testHttpCode = curl_getinfo($chTest, CURLINFO_HTTP_CODE);
        curl_close($chTest);
        
        if ($testHttpCode === 401) {
            return [
                'success' => false,
                'message' => 'Token expirado ou inválido. Gere um novo Access Token no LinkedIn Developers.',
                'http_code' => 401,
                'test_response' => substr($testResponse, 0, 200)
            ];
        }
        
        error_log("LinkedIn - Token válido (HTTP $testHttpCode), tentando publicar...");
        
        // Usar o author URN determinado anteriormente
        error_log("LinkedIn - Author URN para publicação: $authorUrn");
        
        // Variavel para guardar o URN da imagem se upload for feito
        $imageUrn = null;
        $temUrlValido = !empty($url);

        // Fazer upload da imagem para o LinkedIn se tiver imagem
        // Isso é necessário tanto para posts com imagem direta quanto para article cards com thumbnail
        $imageUploadError = null;
        if (!empty($imagem)) {
            $imageUrn = uploadImagemLinkedIn($imagem, $authorUrn, $config['access_token']);
            if ($imageUrn) {
                error_log("LinkedIn - Imagem uploaded com sucesso: $imageUrn");
            } else {
                $imageUploadError = 'Falha no upload da imagem para LinkedIn (URN não retornado)';
                error_log("LinkedIn - Falha no upload da imagem, continuando sem thumbnail");
            }
        }

        // Texto do post: Título, Resumo, Call-to-action
        $textoPost = "";
        
        // Log para debug
        error_log("LinkedIn - Resumo recebido: " . ($resumo ?: '[VAZIO]'));
        error_log("LinkedIn - Resumo length: " . strlen($resumo ?? ''));
        
        // 1. Título com emoji
        if (!empty($titulo)) {
            $textoPost .= "📌 " . $titulo . "\n\n";
        }
        
        // 2. Resumo
        if (!empty($resumo)) {
            $textoPost .= $resumo . "\n\n";
        }
        
        // 3. Call-to-action
        $textoPost .= "👇 Clique para ler o artigo completo";
        // Adicionar link ao final do texto (links são clicáveis no LinkedIn)
        $textoPost .= "\n\n🔗 " . $url;
        
        error_log("LinkedIn - Texto final length: " . strlen($textoPost));
        
        // Payload para publicação
        $payload = [
            'author' => $authorUrn,
            'commentary' => $textoPost,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => []
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false
        ];
        
        // Priorizar post com mídia quando houver imagem URN (mais consistente para exibir imagem no feed).
        if (!empty($imageUrn)) {
            $payload['content'] = [
                'media' => [
                    'title' => $titulo ?: 'Imagem',
                    'id' => $imageUrn
                ]
            ];
            error_log("LinkedIn - Usando IMAGEM direta (media) com URL no texto do post");
        }
        // Sem imagem disponível, usar article card com link
        elseif ($temUrlValido) {
            $descricaoCard = $resumo ?: ($dadosArtigo['conteudo'] ?? '');
            $descricaoCard = trim(html_entity_decode(strip_tags($descricaoCard), ENT_QUOTES, 'UTF-8'));
            if (mb_strlen($descricaoCard, 'UTF-8') > 256) {
                $descricaoCard = mb_substr($descricaoCard, 0, 256, 'UTF-8') . '...';
            }
            if (empty($descricaoCard)) {
                $descricaoCard = 'Confira o conteudo completo no site.';
            }

            // Força o LinkedIn a re-scrapear o preview e evita cache antigo sem thumbnail.
            $sourceUrl = $url;
            $sourceUrl .= (strpos($sourceUrl, '?') === false ? '?' : '&') . 'li_preview=' . time();

            // Montar article card
            $articlePayload = [
                'source' => $sourceUrl,
                'title' => $titulo ?: 'Artigo',
                'description' => $descricaoCard
            ];

            error_log("LinkedIn - Sem imagem URN, usando ARTICLE CARD com re-scrape da URL. erro_upload=" . ($imageUploadError ?: 'none'));

            $payload['content'] = [
                'article' => $articlePayload
            ];
            error_log("LinkedIn - Usando ARTICLE CARD (com resumo e link)");
        } else {
            error_log("LinkedIn - Sem URL e sem imagem, post apenas com texto");
        }

        error_log("LinkedIn - Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
        
        // Construir lista de versões candidatas dinamicamente (prioridade para a constante LINKEDIN_API_VERSION)
        // Inclui a versão atual, meses anteriores e versões históricas conhecidas
        $candidate_versions = buildLinkedInVersionCandidates(false);
        $deadline = microtime(true) + 26;

        // Preparar lista final de tentativa (sempre incluir apenas versões no formato YYYYMM)
        $versoesParaTestar = $candidate_versions;
        $ultimoErro = null;
        
        foreach ($versoesParaTestar as $versao) {
            if (microtime(true) >= $deadline) {
                $ultimoErro = ['message' => 'Tempo limite interno atingido ao tentar publicar no LinkedIn'];
                error_log('LinkedIn - deadline atingido durante tentativas de publicação.');
                break;
            }

            // Headers
            $headers = [
                'Authorization: Bearer ' . $config['access_token'],
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0'
            ];

            if (!is_null($versao)) {
                // Enviar header LinkedIn-Version apenas se tivermos uma string de versão
                $headers[] = 'LinkedIn-Version: ' . $versao;
            }

            error_log("LinkedIn - Tentando publicar com Author: $authorUrn, versão: " . ($versao ?? 'sem header'));

            
            // Capturar headers da resposta para obter o x-restli-id
            $responseHeaders = [];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.linkedin.com/rest/posts',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) return $len;
                    $responseHeaders[strtolower(trim($header[0]))] = trim($header[1]);
                    return $len;
                }
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                $ultimoErro = ['success' => false, 'message' => 'Erro cURL: ' . $error];
                continue;
            }
            
            if ($httpCode === 201) {
                // O LinkedIn retorna o ID do post no header x-restli-id
                $postUrn = $responseHeaders['x-restli-id'] ?? null;
                
                // Fallback para o body se não estiver no header
                if (!$postUrn) {
                    $responseData = json_decode($response, true);
                    $postUrn = $responseData['id'] ?? $responseData['urn'] ?? null;
                }
                
                error_log("LinkedIn - Post criado com URN: $postUrn");

                // Nota: Comentários via API requerem permissões especiais (Partner API)
                // O link clicável está no article card

                // Registrar publicação na tabela publicacoes_redes (salva também a url do artigo)
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, conteudo, status, publicado_em)
                        VALUES (?, ?, ?, ?, ?, 'publicado', CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute(['linkedin', $artigoId, $postUrn, $url, $texto]);
                } catch (Exception $e) {
                    error_log("Erro ao registrar publicação LinkedIn: " . $e->getMessage());
                }

                // Atualizar linkedin_post_id na tabela artigos
                try {
                    $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = ? WHERE id = ?");
                    $stmt->execute([$postUrn, $artigoId]);
                    error_log("LinkedIn - linkedin_post_id atualizado no artigo $artigoId: $postUrn");
                } catch (Exception $e) {
                    error_log("Erro ao atualizar linkedin_post_id: " . $e->getMessage());
                }
                
                $targetType = strpos($authorUrn, 'organization') !== false ? 'página da empresa' : 'perfil pessoal';
                
                return [
                    'success' => true,
                    'message' => "Publicado com sucesso no LinkedIn ($targetType)!",
                    'post_urn' => $postUrn,
                    'versao_usada' => $versao,
                    'author_urn' => $authorUrn
                ];
            }
            
            // Se erro 426 (versão não ativa), tentar próxima e tentar derivar versões se aplicável
            if ($httpCode === 426) {
                $ultimoErro = json_decode($response, true);

                // Tentar extrair a versão requisitada na mensagem (ex: Requested version 20241101 is not active)
                if (is_array($ultimoErro) && isset($ultimoErro['message']) && preg_match('/Requested version\s+(\d{6,8})/i', $ultimoErro['message'], $m)) {
                    $requested = $m[1];
                    // Normalizar para formato YYYYMM (pegar os primeiros 6 dígitos)
                    $derived = substr($requested, 0, 6);
                    if (!in_array($derived, $versoesParaTestar)) {
                        array_unshift($versoesParaTestar, $derived);
                        error_log("LinkedIn - Versão derivada adicionada à lista de tentativas: $derived");
                    }
                }

                continue; // Próxima versão
            }
            
            // Se erro 403, capturar detalhes e continuar tentando outras versões
            if ($httpCode === 403) {
                $errorData = json_decode($response, true);
                if (!$errorData) {
                    $errorData = ['status' => 403, 'raw_response' => substr($response, 0, 500)];
                }
                $ultimoErro = $errorData;
                
                // Log detalhado do erro 403
                error_log("LinkedIn 403 - Author URN: $authorUrn, Versão: $versao");
                error_log("LinkedIn 403 - Response completa: " . $response);
                error_log("LinkedIn 403 - Payload enviado: " . json_encode($payload));
                
                continue; // Tentar próxima versão
            }
            
            // Outro erro, retornar
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['message'] ?? $errorData['error_description'] ?? json_encode($errorData);
            
            error_log("LinkedIn API Error - Versão: $versao, HTTP: $httpCode, Response: " . substr($response, 0, 500));
            
            return [
                'success' => false,
                'message' => 'Erro ao publicar no LinkedIn: ' . $errorMsg,
                'http_code' => $httpCode,
                'response' => $errorData,
                'versao_tentada' => $versao,
                'author_urn' => $authorUrn,
                'response_raw' => substr($response, 0, 1000)
            ];
        }
        
        // Se chegou aqui, todas as versões falharam
        // Construir mensagem de erro mais detalhada
        $errorMsg = '';
        if (isset($ultimoErro['message'])) {
            $errorMsg = $ultimoErro['message'];
        } elseif (isset($ultimoErro['error_description'])) {
            $errorMsg = $ultimoErro['error_description'];
        } elseif (isset($ultimoErro['code'])) {
            $errorMsg = 'Código de erro: ' . $ultimoErro['code'];
        } else {
            $errorMsg = 'Erro 403 (Acesso Negado) em todas as versões testadas';
        }
        
        $targetType = strpos($authorUrn, 'organization') !== false ? 'página da empresa' : 'perfil pessoal';
        
        // Mensagem de ajuda mais completa
        $mensagemCompleta = $errorMsg . "\n\n";
        $mensagemCompleta .= "⚠️ IMPORTANTE: O erro 403 indica que o app não tem permissão para publicar.\n\n";
        $mensagemCompleta .= "TENTANDO PUBLICAR EM: $targetType ($authorUrn)\n\n";
        $mensagemCompleta .= "SOLUÇÕES (em ordem de prioridade):\n\n";
        $mensagemCompleta .= "1. 🔑 GERAR NOVO ACCESS TOKEN (MAIS IMPORTANTE):\n";
        $mensagemCompleta .= "   Se o produto 'Share on LinkedIn' foi aprovado RECENTEMENTE,\n";
        $mensagemCompleta .= "   você PRECISA gerar um NOVO token APÓS a aprovação!\n\n";
        $mensagemCompleta .= "   Passos:\n";
        $mensagemCompleta .= "   • Acesse: https://www.linkedin.com/developers/apps\n";
        $mensagemCompleta .= "   • Selecione seu app → Auth → Generate Token\n";
        $mensagemCompleta .= "   • Certifique-se de incluir o scope 'w_member_social'\n";
        $mensagemCompleta .= "   • Copie o NOVO token e cole em Redes Sociais\n\n";
        $mensagemCompleta .= "2. Verificar se o produto 'Share on LinkedIn' está aprovado:\n";
        $mensagemCompleta .= "   • Acesse: https://www.linkedin.com/developers/apps\n";
        $mensagemCompleta .= "   • Selecione seu app → Products\n";
        $mensagemCompleta .= "   • Verifique se 'Share on LinkedIn' está com status 'Approved'\n\n";
        
        if (strpos($authorUrn, 'organization') !== false) {
            $mensagemCompleta .= "3. ⚠️ PUBLICANDO EM PÁGINA DA EMPRESA:\n";
            $mensagemCompleta .= "   • Você PRECISA ser admin da página para publicar nela\n";
            $mensagemCompleta .= "   • O token precisa ter scope 'w_organization_social' para páginas\n";
            $mensagemCompleta .= "   • Verifique se o Organization URN está correto: $authorUrn\n\n";
        } else {
            $mensagemCompleta .= "3. Verificar Person URN: $authorUrn\n";
            $mensagemCompleta .= "   (Deve estar no formato: urn:li:person:NUMERO)\n\n";
        }
        
        $mensagemCompleta .= "💡 DICA: Tokens gerados ANTES da aprovação do produto não funcionam.\n";
        $mensagemCompleta .= "   Você DEVE gerar um novo token após a aprovação!";
        
        return [
            'success' => false,
            'message' => $mensagemCompleta,
            'http_code' => 403,
            'response' => $ultimoErro,
            'versoes_testadas' => $versoesParaTestar,
            'author_urn' => $authorUrn,
            'publish_target' => $publishTarget,
            'debug_info' => [
                'author_urn' => $authorUrn,
                'token_length' => strlen($config['access_token']),
                'token_valido' => $testHttpCode !== 401,
                'ultimo_erro_completo' => $ultimoErro
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Erro em publicarNoLinkedInViaAPI: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro interno ao publicar no LinkedIn: ' . $e->getMessage()
        ];
    }
}

/**
 * Publicar no LinkedIn (função antiga - mantida para compatibilidade)
 * @deprecated Use publicarNoLinkedInViaAPI
 */
function publicarNoLinkedIn($artigoId, $texto, $url, $dadosArtigo) {
    global $pdo;
    
    // Buscar credenciais do LinkedIn
    $stmt = $pdo->prepare("
        SELECT ativo, access_token, person_urn, client_id, client_secret 
        FROM redes_sociais_config 
        WHERE rede = 'linkedin'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        return [
            'success' => false,
            'message' => 'LinkedIn não configurado. Configure em Redes Sociais.'
        ];
    }
    
    if (empty($config['access_token'])) {
        return [
            'success' => false,
            'message' => 'Access Token do LinkedIn não configurado. Configure em Redes Sociais.'
        ];
    }
    
    if (empty($config['person_urn'])) {
        return [
            'success' => false,
            'message' => 'Person URN do LinkedIn não configurado. Configure em Redes Sociais.'
        ];
    }
    
    // Validar formato do Person URN
    $personUrn = trim($config['person_urn']);
    if (strpos($personUrn, 'urn:li:person:') !== 0) {
        return [
            'success' => false,
            'message' => 'Person URN inválido. Deve começar com "urn:li:person:". Valor atual: ' . substr($personUrn, 0, 50)
        ];
    }
    
    // Extrair ID do Person URN (pode ser slug ou numérico)
    $personId = str_replace('urn:li:person:', '', $personUrn);
    
    // Se o Person URN tem formato slug (com hífens), tentar extrair ID numérico do final
    // Exemplo: washington-alves-viana-38583269 -> tentar usar 38583269
    $personIdNumerico = null;
    if (preg_match('/-(\d+)$/', $personId, $matches)) {
        $personIdNumerico = $matches[1];
        error_log("LinkedIn - ID numérico extraído do Person URN: $personIdNumerico");
    }
    
    // Verificar se está ativo (PostgreSQL retorna 't'/'f' ou true/false)
    $ativo = $config['ativo'] ?? false;
    if ($ativo === false || $ativo === 'f' || $ativo === 0) {
        return [
            'success' => false,
            'message' => 'LinkedIn não está ativado. Ative em Redes Sociais.'
        ];
    }
    
    // Validar token (verificar se não está vazio e tem formato básico)
    $accessToken = trim($config['access_token']);
    if (strlen($accessToken) < 50) {
        return [
            'success' => false,
            'message' => 'Access Token parece inválido (muito curto). Gere um novo token.'
        ];
    }
    
    // Preparar payload
    // Primeiro tentar post simples (sem article) para evitar erro 403
    // Se tiver URL, adicionar como link no commentary
    $textoComLink = $texto;
    if (!empty($url)) {
        $textoComLink = $texto . "\n\n" . $url;
    }
    
    // Payload básico para post de texto (formato mínimo conforme documentação)
    // Remover campos opcionais que podem causar 403
    $payload = [
        'author' => $personUrn, // Usar variável validada
        'commentary' => $textoComLink,
        'visibility' => 'PUBLIC',
        'lifecycleState' => 'PUBLISHED'
    ];
    
    // Adicionar distribution apenas se necessário (pode causar 403 se mal formatado)
    // Tentar primeiro sem distribution
    $payloadComDistribution = array_merge($payload, [
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED'
        ]
    ]);
    
    // Log do payload para debug (sem token)
    error_log("LinkedIn Payload - Author: $personUrn, Texto length: " . strlen($textoComLink));
    
    // Fazer requisição para API do LinkedIn
    // Tentar primeiro com payload mínimo (sem distribution), depois com distribution
    $payloadsParaTestar = [
        ['payload' => $payload, 'nome' => 'mínimo'],
        ['payload' => $payloadComDistribution, 'nome' => 'com distribution']
    ];
    
    // Tentar com versões diferentes se necessário
    // LinkedIn agora exige sempre o header LinkedIn-Version
    $versoesParaTestar = ['202410', '202411', '202412', '202501'];
    $ultimoErro = null;
    
    foreach ($payloadsParaTestar as $payloadInfo) {
        $payloadAtual = $payloadInfo['payload'];
        $nomePayload = $payloadInfo['nome'];
        
        foreach ($versoesParaTestar as $versao) {
            $headers = [
                'Authorization: Bearer ' . $accessToken, // Usar variável validada
                'Content-Type: application/json',
                'X-Restli-Protocol-Version: 2.0.0',
                'LinkedIn-Version: ' . $versao
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.linkedin.com/rest/posts',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payloadAtual),
                CURLOPT_HTTPHEADER => $headers
            ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $ultimoErro = ['success' => false, 'message' => 'Erro cURL: ' . $error];
            continue;
        }
        
        // Se sucesso (201 = Created), retornar
        if ($httpCode === 201) {
            $responseData = json_decode($response, true);
            $postUrn = $responseData['id'] ?? $responseData['urn'] ?? 'unknown';
            
            // Registrar publicação no banco
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO publicacoes_redes (rede, artigo_id, post_id, conteudo, status, publicado_em)
                    VALUES (?, ?, ?, ?, 'publicado', CURRENT_TIMESTAMP)
                ");
                $stmt->execute(['linkedin', $artigoId, $postUrn, $texto]);
            } catch (Exception $e) {
                error_log("Erro ao registrar publicação LinkedIn: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Publicado com sucesso no LinkedIn! (payload: ' . $nomePayload . ', versão: ' . $versao . ')',
                'post_urn' => $postUrn,
                'versao_usada' => $versao,
                'payload_usado' => $nomePayload
            ];
        }
        
        // Se erro 426 (versão não ativa), tentar próxima versão
        if ($httpCode === 426) {
            $ultimoErro = json_decode($response, true);
            continue; // Próxima versão
        }
        
        // Se erro 403, capturar detalhes e tentar próximo payload ou próxima versão
        if ($httpCode === 403) {
            $errorData = json_decode($response, true);
            if (!$errorData) {
                $errorData = ['status' => 403, 'raw_response' => substr($response, 0, 500)];
            }
            $ultimoErro = $errorData;
            
            // Log detalhado do erro 403
            error_log("LinkedIn 403 - Payload: $nomePayload, Versão: " . $versao);
            error_log("LinkedIn 403 - Response completa: " . $response);
            error_log("LinkedIn 403 - Payload enviado: " . json_encode($payloadAtual));
            
            continue; // Tentar próxima combinação
        }
        
        // Outro erro, retornar com detalhes completos
        $errorData = json_decode($response, true);
        if (!$errorData) {
            $errorData = ['raw_response' => substr($response, 0, 500)];
        }
        
        // Log detalhado para debug
        error_log("LinkedIn API Error - Versão: $versao, HTTP: $httpCode");
        error_log("LinkedIn Response: " . $response);
        error_log("LinkedIn Payload enviado: " . json_encode($payload));
        
        // Mensagem de erro mais específica para 403
        if ($httpCode === 403) {
            $errorMsg = 'Acesso negado (403 Forbidden). ';
            $errorMsg .= 'SOLUÇÃO: Gere um novo Access Token com scope w_member_social. ';
            $errorMsg .= 'Passos: 1) Acesse https://www.linkedin.com/developers/apps; ';
            $errorMsg .= '2) Selecione seu app → Auth → Generate Token; ';
            $errorMsg .= '3) Certifique-se de incluir o scope "w_member_social"; ';
            $errorMsg .= '4) Cole o novo token em Redes Sociais. ';
            $errorMsg .= 'Também verifique se o produto "Share on LinkedIn" está aprovado no seu app.';
            
            // Adicionar informações de debug
            $errorMsg .= ' | Debug: Person URN=' . substr($personUrn, 0, 60) . ', Token length=' . strlen($accessToken);
        } else {
            $errorMsg = $errorData['message'] ?? $errorData['error_description'] ?? $errorData['code'] ?? json_encode($errorData);
        }
        
        return [
            'success' => false,
            'message' => 'Erro ao publicar no LinkedIn: ' . $errorMsg,
            'http_code' => $httpCode,
            'response' => $errorData,
            'versao_tentada' => $versao,
            'response_raw' => substr($response, 0, 1000), // Primeiros 1000 chars para debug
            'debug_info' => [
                'person_urn' => substr($personUrn, 0, 50),
                'token_length' => strlen($accessToken),
                'texto_length' => strlen($textoComLink)
            ]
        ];
        }
    }
    
    // Se chegou aqui, todas as versões falharam
    // Construir mensagem de erro mais detalhada
    $errorMsg = '';
    if (isset($ultimoErro['message'])) {
        $errorMsg = $ultimoErro['message'];
    } elseif (isset($ultimoErro['error_description'])) {
        $errorMsg = $ultimoErro['error_description'];
    } elseif (isset($ultimoErro['code'])) {
        $errorMsg = 'Código de erro: ' . $ultimoErro['code'];
    } else {
        $errorMsg = 'Erro 403 (Acesso Negado) em todas as versões testadas';
    }
    
    // Determinar código HTTP correto
    $httpCodeFinal = 403;
    if (isset($ultimoErro['status']) && $ultimoErro['status'] == 426) {
        $httpCodeFinal = 426;
    }
    
    // Mensagem de ajuda mais completa
    $mensagemCompleta = $errorMsg . "\n\n";
    $mensagemCompleta .= "SOLUÇÕES POSSÍVEIS:\n";
    $mensagemCompleta .= "1. Verifique se o produto 'Share on LinkedIn' está aprovado:\n";
    $mensagemCompleta .= "   - Acesse: https://www.linkedin.com/developers/apps\n";
    $mensagemCompleta .= "   - Selecione seu app → Products\n";
    $mensagemCompleta .= "   - Procure por 'Share on LinkedIn' e solicite aprovação se necessário\n\n";
    $mensagemCompleta .= "2. Verifique se o Access Token tem o scope 'w_member_social':\n";
    $mensagemCompleta .= "   - Gere um novo token em: Auth → Generate Token\n";
    $mensagemCompleta .= "   - Certifique-se de incluir o scope 'w_member_social'\n\n";
    $mensagemCompleta .= "3. Verifique se o token não expirou\n";
    $mensagemCompleta .= "4. Verifique se o Person URN está correto: " . substr($personUrn, 0, 50);
    
    return [
        'success' => false,
        'message' => $mensagemCompleta,
        'http_code' => $httpCodeFinal,
        'response' => $ultimoErro,
        'versoes_testadas' => $versoesParaTestar,
        'debug_info' => [
            'person_urn' => substr($personUrn, 0, 50),
            'token_length' => strlen($accessToken),
            'ultimo_erro_completo' => $ultimoErro
        ]
    ];
}

/**
 * Upload de arquivo (imagem ou vídeo)
 */
function uploadArquivo($file, $tipo) {
    $extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validar tipo
    if ($tipo === 'imagem') {
        $permitidos = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
    } else {
        $permitidos = ['mp4', 'webm', 'mov', 'avi'];
        $maxSize = 100 * 1024 * 1024; // 100MB
    }

    if (!in_array($extensao, $permitidos)) {
        throw new Exception("Formato de arquivo não permitido: $extensao");
    }

    if ($file['size'] > $maxSize) {
        throw new Exception("Arquivo muito grande. Máximo: " . ($maxSize / 1024 / 1024) . "MB");
    }

    // Gerar nome único (sempre .jpg para imagens otimizadas)
    $nomeArquivo = $tipo . '_' . uniqid() . '_' . time() . ($tipo === 'imagem' ? '.jpg' : '.' . $extensao);
    $destino = UPLOAD_DIR . $nomeArquivo;

    // Criar diretório se não existir
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        throw new Exception("Erro ao fazer upload do arquivo");
    }

    // Otimizar imagens automaticamente
    if ($tipo === 'imagem') {
        require_once __DIR__ . '/image_optimizer.php';
        $resultado = otimizarImagemParaRedesSociais($destino, $destino, 1200, 630, 500, 85);
        if ($resultado['success']) {
            error_log("Imagem otimizada: {$resultado['sizeKB']}KB, {$resultado['dimensions']['width']}x{$resultado['dimensions']['height']}");
        } else {
            error_log("Aviso: Não foi possível otimizar imagem: {$resultado['message']}");
        }
    }

    return $nomeArquivo;
}

/**
 * Gerar slug a partir de texto
 */
function gerarSlug($texto) {
    $slug = mb_strtolower($texto, 'UTF-8');
    $slug = preg_replace('/[áàãâä]/u', 'a', $slug);
    $slug = preg_replace('/[éèêë]/u', 'e', $slug);
    $slug = preg_replace('/[íìîï]/u', 'i', $slug);
    $slug = preg_replace('/[óòõôö]/u', 'o', $slug);
    $slug = preg_replace('/[úùûü]/u', 'u', $slug);
    $slug = preg_replace('/[ç]/u', 'c', $slug);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}
