<?php
/**
 * WASHIVIANA PORTFOLIO - Logout
 */
$forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['SERVER_PORT'] ?? '') === '443') ||
    ($forwardedProto === 'https');
if (session_status() === PHP_SESSION_NONE) {
    // Evita colisão com outros apps PHP no mesmo domínio (ex.: WordPress)
    session_name('washiviana_sess');

    // Garantir persistência da sessão mesmo quando o save_path padrão não é gravável
    $sessionSavePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'washiviana_sessions';
    if (!is_dir($sessionSavePath)) {
        @mkdir($sessionSavePath, 0700, true);
    }
    if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
        session_save_path($sessionSavePath);
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Limpar sessão
$_SESSION = [];

// Destruir cookie de sessão
if (isset($_COOKIE[session_name()])) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 3600,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'] ?? $isHttps,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

// Destruir sessão
session_destroy();

// Redirecionar para login
header('Location: index.php');
exit;

