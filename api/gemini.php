<?php
/**
 * WASHIVIANA PORTFOLIO - Google Gemini API Integration
 * Geração de texto e imagens com Google AI
 */
require_once __DIR__ . '/config.php';

// Permite incluir este arquivo como biblioteca (ex.: scripts CLI) sem executar o dispatch HTTP.
if (!defined('WASHIVIANA_SKIP_DISPATCH')) {

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF para POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
}

switch ($action) {
    case 'test':
        testarConexao();
        break;
    case 'generate_text':
        gerarTexto();
        break;
    case 'generate_image':
        gerarImagem();
        break;
    case 'generate_images_multi':
        gerarImagensMultiplosFormatos();
        break;
    case 'generate_article':
        gerarArtigo();
        break;
    case 'generate_social_agent':
        gerarSocialAgent();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Ação não especificada']);
}

} // fim do dispatch HTTP

function getLlmProviderByType($type) {
    $key = 'llm_' . $type . '_provider';
    $provider = strtolower(trim((string)(getConfig($key) ?: 'gemini')));
    $allowed = ['gemini', 'openai', 'deepseek', 'openrouter', 'anthropic', 'ollama'];
    return in_array($provider, $allowed, true) ? $provider : 'gemini';
}

function getLlmModelByType($type, $provider) {
    $genericKey = 'llm_' . $type . '_model';
    $generic = trim((string)getConfig($genericKey));
    if ($generic !== '') {
        return $generic;
    }

    if ($type === 'text' && $provider === 'gemini') {
        return trim((string)(getConfig('gemini_model') ?: 'gemini-2.0-flash'));
    }
    if ($type === 'image' && $provider === 'gemini') {
        return trim((string)(getConfig('gemini_image_model') ?: 'imagen-3.0-generate-002'));
    }
    if ($type === 'text' && $provider === 'openai') {
        return trim((string)(getConfig('openai_model') ?: 'gpt-4o-mini'));
    }
    if ($type === 'image' && $provider === 'openai') {
        return 'gpt-image-1';
    }
    if ($type === 'text' && $provider === 'deepseek') {
        return 'deepseek-chat';
    }
    if ($type === 'text' && $provider === 'openrouter') {
        return 'openai/gpt-4o-mini';
    }
    if ($type === 'text' && $provider === 'anthropic') {
        return 'claude-3-5-sonnet-latest';
    }
    if ($type === 'text' && $provider === 'ollama') {
        return trim((string)(getConfig('ollama_model') ?: 'llama3.1'));
    }

    return '';
}

function getLlmApiKey($provider) {
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

function callJsonHttp($url, $payload, $headers = [], $timeout = 120) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'Erro de conexão: ' . $error, 'status' => 0, 'data' => null];
    }

    $decoded = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $decoded['error']['message'] ?? $decoded['message'] ?? ('HTTP ' . $httpCode);
        return ['success' => false, 'error' => $msg, 'status' => $httpCode, 'data' => $decoded];
    }

    return ['success' => true, 'status' => $httpCode, 'data' => $decoded];
}

function gerarTextoComProvider($provider, $model, $prompt, $maxTokens) {
    $apiKey = getLlmApiKey($provider);
    if ($provider !== 'ollama' && $apiKey === '') {
        return ['success' => false, 'error' => 'API Key não configurada para ' . strtoupper($provider)];
    }

    if ($provider === 'openai' || $provider === 'deepseek' || $provider === 'openrouter') {
        $baseUrl = 'https://api.openai.com/v1';
        $headers = ['Authorization: Bearer ' . $apiKey];

        if ($provider === 'openai') {
            // Base URL opcional para endpoints OpenAI-compatíveis (vLLM, LM Studio, etc.)
            $customBaseUrl = trim((string)getConfig('openai_base_url'));
            if ($customBaseUrl !== '') {
                $baseUrl = rtrim($customBaseUrl, '/');
            }
        } elseif ($provider === 'deepseek') {
            $baseUrl = 'https://api.deepseek.com/v1';
        } elseif ($provider === 'openrouter') {
            $baseUrl = 'https://openrouter.ai/api/v1';
            $headers[] = 'HTTP-Referer: https://washiviana.com';
            $headers[] = 'X-Title: Washiviana Admin';
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => (int)$maxTokens,
            'temperature' => 0.7
        ];

        // Endpoints OpenAI-compatíveis self-hosted (ex.: vLLM) com modelos híbridos
        // (Qwen3) podem consumir todos os tokens em "reasoning" e devolver content vazio.
        // Desabilita o modo thinking apenas quando uma base URL customizada está configurada.
        if ($provider === 'openai' && trim((string)getConfig('openai_base_url')) !== '') {
            $payload['chat_template_kwargs'] = ['enable_thinking' => false];
        }

        $resp = callJsonHttp($baseUrl . '/chat/completions', $payload, $headers, 180);

        if (!$resp['success']) {
            return ['success' => false, 'error' => $resp['error']];
        }

        $text = trim((string)($resp['data']['choices'][0]['message']['content'] ?? ''));
        if ($text === '') {
            return ['success' => false, 'error' => 'A IA não retornou texto'];
        }

        return ['success' => true, 'text' => $text];
    }

    if ($provider === 'anthropic') {
        $resp = callJsonHttp('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => (int)$maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ], [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ], 180);

        if (!$resp['success']) {
            return ['success' => false, 'error' => $resp['error']];
        }

        $content = $resp['data']['content'] ?? [];
        $text = '';
        foreach ($content as $part) {
            if (($part['type'] ?? '') === 'text') {
                $text .= (string)($part['text'] ?? '');
            }
        }
        $text = trim($text);
        if ($text === '') {
            return ['success' => false, 'error' => 'A IA não retornou texto'];
        }

        return ['success' => true, 'text' => $text];
    }

    if ($provider === 'ollama') {
        $baseUrl = rtrim((string)(getConfig('ollama_base_url') ?: 'http://localhost:11434'), '/');
        $headers = [];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $resp = callJsonHttp($baseUrl . '/api/chat', [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'stream' => false
        ], $headers, 180);

        if (!$resp['success']) {
            return ['success' => false, 'error' => $resp['error']];
        }

        $text = trim((string)($resp['data']['message']['content'] ?? ''));
        if ($text === '') {
            return ['success' => false, 'error' => 'O Ollama não retornou texto'];
        }

        return ['success' => true, 'text' => $text];
    }

    return ['success' => false, 'error' => 'Provedor não suportado para texto'];
}

function salvarImagemBase64($b64, $ext = 'png') {
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $safeExt = in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) ? $ext : 'png';
    $filename = 'ai_' . uniqid() . '.' . ($safeExt === 'jpeg' ? 'jpg' : $safeExt);
    $filepath = UPLOAD_DIR . $filename;
    file_put_contents($filepath, base64_decode($b64));
    return $filename;
}

function baixarImagemParaUpload($url) {
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $binary = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || !$binary) {
        return null;
    }

    $filename = 'ai_' . uniqid() . '.png';
    file_put_contents(UPLOAD_DIR . $filename, $binary);
    return $filename;
}

function gerarImagemComProvider($provider, $prompt, $model) {
    if ($provider !== 'openai') {
        return ['success' => false, 'error' => 'Provedor não suportado para geração de imagem automática neste fluxo'];
    }

    $apiKey = getLlmApiKey('openai');
    if ($apiKey === '') {
        return ['success' => false, 'error' => 'API Key da OpenAI não configurada'];
    }

    $resp = callJsonHttp('https://api.openai.com/v1/images/generations', [
        'model' => $model ?: 'gpt-image-1',
        'prompt' => $prompt,
        'size' => '1536x1024'
    ], [
        'Authorization: Bearer ' . $apiKey
    ], 300);

    if (!$resp['success']) {
        return ['success' => false, 'error' => $resp['error']];
    }

    $item = $resp['data']['data'][0] ?? [];
    if (!empty($item['b64_json'])) {
        $filename = salvarImagemBase64((string)$item['b64_json'], 'png');
        return [
            'success' => true,
            'filename' => $filename,
            'url' => UPLOAD_URL . $filename,
            'image_url' => UPLOAD_URL . $filename,
            'model' => $model ?: 'gpt-image-1'
        ];
    }

    if (!empty($item['url'])) {
        $filename = baixarImagemParaUpload((string)$item['url']);
        if ($filename) {
            return [
                'success' => true,
                'filename' => $filename,
                'url' => UPLOAD_URL . $filename,
                'image_url' => UPLOAD_URL . $filename,
                'model' => $model ?: 'gpt-image-1'
            ];
        }
    }

    return ['success' => false, 'error' => 'Resposta de imagem inválida da OpenAI'];
}

