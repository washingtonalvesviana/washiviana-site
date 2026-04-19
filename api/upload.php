<?php
/**
 * WASHIVIANA PORTFOLIO - Upload API
 * Upload de imagens e arquivos
 */

require_once __DIR__ . '/config.php';

// Verificar autenticação
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

header('Content-Type: application/json; charset=utf-8');

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$action = $_POST['action'] ?? '';

// CSRF para POST
$csrf = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
}

switch ($action) {
    case 'upload':
        handleUpload();
        break;
    
    case 'delete':
        handleDelete();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}

/**
 * Upload de arquivo
 */
function handleUpload() {
    if (!isset($_FILES['file'])) {
        jsonResponse(['success' => false, 'message' => 'Nenhum arquivo enviado.'], 400);
    }
    
    $prefix = sanitize($_POST['prefix'] ?? 'file');
    $result = uploadFile($_FILES['file'], $prefix);
    
    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'message' => 'Arquivo enviado com sucesso!',
            'filename' => $result['filename'],
            'url' => $result['url']
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }
}

/**
 * Deletar arquivo
 */
function handleDelete() {
    $filename = sanitize($_POST['filename'] ?? '');
    
    if (empty($filename)) {
        jsonResponse(['success' => false, 'message' => 'Nome do arquivo é obrigatório.'], 400);
    }
    
    // Validar nome do arquivo (segurança)
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
        jsonResponse(['success' => false, 'message' => 'Nome de arquivo inválido.'], 400);
    }
    
    if (deleteFile($filename)) {
        jsonResponse([
            'success' => true,
            'message' => 'Arquivo deletado com sucesso!'
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao deletar arquivo.'
        ], 500);
    }
}

