<?php
/**
 * WASHIVIANA PORTFOLIO - i18n + SEO generator (LLM provider configurado)
 * Gera EN/ES (a partir do PT) e salva em *_i18n.
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
$langs = $_POST['langs'] ?? ['en', 'es'];
if (!is_array($langs)) $langs = ['en', 'es'];
$langs = array_values(array_unique(array_filter(array_map('normalizeLang', $langs), fn($l) => in_array($l, ['pt', 'en', 'es'], true))));
if (!$langs) $langs = ['pt', 'en', 'es'];

if (!in_array($entity, ['artigo', 'projeto'], true)) {
    jsonResponse(['success' => false, 'message' => 'Entity inválida.'], 400);
}
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID inválido.'], 400);
}

$textProvider = strtolower(trim((string)(getConfig('llm_text_provider') ?: 'gemini')));
$allowedProviders = ['gemini', 'openai', 'deepseek', 'openrouter', 'anthropic', 'ollama'];
if (!in_array($textProvider, $allowedProviders, true)) {
    $textProvider = 'gemini';
}

$model = trim((string)(getConfig('llm_text_model') ?: ''));
if ($model === '') {
    $model = match ($textProvider) {
        'openai' => 'gpt-4o-mini',
        'deepseek' => 'deepseek-chat',
        'openrouter' => 'openai/gpt-4o-mini',
        'anthropic' => 'claude-3-5-sonnet-latest',
        'ollama' => 'llama3.1',
        default => (getConfig('gemini_model') ?: 'gemini-2.0-flash'),
    };
}

$apiKey = trim((string)match ($textProvider) {
    'openai' => getConfig('openai_api_key'),
    'deepseek' => getConfig('deepseek_api_key'),
    'openrouter' => getConfig('openrouter_api_key'),
    'anthropic' => getConfig('anthropic_api_key'),
    'ollama' => getConfig('ollama_api_key'),
    default => getConfig('gemini_api_key'),
});

if ($textProvider !== 'ollama' && empty($apiKey)) {
    jsonResponse(['success' => false, 'message' => 'API Key não configurada para o provedor de texto selecionado (' . strtoupper($textProvider) . ').'], 400);
}

if ($textProvider === 'ollama') {
    $ollamaBaseUrl = trim((string)(getConfig('ollama_base_url') ?: 'http://localhost:11434'));
    if ($ollamaBaseUrl === '') {
        jsonResponse(['success' => false, 'message' => 'Ollama Base URL não configurada.'], 400);
    }
}

try {
    if ($entity === 'artigo') {
        gerarArtigoI18n($id, $langs, $textProvider, $apiKey, $model);
    } else {
        gerarProjetoI18n($id, $langs, $textProvider, $apiKey, $model);
    }
} catch (Exception $e) {
    $msg = $e->getMessage();
    // Log em arquivo acessível (evita depender de permissões do nginx/php-fpm log)
    @file_put_contents(
        sys_get_temp_dir() . '/washiviana_i18n_seo.log',
        '[' . date('c') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
    error_log("i18n_seo error: " . $msg);
    jsonResponse(['success' => false, 'message' => 'Erro ao gerar i18n/SEO.', 'details' => $msg], 500);
}

function slugify(string $text): string {
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    $text = strtr($text, $map);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-{2,}~', '-', $text);
    return $text ?: 'item';
}

function ensureUniqueSlug(string $table, string $idCol, int $id, string $lang, string $slug): string {
    global $pdo;
    $slug = slugify($slug);
    $base = $slug;
    $n = 0;
    while (true) {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE lang = ? AND slug = ? AND {$idCol} <> ? LIMIT 1");
        $stmt->execute([$lang, $slug, $id]);
        if (!$stmt->fetch()) return $slug;
        $n++;
        $slug = $base . '-' . $id . ($n > 1 ? '-' . $n : '');
    }
}

function normalizeJsonPayload(string $text): string {
    $text = trim($text);

    // Remover rawPreview e tudo após ele (é lixo de debug)
    $rawPos = stripos($text, 'rawPreview=');
    if ($rawPos !== false) {
        $text = substr($text, 0, $rawPos);
        $text = trim($text);
    }

    // Remove blocos de código markdown (```json ... ```) - regex com flag 's' para '.' casar com newlines
    // Tenta capturar o JSON completo dentro dos backticks
    if (preg_match('~```(?:json)?\\s*(.+?)\\s*```~s', $text, $m)) {
        $extracted = trim($m[1]);
        // Verifica se o conteúdo extraído parece ser JSON válido (começa com { ou [)
        if (preg_match('/^[\\{\\[]/', $extracted)) {
            return $extracted;
        }
    }

    // Remover qualquer coisa antes do primeiro '{' ou '[' se houver ```
    // Isso pega casos onde o regex acima falhou ou se há texto antes do bloco de código
    if (strpos($text, '```') !== false) {
        $text = preg_replace('/^.*?```(?:json)?\s*/s', '', $text);
        $text = preg_replace('/\s*```.*$/s', '', $text);
    }
    
    return trim($text);
}