function testarConexaoProvider($provider, $model) {
    if ($provider === 'gemini') {
        return ['success' => false, 'error' => 'Use o fluxo nativo Gemini'];
    }

    if ($provider === 'ollama') {
        $baseUrl = rtrim((string)(getConfig('ollama_base_url') ?: 'http://localhost:11434'), '/');
        $key = getLlmApiKey('ollama');
        $headers = ['Content-Type: application/json'];
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/api/tags',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['success' => false, 'error' => 'Erro de conexão com Ollama: ' . $err];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['success' => false, 'error' => 'Ollama respondeu HTTP ' . $httpCode];
        }
        return ['success' => true, 'response' => 'Conexão com Ollama estabelecida com sucesso!'];
    }

    $result = gerarTextoComProvider(
        $provider,
        $model,
        'Olá! Responda apenas com "Conexão estabelecida com sucesso!" em português.',
        60
    );

    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    return ['success' => true, 'response' => $result['text']];
}

/**
 * Testar conexão com API do Gemini
 */
function testarConexao() {
    $provider = strtolower(trim((string)($_POST['provider'] ?? getLlmProviderByType('text'))));
    if ($provider !== 'gemini') {
        $model = getLlmModelByType('text', $provider);
        $result = testarConexaoProvider($provider, $model);
        if ($result['success']) {
            jsonResponse([
                'success' => true,
                'message' => 'Conexão OK!',
                'response' => $result['response'] ?? 'OK',
                'model' => $model ?: '-',
                'provider' => $provider
            ]);
        }
        jsonResponse(['success' => false, 'message' => $result['error'] ?? 'Falha ao testar conexão']);
    }

    $apiKey = getConfig('gemini_api_key');
    
    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'message' => 'API Key não configurada']);
    }
    
    $model = getConfig('gemini_model') ?: 'gemini-2.0-flash';
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => 'Olá! Responda apenas com "Conexão estabelecida com sucesso!" em português.']
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 100,
            'temperature' => 0.1
        ]
    ];
    
    $response = callGeminiAPI($url, $data);
    
    if ($response['success']) {
        $text = $response['data']['candidates'][0]['content']['parts'][0]['text'] ?? 'Sem resposta';
        jsonResponse([
            'success' => true, 
            'message' => 'Conexão OK!',
            'response' => $text,
            'model' => $model
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => $response['error']]);
    }
}

function gerarTexto() {
    // Aumentar tempo de execução para evitar timeout em respostas longas
    set_time_limit(300);

    $prompt = $_POST['prompt'] ?? '';
    $contexto = $_POST['context'] ?? '';
    $debug = ($_POST['debug'] ?? '') === '1';
    
    if (empty($prompt)) {
        jsonResponse(['success' => false, 'message' => 'Prompt não fornecido']);
    }
    
    $provider = getLlmProviderByType('text');
    $apiKey = getConfig('gemini_api_key');
    $model = getLlmModelByType('text', $provider);
    
    // Aumentar tokens para descrição de projeto (textos longos)
    $defaultMaxTokens = (int)(getConfig('gemini_max_tokens') ?: 2048);
    $maxTokens = ($contexto === 'projeto') ? 8192 : $defaultMaxTokens;
    
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
    
    // Instruções específicas por contexto
    $instrucoes = '';
    $instrucoesKey = 'ia_instrucoes';

    if ($contexto === 'projeto') {
        $instrucoesKey = 'ia_instrucoes_projeto';
        // Forçar saída HTML para projetos
        $instrucoes .= "IMPORTANTE: A saída DEVE ser formatada em HTML. Use tags <p>, <strong>, <ul>, <li>, <h2>, etc. NÃO use Markdown (como ** ou #). O texto deve ser visualmente rico e bem estruturado. Limite o texto a NO MÁXIMO 800 palavras.\n";
    } elseif ($contexto === 'linkedin') {
        $instrucoesKey = 'ia_instrucoes_linkedin';
    }

    $dbInstrucoes = getConfig($instrucoesKey) ?: '';
    $instrucoes .= $dbInstrucoes;

    // Fallback: se não houver instruções específicas, usar a geral
    if (empty($instrucoes) && $instrucoesKey !== 'ia_instrucoes') {
        $fallback = getConfig('ia_instrucoes') ?: '';
        if (!empty($fallback)) {
            $instrucoes = $fallback;
            $instrucoesKey = 'ia_instrucoes (fallback)';
        }
    }

    $promptFinal = '';
    if (!empty($instrucoes)) {
        $promptFinal .= $instrucoes . "\n\n";
    }
    $promptFinal .= $prompt;

    if ($provider !== 'gemini') {
        $providerResult = gerarTextoComProvider($provider, $model, $promptFinal, $maxTokens);
        if ($providerResult['success']) {
            $payload = ['success' => true, 'text' => $providerResult['text']];
            if ($debug) {
                $payload['debug'] = [
                    'context' => $contexto,
                    'provider' => $provider,
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'instructions_key_used' => $instrucoesKey,
                    'instructions_present' => !empty($instrucoes),
                    'instructions_length' => strlen($instrucoes),
                ];
            }
            jsonResponse($payload);
        }
        jsonResponse(['success' => false, 'message' => $providerResult['error'] ?? 'Erro ao gerar texto']);
    }

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $promptFinal]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature' => 0.7
        ]
    ];

    // Liberar sessão e chamar API
    session_write_close();

    $response = callGeminiAPI($url, $data);

    if ($response['success']) {
        $modelUsed = $response['meta']['model_used'] ?? $model;
        $fallbackModelUsed = (bool)($response['meta']['fallback_used'] ?? false);
        $candidate = $response['data']['candidates'][0] ?? null;
        $text = '';
        $finishReason = '';
        if ($candidate && isset($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
            $finishReason = $candidate['finishReason'] ?? '';
        }

        $wordCount = 0;
        $trimmed = trim($text);
        if ($trimmed !== '') {
            $wordCount = count(preg_split('/\s+/u', $trimmed));
        }

        $retryUsed = false;
        $retryWordCount = null;
        $retryFinishReason = null;

        $minWords = 0;
        if ($contexto === 'projeto') {
            $minWords = 120;
        } elseif ($contexto === 'linkedin') {
            $minWords = 160;
        }

        if ($minWords > 0 && $wordCount > 0 && $wordCount < $minWords) {
            $retryUsed = true;
            $retryPromptFinal = $promptFinal . "\n\nIMPORTANTE: Responda com pelo menos {$minWords} palavras. Não responda com menos de {$minWords} palavras.";
            $data['contents'][0]['parts'][0]['text'] = $retryPromptFinal;
            $retryResponse = callGeminiAPI($url, $data);

            if ($retryResponse['success']) {
                $retryCandidate = $retryResponse['data']['candidates'][0] ?? null;
                $retryText = '';
                if ($retryCandidate && isset($retryCandidate['content']['parts'])) {
                    foreach ($retryCandidate['content']['parts'] as $part) {
                        if (isset($part['text'])) {
                            $retryText .= $part['text'];
                        }
                    }
                    $retryFinishReason = $retryCandidate['finishReason'] ?? '';
                }

                $retryTrimmed = trim($retryText);
                if ($retryTrimmed !== '') {
                    $retryWordCount = count(preg_split('/\s+/u', $retryTrimmed));
                } else {
                    $retryWordCount = 0;
                }

                if ($retryWordCount >= $wordCount) {
                    $text = $retryText;
                    $finishReason = $retryFinishReason;
                    $wordCount = $retryWordCount;
                }
            }
        }

        if (trim($text) === '') {
            $promptFeedback = $response['data']['promptFeedback'] ?? [];
            $blockReason = $promptFeedback['blockReason'] ?? '';
            $blockMessage = $promptFeedback['blockReasonMessage'] ?? '';
            $safetyReason = $finishReason ? "finishReason={$finishReason}" : '';

            $parts = array_filter([$blockReason, $blockMessage, $safetyReason]);
            $details = $parts ? (' Detalhes: ' . implode(' | ', $parts)) : '';

            $payload = [
                'success' => false,
                'message' => 'A IA não retornou texto (possível bloqueio por segurança ou conteúdo vazio).' . $details
            ];
            if ($debug) {
                $payload['debug'] = [
                    'context' => $contexto,
                    'model' => $model,
                    'model_used' => $modelUsed,
                    'fallback_used' => $fallbackModelUsed,
                    'max_tokens' => $maxTokens,
                    'instructions_key_used' => $instrucoesKey,
                    'instructions_present' => !empty($instrucoes),
                    'instructions_length' => strlen($instrucoes),
                    'instructions_preview' => !empty($instrucoes) ? mb_substr($instrucoes, 0, 180, 'UTF-8') : '',
                    'finish_reason' => $finishReason,
                    'word_count' => $wordCount,
                    'retry_used' => $retryUsed,
                    'retry_word_count' => $retryWordCount,
                    'retry_finish_reason' => $retryFinishReason,
                    'prompt_feedback' => $promptFeedback,
                ];
            }
            jsonResponse($payload);
        }

        $payload = ['success' => true, 'text' => $text];
        if ($debug) {
            $payload['debug'] = [
                'context' => $contexto,
                'model' => $model,
                'model_used' => $modelUsed,
                'fallback_used' => $fallbackModelUsed,
                'max_tokens' => $maxTokens,
                'instructions_key_used' => $instrucoesKey,
                'instructions_present' => !empty($instrucoes),
                'instructions_length' => strlen($instrucoes),
                'instructions_preview' => !empty($instrucoes) ? mb_substr($instrucoes, 0, 180, 'UTF-8') : '',
                'finish_reason' => $finishReason,
                'word_count' => $wordCount,
                'retry_used' => $retryUsed,
                'retry_word_count' => $retryWordCount,
                'retry_finish_reason' => $retryFinishReason,
            ];
        }
        jsonResponse($payload);
    } else {
        jsonResponse(['success' => false, 'message' => $response['error']]);
    }
}

