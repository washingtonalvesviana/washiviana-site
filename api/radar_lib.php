<?php
/**
 * WASHIVIANA - Radar library (RSS/API/Scrape) + IA ideas
 * Biblioteca reutilizável (API + CLI/cron)
 */

require_once __DIR__ . '/config.php';

function radarNormalizeUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return '';

    // Remove fragment
    $hashPos = strpos($url, '#');
    if ($hashPos !== false) $url = substr($url, 0, $hashPos);

    // Trim whitespace
    $url = preg_replace('/\s+/', '', $url);

    // Basic parse
    $parts = @parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return $url;

    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '/';
    $query = $parts['query'] ?? '';

    // Normalize path (remove trailing slash except root)
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }

    // Remove common tracking params
    $drop = [
        'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
        'gclid','fbclid','igshid','mc_cid','mc_eid','ref','ref_src','s'
    ];
    $filteredQuery = '';
    if ($query !== '') {
        parse_str($query, $q);
        foreach ($drop as $k) {
            if (array_key_exists($k, $q)) unset($q[$k]);
        }
        if (!empty($q)) {
            ksort($q);
            $filteredQuery = http_build_query($q);
        }
    }

    $out = $scheme . '://' . $host . $path;
    if ($filteredQuery !== '') $out .= '?' . $filteredQuery;
    return $out;
}

function radarIsSafeUrl(string $url): bool {
    $parts = @parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) return false;

    $host = $parts['host'];
    $hostLower = strtolower($host);
    if (in_array($hostLower, ['localhost'], true)) return false;

    // Block obvious local networks if host is IP
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        return true;
    }

    // Resolve DNS and block private/reserved results
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if ($records === false) return false;
    foreach ($records as $rec) {
        $ip = $rec['ip'] ?? ($rec['ipv6'] ?? null);
        if (!$ip) continue;
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }

    return true;
}

function radarHttpGet(string $url, int $timeoutSeconds = 20, int $maxBytes = 2_000_000): array {
    if (!radarIsSafeUrl($url)) {
        return ['success' => false, 'error' => 'URL bloqueada por segurança (SSRF).'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_USERAGENT => 'WashivianaRadar/1.0 (+https://washiviana.com)',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
    ]);

    $data = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($err) return ['success' => false, 'error' => $err];
    if ($http < 200 || $http >= 300) return ['success' => false, 'error' => 'HTTP ' . $http];
    if ($data === false || $data === null) return ['success' => false, 'error' => 'Resposta vazia'];

    if (strlen($data) > $maxBytes) {
        $data = substr($data, 0, $maxBytes);
    }

    return ['success' => true, 'body' => $data, 'content_type' => $ct, 'http' => $http];
}

function radarParseRss(string $xml): array {
    $xml = trim($xml);
    if ($xml === '') return ['success' => false, 'error' => 'RSS vazio', 'items' => []];

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    if ($rss === false) {
        $errs = libxml_get_errors();
        libxml_clear_errors();
        return ['success' => false, 'error' => 'XML inválido', 'items' => [], 'libxml' => $errs];
    }

    $items = [];

    // RSS 2.0
    if (isset($rss->channel->item)) {
        foreach ($rss->channel->item as $it) {
            $link = (string)($it->link ?? '');
            $title = (string)($it->title ?? '');
            $desc = (string)($it->description ?? '');
            $pub = (string)($it->pubDate ?? '');
            $date = $pub ? date('Y-m-d H:i:s', strtotime($pub)) : null;

            $items[] = [
                'url' => $link,
                'title' => $title,
                'description' => trim(strip_tags($desc)),
                'published_at' => $date,
                'raw' => [
                    'guid' => (string)($it->guid ?? ''),
                ],
            ];
        }
        return ['success' => true, 'items' => $items];
    }

    // Atom
    if (isset($rss->entry)) {
        foreach ($rss->entry as $it) {
            $title = (string)($it->title ?? '');
            $link = '';
            if (isset($it->link)) {
                foreach ($it->link as $lnk) {
                    $rel = (string)($lnk['rel'] ?? '');
                    if ($rel === '' || $rel === 'alternate') {
                        $link = (string)($lnk['href'] ?? '');
                        break;
                    }
                }
            }
            $summary = (string)($it->summary ?? $it->content ?? '');
            $updated = (string)($it->updated ?? $it->published ?? '');
            $date = $updated ? date('Y-m-d H:i:s', strtotime($updated)) : null;

            $items[] = [
                'url' => $link,
                'title' => $title,
                'description' => trim(strip_tags($summary)),
                'published_at' => $date,
                'raw' => [],
            ];
        }
        return ['success' => true, 'items' => $items];
    }

    return ['success' => true, 'items' => []];
}

function radarScrapeMetadata(string $html): array {
    $html = (string)$html;
    if ($html === '') return ['title' => null, 'description' => null];

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    @$doc->loadHTML($html, LIBXML_NONET);
    $xpath = new DOMXPath($doc);

    $getMeta = function (string $key, string $attr = 'property') use ($xpath): ?string {
        $q = sprintf("//meta[@%s='%s']/@content", $attr, $key);
        $n = $xpath->query($q);
        if ($n && $n->length > 0) {
            $v = trim((string)$n->item(0)->nodeValue);
            return $v !== '' ? $v : null;
        }
        return null;
    };

    $title = null;
    $nodes = $xpath->query('//title');
    if ($nodes && $nodes->length > 0) $title = trim((string)$nodes->item(0)->textContent);
    $ogTitle = $getMeta('og:title', 'property') ?: $getMeta('twitter:title', 'name');
    if ($ogTitle) $title = $ogTitle;

    $desc = $getMeta('og:description', 'property')
        ?: $getMeta('description', 'name')
        ?: $getMeta('twitter:description', 'name');

    return [
        'title' => $title !== '' ? $title : null,
        'description' => $desc !== '' ? $desc : null,
    ];
}

