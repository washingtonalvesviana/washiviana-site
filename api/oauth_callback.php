<?php
/**
 * OAuth callback endpoint for Facebook/Instagram
 * Recebe: GET ?rede=instagram|facebook&code=...&state=...
 * Troca o code por token e salva em redes_sociais_config
 */
require_once __DIR__ . '/config.php';

$rede = $_GET['rede'] ?? null;
$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

if (!$rede || !$code) {
    http_response_code(400);
    echo "Missing parameters.";
    exit;
}

session_start();
if (empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    echo "Parâmetro state inválido. Possível CSRF.";
    exit;
}

try {
    // Buscar client_id/secret da tabela conforme rede (usamos 'instagram' client configs stored in redes_sociais_config)
    $stmt = $pdo->prepare("SELECT client_id, client_secret FROM redes_sociais_config WHERE rede = 'instagram' LIMIT 1");
    $stmt->execute();
    $cfg = $stmt->fetch();
    if (!$cfg || empty($cfg['client_id']) || empty($cfg['client_secret'])) {
        echo "App não configurado. Configure Client ID/Secret em Admin → Redes Sociais.";
        exit;
    }

    $clientId = $cfg['client_id'];
    $clientSecret = $cfg['client_secret'];
    $redirectUri = BASE_URL . '/api/oauth_callback.php?rede=' . urlencode($rede);

    // 1) Exchange code for short-lived token
    $tokenUrl = 'https://graph.facebook.com/v17.0/oauth/access_token';
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'client_secret' => $clientSecret,
        'code' => $code
    ];

    $ch = curl_init($tokenUrl . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if (!$data || empty($data['access_token'])) {
        echo "Erro ao trocar code por token: " . ($data['error']['message'] ?? $resp);
        exit;
    }

    $shortLived = $data['access_token'];

    // 2) Exchange short token for long-lived token
    $exchangeUrl = 'https://graph.facebook.com/v17.0/oauth/access_token';
    $params2 = [
        'grant_type' => 'fb_exchange_token',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'fb_exchange_token' => $shortLived
    ];
    $ch = curl_init($exchangeUrl . '?' . http_build_query($params2));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp2 = curl_exec($ch);
    $http2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data2 = json_decode($resp2, true);
    if (!$data2 || empty($data2['access_token'])) {
        echo "Erro ao trocar por long-lived token: " . ($data2['error']['message'] ?? $resp2);
        exit;
    }

    $longUserToken = $data2['access_token'];

    // 3) Buscar Pages do usuário para obter Page Access Token e Instagram Business Account
    $meUrl = 'https://graph.facebook.com/v17.0/me/accounts?access_token=' . urlencode($longUserToken);
    $ch = curl_init($meUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp3 = curl_exec($ch);
    $http3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $pages = json_decode($resp3, true);
    if (!$pages || empty($pages['data'])) {
        echo "Não foi possível listar páginas associadas à conta: " . ($pages['error']['message'] ?? $resp3);
        exit;
    }

    // Escolher a primeira página como default (ou você pode buscar uma específica)
    $page = $pages['data'][0];
    $pageAccessToken = $page['access_token'] ?? null;
    $pageId = $page['id'] ?? null;

    if (!$pageAccessToken || !$pageId) {
        echo "Não conseguimos obter Page Access Token / Page ID. Verifique permissões do App.";
        exit;
    }

    // 4) Obter instagram_business_account do Page
    $pageInfoUrl = 'https://graph.facebook.com/v17.0/' . $pageId . '?fields=instagram_business_account&access_token=' . urlencode($pageAccessToken);
    $ch = curl_init($pageInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp4 = curl_exec($ch);
    curl_close($ch);

    $pageInfo = json_decode($resp4, true);
    $igAccountId = $pageInfo['instagram_business_account']['id'] ?? null;

    if (!$igAccountId) {
        echo "Página não possui conta Instagram Business conectada. Conecte o Instagram à página e tente novamente.";
        exit;
    }

    // 5) Salvar no banco: page token e instagram page id
    $stmt = $pdo->prepare("UPDATE redes_sociais_config SET access_token = ?, page_id = ?, updated_at = CURRENT_TIMESTAMP WHERE rede = 'instagram'");
    $stmt->execute([$pageAccessToken, $igAccountId]);

    // Salvar também no facebook config (opcional)
    $stmt = $pdo->prepare("UPDATE redes_sociais_config SET access_token = ?, page_id = ?, updated_at = CURRENT_TIMESTAMP WHERE rede = 'facebook'");
    $stmt->execute([$pageAccessToken, $pageId]);

    echo "Autenticação concluída com sucesso! Você pode voltar ao Admin (feche esta janela).";

} catch (Exception $e) {
    echo "Erro na callback OAuth: " . $e->getMessage();
    exit;
}