/**
 * Gerar texto usando a instrução configurada especificamente para o agente de redes sociais
 * A instrução é lida de redes_sociais_config.dados_extras.social_agent_instruction para a rede (instagram/facebook)
 */
function gerarSocialAgent() {
    set_time_limit(180);

    $prompt = $_POST['prompt'] ?? '';
    $rede = strtolower(trim($_POST['rede'] ?? 'instagram'));

    if (empty($prompt)) {
        jsonResponse(['success' => false, 'message' => 'Prompt não fornecido']);
    }

    // Buscar instrução do agente na tabela redes_sociais_config (priorizar rede específica)
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT dados_extras FROM redes_sociais_config WHERE rede = ? LIMIT 1");
        $stmt->execute([$rede]);
        $cfg = $stmt->fetch();
    } catch (Exception $e) {
        $cfg = null;
    }

    $dadosExtras = [];
    if ($cfg && !empty($cfg['dados_extras'])) {
        $dadosExtras = json_decode($cfg['dados_extras'], true) ?: [];
    }

    // Instrução customizada para agente social
    $instrucoesAgente = $dadosExtras['social_agent_instruction'] ?? '';

    // Se não houver instrução em rede, tentar usar a instrução geral do sistema
    if (empty($instrucoesAgente)) {
        $instrucoesAgente = getConfig('ia_instrucoes_social') ?: '';
    }

    // Escolher modelo preferencial (se fornecido em dados_extras)
    $model = $dadosExtras['social_agent_model'] ?? getConfig('gemini_model') ?: 'gemini-2.0-flash';
    $apiKey = getConfig('gemini_api_key');

    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'message' => 'API Key do Gemini não configurada.']);
    }

    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";

    // Ajuste de tom por rede
    $tone = "Use tom leve e direto, com emojis e 2-3 hashtags relevantes.";
    if ($rede === 'facebook') {
        $tone = "Use tom mais informal e engajador, adaptado para Facebook.";
    }

    $debug = (($_POST['debug'] ?? '') === '1');

    $promptFinal = '';
    if (!empty($instrucoesAgente)) {
        $promptFinal .= $instrucoesAgente . "\n\n";
    }
    $promptFinal .= "Context: Rede={$rede}. {$tone}\n\n";
    $promptFinal .= "Objetivo: gere a legenda final pronta para publicar, com 2 ou 3 frases completas, emojis e 2-3 hashtags no final (sem explicar o processo). Seja direto, mencione o tema do prompt e use linguagem adaptada à rede.\n\n";
    $promptFinal .= $prompt;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $promptFinal]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 512,
            'temperature' => 0.85
        ]
    ];

    session_write_close();

    $extractCandidate = function($resp) {
        $candidate = $resp['data']['candidates'][0] ?? null;
        $text = '';
        $finishReason = '';
        if ($candidate && isset($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) $text .= $part['text'];
            }
            $finishReason = $candidate['finishReason'] ?? '';
        }
        return ['text' => trim($text), 'finishReason' => $finishReason, 'raw' => $candidate];
    };

    $sanitizeText = function($raw) {
        if ($raw === '') return '';
        $lines = preg_split('/\R/u', $raw);
        $clean = [];
        foreach ($lines as $line) {
            $l = trim($line);
            if ($l === '' || strpos($l, '```') === 0) continue;
            if ($l === '---') continue;
            $l = preg_replace('/^#{1,6}\s*/', '', $l);
            $l = preg_replace('/^(?:-|\*|•)\s+/', '', $l);
            $l = preg_replace('/^\d+\.\s+/', '', $l);
            $clean[] = $l;
        }
        return trim(implode(' ', $clean));
    };

    $response = callGeminiAPI($url, $data);
    if ($response['success']) {
        $candidateData = $extractCandidate($response);
        $text = $sanitizeText($candidateData['text']);
        $finishReason = $candidateData['finishReason'];

        $needsRetry = false;
        $minChars = 140;
        if ($text === '' || mb_strlen($text, 'UTF-8') < $minChars) {
            $needsRetry = true;
        }
        if (preg_match('/^\s*(Certo|A[ií] sim|Ok|Entendido|Aqui est[aá])\b/i', $text)) {
            $needsRetry = true;
        }
        if ($finishReason && $finishReason !== 'STOP') {
            $needsRetry = true;
        }
        if (!preg_match('/[.!?…]$/u', $text)) {
            $needsRetry = true;
        }

        if ($needsRetry) {
            error_log("Gemini social agent: initial output too short or malformed (len=" . mb_strlen($text, 'UTF-8') . ") finishReason={$finishReason}");

            $retryPrompt = $promptFinal . "\n\nINSTRUCAO FINAL: responda APENAS com a legenda pronta para publicar, em texto corrido, 2 ou 3 frases completas, emojis e 2-3 hashtags ao final. NAO use titulos, listas, markdown, separadores ou introducoes. NAO comece com 'Certo', 'Entendido', 'Aqui esta' ou frases meta. Termine com pontuacao. Escreva no minimo {$minChars} caracteres.";
            $data['contents'][0]['parts'][0]['text'] = $retryPrompt;
            $data['generationConfig']['temperature'] = 0.6;
            $data['generationConfig']['maxOutputTokens'] = 512;
            $retryResponse = callGeminiAPI($url, $data);
            if ($retryResponse['success']) {
                $retryCandidateData = $extractCandidate($retryResponse);
                if (trim($retryCandidateData['text']) !== '') {
                    $text = $sanitizeText($retryCandidateData['text']);
                    $finishReason = $retryCandidateData['finishReason'] ?: $finishReason;
                }
            }
        }

        if (mb_strlen($text, 'UTF-8') < $minChars) {
            error_log("Gemini social agent: second/initial retry still short (len=" . mb_strlen($text, 'UTF-8') . ") trying final prompt");

            $finalPrompt = $promptFinal . "\n\nINSTRUCAO FINAL: gere SOMENTE a legenda final pronta, sem introducao, com 2 ou 3 frases completas, emojis e 2-3 hashtags ao final. Mantenha pelo menos {$minChars} caracteres. Nao use markdown ou separadores.";
            $data['contents'][0]['parts'][0]['text'] = $finalPrompt;
            $data['generationConfig']['temperature'] = 0.5;
            $data['generationConfig']['maxOutputTokens'] = 512;
            $finalResponse = callGeminiAPI($url, $data);
            if ($finalResponse['success']) {
                $finalCandidateData = $extractCandidate($finalResponse);
                if (trim($finalCandidateData['text']) !== '') {
                    $text = $sanitizeText($finalCandidateData['text']);
                    $finishReason = $finalCandidateData['finishReason'] ?: $finishReason;
                }
            }
        }

        // If still short, try a stable fallback model (e.g., gemini-2.0-flash) once
        if (mb_strlen($text, 'UTF-8') < $minChars && strpos($model, '2.5') !== false) {
            error_log("Gemini social agent: attempting fallback to gemini-2.0-flash because output too short (len=" . mb_strlen($text, 'UTF-8') . ")");
            $fallbackModel = 'gemini-2.0-flash';
            $oldModel = $model;
            $model = $fallbackModel;
            $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
            $data['contents'][0]['parts'][0]['text'] = $finalPrompt;
            $fallbackResponse = callGeminiAPI($url, $data);
            if ($fallbackResponse['success']) {
                $fbCandidate = $extractCandidate($fallbackResponse);
                if (trim($fbCandidate['text']) !== '') {
                    $text = $sanitizeText($fbCandidate['text']);
                    $finishReason = $fbCandidate['finishReason'] ?: $finishReason;
                }
            }
            $debug ? error_log("Fallback tried: {$oldModel} -> {$model}; new_len=" . mb_strlen($text, 'UTF-8')) : null;
        }

        if (trim($text) === '') {
            jsonResponse(['success' => false, 'message' => 'A IA não retornou texto.']);
        }

        $payload = ['success' => true, 'text' => trim($text), 'model' => $model];
        if ($debug) {
            $payload['debug'] = [
                'initial_candidate' => $candidateData['raw'] ?? null,
                'finishReason' => $finishReason,
                'length_chars' => mb_strlen($text, 'UTF-8'),
                'prompt_preview' => mb_substr($promptFinal, 0, 800, 'UTF-8'),
                'used_model' => $model,
                'api_response' => isset($response['data']) ? array_slice($response['data'], 0, 3) : null
            ];
        }

        jsonResponse($payload);
    } else {
        jsonResponse(['success' => false, 'message' => $response['error']]);
    }
}