function radarComputeScore(?string $publishedAt, ?string $fetchedAt = null): float {
    $base = 10.0;
    $now = time();
    $t = null;
    if ($publishedAt) $t = strtotime($publishedAt);
    if (!$t && $fetchedAt) $t = strtotime($fetchedAt);
    if (!$t) $t = $now;

    $ageHours = max(0, ($now - $t) / 3600.0);
    // Recency weight: decays slowly
    $score = 100.0 / (1.0 + ($ageHours / 24.0));
    return max($base, min(100.0, $score));
}

function radarEnsureTablesExist(): void {
    // No-op: migration should create tables. Kept for future use.
}

function radarDbColumnExists(string $table, string $column): bool {
    static $cache = [];
    $k = $table . '.' . $column;
    if (isset($cache[$k])) return $cache[$k];
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    $cache[$k] = (bool)$stmt->fetchColumn();
    return $cache[$k];
}

function radarUpsertItem(array $item, ?int $sourceId, array $topicIds = []): array {
    global $pdo;

    $url = trim((string)($item['url'] ?? ''));
    if ($url === '') return ['success' => false, 'error' => 'Item sem URL'];
    $urlNorm = radarNormalizeUrl($url);
    if ($urlNorm === '') return ['success' => false, 'error' => 'URL inválida'];

    $title = trim((string)($item['title'] ?? ''));
    $desc = trim((string)($item['description'] ?? ''));
    $publishedAt = $item['published_at'] ?? null;
    $raw = $item['raw'] ?? null;

    $score = radarComputeScore($publishedAt);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO radar_items (source_id, url, url_norm, titulo, descricao, published_at, score, raw)
            VALUES (:source_id, :url, :url_norm, :titulo, :descricao, :published_at, :score, :raw::jsonb)
            ON CONFLICT (url_norm) DO UPDATE SET
                source_id = COALESCE(EXCLUDED.source_id, radar_items.source_id),
                titulo = COALESCE(NULLIF(EXCLUDED.titulo,''), radar_items.titulo),
                descricao = COALESCE(NULLIF(EXCLUDED.descricao,''), radar_items.descricao),
                published_at = COALESCE(EXCLUDED.published_at, radar_items.published_at),
                fetched_at = CURRENT_TIMESTAMP,
                score = GREATEST(radar_items.score, EXCLUDED.score),
                raw = COALESCE(EXCLUDED.raw, radar_items.raw)
            RETURNING id
        ");
        $stmt->execute([
            ':source_id' => $sourceId,
            ':url' => $url,
            ':url_norm' => $urlNorm,
            ':titulo' => $title,
            ':descricao' => $desc,
            ':published_at' => $publishedAt,
            ':score' => $score,
            ':raw' => $raw ? json_encode($raw, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $itemId = (int)$stmt->fetchColumn();

        if (!empty($topicIds)) {
            $stmtIns = $pdo->prepare("INSERT INTO radar_item_topics (item_id, topic_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
            foreach ($topicIds as $tid) {
                $stmtIns->execute([$itemId, (int)$tid]);
            }
        }

        $pdo->commit();
        return ['success' => true, 'item_id' => $itemId, 'url_norm' => $urlNorm, 'score' => $score];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function radarCollectSource(int $sourceId, array $topicIds = []): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM radar_sources WHERE id = ? AND ativo = true");
    $stmt->execute([$sourceId]);
    $src = $stmt->fetch();
    if (!$src) return ['success' => false, 'error' => 'Fonte não encontrada/ativa'];

    $tipo = $src['tipo'];
    $url = (string)($src['url'] ?? '');
    $config = $src['config'] ? json_decode($src['config'], true) : [];
    if (!is_array($config)) $config = [];

    $items = [];
    $errors = [];

    if ($tipo === 'rss') {
        if ($url === '') return ['success' => false, 'error' => 'RSS sem URL'];
        $r = radarHttpGet($url, (int)($config['timeout'] ?? 20), (int)($config['maxBytes'] ?? 2_000_000));
        if (!$r['success']) return ['success' => false, 'error' => 'RSS: ' . $r['error']];
        $parsed = radarParseRss($r['body']);
        if (!$parsed['success']) return ['success' => false, 'error' => 'RSS parse: ' . ($parsed['error'] ?? 'erro')];
        $items = $parsed['items'];
    } elseif ($tipo === 'scrape') {
        if ($url === '') return ['success' => false, 'error' => 'Scrape sem URL'];
        $r = radarHttpGet($url, (int)($config['timeout'] ?? 20), (int)($config['maxBytes'] ?? 2_000_000));
        if (!$r['success']) return ['success' => false, 'error' => 'Scrape: ' . $r['error']];
        $meta = radarScrapeMetadata($r['body']);
        $items = [[
            'url' => $url,
            'title' => $meta['title'] ?? '',
            'description' => $meta['description'] ?? '',
            'published_at' => null,
            'raw' => ['content_type' => $r['content_type'] ?? ''],
        ]];
    } elseif ($tipo === 'api') {
        if ($url === '') return ['success' => false, 'error' => 'API sem URL'];

        // Config esperado (JSON): method, headers, body, items_path, url_path, title_path, description_path, date_path, timeout, maxBytes
        $method = strtoupper($config['method'] ?? 'GET');
        $headers = is_array($config['headers'] ?? null) ? $config['headers'] : [];
        $body = $config['body'] ?? null;
        $timeout = (int)($config['timeout'] ?? 20);
        $maxBytes = (int)($config['maxBytes'] ?? 2_000_000);
        $itemsPath = trim((string)($config['items_path'] ?? ''));
        $urlPath = trim((string)($config['url_path'] ?? 'url'));
        $titlePath = trim((string)($config['title_path'] ?? 'title'));
        $descriptionPath = trim((string)($config['description_path'] ?? 'description'));
        $datePath = trim((string)($config['date_path'] ?? ''));

        $r = radarHttpRequest($url, $method, $headers, $body, $timeout, $maxBytes);
        if (!$r['success']) return ['success' => false, 'error' => 'API: ' . $r['error']];

        $json = json_decode($r['body'], true);
        if (!is_array($json)) return ['success' => false, 'error' => 'Resposta API não é JSON válido'];

        // Extrair lista de itens
        $items = [];
        if ($itemsPath !== '') {
            $found = getValueByPath($json, $itemsPath);
            if (is_array($found)) $items = $found;
        } else {
            // fallback: tentar keys comuns
            if (isset($json['items']) && is_array($json['items'])) $items = $json['items'];
            elseif (isset($json['data']) && is_array($json['data'])) $items = $json['data'];
        }

        if (!is_array($items) || empty($items)) return ['success' => false, 'error' => 'API: nenhum item encontrado (ver items_path)'];

        // Mapear cada item pelo paths configurados
        $mapped = [];
        foreach ($items as $it) {
            $urlVal = getValueByPath($it, $urlPath);
            $titleVal = getValueByPath($it, $titlePath);
            $descVal = getValueByPath($it, $descriptionPath);
            $dateVal = $datePath ? getValueByPath($it, $datePath) : null;

            // Normalizar date (se timestamp numérico)
            if (is_numeric($dateVal)) {
                $dateVal = date('Y-m-d H:i:s', (int)$dateVal);
            }

            $mapped[] = [
                'url' => $urlVal ?: '',
                'title' => $titleVal ?: '',
                'description' => $descVal ?: '',
                'published_at' => $dateVal ?: null,
                'raw' => ['source_body' => null],
            ];
        }

        $items = $mapped;
    } else {
        return ['success' => false, 'error' => 'Tipo inválido'];
    }

    $saved = 0;
    $last = null;
    foreach ($items as $it) {
        $res = radarUpsertItem($it, (int)$sourceId, $topicIds);
        if ($res['success']) {
            $saved++;
            $last = $res;
        } else {
            $errors[] = $res['error'] ?? 'erro';
        }
    }

    return [
        'success' => true,
        'source_id' => $sourceId,
        'saved' => $saved,
        'errors' => array_values(array_unique($errors)),
        'last' => $last,
    ];
}

function radarHttpRequest(string $url, string $method = 'GET', array $headers = [], $body = null, int $timeoutSeconds = 20, int $maxBytes = 2000000): array {
    if (!radarIsSafeUrl($url)) return ['success' => false, 'error' => 'URL bloqueada por segurança (SSRF).'];

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_USERAGENT => $headers['User-Agent'] ?? 'WashivianaRadar/1.0 (+https://washiviana.com)'
    ];

    $method = strtoupper($method);
    if ($method === 'POST') $curlOpts[CURLOPT_POST] = true;
    if ($body !== null) $curlOpts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);

    $hdrs = [];
    foreach ($headers as $k => $v) $hdrs[] = $k . ': ' . $v;
    if (!empty($hdrs)) $curlOpts[CURLOPT_HTTPHEADER] = $hdrs;

    curl_setopt_array($ch, $curlOpts);
    $data = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['success' => false, 'error' => $err];
    if ($http < 200 || $http >= 300) return ['success' => false, 'error' => 'HTTP ' . $http];
    if ($data === false || $data === null) return ['success' => false, 'error' => 'Resposta vazia'];

    if (strlen($data) > $maxBytes) $data = substr($data, 0, $maxBytes);

    return ['success' => true, 'body' => $data, 'http' => $http];
}

// Helper para extrair valor via dot-path (ex.: data.children.0.data.url)
function getValueByPath($data, string $path) {
    if ($path === '' || $path === null) return null;
    $parts = preg_split('/\.|\//', $path);
    $cur = $data;
    foreach ($parts as $p) {
        if ($cur === null) return null;
        if (is_array($cur) && array_key_exists($p, $cur)) {
            $cur = $cur[$p];
            continue;
        }
        // numeric index
        if (is_array($cur) && is_numeric($p)) {
            $idx = (int)$p;
            if (array_key_exists($idx, $cur)) { $cur = $cur[$idx]; continue; }
            return null;
        }
        // try object
        if (is_object($cur) && isset($cur->{$p})) { $cur = $cur->{$p}; continue; }
        return null;
    }
    return $cur;
}

function radarGeminiGenerate(string $prompt, int $maxTokens = 2048, float $temperature = 0.4, ?string $modelOverride = null): array {
    $apiKey = getConfig('gemini_api_key');
    if (empty($apiKey)) return ['success' => false, 'error' => 'Gemini API Key não configurada (Configurações)'];
    $model = $modelOverride ?: (getConfig('gemini_model') ?: 'gemini-2.0-flash');

    $call = function($modelName, $tokens) use ($apiKey, $prompt, $temperature) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
        $data = [
            'contents' => [[ 'parts' => [[ 'text' => $prompt ]]]],
            'generationConfig' => [
                'maxOutputTokens' => $tokens,
                'temperature' => $temperature,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 90,
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) return ['success' => false, 'error' => $err];
        $json = json_decode((string)$resp, true);
        if ($http < 200 || $http >= 300) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $http);
            return ['success' => false, 'error' => $msg, 'http' => $http, 'raw' => $resp];
        }

        // Try to extract text from known places, be robust to API format changes
        $text = '';
        $tryExtract = function($candidate) use (&$text) {
            if (!is_array($candidate)) return;
            // Common structure: candidate.content.parts[0].text
            if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (is_string($part['text'] ?? null)) {
                        $s = trim($part['text']);
                        if (strlen($s) >= 20 && strtolower($s) !== 'model' && strtolower($s) !== 'assistant') {
                            $text = $s;
                            return;
                        }
                    }
                }
            }
            // Alternative: candidate.content.text
            if (is_string($candidate['content']['text'] ?? null)) {
                $s = trim($candidate['content']['text']);
                if (strlen($s) >= 20 && strtolower($s) !== 'model' && strtolower($s) !== 'assistant') {
                    $text = $s;
                    return;
                }
            }
            // Fallback: search recursively for first reasonable string inside content (>=20 chars)
            $fn = function($v) use (&$fn, &$text) {
                if ($text !== '') return;
                if (is_string($v)) {
                    $s = trim($v);
                    if ($s !== '' && strlen($s) >= 20 && strtolower($s) !== 'model' && strtolower($s) !== 'assistant') { $text = $s; return; }
                }
                if (is_array($v)) {
                    foreach ($v as $x) { $fn($x); if ($text !== '') return; }
                }
                if (is_object($v)) {
                    foreach (get_object_vars($v) as $x) { $fn($x); if ($text !== '') return; }
                }
            };
            $fn($candidate['content'] ?? $candidate);
        };

        if (!empty($json['candidates']) && is_array($json['candidates'])) {
            foreach ($json['candidates'] as $c) {
                $tryExtract($c);
                if ($text !== '') break;
            }
        }

        // As ultimate fallback, try to extract any top-level string in raw json
        if ($text === '') {
            $flat = json_encode($json, JSON_UNESCAPED_UNICODE);
            // Remove long non-text tokens
            $text = substr($flat, 0, 10000);
        }

        return ['success' => true, 'text' => (string)$text, 'model' => $modelName, 'raw' => $json];
    };

    // First call with preferred model
    $r = $call($model, $maxTokens);
    if (!$r['success']) return $r;

    // If model returned no useful text, try a secondary model with fewer tokens
    if (trim((string)$r['text']) === '') {
        $fallbackModel = 'gemini-2.0-flash';
        if ($fallbackModel !== $model) {
            $r2 = $call($fallbackModel, min(1024, $maxTokens));
            if ($r2['success'] && trim((string)$r2['text']) !== '') {
                // merge raw info for debugging
                $r2['raw_fallback'] = $r['raw'] ?? null;
                return $r2;
            }
        }
    }

    return $r;
}

