<?php
/**
 * WASHIVIANA PORTFOLIO - Projetos API
 * CRUD completo de projetos
 */

require_once __DIR__ . '/config.php';

// Verificar autenticação para operações de escrita
$writeActions = ['criar', 'atualizar', 'deletar', 'ordenar', 'delete_media'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Se o POST estourar limites do PHP (post_max_size/upload_max_filesize),
// o PHP pode zerar $_POST/$_FILES e o $action vem vazio. Retornar erro explícito.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === '') {
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postMaxSize = ini_get('post_max_size') ?: '';
    $uploadMaxFileSize = ini_get('upload_max_filesize') ?: '';

    $msg = 'Requisição inválida (action ausente).';
    if ($contentLength > 0) {
        $msg = 'Upload/POST muito grande para o PHP. Aumente `post_max_size` e `upload_max_filesize` no PHP-FPM e tente novamente.';
    }

    $statusCode = ($contentLength > 0) ? 413 : 400;

    jsonResponse([
        'success' => false,
        'message' => $msg,
        'debug' => [
            'content_length_bytes' => $contentLength,
            'post_max_size' => $postMaxSize,
            'upload_max_filesize' => $uploadMaxFileSize,
            'hint' => 'Após alterar php.ini, reinicie o php-fpm. Também ajuste max_file_uploads se enviar muitas imagens.'
        ]
    ], $statusCode);
}

if (in_array($action, $writeActions) && !isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'listar':
    case 'list':
        listarProjetos();
        break;
    
    case 'buscar':
    case 'get':
        buscarProjeto();
        break;
    
    case 'criar':
    case 'create':
        criarProjeto();
        break;
    
    case 'atualizar':
    case 'update':
        atualizarProjeto();
        break;
    
    case 'deletar':
    case 'delete':
        deletarProjeto();
        break;
    
    case 'ordenar':
    case 'order':
        ordenarProjetos();
        break;
    
    case 'toggle_status':
        toggleStatus();
        break;
    
    case 'toggle_destaque':
        toggleDestaque();
        break;
    
    case 'save_linkedin_post':
        salvarPostLinkedin();
        break;
    
    case 'get_linkedin_post':
        buscarPostLinkedin();
        break;

    case 'delete_media':
        deletarMediaProjeto();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}

/**
 * Listar projetos
 */
function listarProjetos() {
    global $pdo;
    
    $categoriaId = $_GET['categoria_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $destaque = $_GET['destaque'] ?? null;
    $limit = intval($_GET['limit'] ?? 100);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $sql = "SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug 
                FROM projetos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE 1=1";
        
        $params = [];
        
        if ($categoriaId) {
            $sql .= " AND p.categoria_id = ?";
            $params[] = $categoriaId;
        }
        
        if ($status !== null) {
            $sql .= " AND p.ativo = ?";
            $params[] = $status;
        }
        
        if ($destaque !== null) {
            $sql .= " AND p.destaque = ?";
            $params[] = $destaque;
        }
        
        $sql .= " ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projetos = $stmt->fetchAll();
        
        // Processar imagens_galeria (JSON)
        foreach ($projetos as &$projeto) {
            $projeto['imagens_galeria'] = json_decode($projeto['imagens_galeria'], true) ?: [];
        }
        
        jsonResponse([
            'success' => true,
            'projetos' => $projetos
        ]);
        
    } catch (Exception $e) {
        error_log("List Projects Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao listar projetos.'], 500);
    }
}

/**
 * Buscar projeto por ID ou slug
 */
function buscarProjeto() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    $slug = $_GET['slug'] ?? null;
    
    if (!$id && !$slug) {
        jsonResponse(['success' => false, 'message' => 'ID ou slug é obrigatório.'], 400);
    }
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug 
                                   FROM projetos p 
                                   LEFT JOIN categorias c ON p.categoria_id = c.id 
                                   WHERE p.id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug 
                                   FROM projetos p 
                                   LEFT JOIN categorias c ON p.categoria_id = c.id 
                                   WHERE p.slug = ?");
            $stmt->execute([$slug]);
        }
        
        $projeto = $stmt->fetch();
        
        if (!$projeto) {
            jsonResponse(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }
        
        // Processar imagens_galeria
        $projeto['imagens_galeria'] = json_decode($projeto['imagens_galeria'], true) ?: [];
        
        jsonResponse([
            'success' => true,
            'projeto' => $projeto
        ]);
        
    } catch (Exception $e) {
        error_log("Get Project Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao buscar projeto.'], 500);
    }
}