/**
 * Gerar imagens em múltiplos formatos (1:1 e 9:16)
 * Gera UMA imagem base e cria versões recortadas para cada formato
 */
function gerarImagensMultiplosFormatos() {
    set_time_limit(240);
    $prompt = $_POST['prompt'] ?? '';
    
    if (empty($prompt)) {
        jsonResponse(['success' => false, 'message' => 'Prompt não fornecido']);
    }
    
    $imageProvider = getLlmProviderByType('image');
    $apiKey = getConfig('gemini_api_key');
    if ($imageProvider === 'gemini' && empty($apiKey)) {
        jsonResponse(['success' => false, 'message' => 'API Key não configurada']);
    }
    
    $resultado = [
        'success' => false,
        'imagem_1x1' => null,
        'imagem_1x1_url' => null,
        'imagem_9x16' => null,
        'imagem_9x16_url' => null
    ];
    
    $erros = [];
    $imagemBase = null;
    
    // 1. GERAR UMA ÚNICA IMAGEM BASE
    if ($imageProvider !== 'gemini') {
        $modeloImagem = getLlmModelByType('image', $imageProvider);
        $result = gerarImagemComProvider($imageProvider, $prompt, $modeloImagem);
        if ($result && $result['success']) {
            $imagemBase = UPLOAD_DIR . $result['filename'];
        } else if ($result && isset($result['error'])) {
            $erros[] = strtoupper($imageProvider) . ': ' . $result['error'];
        }
    } else {
        $modeloImagem = getConfig('gemini_image_model');

        if ($modeloImagem && strpos($modeloImagem, 'imagen') !== false) {
            $result = gerarImagemComImagen($prompt, $apiKey, $modeloImagem);
            if ($result && $result['success']) {
                $imagemBase = UPLOAD_DIR . $result['filename'];
            } else if ($result && isset($result['error'])) {
                $erros[] = "Imagen: " . $result['error'];
            }
        }

        if (!$imagemBase || !file_exists($imagemBase)) {
            $result = gerarImagemComGeminiFlash($prompt, $apiKey);
            if ($result && $result['success']) {
                $imagemBase = UPLOAD_DIR . $result['filename'];
            } else if ($result && isset($result['error'])) {
                $erros[] = "Gemini: " . $result['error'];
            }
        }
    }
    
    // Se não conseguiu gerar imagem base
    if (!$imagemBase || !file_exists($imagemBase)) {
        $mensagemErro = 'Não foi possível gerar a imagem.';
        if (!empty($erros)) {
            $mensagemErro .= "\n\nDetalhes:\n- " . implode("\n- ", array_unique($erros));
        }
        $mensagemErro .= "\n\nSugestão: Faça upload manual das imagens.";
        jsonResponse(['success' => false, 'message' => $mensagemErro]);
    }
    
    // 2. CRIAR VERSÕES DA MESMA IMAGEM
    $baseId = uniqid();
    
    // Verificar se GD está disponível para recorte
    $gdDisponivel = extension_loaded('gd');
    
    if ($gdDisponivel) {
        // Criar versão 1:1 (quadrada - recorte central) - salvar como JPG otimizado
        $arquivo1x1 = "ai_1x1_{$baseId}.jpg";
        if (criarVersaoRecortada($imagemBase, UPLOAD_DIR . $arquivo1x1, 1, 1)) {
            // Otimizar imagem 1:1
            require_once __DIR__ . '/image_optimizer.php';
            $resultadoOtimizacao = otimizarImagemParaRedesSociais(UPLOAD_DIR . $arquivo1x1, UPLOAD_DIR . $arquivo1x1, 1200, 1200, 500, 85);
            if ($resultadoOtimizacao['success']) {
                error_log("Imagem 1:1 otimizada: {$resultadoOtimizacao['sizeKB']}KB");
            }

            $resultado['imagem_1x1'] = $arquivo1x1;
            $resultado['imagem_1x1_url'] = UPLOAD_URL . $arquivo1x1;
        }

        // Criar versão 9:16 (vertical - recorte central) - salvar como JPG otimizado
        $arquivo9x16 = "ai_9x16_{$baseId}.jpg";
        if (criarVersaoRecortada($imagemBase, UPLOAD_DIR . $arquivo9x16, 9, 16)) {
            // Otimizar imagem 9:16
            require_once __DIR__ . '/image_optimizer.php';
            $resultadoOtimizacao = otimizarImagemParaRedesSociais(UPLOAD_DIR . $arquivo9x16, UPLOAD_DIR . $arquivo9x16, 1080, 1920, 500, 85);
            if ($resultadoOtimizacao['success']) {
                error_log("Imagem 9:16 otimizada: {$resultadoOtimizacao['sizeKB']}KB");
            }

            $resultado['imagem_9x16'] = $arquivo9x16;
            $resultado['imagem_9x16_url'] = UPLOAD_URL . $arquivo9x16;
        }

        // Remover imagem base temporária
        if (file_exists($imagemBase)) {
            unlink($imagemBase);
        }
    } else {
        // GD não disponível - usar mesma imagem para ambos os formatos
        $nomeBase = basename($imagemBase);
        $arquivo1x1 = "ai_1x1_{$baseId}.jpg";
        $arquivo9x16 = "ai_9x16_{$baseId}.jpg";

        // Copiar imagem para os dois formatos
        copy($imagemBase, UPLOAD_DIR . $arquivo1x1);
        copy($imagemBase, UPLOAD_DIR . $arquivo9x16);

        // Otimizar ambas as imagens
        require_once __DIR__ . '/image_optimizer.php';
        otimizarImagemParaRedesSociais(UPLOAD_DIR . $arquivo1x1, UPLOAD_DIR . $arquivo1x1, 1200, 1200, 500, 85);
        otimizarImagemParaRedesSociais(UPLOAD_DIR . $arquivo9x16, UPLOAD_DIR . $arquivo9x16, 1080, 1920, 500, 85);

        $resultado['imagem_1x1'] = $arquivo1x1;
        $resultado['imagem_1x1_url'] = UPLOAD_URL . $arquivo1x1;
        $resultado['imagem_9x16'] = $arquivo9x16;
        $resultado['imagem_9x16_url'] = UPLOAD_URL . $arquivo9x16;
        $resultado['note'] = 'Extensão GD não disponível. Mesma imagem sem recorte.';

        // Remover imagem base temporária
        if (file_exists($imagemBase)) {
            unlink($imagemBase);
        }
    }
    
    // Verificar resultados
    if ($resultado['imagem_1x1'] || $resultado['imagem_9x16']) {
        $resultado['success'] = true;
        $resultado['note'] = 'Mesma imagem base em formatos diferentes';
        jsonResponse($resultado);
    }
    
    jsonResponse([
        'success' => false,
        'message' => 'Erro ao processar formatos da imagem'
    ]);
}

