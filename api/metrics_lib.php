<?php
/**
 * Coleta de metricas de publicacoes sociais (LinkedIn / Instagram / Facebook).
 *
 * Best-effort: falhas de API sao retornadas como erro e nao interrompem o worker.
 * Nao possui efeitos colaterais ao ser incluido (nao faz dispatch de actions).
 */

require_once __DIR__ . '/config.php';

/**
 * Versao da Graph API (Facebook/Instagram).
 */
function metricsApiVersion(): string {
    $v = getConfig('facebook_api_version');
    return $v ? (string)$v : 'v17.0';
}

/**
 * GET simples com cURL retornando JSON. Nunca lanca exceção.
 */
function metricsHttpGet(string $url, array $headers = [], int $timeout = 20): array {
    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'status' => 0, 'error' => 'curl init falhou', 'data' => null];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['success' => false, 'status' => 0, 'error' => $err ?: 'erro de conexao', 'data' => null];
    }

    $data = json_decode($resp, true);
    if ($code < 200 || $code >= 300) {
        $msg = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $code);
        return ['success' => false, 'status' => $code, 'error' => $msg, 'data' => $data];
    }

    return ['success' => true, 'status' => $code, 'error' => null, 'data' => $data];
}

/**
 * Busca metricas do LinkedIn via socialActions (curtidas/comentarios).
 * Adequado para posts no perfil pessoal (urn:li:share:... / urn:li:ugcPost:...).
 */
function metricsFetchLinkedIn(string $postId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin' LIMIT 1");
    $stmt->execute();
    $token = $stmt->fetchColumn();
    if (!$token) {
        return ['success' => false, 'error' => 'LinkedIn sem access_token'];
    }

    $headers = [
        'Authorization: Bearer ' . $token,
        'X-Restli-Protocol-Version: 2.0.0',
        'LinkedIn-Version: ' . LINKEDIN_API_VERSION,
    ];

    $url = 'https://api.linkedin.com/rest/socialActions/' . rawurlencode($postId);
    $res = metricsHttpGet($url, $headers);
    if (!$res['success']) {
        return ['success' => false, 'error' => 'LinkedIn: ' . $res['error']];
    }

    $d = $res['data'] ?? [];
    $likes = (int)($d['likesSummary']['totalLikes'] ?? 0);
    $comments = (int)($d['commentsSummary']['totalComments'] ?? 0);

    return ['success' => true, 'metrics' => [
        'visualizacoes' => 0,
        'curtidas' => $likes,
        'comentarios' => $comments,
        'compartilhamentos' => 0,
        'cliques' => 0,
        'alcance' => 0,
        'engajamento' => $likes + $comments,
        'extras' => $d,
    ]];
}

/**
 * Busca metricas do Instagram (Graph API insights).
 */
function metricsFetchInstagram(string $mediaId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT access_token FROM redes_sociais_config WHERE rede = 'instagram' LIMIT 1");
    $stmt->execute();
    $token = $stmt->fetchColumn();
    if (!$token) {
        return ['success' => false, 'error' => 'Instagram sem access_token'];
    }

    $v = metricsApiVersion();
    $metrics = 'impressions,reach,likes,comments,shares,saved';
    $url = "https://graph.facebook.com/{$v}/" . rawurlencode($mediaId) . '/insights'
        . '?metric=' . $metrics . '&access_token=' . rawurlencode($token);

    $res = metricsHttpGet($url);
    if (!$res['success']) {
        return ['success' => false, 'error' => 'Instagram: ' . $res['error']];
    }

    $map = [];
    foreach (($res['data']['data'] ?? []) as $item) {
        $name = $item['name'] ?? '';
        $value = $item['values'][0]['value'] ?? ($item['value'] ?? 0);
        if ($name !== '') $map[$name] = (int)$value;
    }

    return ['success' => true, 'metrics' => [
        'visualizacoes' => $map['impressions'] ?? 0,
        'curtidas' => $map['likes'] ?? 0,
        'comentarios' => $map['comments'] ?? 0,
        'compartilhamentos' => $map['shares'] ?? 0,
        'cliques' => 0,
        'alcance' => $map['reach'] ?? 0,
        'engajamento' => ($map['likes'] ?? 0) + ($map['comments'] ?? 0) + ($map['shares'] ?? 0) + ($map['saved'] ?? 0),
        'extras' => $res['data']['data'] ?? [],
    ]];
}

/**
 * Busca metricas do Facebook Page (likes/comentarios/compartilhamentos).
 */
function metricsFetchFacebook(string $postId): array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT access_token FROM redes_sociais_config WHERE rede = 'facebook' LIMIT 1");
    $stmt->execute();
    $token = $stmt->fetchColumn();
    if (!$token) {
        return ['success' => false, 'error' => 'Facebook sem access_token'];
    }

    $v = metricsApiVersion();
    $fields = 'likes.summary(true),comments.summary(true),shares';
    $url = "https://graph.facebook.com/{$v}/" . rawurlencode($postId) . '?fields=' . rawurlencode($fields)
        . '&access_token=' . rawurlencode($token);

    $res = metricsHttpGet($url);
    if (!$res['success']) {
        return ['success' => false, 'error' => 'Facebook: ' . $res['error']];
    }

    $d = $res['data'] ?? [];
    $likes = (int)($d['likes']['summary']['total_count'] ?? 0);
    $comments = (int)($d['comments']['summary']['total_count'] ?? 0);
    $shares = (int)($d['shares']['count'] ?? 0);

    return ['success' => true, 'metrics' => [
        'visualizacoes' => 0,
        'curtidas' => $likes,
        'comentarios' => $comments,
        'compartilhamentos' => $shares,
        'cliques' => 0,
        'alcance' => 0,
        'engajamento' => $likes + $comments + $shares,
        'extras' => $d,
    ]];
}

/**
 * Coleta metricas de uma publicacao conforme a rede.
 *
 * @param array $pub Linha de publicacoes_redes (precisa de rede e post_id).
 */
function collectMetricsForPublication(array $pub): array {
    $rede = strtolower(trim((string)($pub['rede'] ?? '')));
    $postId = trim((string)($pub['post_id'] ?? ''));

    if ($postId === '') {
        return ['success' => false, 'error' => 'publicacao sem post_id'];
    }

    try {
        return match ($rede) {
            'linkedin' => metricsFetchLinkedIn($postId),
            'instagram' => metricsFetchInstagram($postId),
            'facebook' => metricsFetchFacebook($postId),
            default => ['success' => false, 'error' => 'rede sem coletor: ' . $rede],
        };
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'excecao: ' . $e->getMessage()];
    }
}

/**
 * Insere um snapshot de metricas em metricas_publicacoes.
 */
function storeMetricsSnapshot(int $publicacaoId, array $m): void {
    global $pdo;

    $stmt = $pdo->prepare(
        "INSERT INTO metricas_publicacoes
            (publicacao_id, data_coleta, visualizacoes, curtidas, comentarios, compartilhamentos, cliques, alcance, engajamento, dados_extras)
         VALUES (?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $publicacaoId,
        (int)($m['visualizacoes'] ?? 0),
        (int)($m['curtidas'] ?? 0),
        (int)($m['comentarios'] ?? 0),
        (int)($m['compartilhamentos'] ?? 0),
        (int)($m['cliques'] ?? 0),
        (int)($m['alcance'] ?? 0),
        (float)($m['engajamento'] ?? 0),
        isset($m['extras']) ? json_encode($m['extras'], JSON_UNESCAPED_UNICODE) : null,
    ]);
}