/**
 * Criar projeto
 */
function criarProjeto() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
    }

    // CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
    
    $titulo = sanitize($_POST['titulo'] ?? '');
    $descricao = $_POST['descricao'] ?? '';
    $categoriaId = intval($_POST['categoria_id'] ?? 0);
    $tecnologias = sanitize($_POST['tecnologias'] ?? '');
    $urlProjeto = sanitize($_POST['url_projeto'] ?? '');
    $destaque = intval($_POST['destaque'] ?? 0);
    $ativo = intval($_POST['ativo'] ?? 1);
    $ordemRaw = $_POST['ordem'] ?? null;
    $ordem = null;
    if ($ordemRaw !== null) {
        $ordemRaw = trim((string)$ordemRaw);
        if ($ordemRaw !== '') {
            $ordemParsed = (int)$ordemRaw;
            $ordem = ($ordemParsed > 0) ? $ordemParsed : null;
        }
    }
    
    // Validar campos obrigatórios
    if (empty($titulo) || empty($categoriaId)) {
        jsonResponse(['success' => false, 'message' => 'Título e categoria são obrigatórios.'], 400);
    }
    
    // Gerar ou validar slug
    $slug = sanitize($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = generateSlug($titulo);
    } else {
        $slug = generateSlug($slug);
    }
    
    // Verificar se slug já existe
    $stmt = $pdo->prepare("SELECT id FROM projetos WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug = $slug . '-' . uniqid();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Upload de imagem principal
        $imagemPrincipal = '';
        if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['imagem_principal'], 'projeto');
            if ($upload['success']) {
                $imagemPrincipal = $upload['filename'];
            }
        }
        
        // Upload de galeria
        $imagensGaleria = [];
        if (isset($_FILES['imagens_galeria'])) {
            for ($i = 0; $i < count($_FILES['imagens_galeria']['name']); $i++) {
                if ($_FILES['imagens_galeria']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['imagens_galeria']['name'][$i],
                        'type' => $_FILES['imagens_galeria']['type'][$i],
                        'tmp_name' => $_FILES['imagens_galeria']['tmp_name'][$i],
                        'error' => $_FILES['imagens_galeria']['error'][$i],
                        'size' => $_FILES['imagens_galeria']['size'][$i]
                    ];
                    $upload = uploadFile($file, 'galeria');
                    if ($upload['success']) {
                        $imagensGaleria[] = $upload['filename'];
                    }
                }
            }
        }
        
        // Inserir projeto
        $stmt = $pdo->prepare("INSERT INTO projetos 
                              (titulo, slug, descricao, categoria_id, imagem_principal, imagens_galeria, 
                               tecnologias, url_projeto, destaque, ativo, ordem, prompt_descricao, prompt_linkedin) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $promptDescricao = $_POST['prompt_descricao'] ?? null;
        $promptLinkedin = $_POST['prompt_linkedin'] ?? null;

        $stmt->execute([
            $titulo,
            $slug,
            $descricao,
            $categoriaId,
            $imagemPrincipal,
            json_encode($imagensGaleria),
            $tecnologias,
            $urlProjeto,
            $destaque,
            $ativo,
            $ordem,
            $promptDescricao,
            $promptLinkedin
        ]);
        
        $projetoId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Projeto criado com sucesso!',
            'projeto_id' => $projetoId,
            'slug' => $slug
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create Project Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao criar projeto.'], 500);
    }
}

/**
 * Atualizar projeto
 */