function extractFirstJsonBlock(string $text): ?string {
    $len = strlen($text);
    $inString = false;
    $escape = false;
    $depth = 0;
    $start = null;
    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            continue;
        }
        if ($ch === '"') {
            $inString = true;
            continue;
        }
        if ($ch === '{') {
            if ($depth === 0) {
                $start = $i;
            }
            $depth++;
            continue;
        }
        if ($ch === '}') {
            if ($depth > 0) {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
    }
    return null;
}

function repairJsonString(string $text): string {
    $out = '';
    $inString = false;
    $escape = false;
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        $ord = ord($ch);
        if ($inString) {
            if ($escape) {
                $out .= $ch;
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $out .= $ch;
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
                $out .= $ch;
                continue;
            }
            if ($ch === "\n") { $out .= "\\n"; continue; }
            if ($ch === "\r") { $out .= "\\r"; continue; }
            if ($ch === "\t") { $out .= "\\t"; continue; }
            if ($ord < 0x20) {
                $out .= sprintf("\\u%04x", $ord);
                continue;
            }
            $out .= $ch;
            continue;
        }
        if ($ch === '"') {
            $inString = true;
            $out .= $ch;
            continue;
        }
        $out .= $ch;
    }
    return $out;
}

function extractJsonObject(string $text): ?array {
    $text = normalizeJsonPayload($text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) return $decoded;

    // Fallback: extrair primeiro bloco JSON balanceado
    $maybe = extractFirstJsonBlock($text);
    if ($maybe === null) return null;
    $maybe = normalizeJsonPayload($maybe);
    $decoded = json_decode($maybe, true);
    if (is_array($decoded)) return $decoded;

    $repaired = repairJsonString($maybe);
    $decoded = json_decode($repaired, true);
    if (is_array($decoded)) return $decoded;

    $repairedFull = repairJsonString($text);
    $decoded = json_decode($repairedFull, true);
    return is_array($decoded) ? $decoded : null;
}

function concatGeminiTextParts(array $candidate): string {
    $parts = $candidate['content']['parts'] ?? [];
    if (!is_array($parts)) return '';
    $out = '';
    foreach ($parts as $p) {
        if (is_array($p) && isset($p['text']) && is_string($p['text'])) {
            $out .= $p['text'];
        }
    }
    return $out;
}

