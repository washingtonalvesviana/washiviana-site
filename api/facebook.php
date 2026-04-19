<?php
/**
 * Funções auxiliares para publicação no Facebook Page (Graph API)
 */
require_once __DIR__ . '/config.php';

function publicarNoFacebookViaAPI(array $variant): array {
    global $pdo;

    // Buscar config do Facebook (pode estar na mesma tabela redes_sociais_config como 'facebook' ou usar instagram 'page_id')
    $stmt = $pdo->prepare("SELECT access_token, page_id FROM redes_sociais_config WHERE rede = 'facebook' LIMIT 1");
    $stmt->execute();
    $cfg = $stmt->fetch();

    if (!$cfg || empty($cfg['access_token']) || empty($cfg['page_id'])) {
        return ['success' => false, 'message' => 'Facebook não configurado (access_token/page_id ausente)'];
    }

    $accessToken = $cfg['access_token'];
    $pageId = $cfg['page_id'];

    $message = trim($variant['caption'] ?? '');
    $imageFile = $variant['image_1x1'] ?? null;

    if ($imageFile) {
        $photoUrl = UPLOAD_URL . $imageFile;
        $url = "https://graph.facebook.com/" . (getConfig('facebook_api_version') ?: 'v17.0') . "/{$pageId}/photos";
        $data = [
            'url' => $photoUrl,
            'caption' => $message,
            'access_token' => $accessToken
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($resp, true);
        if (!$decoded || empty($decoded['id'])) {
            return ['success' => false, 'message' => 'Erro ao publicar foto no Facebook: ' . ($decoded['error']['message'] ?? ($resp ?: 'sem resposta'))];
        }

        return ['success' => true, 'post_id' => $decoded['id'], 'meta' => $decoded];
    }

    // Caso não tenha imagem, fazer post de texto simples (menos usado)
    $url = "https://graph.facebook.com/" . (getConfig('facebook_api_version') ?: 'v17.0') . "/{$pageId}/feed";
    $data = [
        'message' => $message,
        'access_token' => $accessToken
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($resp, true);
    if (!$decoded || empty($decoded['id'])) {
        return ['success' => false, 'message' => 'Erro ao publicar no Facebook: ' . ($decoded['error']['message'] ?? ($resp ?: 'sem resposta'))];
    }

    return ['success' => true, 'post_id' => $decoded['id'], 'meta' => $decoded];
}
