<?php
/**
 * WASHIVIANA PORTFOLIO - Categorias API
 * CRUD de categorias
 */

require_once __DIR__ . '/config.php';

// Verificar autenticação para operações de escrita
$writeActions = ['criar', 'atualizar', 'deletar', 'ordenar'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (in_array($action, $writeActions) && !isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

header('Content-Type: application/json; charset=utf-8');

/**
 * Helper para resolver tabela alvo (projetos ou artigos)
 */
function getCategoryTables($target) {
    $target = $target ?? '';
    if ($target === 'artigos') {
        return [
            'cat_table' => 'categorias_artigos',
            'item_table' => 'artigos',
            'item_label' => 'artigos',
            'noun' => 'categoria de artigos'
        ];
    }

    return [
        'cat_table' => 'categorias',
        'item_table' => 'projetos',
        'item_label' => 'projetos',
        'noun' => 'categoria'
    ];
}

switch ($action) {
    case 'listar':
        listarCategorias();
        break;
    
    case 'buscar':
        buscarCategoria();
        break;
    
    case 'criar':
        criarCategoria();
        break;
    
    case 'atualizar':
        atualizarCategoria();
        break;
    
    case 'deletar':
        deletarCategoria();
        break;
    
    case 'ordenar':
        ordenarCategorias();
        break;
    
    case 'toggle_status':
        toggleStatus();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}

/**
 * Listar categorias
 */
function listarCategorias() {
    global $pdo;
    
    $apenasAtivas = isset($_GET['ativas']) && $_GET['ativas'] == '1';
    $target = $_GET['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $catTable = $tables['cat_table'];
    $itemTable = $tables['item_table'];
    $itemLabel = $tables['item_label'];
    
    try {
        if ($apenasAtivas) {
            $stmt = $pdo->query("SELECT * FROM {$catTable} WHERE ativo = true ORDER BY ordem ASC");
        } else {
            $stmt = $pdo->query("SELECT * FROM {$catTable} ORDER BY ordem ASC");
        }
        
        $categorias = $stmt->fetchAll();
        
        // Contar itens por categoria (projetos ou artigos)
        foreach ($categorias as &$categoria) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$itemTable} WHERE categoria_id = ?");
            $stmt->execute([$categoria['id']]);
            $result = $stmt->fetch();
            $categoria['total_items'] = $result['total'];
            if ($target === 'artigos') {
                $categoria['total_artigos'] = $result['total'];
            } else {
                $categoria['total_projetos'] = $result['total'];
            }
        }
        
        jsonResponse([
            'success' => true,
            'categorias' => $categorias
        ]);
        
    } catch (Exception $e) {
        error_log("List Categories Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao listar categorias.'], 500);
    }
}

/**
 * Buscar categoria por ID ou slug
 */
function buscarCategoria() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    $slug = $_GET['slug'] ?? null;
    $target = $_GET['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $catTable = $tables['cat_table'];
    $itemTable = $tables['item_table'];
    
    if (!$id && !$slug) {
        jsonResponse(['success' => false, 'message' => 'ID ou slug é obrigatório.'], 400);
    }
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM {$catTable} WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM {$catTable} WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        
        $categoria = $stmt->fetch();
        
        if (!$categoria) {
            jsonResponse(['success' => false, 'message' => 'Categoria não encontrada.'], 404);
        }
        
        // Contar itens (projetos ou artigos)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$itemTable} WHERE categoria_id = ?");
        $stmt->execute([$categoria['id']]);
        $result = $stmt->fetch();
        $categoria['total_items'] = $result['total'];
        
        jsonResponse([
            'success' => true,
            'categoria' => $categoria
        ]);
        
    } catch (Exception $e) {
        error_log("Get Category Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao buscar categoria.'], 500);
    }
}

/**
 * Criar categoria
 */
function criarCategoria() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
    }

    // CSRF
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
    
    $nome = sanitize($_POST['nome'] ?? '');
    $ativo = intval($_POST['ativo'] ?? 1);
    $target = $_POST['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $catTable = $tables['cat_table'];
    $noun = $tables['noun'];
    
    if (empty($nome)) {
        jsonResponse(['success' => false, 'message' => 'Nome é obrigatório.'], 400);
    }
    
    // Gerar slug
    $slug = generateSlug($nome);
    
    // Verificar se slug já existe
    $stmt = $pdo->prepare("SELECT id FROM {$catTable} WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Já existe uma categoria com este nome.'], 400);
    }
    
    try {
        // Obter próxima ordem
        $stmt = $pdo->query("SELECT MAX(ordem) as max_ordem FROM {$catTable}");
        $result = $stmt->fetch();
        $ordem = ($result['max_ordem'] ?? 0) + 1;
        
        // Inserir categoria
        $stmt = $pdo->prepare("INSERT INTO {$catTable} (nome, slug, ordem, ativo) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $slug, $ordem, $ativo]);
        
        $categoriaId = $pdo->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => ucfirst($noun) . ' criada com sucesso!',
            'categoria_id' => $categoriaId,
            'slug' => $slug
        ]);
        
    } catch (Exception $e) {
        error_log("Create Category Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao criar categoria.'], 500);
    }
}

/**
 * Atualizar categoria
 */
function atualizarCategoria() {
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
    $nome = sanitize($_POST['nome'] ?? '');
    $ativo = intval($_POST['ativo'] ?? 1);
    $target = $_POST['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $catTable = $tables['cat_table'];
    $noun = $tables['noun'];
    
    if (!$id || empty($nome)) {
        jsonResponse(['success' => false, 'message' => 'ID e nome são obrigatórios.'], 400);
    }
    
    try {
        // Buscar categoria atual
        $stmt = $pdo->prepare("SELECT slug FROM {$catTable} WHERE id = ?");
        $stmt->execute([$id]);
        $categoriaAtual = $stmt->fetch();
        
        if (!$categoriaAtual) {
            jsonResponse(['success' => false, 'message' => ucfirst($noun) . ' não encontrada.'], 404);
        }
        
        // Gerar novo slug
        $slug = generateSlug($nome);
        
        // Verificar se novo slug já existe (exceto na categoria atual)
        $stmt = $pdo->prepare("SELECT id FROM {$catTable} WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Já existe uma categoria com este nome.'], 400);
        }
        
        // Atualizar categoria
        $stmt = $pdo->prepare("UPDATE {$catTable} SET nome = ?, slug = ?, ativo = ? WHERE id = ?");
        $stmt->execute([$nome, $slug, $ativo, $id]);
        
        jsonResponse([
            'success' => true,
            'message' => ucfirst($noun) . ' atualizada com sucesso!',
            'slug' => $slug
        ]);
        
    } catch (Exception $e) {
        error_log("Update Category Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao atualizar categoria.'], 500);
    }
}

/**
 * Deletar categoria
 */
function deletarCategoria() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    $target = $_POST['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $itemTable = $tables['item_table'];
    $catTable = $tables['cat_table'];
    $itemLabel = $tables['item_label'];
    $noun = $tables['noun'];
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }

        // CSRF
        $csrf = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($csrf)) {
            jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
        }
    
    try {
        // Verificar se categoria tem itens associados (projetos ou artigos)
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM {$itemTable} WHERE categoria_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            jsonResponse([
                'success' => false,
                'message' => 'Não é possível deletar categoria com ' . $itemLabel . ' associados. Remova os ' . $itemLabel . ' primeiro.'
            ], 400);
        }
        
        // Deletar categoria
        $stmt = $pdo->prepare("DELETE FROM {$catTable} WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonResponse([
            'success' => true,
            'message' => ucfirst($noun) . ' deletada com sucesso!'
        ]);
        
    } catch (Exception $e) {
        error_log("Delete Category Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao deletar categoria.'], 500);
    }
}

/**
 * Ordenar categorias (drag and drop)
 */
function ordenarCategorias() {
    global $pdo;
    
    $ordem = $_POST['ordem'] ?? [];
    $target = $_POST['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $catTable = $tables['cat_table'];
    
    if (!is_array($ordem) || empty($ordem)) {
        jsonResponse(['success' => false, 'message' => 'Ordem inválida.'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE {$catTable} SET ordem = ? WHERE id = ?");
        
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
        error_log("Order Categories Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao ordenar categorias.'], 500);
    }
}

/**
 * Toggle status ativo/inativo
 */
function toggleStatus() {
    global $pdo;
    
    $id = intval($_POST['id'] ?? 0);
    $target = $_POST['target'] ?? 'projetos';
    $tables = getCategoryTables($target);
    $catTable = $tables['cat_table'];
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'ID é obrigatório.'], 400);
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE {$catTable} SET ativo = NOT ativo WHERE id = ?");
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

