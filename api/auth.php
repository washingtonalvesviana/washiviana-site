<?php
/**
 * WASHIVIANA PORTFOLIO - Authentication API
 * Gerenciamento de autenticação de usuários
 */

require_once __DIR__ . '/config.php';

// Diagnóstico (apenas headers): facilita validar deploy/cookies no DevTools
if (!headers_sent()) {
    header('X-Washiviana-Build: 2025-12-12');
    header('X-Washiviana-Session-Name: ' . session_name());
    header('X-Washiviana-Session-SavePath: ' . session_save_path());
}

header('Content-Type: application/json; charset=utf-8');

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        login();
        break;
    
    case 'logout':
        logout();
        break;
    
    case 'check':
        checkAuth();
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
}

/**
 * Login de usuário
 */
function login() {
    global $pdo;
    
    $email = sanitize($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Validar CSRF
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
    
    // Rate limiting simples por IP (5 tentativas em 10 minutos)
    $now = time();
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    $_SESSION['login_attempts'][$ip] = $_SESSION['login_attempts'][$ip] ?? [];
    // Filtrar tentativas antigas
    $_SESSION['login_attempts'][$ip] = array_filter($_SESSION['login_attempts'][$ip], function ($ts) use ($now) {
        return ($now - $ts) <= 600; // 10 minutos
    });
    if (count($_SESSION['login_attempts'][$ip]) >= 5) {
        jsonResponse(['success' => false, 'message' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'], 429);
    }

    // Validar campos
    if (empty($email) || empty($senha)) {
        jsonResponse(['success' => false, 'message' => 'Preencha todos os campos.'], 400);
    }
    
    if (!validateEmail($email)) {
        jsonResponse(['success' => false, 'message' => 'Email inválido.'], 400);
    }
    
    try {
        // Buscar usuário
        $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $_SESSION['login_attempts'][$ip][] = $now;
            jsonResponse(['success' => false, 'message' => 'Credenciais inválidas.'], 401);
        }
        
        // Verificar senha
        if (!password_verify($senha, $user['senha'])) {
            $_SESSION['login_attempts'][$ip][] = $now;
            jsonResponse(['success' => false, 'message' => 'Credenciais inválidas.'], 401);
        }
        
        // Resetar tentativas após sucesso
        $_SESSION['login_attempts'][$ip] = [];

        // Regenerar ID de sessão para evitar fixation
        session_regenerate_id(true);

        // Criar sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Gerar token CSRF
        generateCsrfToken();
        
        jsonResponse([
            'success' => true,
            'message' => 'Login realizado com sucesso!',
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'email' => $user['email']
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Login Error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao realizar login.'], 500);
    }
}

/**
 * Logout de usuário
 */
function logout() {
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
                'secure' => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }
    
    // Destruir sessão
    session_destroy();
    
    jsonResponse(['success' => true, 'message' => 'Logout realizado com sucesso!']);
}

/**
 * Verificar autenticação
 */
function checkAuth() {
    if (isAuthenticated()) {
        // Atualizar last_activity para manter sessão viva quando checada por AJAX
        $_SESSION['last_activity'] = time();
        jsonResponse([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'nome' => $_SESSION['user_nome'],
                'email' => $_SESSION['user_email']
            ]
        ]);
    } else {
        jsonResponse(['success' => true, 'authenticated' => false]);
    }
}

