<?php
/**
 * API simples para registrar acessos ao site (usado por beacon JS)
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

function isSameOriginRequest(string $baseUrl): bool {
    $baseHost = parse_url($baseUrl, PHP_URL_HOST);
    if (!$baseHost) return false;

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        $originHost = parse_url($origin, PHP_URL_HOST);
        return $originHost && strtolower($originHost) === strtolower($baseHost);
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer) {
        $refHost = parse_url($referer, PHP_URL_HOST);
        return $refHost && strtolower($refHost) === strtolower($baseHost);
    }

    return true;
}

if (!isSameOriginRequest(BASE_URL)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Origem não permitida.']);
    exit;
}

// Ler payload (aceita JSON ou form)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$path = trim((string)($data['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/')));
$ua = trim((string)($data['ua'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''));
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($path === '') $path = '/';
if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
    $path = parse_url($path, PHP_URL_PATH) ?: '/';
}
$path = substr($path, 0, 500);
$ua = substr($ua, 0, 500);
$ip = $ip ? substr($ip, 0, 45) : null;

try {
    // Rate limit simples: evita duplicar acessos do mesmo IP/página em 60s
    if ($ip) {
        $stmt = $pdo->prepare("SELECT created_at FROM site_accesses WHERE ip = ? AND path = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$ip, $path]);
        $lastAccess = $stmt->fetchColumn();
        if ($lastAccess) {
            $lastTs = strtotime($lastAccess);
            if ($lastTs !== false && (time() - $lastTs) < 60) {
                echo json_encode(['success' => true, 'skipped' => true]);
                exit;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO site_accesses (path, user_agent, ip, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$path, $ua, $ip]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Metrics insert error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Não foi possível registrar acesso.']);
}
