<?php
/**
 * WASHIVIANA PORTFOLIO - Save Single Config
 * Salvar configuração individual
 */
require_once __DIR__ . '/config.php';

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$chave = $_POST['chave'] ?? '';
$valor = $_POST['valor'] ?? '';

if (empty($chave)) {
    jsonResponse(['success' => false, 'message' => 'Chave é obrigatória.'], 400);
}

try {
    setConfig($chave, $valor);
    jsonResponse(['success' => true, 'message' => 'Configuração salva.']);
} catch (Exception $e) {
    error_log("Save Config Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao salvar configuração.'], 500);
}