function callGeminiJson(string $apiKey, string $model, string $prompt, int $maxTokens = 8192): array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

    $effectiveMaxTokens = $maxTokens;
    $thinkingConfig = null;
    // Gemini 2.5 tende a gastar muitos "thought tokens"; limitar orçamento de thinking ajuda a garantir saída.
    if (str_contains($model, 'gemini-2.5')) {
        $effectiveMaxTokens = max($effectiveMaxTokens, 4096);
        $thinkingConfig = ['thinkingBudget' => 512];
    }

    $base = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => $effectiveMaxTokens,
            'temperature' => 0.2,
        ],
    ];

    if (is_array($thinkingConfig)) {
        $base['generationConfig']['thinkingConfig'] = $thinkingConfig;
    }

    $attempts = [
        $base + ['generationConfig' => $base['generationConfig'] + ['responseMimeType' => 'application/json']],
        $base,
    ];

    $lastError = null;
    foreach ($attempts as $data) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 300, // Aumentado para 5 minutos para respostas grandes
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            $lastError = "cURL error: " . $err;
            continue;
        }
        if ($code < 200 || $code >= 300) {
            $errMsg = $raw;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $errMsg = $decoded['error']['message'] ?? $raw;
            }
            $lastError = "Gemini HTTP {$code}: " . $errMsg;
            continue;
        }

        $json = json_decode($raw, true);
        $candidate = $json['candidates'][0] ?? null;
        $finishReason = $candidate['finishReason'] ?? '';

        // Verificar se a resposta foi truncada por limite de tokens
        if ($finishReason === 'MAX_TOKENS') {
            $lastError = "Resposta do Gemini truncada (MAX_TOKENS). Tente gerar um idioma por vez ou reduza o conteúdo do artigo.";
            continue;
        }

        $text = is_array($candidate) ? concatGeminiTextParts($candidate) : '';
        if ($text === '' && is_array($candidate)) {
            $blockReason = $json['promptFeedback']['blockReason'] ?? '';
            $previewRaw = mb_substr($raw, 0, 1200, 'UTF-8');
            $lastError = "Gemini retornou resposta sem texto. finishReason={$finishReason} blockReason={$blockReason} rawPreview={$previewRaw}";
            continue;
        }
        $parsed = extractJsonObject($text);
        if (is_array($parsed)) return $parsed;

        $preview = mb_substr(trim($text), 0, 600, 'UTF-8');
        $previewRaw = mb_substr($raw, 0, 1200, 'UTF-8');
        $lastError = "Resposta não-JSON do Gemini. Preview: " . $preview . " rawPreview=" . $previewRaw;
    }

    throw new Exception($lastError ?: 'Erro desconhecido ao chamar Gemini.');
}

function callLlmJson(string $provider, string $apiKey, string $model, string $prompt, int $maxTokens = 8192): array {
    $provider = strtolower(trim($provider));

    if ($provider === 'openai' || $provider === 'deepseek' || $provider === 'openrouter') {
        $baseUrl = 'https://api.openai.com/v1';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        if ($provider === 'openai') {
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

        $basePayload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Retorne apenas JSON válido, sem markdown e sem texto extra.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        // Evita que modelos híbridos (Qwen3) em vLLM gastem tudo em reasoning e retornem JSON vazio.
        if ($provider === 'openai' && trim((string)getConfig('openai_base_url')) !== '') {
            $basePayload['chat_template_kwargs'] = ['enable_thinking' => false];
        }

        $payloadVariants = [
            $basePayload + ['max_tokens' => $maxTokens],
            $basePayload + ['max_completion_tokens' => $maxTokens],
        ];

        $lastErrMsg = null;
        $json = null;
        foreach ($payloadVariants as $payload) {
            $ch = curl_init(rtrim($baseUrl, '/') . '/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 300,
            ]);
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                throw new Exception(strtoupper($provider) . ' cURL error: ' . $err);
            }

            if ($code < 200 || $code >= 300) {
                $decoded = json_decode($raw, true);
                $msg = is_array($decoded) ? ($decoded['error']['message'] ?? $raw) : $raw;
                $lastErrMsg = strtoupper($provider) . " HTTP {$code}: " . $msg;

                $isTokenParamError = stripos((string)$msg, 'Unsupported parameter') !== false
                    && (stripos((string)$msg, 'max_tokens') !== false || stripos((string)$msg, 'max_completion_tokens') !== false);
                if ($isTokenParamError) {
                    continue;
                }

                throw new Exception($lastErrMsg);
            }

            $json = json_decode($raw, true);
            break;
        }

        if (!is_array($json)) {
            throw new Exception($lastErrMsg ?: (strtoupper($provider) . ' não retornou resposta válida.'));
        }

        $text = (string)($json['choices'][0]['message']['content'] ?? '');
        $parsed = extractJsonObject($text);
        if (!is_array($parsed)) throw new Exception(strtoupper($provider) . ' retornou resposta não-JSON.');
        return $parsed;
    }

    if ($provider === 'anthropic') {
        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'user', 'content' => 'Retorne apenas JSON válido, sem markdown e sem texto extra.\n\n' . $prompt],
            ],
        ];
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 300,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) throw new Exception('ANTHROPIC cURL error: ' . $err);
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($raw, true);
            $msg = is_array($decoded) ? ($decoded['error']['message'] ?? $raw) : $raw;
            throw new Exception("ANTHROPIC HTTP {$code}: " . $msg);
        }

        $json = json_decode($raw, true);
        $text = '';
        foreach (($json['content'] ?? []) as $part) {
            if (($part['type'] ?? '') === 'text') $text .= (string)($part['text'] ?? '');
        }
        $parsed = extractJsonObject($text);
        if (!is_array($parsed)) throw new Exception('ANTHROPIC retornou resposta não-JSON.');
        return $parsed;
    }

    if ($provider === 'ollama') {
        $baseUrl = rtrim((string)(getConfig('ollama_base_url') ?: 'http://localhost:11434'), '/');
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Retorne apenas JSON válido, sem markdown e sem texto extra.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'stream' => false,
        ];

        $ch = curl_init($baseUrl . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 300,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) throw new Exception('OLLAMA cURL error: ' . $err);
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($raw, true);
            $msg = is_array($decoded) ? ($decoded['error'] ?? $decoded['message'] ?? $raw) : $raw;
            throw new Exception("OLLAMA HTTP {$code}: " . $msg);
        }

        $json = json_decode($raw, true);
        $text = (string)($json['message']['content'] ?? '');
        $parsed = extractJsonObject($text);
        if (!is_array($parsed)) throw new Exception('OLLAMA retornou resposta não-JSON.');
        return $parsed;
    }

    return callGeminiJson($apiKey, $model, $prompt, $maxTokens);
}

