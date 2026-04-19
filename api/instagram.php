<?php
/**
 * Funções auxiliares para publicação no Instagram (Instagram Graph API)
 */
require_once __DIR__ . '/config.php';

function publicarNoInstagramViaAPI(array $variant): array {
    global $pdo;

    // Buscar config do Instagram
    $stmt = $pdo->prepare("SELECT access_token, page_id, dados_extras FROM redes_sociais_config WHERE rede = 'instagram' LIMIT 1");
    $stmt->execute();
    $cfg = $stmt->fetch();

    if (!$cfg || empty($cfg['access_token']) || empty($cfg['page_id'])) {
        return ['success' => false, 'message' => 'Instagram não configurado (access_token/page_id ausente)'];
    }

    $accessToken = $cfg['access_token'];
    $igUserId = $cfg['page_id']; // deve ser o instagram business account id

    $caption = trim($variant['caption'] ?? '');

    // Preferir image_1x1 para feed
    $imageFile = $variant['image_1x1'] ?? null;
    if (!$imageFile) {
        return ['success' => false, 'message' => 'Nenhuma imagem 1:1 disponível para publicar no Instagram.'];
    }

    // URL pública da imagem (deve estar acessível pelo Facebook Graph API)
    $imageUrl = UPLOAD_URL . $imageFile;

    // 1) Criar media container
    $createUrl = "https://graph.facebook.com/" . (getConfig('facebook_api_version') ?: 'v17.0') . "/{$igUserId}/media";
    $data = [
        'image_url' => $imageUrl,
        'caption' => $caption,
        'access_token' => $accessToken
    ];

    $ch = curl_init($createUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($resp, true);
    if (!$decoded || empty($decoded['id'])) {
        return ['success' => false, 'message' => 'Erro ao criar media container no Instagram: ' . ($decoded['error']['message'] ?? ($resp ?: 'sem resposta'))];
    }

    $creationId = $decoded['id'];

    // 2) Publicar o container
    $publishUrl = "https://graph.facebook.com/" . (getConfig('facebook_api_version') ?: 'v17.0') . "/{$igUserId}/media_publish";
    $pubData = [
        'creation_id' => $creationId,
        'access_token' => $accessToken
    ];

    $ch = curl_init($publishUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pubData));
    $resp2 = curl_exec($ch);
    $http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded2 = json_decode($resp2, true);
    if (!$decoded2 || empty($decoded2['id'])) {
        return ['success' => false, 'message' => 'Erro ao publicar no Instagram: ' . ($decoded2['error']['message'] ?? ($resp2 ?: 'sem resposta'))];
    }

    // Retornar id do post (media id)
    return ['success' => true, 'post_id' => $decoded2['id'], 'meta' => $decoded2];
}