function atualizarProjeto() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
    }

    // CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }
    
    try {
        // Buscar projeto atual
        $stmt = $pdo->prepare("SELECT * FROM projetos WHERE id = ?");
        $stmt->execute([$id]);
        $projetoAtual = $stmt->fetch();
        
        if (!$projetoAtual) {
            jsonResponse(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }
        
        $pdo->beginTransaction();
        
        // Atualizar campos
        $titulo = sanitize($_POST['titulo'] ?? $projetoAtual['titulo']);
        $descricao = $_POST['descricao'] ?? $projetoAtual['descricao'];
        $categoriaId = intval($_POST['categoria_id'] ?? $projetoAtual['categoria_id']);
        $tecnologias = sanitize($_POST['tecnologias'] ?? $projetoAtual['tecnologias']);
        $urlProjeto = sanitize($_POST['url_projeto'] ?? $projetoAtual['url_projeto']);
        $destaque = intval($_POST['destaque'] ?? $projetoAtual['destaque']);
        $ativo = intval($_POST['ativo'] ?? $projetoAtual['ativo']);
        $ordem = $projetoAtual['ordem'];
        if (array_key_exists('ordem', $_POST)) {
            $ordemRaw = trim((string)($_POST['ordem'] ?? ''));
            if ($ordemRaw === '') {
                $ordem = null;
            } else {
                $ordemParsed = (int)$ordemRaw;
                $ordem = ($ordemParsed > 0) ? $ordemParsed : null;
            }
        }
        
        // Atualizar slug
        $slug = sanitize($_POST['slug'] ?? '');
        if (empty($slug)) {
            if ($titulo !== $projetoAtual['titulo']) {
                $slug = generateSlug($titulo);
            } else {
                $slug = $projetoAtual['slug'];
            }
        } else {
            $slug = generateSlug($slug);
        }

        if ($slug !== $projetoAtual['slug']) {
            $stmt = $pdo->prepare("SELECT id FROM projetos WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetch()) {
                $slug = $slug . '-' . uniqid();
            }
        }
        
        // Upload de nova imagem principal
        $imagemPrincipal = $projetoAtual['imagem_principal'];
        if (isset($_FILES['imagem_principal']) && $_FILES['imagem_principal']['error'] === UPLOAD_ERR_OK) {
            // Deletar imagem antiga
            if ($imagemPrincipal) {
                deleteFile($imagemPrincipal);
            }
            $upload = uploadFile($_FILES['imagem_principal'], 'projeto');
            if ($upload['success']) {
                $imagemPrincipal = $upload['filename'];
            }
        }
        
        // Upload de novas imagens da galeria
        $imagensGaleria = json_decode($projetoAtual['imagens_galeria'], true) ?: [];
        if (isset($_FILES['imagens_galeria'])) {
            for ($i = 0; $i < count($_FILES['imagens_galeria']['name']); $i++) {
                if ($_FILES['imagens_galeria']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['imagens_galeria']['name'][$i],
                        'type' => $_FILES['imagens_galeria']['type'][$i],
                        'tmp_name' => $_FILES['imagens_galeria']['tmp_name'][$i],
                        'error' => $_FILES['imagens_galeria']['error'][$i],
                        'size' => $_FILES['imagens_galeria']['size'][$i]
                    ];
                    $upload = uploadFile($file, 'galeria');
                    if ($upload['success']) {
                        $imagensGaleria[] = $upload['filename'];
                    }
                }
            }
        }
        
        // Atualizar projeto
        $promptDescricao = $_POST['prompt_descricao'] ?? $projetoAtual['prompt_descricao'] ?? null;
        $promptLinkedin = $_POST['prompt_linkedin'] ?? $projetoAtual['prompt_linkedin'] ?? null;

        $stmt = $pdo->prepare("UPDATE projetos SET 
                              titulo = ?, slug = ?, descricao = ?, categoria_id = ?, 
                              imagem_principal = ?, imagens_galeria = ?, tecnologias = ?, 
                              url_projeto = ?, destaque = ?, ativo = ?, ordem = ?, prompt_descricao = ?, prompt_linkedin = ?, updated_at = CURRENT_TIMESTAMP 
                              WHERE id = ?");
        
        $stmt->execute([
            $titulo,
            $slug,
            $descricao,
            $categoriaId,
            $imagemPrincipal,
            json_encode($imagensGaleria),
            $tecnologias,
            $urlProjeto,
            $destaque,
            $ativo,
            $ordem,
            $promptDescricao,
            $promptLinkedin,
            $id
        ]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Projeto atualizado com sucesso!',
            'slug' => $slug
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Update Project Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao atualizar projeto.'], 500);
    }
}

/**
 * Deletar projeto
 */
function deletarProjeto() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }

        // CSRF
        $csrf = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
        }
    
    try {
        // Buscar projeto
        $stmt = $pdo->prepare("SELECT imagem_principal, imagens_galeria FROM projetos WHERE id = ?");
        $stmt->execute([$id]);
        $projeto = $stmt->fetch();
        
        if (!$projeto) {
            jsonResponse(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }
        
        $pdo->beginTransaction();
        
        // Deletar arquivos
        if ($projeto['imagem_principal']) {
            deleteFile($projeto['imagem_principal']);
        }
        
        $imagensGaleria = json_decode($projeto['imagens_galeria'], true) ?: [];
        foreach ($imagensGaleria as $imagem) {
            deleteFile($imagem);
        }
        
        // Deletar projeto
        $stmt = $pdo->prepare("DELETE FROM projetos WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Projeto deletado com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete Project Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao deletar projeto.'], 500);
    }
}

/**
 * Ordenar projetos (drag and drop)
 */
function ordenarProjetos() {
    global $pdo;
    
    $ordem = $_POST['ordem'] ?? [];
    
    if (!is_array($ordem) || empty($ordem)) {
        jsonResponse(['success' => false, 'message' => 'Ordem inválida.'], 400);
    }

        // CSRF
        $csrf = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
        }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE projetos SET ordem = ? WHERE id = ?");
        
        foreach ($ordem as $index => $id) {
            $stmt->execute([$index, $id]);
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Ordem atualizada com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Order Projects Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao ordenar projetos.'], 500);
    }
}

