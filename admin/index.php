<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Login
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

// Gerar token CSRF para o login
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Diagnóstico (apenas headers): facilita validar deploy/cookies no DevTools
if (!headers_sent()) {
    header('X-Washiviana-Build: 2025-12-12');
    header('X-Washiviana-Session-Name: ' . session_name());
    header('X-Washiviana-Session-SavePath: ' . session_save_path());
}

// Definir BASE_URL dinâmica
$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostNoPort = preg_replace('/:\\d+$/', '', $host);
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = rtrim($scriptDir, '/');
$basePath = preg_replace('#/admin$#', '', $scriptDir);
if ($basePath === '/' || $basePath === null) {
    $basePath = '';
}
if (!defined('BASE_URL')) define('BASE_URL', $protocol . '://' . $hostNoPort . $basePath);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>Washington Viana</h1>
                <p>Painel Administrativo</p>
            </div>
            
            <form id="loginForm" class="login-form">
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                
                <div id="messageDiv" class="message" style="display: none;"></div>
                
                <button type="submit" class="btn btn-primary btn-block" id="btnLogin">
                    <span class="btn-text">Entrar</span>
                    <span class="btn-loader" style="display: none;">Entrando...</span>
                </button>
            </form>
            
            <div class="login-footer">
                <p>
                    <small>Solicite credenciais ao administrador do site.</small>
                </p>
            </div>
        </div>
    </div>

    <script>
        const loginForm = document.getElementById('loginForm');
        const messageDiv = document.getElementById('messageDiv');
        const btnLogin = document.getElementById('btnLogin');
        const btnText = btnLogin.querySelector('.btn-text');
        const btnLoader = btnLogin.querySelector('.btn-loader');

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Desabilitar botão
            btnLogin.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline';
            
            // Coletar dados
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('email', document.getElementById('email').value);
            formData.append('senha', document.getElementById('senha').value);
            formData.append('csrf_token', document.getElementById('csrf_token').value);
            
            try {
                const response = await fetch('../api/auth.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Login realizado com sucesso! Redirecionando...', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    showMessage(data.message || 'Erro ao fazer login.', 'error');
                    btnLogin.disabled = false;
                    btnText.style.display = 'inline';
                    btnLoader.style.display = 'none';
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('Erro ao conectar com servidor.', 'error');
                btnLogin.disabled = false;
                btnText.style.display = 'inline';
                btnLoader.style.display = 'none';
            }
        });

        function showMessage(message, type) {
            messageDiv.textContent = message;
            messageDiv.className = `message message-${type}`;
            messageDiv.style.display = 'block';
        }
    </script>
</body>
</html>