/**
 * Criar versão recortada de uma imagem com aspect ratio específico
 */
function criarVersaoRecortada($origem, $destino, $ratioW, $ratioH) {
    // Obter informações da imagem
    $info = getimagesize($origem);
    if (!$info) return false;
    
    $larguraOriginal = $info[0];
    $alturaOriginal = $info[1];
    $tipo = $info[2];
    
    // Carregar imagem baseado no tipo
    switch ($tipo) {
        case IMAGETYPE_PNG:
            $img = imagecreatefrompng($origem);
            break;
        case IMAGETYPE_JPEG:
            $img = imagecreatefromjpeg($origem);
            break;
        case IMAGETYPE_GIF:
            $img = imagecreatefromgif($origem);
            break;
        case IMAGETYPE_WEBP:
            $img = imagecreatefromwebp($origem);
            break;
        default:
            return false;
    }
    
    if (!$img) return false;
    
    // Calcular dimensões do recorte
    $ratioDesejado = $ratioW / $ratioH;
    $ratioAtual = $larguraOriginal / $alturaOriginal;
    
    if ($ratioAtual > $ratioDesejado) {
        // Imagem mais larga - recortar laterais
        $novaAltura = $alturaOriginal;
        $novaLargura = (int)($alturaOriginal * $ratioDesejado);
        $x = (int)(($larguraOriginal - $novaLargura) / 2);
        $y = 0;
    } else {
        // Imagem mais alta - recortar topo/baixo
        $novaLargura = $larguraOriginal;
        $novaAltura = (int)($larguraOriginal / $ratioDesejado);
        $x = 0;
        $y = (int)(($alturaOriginal - $novaAltura) / 2);
    }
    
    // Criar imagem recortada
    $imgRecortada = imagecreatetruecolor($novaLargura, $novaAltura);

    // Preencher com branco (para JPEGs sem transparência)
    $branco = imagecolorallocate($imgRecortada, 255, 255, 255);
    imagefill($imgRecortada, 0, 0, $branco);

    // Fazer o recorte
    imagecopy($imgRecortada, $img, 0, 0, $x, $y, $novaLargura, $novaAltura);

    // Salvar como JPEG (melhor compressão)
    $resultado = imagejpeg($imgRecortada, $destino, 90);
    
    // Limpar memória
    imagedestroy($img);
    imagedestroy($imgRecortada);
    
    return $resultado;
}

/**
 * Gerar imagem com Imagen especificando aspect ratio
 */
function gerarImagemComImagenFormato($prompt, $apiKey, $modelo, $aspectRatio) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:predict?key={$apiKey}";
    
    $data = [
        'instances' => [
            ['prompt' => $prompt]
        ],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => $aspectRatio
        ]
    ];
    
    $response = callGeminiAPI($url, $data, 300);
    
    if ($response['success'] && isset($response['data']['predictions'][0]['bytesBase64Encoded'])) {
        $imageBase64 = $response['data']['predictions'][0]['bytesBase64Encoded'];
        
        $formatoSuffix = str_replace(':', 'x', $aspectRatio);
        $filename = 'ai_' . $formatoSuffix . '_' . uniqid() . '.png';
        $filepath = UPLOAD_DIR . $filename;
        
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        file_put_contents($filepath, base64_decode($imageBase64));
        
        return [
            'success' => true,
            'filename' => $filename,
            'url' => UPLOAD_URL . $filename,
            'model' => $modelo,
            'aspectRatio' => $aspectRatio
        ];
    }
    
    $erro = '';
    if (!$response['success']) {
        $erro = $response['error'] ?? 'Erro desconhecido';
    } elseif (!isset($response['data']['predictions'])) {
        $erro = 'Resposta sem predictions';
    }
    
    return ['success' => false, 'error' => $erro];
}

/**
 * Gerar imagem com Gemini/Imagen
 */
function gerarImagem() {
    $prompt = $_POST['prompt'] ?? '';
    
    if (empty($prompt)) {
        jsonResponse(['success' => false, 'message' => 'Prompt não fornecido']);
    }
    
    $provider = getLlmProviderByType('image');

    if ($provider !== 'gemini') {
        $model = getLlmModelByType('image', $provider);
        $result = gerarImagemComProvider($provider, $prompt, $model);
        if ($result && $result['success']) {
            $result = otimizarImagemResultado($result);
            jsonResponse($result);
        }
        jsonResponse([
            'success' => false,
            'message' => $result['error'] ?? ('Falha ao gerar imagem com ' . strtoupper($provider))
        ]);
    }

    $apiKey = getConfig('gemini_api_key');
    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'message' => 'API Key não configurada']);
    }
    
    $erros = [];
    
    // Método 1: Tentar com modelo de imagem configurado
    $modeloImagem = getLlmModelByType('image', 'gemini');
    if ($modeloImagem) {
        $isImagen = (strpos($modeloImagem, 'imagen') !== false);
        $isGeminiImage = (strpos($modeloImagem, 'image') !== false || strpos($modeloImagem, 'banana') !== false);

        if ($isImagen) {
            $result = gerarImagemComImagen($prompt, $apiKey, $modeloImagem);
        } elseif ($isGeminiImage) {
            // Alguns modelos Gemini (como gemini-2.0-flash-exp ou nano-banana) geram imagens via generateContent
            $result = gerarImagemComGeminiFlash($prompt, $apiKey, $modeloImagem);
        } else {
            // Fallback genérico se não soubermos o tipo
            $result = gerarImagemComImagen($prompt, $apiKey, $modeloImagem);
        }

        if ($result && $result['success']) {
            $result = otimizarImagemResultado($result);
            jsonResponse($result);
        }
        if ($result && isset($result['error'])) {
            $erros[] = "Modelo configurado ({$modeloImagem}): " . $result['error'];
        }
    }

    // Método 2: Forçar fallback para Gemini 2.0 Flash ou similar se o configurado falhou
    $result = gerarImagemComGeminiFlash($prompt, $apiKey);
    if ($result && $result['success']) {
        $result = otimizarImagemResultado($result);
        jsonResponse($result);
    }
    if ($result && isset($result['error'])) {
        $erros[] = $result['error'];
    }

    // Método 3: Tentar Imagen 3 padrão como última tentativa
    if ($modeloImagem !== 'imagen-3.0-generate-002') {
        $result = gerarImagemComImagen($prompt, $apiKey, 'imagen-3.0-generate-002');
        if ($result && $result['success']) {
            $result = otimizarImagemResultado($result);
            jsonResponse($result);
        }
        if ($result && isset($result['error'])) {
            $erros[] = "Imagen 3.0 (fallback): " . $result['error'];
        }
    }
    
    // Nenhum método funcionou
    $mensagemErro = 'Não foi possível gerar a imagem.';
    if (!empty($erros)) {
        $mensagemErro .= "\n\nDetalhes:\n- " . implode("\n- ", array_unique($erros));
    }
    $mensagemErro .= "\n\nSugestão: Faça upload manual de uma imagem.";
    
    jsonResponse([
        'success' => false, 
        'message' => $mensagemErro
    ]);
}

