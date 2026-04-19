<?php
/**
 * Return fresh CSRF token for authenticated admin session
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

// refresh/generate token
$token = generateCsrfToken();
error_log("csrf.php: issued new csrf token (prefix=" . substr($token,0,6) . "...) for session=" . session_id());
jsonResponse(['success' => true, 'csrf_token' => $token]);
