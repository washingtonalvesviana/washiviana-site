<?php
/**
 * WASHIVIANA PORTFOLIO - OpenAI Integration API
 * Integração com OpenAI para geração de conteúdo com IA
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
}

switch ($action) {
    case 'gerar':
        gerarTextoIA();
        break;
    
    case 'testar':
        testarConexao();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}

/**
 * Gerar texto com IA
 */
function gerarTextoIA() {
    global $pdo;
    
    $prompt = $_POST['prompt'] ?? '';
    $maxTokens = intval($_POST['max_tokens'] ?? 800);
    
    if (empty($prompt)) {
        jsonResponse(['success' => false, 'message' => 'Prompt é obrigatório.'], 400);
    }
    
    // Buscar configurações
    $apiKey = getConfig('openai_api_key');
    $model = getConfig('openai_model') ?: 'gpt-4';
    
    if (empty($apiKey)) {
        jsonResponse([
            'success' => false, 
            'message' => 'API Key da OpenAI não configurada. Configure em Configurações.'
        ], 400);
    }
    
    try {
        // Chamar OpenAI API
        $response = callOpenAI($apiKey, $model, $prompt, $maxTokens);
        
        if ($response['success']) {
            jsonResponse([
                'success' => true,
                'texto' => $response['texto'],
                'tokens_usados' => $response['tokens_usados']
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => $response['error']
            ], 500);
        }
        
    } catch (Exception $e) {
        error_log("OpenAI Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao gerar texto com IA.'], 500);
    }
}

/**
 * Testar conexão com OpenAI
 */
function testarConexao() {
    $apiKey = getConfig('openai_api_key');
    
    if (empty($apiKey)) {
        jsonResponse([
            'success' => false,
            'message' => 'API Key não configurada.'
        ], 400);
    }
    
    $testPrompt = "Diga apenas: 'Conexão estabelecida com sucesso!'";
    $response = callOpenAI($apiKey, 'gpt-3.5-turbo', $testPrompt, 50);
    
    if ($response['success']) {
        jsonResponse([
            'success' => true,
            'message' => 'Conexão com OpenAI estabelecida com sucesso!',
            'resposta' => $response['texto']
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao conectar com OpenAI: ' . $response['error']
        ], 500);
    }
}

/**
 * Função auxiliar para chamar OpenAI API
 */
function callOpenAI($apiKey, $model, $prompt, $maxTokens = 800) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Você é um assistente especializado em criar conteúdo profissional para projetos de design, tecnologia e desenvolvimento. Seja criativo, objetivo e use linguagem profissional.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => $maxTokens,
        'temperature' => 0.7,
        'top_p' => 1.0,
        'frequency_penalty' => 0.0,
        'presence_penalty' => 0.0
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'Erro de conexão: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $errorMessage = $responseData['error']['message'] ?? 'Erro desconhecido';
        return [
            'success' => false,
            'error' => $errorMessage
        ];
    }
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        return [
            'success' => false,
            'error' => 'Resposta inválida da API'
        ];
    }
    
    return [
        'success' => true,
        'texto' => trim($responseData['choices'][0]['message']['content']),
        'tokens_usados' => $responseData['usage']['total_tokens'] ?? 0
    ];
}