/**
 * Lista modelos disponíveis (v1 + v1beta) para a API key
 */
function listAvailableModelsForKey($apiKey) {
    $models = [];
    $endpoints = [
        "https://generativelanguage.googleapis.com/v1/models?key=" . urlencode($apiKey),
        "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey)
    ];

    foreach ($endpoints as $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$resp || $code !== 200) continue;
        $data = json_decode($resp, true);
        if (empty($data['models'])) continue;
        foreach ($data['models'] as $m) {
            $id = str_replace('models/', '', $m['name'] ?? '');
            if (!isset($models[$id])) $models[$id] = $m;
        }
    }

    return $models; // assoc por id
}

/**
 * Gerar imagem usando Gemini 2.0 Flash (experimental - gratuito)
 * Agora escolhe dinamicamente modelos com capacidade de imagem a partir da lista disponível
 */
function gerarImagemComGeminiFlash($prompt, $apiKey, $modeloEspecifico = null) {
    // Obter lista de modelos disponíveis e priorizar candidatos para geração de imagens
    $models = listAvailableModelsForKey($apiKey);

    $candidatos = [];
    
    // Se um modelo específico foi solicitado, ele é o primeiro candidato
    if ($modeloEspecifico) {
        $candidatos[] = $modeloEspecifico;
    }

    foreach ($models as $id => $m) {
        if ($id === $modeloEspecifico) continue; // já adicionado
        $disp = strtolower($m['displayName'] ?? '');
        $lower = strtolower($id);
        $methods = $m['supportedGenerationMethods'] ?? [];

        // Preferir modelos que declaram suporte a generateContent
        if (!in_array('generateContent', $methods)) continue;

        // Detectar modelos de imagem (imagen, imagegeneration, image, experimental image generation)
        if (strpos($lower, 'imagen') !== false || strpos($lower, 'image') !== false || strpos($disp, 'image') !== false) {
            $candidatos[] = $id;
        }
    }

    // Se não houver candidatos detectados, usar fallback experimental conhecido
    if (empty($candidatos)) {
        $candidatos = [
            'gemini-2.0-flash-exp-image-generation',
            'gemini-2.0-flash-exp',
            'gemini-exp-1206'
        ];
    }

    // Remover duplicados e ordenar por custo/compatibilidade esperada
    $candidatos = array_values(array_unique($candidatos));

    $scoreModelo = function(string $id): int {
        $v = strtolower($id);
        $score = 50;
        if (strpos($v, 'flash-exp-image-generation') !== false) $score = 1;
        else if (strpos($v, 'flash-image') !== false) $score = 3;
        else if (strpos($v, 'flash') !== false) $score = 7;
        else if (strpos($v, 'imagen') !== false) $score = 15;
        else if (strpos($v, 'pro') !== false) $score = 90;
        return $score;
    };

    // Mantem o modelo explicitamente selecionado como primeira tentativa.
    if (!empty($modeloEspecifico)) {
        $rest = array_values(array_filter($candidatos, fn($m) => $m !== $modeloEspecifico));
        usort($rest, function($a, $b) use ($scoreModelo) {
            $sa = $scoreModelo($a);
            $sb = $scoreModelo($b);
            if ($sa === $sb) return strcmp($a, $b);
            return $sa <=> $sb;
        });
        $candidatos = array_merge([$modeloEspecifico], $rest);
    } else {
        usort($candidatos, function($a, $b) use ($scoreModelo) {
            $sa = $scoreModelo($a);
            $sb = $scoreModelo($b);
            if ($sa === $sb) return strcmp($a, $b);
            return $sa <=> $sb;
        });
    }

    $ultimoErro = '';
    $errosDetalhados = [];

    foreach ($candidatos as $modelo) {
        $url = "https://generativelanguage.googleapis.com/v1/models/{$modelo}:generateContent?key={$apiKey}";

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => "Generate a professional image: " . $prompt]
                    ]
                ]
            ],
            // Tentativa inicial com responseModalities (alguns modelos experimentais aceitam)
            'generationConfig' => [
                'responseModalities' => ['IMAGE', 'TEXT']
            ]
        ];

        $response = callGeminiAPI($url, $data, 300);

        // Se o modelo rejeitou 'responseModalities' ou retornou payload inválido, tentar novamente sem generationConfig
        if (!$response['success']) {
            $err = $response['error'] ?? '';
            if (stripos($err, 'responseModalities') !== false || stripos($err, 'Unknown name "responseModalities"') !== false || stripos($err, 'Invalid JSON payload') !== false) {
                unset($data['generationConfig']);
                $retry = callGeminiAPI($url, $data, 300);
                if ($retry['success']) {
                    $response = $retry;
                } else {
                    $response = $retry;
                }
            }
        }

        if ($response['success']) {
            $parts = $response['data']['candidates'][0]['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['inlineData'])) {
                    $resultado = salvarImagemGerada($part['inlineData']);
                    $resultado['model'] = $modelo;
                    return $resultado;
                }
            }
            $ultimoErro = "Modelo {$modelo}: resposta sem imagem";
        } else {
            $ultimoErro = "Modelo {$modelo}: " . ($response['error'] ?? 'erro desconhecido');
            $errosDetalhados[] = $ultimoErro;
        }
    }

    if (!empty($errosDetalhados)) {
        $preview = implode(' | ', array_slice($errosDetalhados, 0, 3));
        return ['success' => false, 'error' => $preview, 'attempted_models' => $candidatos];
    }

    return ['success' => false, 'error' => $ultimoErro ?: 'Nenhum modelo compatível para geração de imagem encontrado', 'attempted_models' => $candidatos];
}

/**
 * Tentar gerar com Imagen (requer acesso especial)
 */