function gerarArtigoI18n(int $id, array $langs, string $provider, string $apiKey, string $model): void {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id, titulo, slug, resumo, conteudo FROM artigos WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $a = $stmt->fetch();
    if (!$a) jsonResponse(['success' => false, 'message' => 'Artigo não encontrado.'], 404);

    $promptLangs = implode(', ', $langs);
    $resumoSrc = (string)($a['resumo'] ?? '');
    $conteudoSrc = (string)($a['conteudo'] ?? '');
    if (mb_strlen($resumoSrc, 'UTF-8') > 3000) {
        $resumoSrc = mb_substr($resumoSrc, 0, 3000, 'UTF-8') . "\n\n[TRUNCADO]";
    }
    if (mb_strlen($conteudoSrc, 'UTF-8') > 12000) {
        $conteudoSrc = mb_substr($conteudoSrc, 0, 12000, 'UTF-8') . "\n\n[TRUNCADO]";
    }
    $prompt = "Você é especialista em SEO e tradução.\n" .
        "Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" .
        "Não inclua pensamentos/raciocínio: retorne diretamente o JSON.\n\n" .
        "A partir do conteúdo em PT-BR abaixo, gere versões em {$promptLangs}.\n\n" .
        "Regras:\n" .
        "- Para Português (pt), OTIMIZE os metadados (como meta_title e meta_description) focando em SEO, mas mantenha o texto do conteúdo original.\n" .
        "- Para os outros idiomas (en, es), TRADUZA mantendo o sentido e tom profissional.\n" .
        "- Gere slug em cada idioma (minúsculo, hífen, sem acentos).\n" .
        "- Gere meta_title (<= 60 chars) e meta_description (<= 160 chars) por idioma.\n" .
        "- Gere og_title e og_description coerentes.\n" .
        "- Gere keywords como lista (5 a 12 itens).\n" .
        "- Retorne este JSON:\n";

    $jsonExample = [];
    foreach ($langs as $l) {
        $jsonExample[$l] = [
            "titulo" => "",
            "slug" => "",
            "resumo" => "",
            "conteudo" => "",
            "meta_title" => "",
            "meta_description" => "",
            "og_title" => "",
            "og_description" => "",
            "keywords" => ["..."]
        ];
    }
    $prompt .= json_encode($jsonExample, JSON_PRETTY_PRINT) . "\n\n";
    
    $prompt .= "PT-BR:\n" .
        "titulo: {$a['titulo']}\n" .
        "resumo: " . $resumoSrc . "\n" .
        "conteudo: " . $conteudoSrc;

    // Calcular tokens necessários baseado no tamanho do conteúdo
    // Regra: ~4 chars por token, precisamos de ~2x o input (2 idiomas) + overhead
    $inputLen = mb_strlen($conteudoSrc, 'UTF-8') + mb_strlen($resumoSrc, 'UTF-8');
    $estimatedTokens = 8192; // Maximize token usage for translations

    // Liberar sessão antes de chamar API demorada
    session_write_close();

    $out = callLlmJson($provider, $apiKey, $model, $prompt, $estimatedTokens);

    $saved = [];
    foreach ($langs as $lang) {
        $t = $out[$lang] ?? null;
        if (!is_array($t)) continue;

        $titulo = trim((string)($t['titulo'] ?? ''));
        $resumo = trim((string)($t['resumo'] ?? ''));
        $conteudo = (string)($t['conteudo'] ?? '');
        $slug = trim((string)($t['slug'] ?? ''));
        if ($slug === '') $slug = slugify($titulo ?: ($a['titulo'] . ' ' . $lang));
        $slug = ensureUniqueSlug('artigos_i18n', 'artigo_id', $id, $lang, $slug);

        $metaTitle = trim((string)($t['meta_title'] ?? ''));
        $metaDesc = trim((string)($t['meta_description'] ?? ''));
        $ogTitle = trim((string)($t['og_title'] ?? ''));
        $ogDesc = trim((string)($t['og_description'] ?? ''));

        $keywords = $t['keywords'] ?? null;
        if (is_array($keywords)) $keywords = implode(', ', array_slice(array_map('trim', $keywords), 0, 20));
        if (!is_string($keywords)) $keywords = null;

        $stmt = $pdo->prepare("
            INSERT INTO artigos_i18n
              (artigo_id, lang, titulo, slug, resumo, conteudo, meta_title, meta_description, og_title, og_description, keywords, status_traducao, generated_by, generated_at)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'generated', ?, CURRENT_TIMESTAMP)
            ON CONFLICT (artigo_id, lang) DO UPDATE SET
              titulo = EXCLUDED.titulo,
              slug = EXCLUDED.slug,
              resumo = EXCLUDED.resumo,
              conteudo = EXCLUDED.conteudo,
              meta_title = EXCLUDED.meta_title,
              meta_description = EXCLUDED.meta_description,
              og_title = EXCLUDED.og_title,
              og_description = EXCLUDED.og_description,
              keywords = EXCLUDED.keywords,
              status_traducao = EXCLUDED.status_traducao,
              generated_by = EXCLUDED.generated_by,
              generated_at = EXCLUDED.generated_at,
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$id, $lang, $titulo, $slug, $resumo, $conteudo, $metaTitle, $metaDesc, $ogTitle, $ogDesc, $keywords, $provider]);
        $saved[] = $lang;
    }

    jsonResponse(['success' => true, 'message' => 'Traduções/SEO gerados.', 'saved' => $saved]);
}

