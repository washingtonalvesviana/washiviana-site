<?php
/**
 * WASHIVIANA PORTFOLIO - API para listar modelos de LLM multimodal por provedor
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

$providerText = strtolower(trim($_GET['provider'] ?? $_POST['provider'] ?? getConfig('llm_text_provider') ?? 'gemini'));
$providerImage = strtolower(trim($_GET['provider_image'] ?? $_POST['provider_image'] ?? getConfig('llm_image_provider') ?? $providerText));
$providerVideo = strtolower(trim($_GET['provider_video'] ?? $_POST['provider_video'] ?? getConfig('llm_video_provider') ?? $providerImage));

$allowedProviders = ['gemini', 'openai', 'deepseek', 'openrouter', 'anthropic', 'ollama'];
if (!in_array($providerText, $allowedProviders, true)) $providerText = 'gemini';
if (!in_array($providerImage, $allowedProviders, true)) $providerImage = $providerText;
if (!in_array($providerVideo, $allowedProviders, true)) $providerVideo = $providerImage;

$apiKeyParam = trim((string)($_GET['api_key'] ?? $_POST['api_key'] ?? ''));
$ollamaBaseUrl = trim((string)($_GET['ollama_base_url'] ?? $_POST['ollama_base_url'] ?? getConfig('ollama_base_url') ?? 'http://localhost:11434'));
$openaiBaseUrl = trim((string)($_GET['openai_base_url'] ?? $_POST['openai_base_url'] ?? getConfig('openai_base_url') ?? ''));

function requestJson($url, $headers = [], $timeout = 30) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Erro de conexão: ' . $error, 'status' => 0, 'data' => null];
    }

    $data = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $httpCode);
        return ['success' => false, 'message' => $message, 'status' => $httpCode, 'data' => $data];
    }

    return ['success' => true, 'message' => 'OK', 'status' => $httpCode, 'data' => $data];
}

function providerApiKey($provider, $apiKeyParam) {
    if ($apiKeyParam !== '') {
        return $apiKeyParam;
    }

    switch ($provider) {
        case 'openai':
            return trim((string)getConfig('openai_api_key'));
        case 'deepseek':
            return trim((string)getConfig('deepseek_api_key'));
        case 'openrouter':
            return trim((string)getConfig('openrouter_api_key'));
        case 'anthropic':
            return trim((string)getConfig('anthropic_api_key'));
        case 'ollama':
            return trim((string)getConfig('ollama_api_key'));
        case 'gemini':
        default:
            return trim((string)getConfig('gemini_api_key'));
    }
}

function normalizeModel($id, $name = '', $description = '') {
    return [
        'id' => (string)$id,
        'name' => $name !== '' ? (string)$name : (string)$id,
        'description' => (string)$description
    ];
}

function categorizeById($id) {
    $v = strtolower((string)$id);
    $isImage = strpos($v, 'image') !== false || strpos($v, 'imagen') !== false || strpos($v, 'vision') !== false || strpos($v, 'flux') !== false;
    $isVideo = strpos($v, 'video') !== false || strpos($v, 'veo') !== false || strpos($v, 'sora') !== false;

    return [
        'text' => !$isImage && !$isVideo,
        'image' => $isImage,
        'video' => $isVideo,
    ];
}

function listGeminiModels($apiKey) {
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'API Key do Gemini não configurada'];
    }

    $models = [];
    $endpoints = [
        'https://generativelanguage.googleapis.com/v1/models?key=' . urlencode($apiKey),
        'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode($apiKey)
    ];

    foreach ($endpoints as $url) {
        $resp = requestJson($url, ['Content-Type: application/json']);
        if (!$resp['success'] || empty($resp['data']['models'])) {
            continue;
        }
        foreach ($resp['data']['models'] as $m) {
            $id = str_replace('models/', '', (string)($m['name'] ?? ''));
            if ($id === '') continue;
            if (!isset($models[$id])) {
                $models[$id] = $m;
            }
        }
    }

    if (empty($models)) {
        return ['success' => false, 'message' => 'Nenhum modelo Gemini encontrado para esta chave'];
    }

    $text = [];
    $image = [];
    $video = [];

    foreach ($models as $id => $m) {
        $displayName = (string)($m['displayName'] ?? $id);
        $description = (string)($m['description'] ?? '');
        $methods = $m['supportedGenerationMethods'] ?? [];
        $supportsText = in_array('generateContent', $methods, true);

        $c = categorizeById($id . ' ' . $displayName);

        if ($supportsText && !$c['image'] && !$c['video']) {
            $text[] = normalizeModel($id, $displayName, $description);
        }
        if ($c['image']) {
            $image[] = normalizeModel($id, $displayName, $description);
        }
        if ($c['video'] || in_array('generateVideos', $methods, true)) {
            $video[] = normalizeModel($id, $displayName, $description);
        }
    }

    usort($text, fn($a, $b) => strcmp($b['id'], $a['id']));
    usort($image, fn($a, $b) => strcmp($b['id'], $a['id']));
    usort($video, fn($a, $b) => strcmp($b['id'], $a['id']));

    return ['success' => true, 'textModels' => $text, 'imageModels' => $image, 'videoModels' => $video];
}

function listOpenAICompatibleModels($baseUrl, $apiKey, $extraHeaders = []) {
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'API Key não configurada'];
    }

    $headers = array_merge([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ], $extraHeaders);

    $resp = requestJson(rtrim($baseUrl, '/') . '/models', $headers, 30);
    if (!$resp['success']) {
        return ['success' => false, 'message' => 'Falha ao listar modelos: ' . $resp['message']];
    }

    $items = $resp['data']['data'] ?? [];
    $text = [];
    $image = [];
    $video = [];

    foreach ($items as $m) {
        $id = (string)($m['id'] ?? '');
        if ($id === '') continue;
        $c = categorizeById($id);
        if ($c['text']) $text[] = normalizeModel($id, $id, '');
        if ($c['image']) $image[] = normalizeModel($id, $id, '');
        if ($c['video']) $video[] = normalizeModel($id, $id, '');
    }

    if (empty($image) && strpos($baseUrl, 'api.openai.com') !== false) {
        $image[] = normalizeModel('gpt-image-1', 'gpt-image-1', 'Modelo de geração de imagem da OpenAI');
    }

    usort($text, fn($a, $b) => strcmp($a['id'], $b['id']));
    usort($image, fn($a, $b) => strcmp($a['id'], $b['id']));
    usort($video, fn($a, $b) => strcmp($a['id'], $b['id']));

    return ['success' => true, 'textModels' => $text, 'imageModels' => $image, 'videoModels' => $video];
}

function listAnthropicModels($apiKey) {
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'API Key da Anthropic não configurada'];
    }

    $resp = requestJson(
        'https://api.anthropic.com/v1/models',
        [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ],
        30
    );

    $text = [];
    if ($resp['success']) {
        $items = $resp['data']['data'] ?? [];
        foreach ($items as $m) {
            $id = (string)($m['id'] ?? '');
            if ($id !== '') $text[] = normalizeModel($id, $id, '');
        }
    }

    if (empty($text)) {
        $text = [
            normalizeModel('claude-3-5-sonnet-latest', 'claude-3.5 Sonnet (latest)', ''),
            normalizeModel('claude-3-7-sonnet-latest', 'claude-3.7 Sonnet (latest)', ''),
            normalizeModel('claude-3-5-haiku-latest', 'claude-3.5 Haiku (latest)', '')
        ];
    }

    return ['success' => true, 'textModels' => $text, 'imageModels' => [], 'videoModels' => []];
}

function listOllamaModels($baseUrl, $apiKey = '') {
    $headers = ['Content-Type: application/json'];
    if ($apiKey !== '') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }

    $resp = requestJson(rtrim($baseUrl, '/') . '/api/tags', $headers, 15);
    if (!$resp['success']) {
        return ['success' => false, 'message' => 'Não foi possível consultar Ollama: ' . $resp['message']];
    }

    $items = $resp['data']['models'] ?? [];
    $text = [];
    $image = [];
    $video = [];

    foreach ($items as $m) {
        $id = (string)($m['name'] ?? '');
        if ($id === '') continue;
        $c = categorizeById($id);
        if ($c['text']) $text[] = normalizeModel($id, $id, 'Local Ollama');
        if ($c['image']) $image[] = normalizeModel($id, $id, 'Local Ollama');
        if ($c['video']) $video[] = normalizeModel($id, $id, 'Local Ollama');
    }

    usort($text, fn($a, $b) => strcmp($a['id'], $b['id']));
    usort($image, fn($a, $b) => strcmp($a['id'], $b['id']));
    usort($video, fn($a, $b) => strcmp($a['id'], $b['id']));

    return ['success' => true, 'textModels' => $text, 'imageModels' => $image, 'videoModels' => $video];
}

try {
    $providerForFetch = $providerText;
    $apiKey = providerApiKey($providerForFetch, $apiKeyParam);

    switch ($providerForFetch) {
        case 'openai':
            $result = listOpenAICompatibleModels($openaiBaseUrl !== '' ? $openaiBaseUrl : 'https://api.openai.com/v1', $apiKey);
            if (!$result['success'] && $apiKey === '') {
                $result['message'] = 'OpenAI: API Key não configurada';
            }
            break;
        case 'deepseek':
            $result = listOpenAICompatibleModels('https://api.deepseek.com/v1', $apiKey);
            if (!$result['success'] && $apiKey === '') {
                $result['message'] = 'DeepSeek: API Key não configurada';
            }
            break;
        case 'openrouter':
            $result = listOpenAICompatibleModels('https://openrouter.ai/api/v1', $apiKey, [
                'HTTP-Referer: https://washiviana.com',
                'X-Title: Washiviana Admin'
            ]);
            if (!$result['success'] && $apiKey === '') {
                $result['message'] = 'OpenRouter: API Key não configurada';
            }
            break;
        case 'anthropic':
            $result = listAnthropicModels($apiKey);
            break;
        case 'ollama':
            $result = listOllamaModels($ollamaBaseUrl, $apiKey);
            break;
        case 'gemini':
        default:
            $result = listGeminiModels($apiKey);
            if (!$result['success'] && $apiKey === '') {
                $result['message'] = 'Gemini: API Key não configurada';
            }
            break;
    }

    if (!$result['success']) {
        jsonResponse(['success' => false, 'message' => $result['message'] ?? 'Erro ao listar modelos']);
    }

    jsonResponse([
        'success' => true,
        'provider' => $providerForFetch,
        'textModels' => $result['textModels'] ?? [],
        'imageModels' => $result['imageModels'] ?? [],
        'videoModels' => $result['videoModels'] ?? [],
        'totalModels' => count($result['textModels'] ?? []) + count($result['imageModels'] ?? []) + count($result['videoModels'] ?? [])
    ]);
} catch (Exception $e) {
    error_log('LLM Models API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro interno ao listar modelos'], 500);
}