function gerarImagemComImagen($prompt, $apiKey, $modelo = null) {
    $modelo = $modelo ?: getConfig('gemini_image_model') ?: 'imagen-3.0-generate-002';
    
    // Imagen usa endpoint :predict
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:predict?key={$apiKey}";
    
    $data = [
        'instances' => [
            ['prompt' => $prompt]
        ],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => '16:9'  // Formato widescreen para posts
        ]
    ];
    
    $response = callGeminiAPI($url, $data, 300);

    if ($response['success'] && isset($response['data']['predictions'][0]['bytesBase64Encoded'])) {
        $imageBase64 = $response['data']['predictions'][0]['bytesBase64Encoded'];

        $filename = 'ai_' . uniqid() . '.png';
        $filepath = UPLOAD_DIR . $filename;

        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        file_put_contents($filepath, base64_decode($imageBase64));

        return [
            'success' => true,
            'filename' => $filename,
            'url' => UPLOAD_URL . $filename,
            'image_url' => UPLOAD_URL . $filename,
            'model' => $modelo
        ];
    }

    // Verificar se há erro específico
    $erro = '';
    if (!$response['success']) {
        $erro = $response['error'] ?? 'Erro desconhecido';
        // Mensagem mais explícita se o modelo não existir ou não suportar predict
        if (stripos($erro, 'not found') !== false || stripos($erro, 'not supported for predict') !== false || stripos($erro, 'API version') !== false) {
            $erro = "Modelo {$modelo} não disponível para esta API key ou não suporta o método predict. Verifique sua API key com ListModels ou escolha outro modelo. Detalhe: {$erro}";
        }
    } elseif (!isset($response['data']['predictions'])) {
        $erro = 'Resposta sem predictions - modelo pode não suportar geração de imagem';
    }

    return ['success' => false, 'error' => $erro];
}

/**
 * Salvar imagem gerada e retornar resposta
 */
function salvarImagemGerada($inlineData) {
    $imageBase64 = $inlineData['data'];
    $mimeType = $inlineData['mimeType'] ?? 'image/png';

    $ext = strpos($mimeType, 'png') !== false ? 'png' : 'jpg';
    $filename = 'ai_' . uniqid() . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;

    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    file_put_contents($filepath, base64_decode($imageBase64));

    return [
        'success' => true,
        'filename' => $filename,
        'url' => UPLOAD_URL . $filename,
        'image' => $imageBase64
    ];
}

/**
 * Otimizar imagem gerada pela IA para redes sociais
 * Redimensiona para 1200x630 (LinkedIn/OpenGraph) e comprime para máx 500KB
 */
function otimizarImagemResultado($result) {
    if (!$result['success'] || empty($result['filename'])) {
        return $result;
    }

    $filepath = UPLOAD_DIR . $result['filename'];

    if (!file_exists($filepath)) {
        return $result;
    }

    // Incluir otimizador
    require_once __DIR__ . '/image_optimizer.php';

    // Novo nome de arquivo (sempre JPG)
    $novoFilename = pathinfo($result['filename'], PATHINFO_FILENAME) . '.jpg';
    $novoFilepath = UPLOAD_DIR . $novoFilename;

    // Otimizar para LinkedIn/OpenGraph (1200x630)
    $otimizado = otimizarImagemParaRedesSociais($filepath, $novoFilepath, 1200, 630, 500, 85);

    if ($otimizado['success']) {
        // Remover arquivo original se diferente
        if ($filepath !== $novoFilepath && file_exists($filepath)) {
            unlink($filepath);
        }

        error_log("Imagem IA otimizada: {$otimizado['sizeKB']}KB, {$otimizado['dimensions']['width']}x{$otimizado['dimensions']['height']}");

        // Atualizar resultado
        $result['filename'] = $novoFilename;
        $result['url'] = UPLOAD_URL . $novoFilename;
        $result['otimizado'] = true;
        $result['tamanhoKB'] = $otimizado['sizeKB'];
        $result['dimensoes'] = $otimizado['dimensions'];
    } else {
        error_log("Aviso: Não foi possível otimizar imagem: {$otimizado['message']}");
    }

    return $result;
}

/**
 * Gerar artigo completo com IA
 * Usa as instruções configuradas pelo usuário + formato de saída estruturado
 */
function gerarArtigo() {
    $tema = $_POST['tema'] ?? '';
    
    if (empty($tema)) {
        jsonResponse(['success' => false, 'message' => 'Prompt não fornecido']);
    }
    
    $provider = getLlmProviderByType('text');
    $model = getLlmModelByType('text', $provider);
    
    // FORÇAR mínimo de 8192 tokens para conteúdos longos
    $maxTokens = 8192;
    
    // Buscar instruções personalizadas do agente de IA
    $instrucoes = getConfig('ia_instrucoes') ?? '';
    
    // Montar prompt
    $prompt = "";
    
    if (!empty($instrucoes)) {
        $prompt .= $instrucoes . "\n\n";
    }
    
    // Solicitação do usuário
    $prompt .= "SOLICITAÇÃO: " . $tema . "\n\n";
    
    // Formato de saída (necessário para o sistema extrair os campos)
    $prompt .= "FORMATO DE SAÍDA OBRIGATÓRIO:
- Comece DIRETAMENTE com os campos, sem introdução ou comentários
- Use EXATAMENTE estas labels no início de cada linha
- O CONTEÚDO deve ser EXTENSO e COMPLETO conforme solicitado nas instruções
- O CONTEÚDO deve estar em formato HTML com tags apropriadas

Título: [título aqui]
Slug: [slug-aqui-em-minusculas-sem-acentos]
Categoria: [categoria]
Resumo: [resumo até 320 caracteres, texto puro sem HTML]
Conteúdo: [ARTIGO COMPLETO EM HTML usando:
  - <p> para parágrafos
  - <h2> e <h3> para subtítulos
  - <strong> para negrito
  - <em> para itálico
  - <ul><li> para listas
  - <blockquote> para citações
  - Cada parágrafo deve ter pelo menos 3-4 frases
  - Use subtítulos (h2) para dividir seções
  - NÃO use markdown (**, ##, etc), APENAS HTML
]";

    // Fluxo multi-provedor para artigos: respeita llm_text_provider + llm_text_model.
    if ($provider !== 'gemini') {
        $providerResult = gerarTextoComProvider($provider, $model, $prompt, $maxTokens);
        if (!$providerResult['success']) {
            jsonResponse(['success' => false, 'message' => $providerResult['error'] ?? 'Erro ao gerar conteúdo']);
        }

        $text = (string)($providerResult['text'] ?? '');
        jsonResponse([
            'success' => true,
            'texto' => $text,
            'fonte_ia' => $provider . ':' . $model,
            'chars_gerados' => strlen($text),
            'max_tokens_usado' => $maxTokens
        ]);
    }

    $apiKey = getLlmApiKey('gemini');
    if (empty($apiKey)) {
        jsonResponse(['success' => false, 'message' => 'API Key não configurada para GEMINI']);
    }

    $buildGeminiPayload = function(string $fullPrompt, int $tokens): array {
        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $tokens,
                'temperature' => 0.7
            ]
        ];
    };

    $extractGeminiText = function(array $response): string {
        $candidate = $response['data']['candidates'][0] ?? null;
        $text = '';
        if ($candidate && isset($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }
        return $text;
    };

    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key={$apiKey}";
    $data = $buildGeminiPayload($prompt, $maxTokens);

    // Liberar trava de sessão para não congelar o navegador do usuário
    session_write_close();

    $response = callGeminiAPI($url, $data);
    if (!$response['success']) {
        $err = (string)($response['error'] ?? 'Erro desconhecido');
        $isQuotaError = (stripos($err, '429') !== false || stripos($err, 'quota') !== false);

        // Em chaves com free tier, modelos Pro podem falhar por cota; tenta fallback para Flash.
        if ($isQuotaError && $model !== 'gemini-2.0-flash') {
            $fallbackModel = 'gemini-2.0-flash';
            $fallbackUrl = "https://generativelanguage.googleapis.com/v1/models/{$fallbackModel}:generateContent?key={$apiKey}";
            $fallbackResponse = callGeminiAPI($fallbackUrl, $data);
            if ($fallbackResponse['success']) {
                $text = $extractGeminiText($fallbackResponse);
                error_log("Gemini article fallback used due to quota: {$model} -> {$fallbackModel}");
                jsonResponse([
                    'success' => true,
                    'texto' => $text,
                    'fonte_ia' => $fallbackModel,
                    'chars_gerados' => strlen($text),
                    'max_tokens_usado' => $maxTokens,
                    'fallback_quota' => true,
                    'fallback_from' => $model
                ]);
            }
        }

        jsonResponse(['success' => false, 'message' => $err]);
    }

    $text = $extractGeminiText($response);
    $modelUsed = (string)($response['meta']['model_used'] ?? $model);

    // Log para debug
    error_log("Gemini Response length: " . strlen($text) . " chars");

    jsonResponse([
        'success' => true,
        'texto' => $text,
        'fonte_ia' => $modelUsed,
        'chars_gerados' => strlen($text),
        'max_tokens_usado' => $maxTokens
    ]);
}