function gerarProjetoI18n(int $id, array $langs, string $provider, string $apiKey, string $model): void {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id, titulo, slug, descricao, tecnologias, url_projeto FROM projetos WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) jsonResponse(['success' => false, 'message' => 'Projeto não encontrado.'], 404);

    $promptLangs = implode(', ', $langs);
    $descricaoSrc = (string)($p['descricao'] ?? '');
    if (mb_strlen($descricaoSrc, 'UTF-8') > 8000) {
        $descricaoSrc = mb_substr($descricaoSrc, 0, 8000, 'UTF-8') . "\n\n[TRUNCADO]";
    }
    $prompt = "Você é especialista em SEO e tradução.\n" .
        "Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" .
        "Não inclua pensamentos/raciocínio: retorne diretamente o JSON.\n\n" .
        "A partir do conteúdo do PROJETO em PT-BR abaixo, gere versões em {$promptLangs}.\n\n" .
        "Regras:\n" .
        "- Para Português (pt), OTIMIZE os metadados (como meta_title e meta_description) focando em SEO, mas mantenha o texto da descrição original.\n" .
        "- Para os outros idiomas (en, es), TRADUZA mantendo o sentido e tom profissional.\n" .
        "- Gere slug em cada idioma (minúsculo, hífen, sem acentos).\n" .
        "- Gere meta_title (<= 60 chars) e meta_description (<= 160 chars) por idioma.\n" .
        "- Gere og_title e og_description coerentes.\n" .
        "- Gere keywords como lista (5 a 12 itens).\n" .
        "- Retorne este JSON:\n";

    $jsonExample = [];
    foreach ($langs as $l) {
        $jsonExample[$l] = [
            "titulo" => "",
            "slug" => "",
            "descricao" => "",
            "meta_title" => "",
            "meta_description" => "",
            "og_title" => "",
            "og_description" => "",
            "keywords" => ["..."]
        ];
    }
    $prompt .= json_encode($jsonExample, JSON_PRETTY_PRINT) . "\n\n";

    $prompt .= "PT-BR:\n" .
        "titulo: {$p['titulo']}\n" .
        "descricao: " . $descricaoSrc . "\n" .
        "tecnologias: " . ($p['tecnologias'] ?? '') . "\n" .
        "url: " . ($p['url_projeto'] ?? '');

    // Liberar sessão antes de chamar API demorada
    session_write_close();

    $out = callLlmJson($provider, $apiKey, $model, $prompt, 8192);

    $saved = [];
    foreach ($langs as $lang) {
        $t = $out[$lang] ?? null;
        if (!is_array($t)) continue;

        $titulo = trim((string)($t['titulo'] ?? ''));
        $descricao = (string)($t['descricao'] ?? '');
        $slug = trim((string)($t['slug'] ?? ''));
        if ($slug === '') $slug = slugify($titulo ?: ($p['titulo'] . ' ' . $lang));
        $slug = ensureUniqueSlug('projetos_i18n', 'projeto_id', $id, $lang, $slug);

        $metaTitle = trim((string)($t['meta_title'] ?? ''));
        $metaDesc = trim((string)($t['meta_description'] ?? ''));
        $ogTitle = trim((string)($t['og_title'] ?? ''));
        $ogDesc = trim((string)($t['og_description'] ?? ''));

        $keywords = $t['keywords'] ?? null;
        if (is_array($keywords)) $keywords = implode(', ', array_slice(array_map('trim', $keywords), 0, 20));
        if (!is_string($keywords)) $keywords = null;

        $stmt = $pdo->prepare("
            INSERT INTO projetos_i18n
              (projeto_id, lang, titulo, slug, descricao, meta_title, meta_description, og_title, og_description, keywords, status_traducao, generated_by, generated_at)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'generated', ?, CURRENT_TIMESTAMP)
            ON CONFLICT (projeto_id, lang) DO UPDATE SET
              titulo = EXCLUDED.titulo,
              slug = EXCLUDED.slug,
              descricao = EXCLUDED.descricao,
              meta_title = EXCLUDED.meta_title,
              meta_description = EXCLUDED.meta_description,
              og_title = EXCLUDED.og_title,
              og_description = EXCLUDED.og_description,
              keywords = EXCLUDED.keywords,
              status_traducao = EXCLUDED.status_traducao,
              generated_by = EXCLUDED.generated_by,
              generated_at = EXCLUDED.generated_at,
              updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$id, $lang, $titulo, $slug, $descricao, $metaTitle, $metaDesc, $ogTitle, $ogDesc, $keywords, $provider]);
        $saved[] = $lang;
    }

    jsonResponse(['success' => true, 'message' => 'Traduções/SEO gerados.', 'saved' => $saved]);
}