/**
 * Toggle status ativo/inativo
 */
function toggleStatus() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE projetos SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Status atualizado com sucesso!'
        ]);
        
    } catch (Exception $e) {
        error_log("Toggle Status Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao atualizar status.'], 500);
    }
}

/**
 * Toggle destaque
 */
function toggleDestaque() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE projetos SET destaque = NOT destaque WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Destaque atualizado com sucesso!'
        ]);
        
    } catch (Exception $e) {
        error_log("Toggle Destaque Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao atualizar destaque.'], 500);
    }
}

/**
 * Salvar post LinkedIn gerado por IA
 */
function salvarPostLinkedin() {
    global $pdo;
    
    $projetoId = intval($_POST['projeto_id'] ?? 0);
    $conteudo = $_POST['conteudo'] ?? '';
    $promptUsado = $_POST['prompt'] ?? '';
    
    if (!$projetoId || empty($conteudo)) {
        jsonResponse(['success' => false, 'message' => 'Projeto ID e conteúdo são obrigatórios.'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO posts_linkedin (projeto_id, conteudo, prompt_usado) VALUES (?, ?, ?)");
        $stmt->execute([$projetoId, $conteudo, $promptUsado]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Post salvo com sucesso!',
            'post_id' => $pdo->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        error_log("Save LinkedIn Post Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao salvar post.'], 500);
    }
}

/**
 * Buscar post LinkedIn por ID
 */
function buscarPostLinkedin() {
    global $pdo;
    
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM posts_linkedin WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        
        if (!$post) {
            jsonResponse(['success' => false, 'message' => 'Post não encontrado.'], 404);
        }
        
        jsonResponse([
            'success' => true,
            'post' => $post
        ]);
        
    } catch (Exception $e) {
        error_log("Get LinkedIn Post Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao buscar post.'], 500);
    }
}

/**
 * Deletar mídia específica da galeria de um projeto
 */
function deletarMediaProjeto() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
    }

    // CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }

    $projetoId = intval($_POST['projeto_id'] ?? 0);
    $filename = $_POST['filename'] ?? '';

    if (!$projetoId || empty($filename)) {
        jsonResponse(['success' => false, 'message' => 'Projeto ID e nome de arquivo são obrigatórios.'], 400);
    }

    try {
        // Buscar projeto
        $stmt = $pdo->prepare("SELECT imagens_galeria FROM projetos WHERE id = ?");
        $stmt->execute([$projetoId]);
        $projeto = $stmt->fetch();

        if (!$projeto) {
            jsonResponse(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }

        $imagens = json_decode($projeto['imagens_galeria'], true) ?: [];

        if (!in_array($filename, $imagens, true)) {
            jsonResponse(['success' => false, 'message' => 'Arquivo não pertence a este projeto.'], 400);
        }

        // Deletar arquivo do disco
        deleteFile($filename);

        // Remover da lista e atualizar
        $imagens = array_values(array_filter($imagens, function($i) use ($filename) { return $i !== $filename; }));

        $stmt = $pdo->prepare("UPDATE projetos SET imagens_galeria = ? WHERE id = ?");
        $stmt->execute([json_encode($imagens), $projetoId]);

        jsonResponse(['success' => true, 'message' => 'Mídia deletada com sucesso.']);

    } catch (Exception $e) {
        error_log("Delete Media Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao deletar mídia.'], 500);
    }
}