/**
 * Extrair campo de texto não-JSON
 */
function extrairCampo($texto, $campo) {
    // Tentar encontrar padrões como "titulo: ..." ou "**Título:**"
    $patterns = [
        "/{$campo}[\"']?\s*:\s*[\"']([^\"']+)[\"']/i",
        "/{$campo}:\s*(.+?)(?:\n|$)/i",
        "/\*\*{$campo}\*\*:\s*(.+?)(?:\n|$)/i"
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $texto, $matches)) {
            return trim($matches[1]);
        }
    }
    
    return null;
}

/**
 * Limpar e formatar texto gerado pela IA
 */
function limparTextoIA($texto) {
    if (empty($texto)) return '';
    
    // Se for array ou objeto, converter para string
    if (is_array($texto) || is_object($texto)) {
        $texto = json_encode($texto, JSON_UNESCAPED_UNICODE);
    }
    
    // Decodificar se estiver JSON encoded
    $decoded = json_decode($texto, true);
    if (is_string($decoded)) {
        $texto = $decoded;
    }
    
    // Converter \n literais (escapados) para quebras de linha reais
    // Padrão 1: \\n (double escaped)
    $texto = str_replace('\\\\n', "\n", $texto);
    // Padrão 2: \n (single escaped em string)
    $texto = str_replace('\\n', "\n", $texto);
    // Padrão 3: literal backslash-n
    $texto = preg_replace('/(?<!\\\\)\\\\n/', "\n", $texto);
    
    // Converter \r\n para \n
    $texto = str_replace("\r\n", "\n", $texto);
    $texto = str_replace("\r", "\n", $texto);
    
    // Remover aspas extras no início/fim
    $texto = preg_replace('/^["\']|["\']$/', '', $texto);
    
    // Remover emojis
    $texto = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $texto);
    $texto = preg_replace('/[\x{2600}-\x{27BF}]/u', '', $texto);
    $texto = preg_replace('/[\x{FE00}-\x{FE0F}]/u', '', $texto);
    
    // Remover caracteres de controle (exceto newline e tab)
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);
    
    // Normalizar múltiplas quebras de linha (máximo 2)
    $texto = preg_replace('/\n{3,}/', "\n\n", $texto);
    
    // Limpar espaços em branco excessivos
    $texto = preg_replace('/[ \t]+/', ' ', $texto);
    
    // Remover espaços no início/fim de cada linha
    $linhas = explode("\n", $texto);
    $linhas = array_map('trim', $linhas);
    $texto = implode("\n", $linhas);
    
    // Trim final
    $texto = trim($texto);
    
    return $texto;
}

/**
 * Chamar API do Gemini
 */
function callGeminiAPI($url, $data, $timeout = 120) {
    $doRequest = function($requestUrl) use ($data, $timeout) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $requestUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => (int)$timeout, // timeout configurável em segundos
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'response' => $response,
            'httpCode' => $httpCode,
            'error' => $error
        ];
    };
    $extractApiKey = function($requestUrl) {
        $parts = parse_url($requestUrl);
        $query = $parts['query'] ?? '';
        parse_str($query, $params);
        return $params['key'] ?? '';
    };
    $extractModel = function($requestUrl) {
        if (preg_match('/\\/models\\/([^:]+):/i', $requestUrl, $matches)) {
            return $matches[1];
        }
        return '';
    };
    $listTextModels = function($apiKey) {
        if (!$apiKey) return [];
        $modelsUrl = "https://generativelanguage.googleapis.com/v1/models?key=" . urlencode($apiKey);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $modelsUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            return [];
        }
        $data = json_decode($response, true);
        $models = $data['models'] ?? [];
        $result = [];
        foreach ($models as $model) {
            $name = $model['name'] ?? '';
            $modelId = str_replace('models/', '', $name);
            $supported = $model['supportedGenerationMethods'] ?? [];
            if (strpos($modelId, 'gemini') !== false && in_array('generateContent', $supported)) {
                $result[] = $modelId;
            }
        }
        if (!empty($result)) {
            return $result;
        }
        return [
            'gemini-2.5-pro',
            'gemini-2.0-flash',
            'gemini-1.5-pro',
            'gemini-1.5-flash'
        ];
    };
    $pickPreferredModel = function($models) {
        $preferred = [
            'gemini-2.5-pro',
            'gemini-2.0-flash',
            'gemini-1.5-pro',
            'gemini-1.5-flash'
        ];
        foreach ($preferred as $pref) {
            if (in_array($pref, $models, true)) {
                return $pref;
            }
        }
        return $models[0] ?? '';
    };
    $meta = [];

    $result = $doRequest($url);

    if ($result['error']) {
        return ['success' => false, 'error' => 'Erro de conexão: ' . $result['error']];
    }

    $decoded = json_decode($result['response'], true);
    $errorMessage = $decoded['error']['message'] ?? 'Erro desconhecido';

    $isGenerateContent = strpos($url, ':generateContent') !== false;
    $hasVersionMismatch = $result['httpCode'] === 404
        && (stripos($errorMessage, 'API version v1beta') !== false || stripos($errorMessage, 'API version v1') !== false);
    $hasModelMismatch = $result['httpCode'] === 404
        && (stripos($errorMessage, 'not found') !== false || stripos($errorMessage, 'not supported for generateContent') !== false);

    if ($isGenerateContent && $hasVersionMismatch) {
        if (strpos($url, '/v1beta/') !== false) {
            $altUrl = str_replace('/v1beta/', '/v1/', $url);
        } elseif (strpos($url, '/v1/') !== false) {
            $altUrl = str_replace('/v1/', '/v1beta/', $url);
        } else {
            $altUrl = '';
        }

        if ($altUrl) {
            $retry = $doRequest($altUrl);
            if ($retry['error']) {
                return ['success' => false, 'error' => 'Erro de conexão: ' . $retry['error']];
            }

            $retryDecoded = json_decode($retry['response'], true);
            if ($retry['httpCode'] === 200) {
                return ['success' => true, 'data' => $retryDecoded];
            }

            $retryError = $retryDecoded['error']['message'] ?? 'Erro desconhecido';
            return ['success' => false, 'error' => "HTTP {$retry['httpCode']}: {$retryError}"];
        }
    }
    
    if ($isGenerateContent && $hasModelMismatch) {
        $apiKey = $extractApiKey($url);
        $models = $listTextModels($apiKey);
        $currentModel = $extractModel($url);
        $fallbackModel = $pickPreferredModel($models);

        if ($fallbackModel && $fallbackModel !== $currentModel) {
            $altUrl = preg_replace('/\\/models\\/[^:]+:/', '/models/' . $fallbackModel . ':', $url);
            $retry = $doRequest($altUrl);
            if ($retry['error']) {
                return ['success' => false, 'error' => 'Erro de conexão: ' . $retry['error']];
            }

            $retryDecoded = json_decode($retry['response'], true);
            if ($retry['httpCode'] === 200) {
                $meta = [
                    'fallback_used' => true,
                    'model_used' => $fallbackModel,
                    'fallback_reason' => 'model_not_found'
                ];
                return ['success' => true, 'data' => $retryDecoded, 'meta' => $meta];
            }

            $retryError = $retryDecoded['error']['message'] ?? 'Erro desconhecido';
            return ['success' => false, 'error' => "HTTP {$retry['httpCode']}: {$retryError}"];
        }
    }

    if ($result['httpCode'] !== 200) {
        return ['success' => false, 'error' => "HTTP {$result['httpCode']}: {$errorMessage}"];
    }

    return ['success' => true, 'data' => $decoded, 'meta' => $meta];
}