function radarExtractJson(string $text): ?array {
    $t = trim($text);
    if ($t === '') return null;

    // Strip common markdown fences ```json or ```
    $t = preg_replace('/^\s*```json\s*/i', '', $t);
    $t = preg_replace('/^\s*```\s*/', '', $t);
    $t = preg_replace('/\s*```\s*$/', '', $t);

    // Try direct
    $decoded = json_decode($t, true);
    if (is_array($decoded)) return $decoded;

    // Find first { or [ and attempt to extract a balanced JSON substring
    $firstObj = strpos($t, '{');
    $firstArr = strpos($t, '[');
    $start = null;
    $startChar = null;
    if ($firstObj !== false && ($firstArr === false || $firstObj < $firstArr)) { $start = $firstObj; $startChar = '{'; }
    elseif ($firstArr !== false) { $start = $firstArr; $startChar = '['; }
    if ($start === null) return null;

    // Attempt to find matching closing brace/bracket with simple stack aware of strings
    $len = strlen($t);
    $stack = [];
    $inStr = false;
    $escape = false;
    $endPos = null;
    for ($i = $start; $i < $len; $i++) {
        $ch = $t[$i];
        if ($inStr) {
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\') { $escape = true; continue; }
            if ($ch === '"') { $inStr = false; continue; }
            continue;
        }
        if ($ch === '"') { $inStr = true; continue; }
        if ($ch === '{' || $ch === '[') { $stack[] = $ch; continue; }
        if ($ch === '}' || $ch === ']') {
            if (empty($stack)) { continue; }
            $last = array_pop($stack);
            if ($last === '{' && $ch !== '}') { /* mismatch */ }
            if ($last === '[' && $ch !== ']') { /* mismatch */ }
            if (empty($stack)) { $endPos = $i; break; }
        }
    }

    if ($endPos === null) {
        // No balanced end found — try to use last occurrence of } or ] as fallback
        $lastObj = strrpos($t, '}');
        $lastArr = strrpos($t, ']');
        $endPos = $lastObj !== false ? $lastObj : ($lastArr !== false ? $lastArr : null);
    }

    if ($endPos === null || $endPos <= $start) {
        // Can't extract
        return null;
    }

    $slice = substr($t, $start, $endPos - $start + 1);

    // If slice seems unbalanced (missing closing braces), try to balance by counting
    $open = substr_count($slice, '{');
    $close = substr_count($slice, '}');
    if ($open > $close) {
        $slice .= str_repeat('}', $open - $close);
    }
    $openArr = substr_count($slice, '[');
    $closeArr = substr_count($slice, ']');
    if ($openArr > $closeArr) {
        $slice .= str_repeat(']', $openArr - $closeArr);
    }

    $decoded = json_decode($slice, true);
    if (is_array($decoded)) return $decoded;

    return null;
}

