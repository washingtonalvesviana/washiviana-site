<?php
/**
 * WASHIVIANA - LinkedIn OAuth 2.0 Callback
 * 
 * Este arquivo recebe o código de autorização do LinkedIn
 * e troca por um Access Token.
 * 
 * Fluxo OAuth 2.0:
 * 1. Usuário autoriza no LinkedIn
 * 2. LinkedIn redireciona para este callback com ?code=xxx
 * 3. Trocamos o code por access_token
 * 4. Salvamos o token e redirecionamos para admin
 */
require_once __DIR__ . '/../config.php';

// Verificar se há erro
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Erro desconhecido';
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode($errorDescription));
    exit;
}

// Verificar se recebemos o código
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if (empty($code)) {
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode('Código de autorização não recebido'));
    exit;
}

// Validar state (CSRF do OAuth) quando o cookie estiver presente
$cookieState = isset($_COOKIE['linkedin_oauth_state']) ? (string)$_COOKIE['linkedin_oauth_state'] : '';
if ($cookieState !== '' && !hash_equals($cookieState, (string)$state)) {
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode('State OAuth inválido. Tente novamente.'));
    exit;
}

// Buscar credenciais do banco
$stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
$stmt->execute();
$config = $stmt->fetch();

if (!$config || empty($config['client_id']) || empty($config['client_secret'])) {
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode('Credenciais do LinkedIn não configuradas'));
    exit;
}

// Trocar código por Access Token
$tokenUrl = 'https://www.linkedin.com/oauth/v2/accessToken';
$redirectUri = BASE_URL . '/api/oauth/linkedin-callback.php';

$postData = http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirectUri,
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret']
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $tokenUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode('Erro cURL: ' . $error));
    exit;
}

$tokenData = json_decode($response, true);

if ($httpCode !== 200 || !isset($tokenData['access_token'])) {
    $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Falha ao obter Access Token';
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode($errorMsg));
    exit;
}

$accessToken = $tokenData['access_token'];
$expiresIn = $tokenData['expires_in'] ?? 5184000; // 60 dias padrão

// Calcular data de expiração
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// Obter Person URN (OpenID Connect userinfo, com fallback legado em /v2/me)
$personUrn = trim((string)($config['person_urn'] ?? ''));
$detectedUrn = '';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.linkedin.com/v2/userinfo',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'X-Restli-Protocol-Version: 2.0.0'
    ]
]);
$userInfoResponse = curl_exec($ch);
$userInfoCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userInfoCode === 200) {
    $userInfoData = json_decode($userInfoResponse, true);
    if (!empty($userInfoData['sub'])) {
        $detectedUrn = 'urn:li:person:' . $userInfoData['sub'];
    }
}

if ($detectedUrn === '') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/v2/me',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'X-Restli-Protocol-Version: 2.0.0'
        ]
    ]);
    $meResponse = curl_exec($ch);
    $meCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($meCode === 200) {
        $meData = json_decode($meResponse, true);
        if (!empty($meData['id'])) {
            $detectedUrn = 'urn:li:person:' . $meData['id'];
        }
    }
}

// So sobrescreve o URN quando um valor valido foi detectado;
// caso contrario, preserva o person_urn ja salvo no banco.
if ($detectedUrn !== '') {
    $personUrn = $detectedUrn;
}

// Salvar no banco
try {
    $stmt = $pdo->prepare("
        UPDATE redes_sociais_config SET 
            access_token = ?,
            person_urn = ?,
            token_expires_at = ?,
            ativo = true,
            updated_at = CURRENT_TIMESTAMP
        WHERE rede = 'linkedin'
    ");
    $stmt->execute([$accessToken, $personUrn, $expiresAt]);
    
    // Redirecionar com sucesso
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?success=' . urlencode('LinkedIn conectado com sucesso! Token válido até ' . $expiresAt));
    
} catch (Exception $e) {
    header('Location: ' . BASE_URL . '/admin/redes-sociais.php?error=' . urlencode('Erro ao salvar: ' . $e->getMessage()));
}