function radarGenerateIdeasForTopic(int $topicId, int $limitItems = 20, int $numIdeas = 8): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM radar_topics WHERE id = ? AND ativo = true");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch();
    if (!$topic) return ['success' => false, 'error' => 'Tema não encontrado/ativo'];

    $stmt = $pdo->prepare("
        SELECT i.*
        FROM radar_items i
        JOIN radar_item_topics it ON it.item_id = i.id
        WHERE it.topic_id = ?
        ORDER BY i.score DESC, i.fetched_at DESC
        LIMIT ?
    ");
    $stmt->execute([$topicId, $limitItems]);
    $items = $stmt->fetchAll();
    if (!$items) return ['success' => false, 'error' => 'Sem itens coletados para este tema'];

    $refs = [];
    $ids = [];
    foreach ($items as $it) {
        $ids[] = (int)$it['id'];
        $refs[] = "- " . trim((string)($it['titulo'] ?? '')) . " | " . (string)$it['url'];
    }

    $idiomas = (string)($topic['idiomas'] ?? 'pt,en');
    $regioes = (string)($topic['regioes'] ?? 'br,us,eu');

    $forcePt = stripos($idiomas, 'pt') !== false;

    $prompt =
        "Você é um editor de conteúdo.\n" .
        "Objetivo: sugerir ideias de artigos ORIGINAIS a partir de tendências e links coletados.\n" .
        "Regras:\n" .
        "- NÃO copie texto das fontes.\n" .
        "- Gere ideias com ângulo próprio e valor prático.\n" .
        "- Idiomas-alvo: {$idiomas}.\n" .
        "- Regiões-alvo: {$regioes} (priorize Brasil, EUA e Europa).\n\n" .
        ( $forcePt ? "ATENÇÃO: RETORNE APENAS EM PORTUGUÊS (PT-BR). TODOS OS CAMPOS (title, angle, summary, outline, tags) DEVEM SER RESPONDIDOS EM PT-BR.\n\n" : "" ) .
        "Tema: " . (string)$topic['nome'] . "\n" .
        "Palavras-chave: " . (string)($topic['keywords'] ?? '') . "\n\n" .
        "Fontes (links coletados):\n" . implode("\n", $refs) . "\n\n" .
        "Retorne APENAS JSON válido no formato:\n" .
        "{\n" .
        "  \"ideas\": [\n" .
        "    {\n" .
        "      \"title\": \"...\",\n" .
        "      \"angle\": \"...\",\n" .
        "      \"summary\": \"...\",\n" .
        "      \"outline\": [\"...\",\"...\"],\n" .
        "      \"tags\": [\"...\"],\n" .
        "      \"priority\": \"hype|medium|evergreen\"\n" .
        "    }\n" .
        "  ]\n" .
        "}\n" .
        "Gere exatamente {$numIdeas} ideias. Seja conciso no resumo e outline para evitar cortes no JSON.\n";

    $r = radarGeminiGenerate($prompt, 4096, 0.35);
    if (!$r['success']) return ['success' => false, 'error' => $r['error']];

    $json = radarExtractJson($r['text']);
    // If extraction failed, try a fallback model with fewer tokens
    if (!$json || empty($json['ideas']) || !is_array($json['ideas'])) {
        $r2 = radarGeminiGenerate($prompt, 1024, 0.2, 'gemini-2.0-flash');
        if ($r2['success']) {
            $json2 = radarExtractJson($r2['text']);
            if ($json2 && !empty($json2['ideas']) && is_array($json2['ideas'])) {
                $r['text'] = $r2['text'];
                $r['raw_fallback'] = $r2['raw'] ?? null;
                $json = $json2;
            }
        }
    }

    if (!$json || empty($json['ideas']) || !is_array($json['ideas'])) {
        // Provide raw responses to help debugging
        return [
            'success' => false,
            'error' => 'IA não retornou JSON válido',
            'ai_text' => $r['text'] ?? '',
            'ai_raw' => $r['raw'] ?? null,
            'ai_fallback_raw' => $r['raw_fallback'] ?? null
        ];
    }

    // If Portuguese is required but ideas appear to be in English, try to translate JSON into PT-BR
    if ($forcePt) {
        $needTranslation = false;
        foreach ($json['ideas'] as $item) {
            $t = (string)($item['title'] ?? '');
            if (preg_match('/\b(the|and|of|for|with|in|by|are|is)\b/i', $t)) { $needTranslation = true; break; }
        }
        if ($needTranslation) {
            $translationPrompt = "TRADUZA o JSON abaixo para PORTUGUÊS (PT-BR) e RETORNE APENAS JSON válido no mesmo formato (campos: title, angle, summary, outline, tags):\n\n" . json_encode($json, JSON_UNESCAPED_UNICODE);
            $tr = radarGeminiGenerate($translationPrompt, 2048, 0.2, 'gemini-2.0-flash');
            if ($tr['success']) {
                $maybe = radarExtractJson($tr['text']);
                if ($maybe && !empty($maybe['ideas']) && is_array($maybe['ideas'])) {
                    $json = $maybe;
                    $r['translated'] = true;
                    $r['translated_raw'] = $tr['raw'] ?? null;
                }
            }
        }
    }

    $saved = 0;
    $ideaIds = [];
    $stmtIns = $pdo->prepare("
        INSERT INTO radar_ideas (topic_id, titulo, angulo, resumo, outline, tags, status, source_item_ids, ai_model, ai_prompt, ai_raw)
        VALUES (:topic_id, :titulo, :angulo, :resumo, :outline, :tags, 'nova', :source_item_ids::jsonb, :ai_model, :ai_prompt, :ai_raw)
        RETURNING id
    ");

    foreach ($json['ideas'] as $idea) {
        if (!is_array($idea)) continue;
        $title = trim((string)($idea['title'] ?? ''));
        if ($title === '') continue;
        $outline = $idea['outline'] ?? [];
        if (is_array($outline)) {
            $outlineStr = implode("\n", array_map(fn($x) => '- ' . trim((string)$x), $outline));
        } else {
            $outlineStr = trim((string)$outline);
        }
        $tags = $idea['tags'] ?? [];
        $tagsStr = is_array($tags) ? implode(', ', array_map('strval', $tags)) : trim((string)$tags);

        $stmtIns->execute([
            ':topic_id' => $topicId,
            ':titulo' => $title,
            ':angulo' => trim((string)($idea['angle'] ?? '')),
            ':resumo' => trim((string)($idea['summary'] ?? '')),
            ':outline' => $outlineStr,
            ':tags' => $tagsStr,
            ':source_item_ids' => json_encode($ids),
            ':ai_model' => (string)($r['model'] ?? ''),
            ':ai_prompt' => $prompt,
            ':ai_raw' => (string)($r['text'] ?? ''),
        ]);
        $ideaIds[] = (int)$stmtIns->fetchColumn();
        $saved++;
    }

    return ['success' => true, 'saved' => $saved, 'idea_ids' => $ideaIds, 'model' => $r['model'] ?? null];
}

function radarParseStructuredArticle(string $text): array {
    $out = [
        'titulo' => null,
        'slug' => null,
        'categoria' => null,
        'resumo' => null,
        'conteudo' => null,
        'raw' => $text,
    ];
    $t = trim($text);
    if ($t === '') return $out;

    // Remove qualquer intro antes de "Título:"
    $t = preg_replace('/^[\\s\\S]*?(?=T[ií]tulo\\s*:)/im', '', $t);

    $m = [];
    if (preg_match('/T[ií]tulo\\s*:\\s*(.+?)(?:\\n|$)/im', $t, $m)) $out['titulo'] = trim($m[1]);
    if (preg_match('/Slug\\s*:\\s*(.+?)(?:\\n|$)/im', $t, $m)) $out['slug'] = trim($m[1]);
    if (preg_match('/Categoria\\s*:\\s*(.+?)(?:\\n|$)/im', $t, $m)) $out['categoria'] = trim($m[1]);
    if (preg_match('/Resumo\\s*:\\s*([\\s\\S]+?)(?=\\nConte[uú]do\\s*:)/im', $t, $m)) $out['resumo'] = trim($m[1]);
    if (preg_match('/Conte[uú]do\\s*:\\s*([\\s\\S]+)$/im', $t, $m)) $out['conteudo'] = trim($m[1]);

    return $out;
}

function radarIdeaToDraftArticle(int $ideaId): array {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT i.*, t.nome AS topic_nome, t.categoria_artigos_id
        FROM radar_ideas i
        JOIN radar_topics t ON t.id = i.topic_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$ideaId]);
    $idea = $stmt->fetch();
    if (!$idea) return ['success' => false, 'error' => 'Ideia não encontrada'];

    $itemIds = [];
    if (!empty($idea['source_item_ids'])) {
        $tmp = json_decode($idea['source_item_ids'], true);
        if (is_array($tmp)) $itemIds = array_map('intval', $tmp);
    }

    $refs = [];
    if (!empty($itemIds)) {
        $in = implode(',', array_fill(0, count($itemIds), '?'));
        $q = $pdo->prepare("SELECT id, url, titulo, descricao FROM radar_items WHERE id IN ($in) ORDER BY score DESC, fetched_at DESC LIMIT 20");
        $q->execute($itemIds);
        foreach ($q->fetchAll() as $it) {
            $refs[] = "- " . trim((string)($it['titulo'] ?? '')) . " | " . (string)$it['url'];
        }
    }

    $instrucoes = getConfig('ia_instrucoes') ?: '';
    $prompt = '';
    if ($instrucoes !== '') $prompt .= $instrucoes . "\n\n";

    $prompt .=
        "SOLICITAÇÃO: Escreva um artigo ORIGINAL baseado nesta ideia, no meu estilo.\n" .
        "Tema/Área: " . (string)$idea['topic_nome'] . "\n" .
        "Ideia: " . (string)$idea['titulo'] . "\n" .
        "Ângulo: " . (string)($idea['angulo'] ?? '') . "\n" .
        "Resumo da ideia: " . (string)($idea['resumo'] ?? '') . "\n" .
        "Outline sugerido:\n" . (string)($idea['outline'] ?? '') . "\n\n" .
        "Referências (para você entender o contexto; NÃO copie frases):\n" .
        (empty($refs) ? "- (sem links)\n" : implode("\n", $refs) . "\n") . "\n" .
        "Regras obrigatórias:\n" .
        "- Não copiar trechos literalmente das fontes.\n" .
        "- Escrever com voz autoral, didática e prática.\n" .
        "- Incluir no final uma seção <h2>Fontes</h2> com lista (<ul><li>) de links usados.\n\n" .
        "FORMATO DE SAÍDA OBRIGATÓRIO:\n" .
        "- Comece DIRETAMENTE com os campos, sem introdução.\n" .
        "- Use EXATAMENTE estas labels no início de cada linha.\n" .
        "- O CONTEÚDO deve estar em HTML.\n\n" .
        "Título: [título aqui]\n" .
        "Slug: [slug-aqui-em-minusculas-sem-acentos]\n" .
        "Categoria: [categoria]\n" .
        "Resumo: [resumo até 320 caracteres, texto puro sem HTML]\n" .
        "Conteúdo: [ARTIGO COMPLETO EM HTML]\n";

    $r = radarGeminiGenerate($prompt, 8192, 0.6);
    if (!$r['success']) return ['success' => false, 'error' => $r['error']];

    $parsed = radarParseStructuredArticle($r['text']);
    $titulo = $parsed['titulo'] ?: (string)$idea['titulo'];
    $slug = $parsed['slug'] ?: generateSlug($titulo);
    $resumo = $parsed['resumo'] ?: (string)($idea['resumo'] ?? '');
    $conteudo = $parsed['conteudo'] ?: $r['text'];

    // Ensure slug unique in artigos
    $stmt = $pdo->prepare("SELECT id FROM artigos WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) $slug = $slug . '-' . time();

    // Build dynamic insert based on existing columns
    $cols = ['titulo', 'slug', 'resumo', 'conteudo', 'autor', 'status_publicacao', 'ativo'];
    $vals = [':titulo' => $titulo, ':slug' => $slug, ':resumo' => $resumo, ':conteudo' => $conteudo, ':autor' => 'Washington Viana', ':status_publicacao' => 'rascunho', ':ativo' => true];

    if (radarDbColumnExists('artigos', 'categoria_id') && !empty($idea['categoria_artigos_id'])) {
        $cols[] = 'categoria_id';
        $vals[':categoria_id'] = (int)$idea['categoria_artigos_id'];
    }
    if (radarDbColumnExists('artigos', 'prompt_texto')) {
        $cols[] = 'prompt_texto';
        $vals[':prompt_texto'] = $prompt;
    }
    if (radarDbColumnExists('artigos', 'tags') && !empty($idea['tags'])) {
        $cols[] = 'tags';
        $vals[':tags'] = (string)$idea['tags'];
    }

    $place = array_map(fn($c) => ':' . $c, $cols);
    $sql = "INSERT INTO artigos (" . implode(',', $cols) . ") VALUES (" . implode(',', $place) . ") RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
    $artigoId = (int)$stmt->fetchColumn();

    $pdo->prepare("UPDATE radar_ideas SET status = 'virou_artigo', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ideaId]);

    return ['success' => true, 'artigo_id' => $artigoId, 'slug' => $slug, 'model' => $r['model'] ?? null];
}

function radarIdeaToCreateSimpleDraft(int $ideaId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM radar_ideas WHERE id = ? LIMIT 1");
    $stmt->execute([$ideaId]);
    $idea = $stmt->fetch();
    if (!$idea) return ['success' => false, 'error' => 'Ideia não encontrada'];

    $titulo = trim((string)($idea['titulo'] ?? ''));
    if ($titulo === '') return ['success' => false, 'error' => 'Título vazio'];
    $slug = generateSlug($titulo);

    $resumo = trim((string)($idea['resumo'] ?? ''));
    $outline = $idea['outline'] ?? '';
    $contentHtml = '';
    if ($resumo !== '') $contentHtml .= '<p>' . htmlspecialchars($resumo) . '</p>';
    if (!empty($outline)) {
        $lines = preg_split('/\r?\n/', trim($outline));
        $items = [];
        foreach ($lines as $ln) {
            $ln = trim(preg_replace('/^[-\*\s]*/', '', $ln));
            if ($ln !== '') $items[] = '<li>' . htmlspecialchars($ln) . '</li>';
        }
        if (!empty($items)) $contentHtml .= '<h2>Outline</h2><ul>' . implode('', $items) . '</ul>';
    }

    // Ensure unique slug
    $stmt = $pdo->prepare("SELECT id FROM artigos WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) $slug = $slug . '-' . time();

    $cols = ['titulo', 'slug', 'resumo', 'conteudo', 'autor', 'status_publicacao', 'ativo'];
    $vals = [':titulo' => $titulo, ':slug' => $slug, ':resumo' => $resumo, ':conteudo' => $contentHtml, ':autor' => 'Washington Viana', ':status_publicacao' => 'rascunho', ':ativo' => true];

    if (radarDbColumnExists('artigos', 'tags') && !empty($idea['tags'])) {
        $cols[] = 'tags';
        $vals[':tags'] = (string)$idea['tags'];
    }

    $place = array_map(fn($c) => ':' . $c, $cols);
    $sql = "INSERT INTO artigos (" . implode(',', $cols) . ") VALUES (" . implode(',', $place) . ") RETURNING id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
    $artigoId = (int)$stmt->fetchColumn();

    $pdo->prepare("UPDATE radar_ideas SET status = 'virou_artigo', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$ideaId]);

    return ['success' => true, 'artigo_id' => $artigoId, 'slug' => $slug, 'mode' => 'simple'];
}


function radarAnalyzeHype(int $hours = 48, float $threshold = 0.55): array {
    global $pdo;
    
    // 1. Fetch recent items
    $stmt = $pdo->prepare("
        SELECT id, titulo, published_at, fetched_at 
        FROM radar_items 
        WHERE (published_at >= NOW() - INTERVAL '48 HOURS' OR fetched_at >= NOW() - INTERVAL '48 HOURS')
        ORDER BY fetched_at DESC
        LIMIT 500
    ");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    if (count($items) < 2) return ['success' => true, 'message' => 'Poucos itens para analisar.', 'clusters_found' => 0, 'items_updated' => 0];

    $clusters = [];
    $processed = [];

    // 2. Simple Clustering (O(N^2))
    foreach ($items as $i => $itemA) {
        if (isset($processed[$itemA['id']])) continue;
        
        $cluster = [$itemA];
        $processed[$itemA['id']] = true;
        
        $titleA = mb_strtolower(trim($itemA['titulo']));
        
        // PHP levenshtein() limit is 255 chars
        if (strlen($titleA) > 255) $titleA = substr($titleA, 0, 255);
        
        for ($j = $i + 1; $j < count($items); $j++) {
            $itemB = $items[$j];
            if (isset($processed[$itemB['id']])) continue;
            
            $titleB = mb_strtolower(trim($itemB['titulo']));
            if (strlen($titleB) > 255) $titleB = substr($titleB, 0, 255);
            
            // Similitude
            $lev = levenshtein($titleA, $titleB);
            $maxLen = max(mb_strlen($titleA), mb_strlen($titleB));
            if ($maxLen == 0) continue;
            
            $ratio = $lev / $maxLen; // 0 = identico, 1 = diferente
            
            if ($ratio <= $threshold) {
                $cluster[] = $itemB;
                $processed[$itemB['id']] = true;
            }
        }
        
        if (count($cluster) > 1) {
            $clusters[] = $cluster;
        }
    }
    
    // 3. Calculate Metrics
    $updatedCount = 0;
    $pdo->beginTransaction();
    try {
        foreach ($clusters as $cluster) {
            $times = [];
            foreach ($cluster as $it) {
                $t = $it['published_at'] ? strtotime($it['published_at']) : strtotime($it['fetched_at']);
                if ($t) $times[] = $t;
            }
            if (empty($times)) continue;

            $minTime = min($times);
            $maxTime = max($times);
            $spanHours = ($maxTime - $minTime) / 3600;
            if ($spanHours < 0.1) $spanHours = 0.1; // avoid zero
            
            // Velocity = Items / Hour
            $velocity = count($cluster) / $spanHours;
            
            // Hype Score = Velocity * Log(Count) (boost larger clusters)
            $hypeScore = $velocity * log(count($cluster) + 1);
            
            $isTrending = ($velocity >= 2.0 && count($cluster) >= 3); 
            
            // Update items
            foreach ($cluster as $it) {
                // Fetch current raw
                $stmtRaw = $pdo->prepare("SELECT raw FROM radar_items WHERE id = ?");
                $stmtRaw->execute([$it['id']]);
                $currRaw = json_decode($stmtRaw->fetchColumn() ?? '{}', true);
                if (!is_array($currRaw)) $currRaw = [];
                
                $currRaw['velocity'] = round($velocity, 2);
                $currRaw['hype_score'] = round($hypeScore, 2);
                $currRaw['is_trending'] = $isTrending;
                $currRaw['cluster_size'] = count($cluster);
                
                $newRaw = json_encode($currRaw, JSON_UNESCAPED_UNICODE);
                
                $stmtUp = $pdo->prepare("UPDATE radar_items SET raw = ?::jsonb WHERE id = ?");
                $stmtUp->execute([$newRaw, $it['id']]);
                
                $updatedCount++;
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
    
    return [
        'success' => true, 
        'clusters_found' => count($clusters),
        'items_updated' => $updatedCount
    ];
}
