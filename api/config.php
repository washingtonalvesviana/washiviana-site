<?php
/**
 * WASHIVIANA PORTFOLIO - Configuration File
 * Conexão com MySQL e configurações globais
 */

require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/i18n_ui.php';

// Configurar cookie de sessão com flags de segurança antes de iniciar
$forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['SERVER_PORT'] ?? '') === '443') ||
    ($forwardedProto === 'https');
if (session_status() === PHP_SESSION_NONE) {
    // Evita colisão com outros apps PHP no mesmo domínio (ex.: WordPress)
    session_name('washiviana_sess');

    // Garantir persistência da sessão mesmo quando o save_path padrão não é gravável
    // (ocorre em alguns setups de PHP-FPM/Nginx). Usar diretório fora do webroot (ex.: /tmp).
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

// ============================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ============================================
// Permite sobrescrever credenciais via api/config.local.php (não versionado)
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    require_once $localConfigPath;
}

// Use DB_DRIVER=mysql para este site, ou DB_DRIVER=pgsql para PostgreSQL
if (!defined('DB_DRIVER')) {
    define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}
if (!defined('DB_PORT')) {
    // se DB_DRIVER já estiver definido podemos usar porta padrão para postgres/mysql
    $defaultPort = (getenv('DB_PORT') ?: (getenv('DB_DRIVER') === 'pgsql' ? '5432' : '3306'));
    define('DB_PORT', $defaultPort);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'washiviana_portfolio');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'seu_usuario');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'sua_senha');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');
}

// ============================================
// CONFIGURAÇÕES GLOBAIS
// ============================================
// Detectar automaticamente a URL base (inclui subpasta) e evitar host hardcoded
$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Remover porta do host (ex.: localhost:8080) para evitar cookies/URLs inválidas
$hostNoPort = preg_replace('/:\\d+$/', '', $host);

// Ex.: /washiviana/api/config.php -> /washiviana/api -> /washiviana
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$scriptDir = str_replace('\\', '/', $scriptDir);
$scriptDir = rtrim($scriptDir, '/');
if ($scriptDir === '') {
    $scriptDir = '/';
}
// Remover sufixos conhecidos
$basePath = preg_replace('#/(api|admin)$#', '', $scriptDir);
if ($basePath === '/' || $basePath === null) {
    $basePath = '';
}

if (!defined('BASE_URL')) define('BASE_URL', $protocol . '://' . $hostNoPort . $basePath);

// Idioma atual (pt/en/es)
if (!defined('CURRENT_LANG')) {
    define('CURRENT_LANG', currentLang());
}

if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', __DIR__ . '/../uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', BASE_URL . '/uploads/');
if (!defined('MAX_IMAGE_FILE_SIZE')) define('MAX_IMAGE_FILE_SIZE', 5242880); // 5MB
if (!defined('MAX_MEDIA_FILE_SIZE')) define('MAX_MEDIA_FILE_SIZE', 2147483648); // 2GB
if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'webm']);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Versão padrão da API LinkedIn (formato YYYYMM). Pode ser sobrescrita em config.local.php se necessário.
if (!defined('LINKEDIN_API_VERSION')) {
    define('LINKEDIN_API_VERSION', date('Ym'));
} // ex: 202601

// Tempo máximo de inatividade da sessão (30 minutos)
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 1800);

// ============================================
// CONEXÃO COM O BANCO DE DADOS (PDO - PostgreSQL)
// ============================================
try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (DB_DRIVER === 'pgsql') {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES 'UTF8'");
    } else {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
} catch (PDOException $e) {
    // Em produção, não exibir detalhes do erro
    error_log("Database Connection Error: " . $e->getMessage());
    die(json_encode([
        'success' => false,
        'message' => 'Erro ao conectar com o banco de dados. Por favor, tente novamente mais tarde.'
    ]));
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Buscar configuração do banco
 */
function getConfig($chave) {
    global $pdo;
    
    // Cache em sessão
    if (isset($_SESSION['config'][$chave])) {
        return $_SESSION['config'][$chave];
    }
    
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$chave]);
    $result = $stmt->fetch();
    
    if ($result) {
        $_SESSION['config'][$chave] = $result['valor'];
        return $result['valor'];
    }
    
    return null;
}

/**
 * Buscar configuração por idioma (fallback PT)
 */
function getConfigI18n(string $chave, ?string $lang = null): ?string {
    global $pdo;
    $lang = normalizeLang($lang ?: (defined('CURRENT_LANG') ? CURRENT_LANG : currentLang()));
    if ($lang === 'pt') {
        $v = getConfig($chave);
        return $v === null ? null : (string)$v;
    }

    $cacheKey = "__config_i18n_{$lang}";
    if (isset($_SESSION[$cacheKey][$chave])) {
        return $_SESSION[$cacheKey][$chave];
    }

    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_i18n WHERE chave = ? AND lang = ? LIMIT 1");
        $stmt->execute([$chave, $lang]);
        $row = $stmt->fetch();
        $val = $row['valor'] ?? null;
        if (!isset($_SESSION[$cacheKey])) $_SESSION[$cacheKey] = [];
        $_SESSION[$cacheKey][$chave] = $val;
        if ($val !== null && $val !== '') return (string)$val;
    } catch (Exception $e) {
        // ignore
    }

    $fallback = getConfig($chave);
    return $fallback === null ? null : (string)$fallback;
}

function clearConfigI18nCache(): void {
    foreach (['en', 'es'] as $lang) {
        $k = "__config_i18n_{$lang}";
        if (isset($_SESSION[$k])) unset($_SESSION[$k]);
    }
}

/**
 * Normaliza nome de arquivo em uploads para evitar caminhos quebrados.
 */
function normalizeUploadFilename(?string $filename): ?string {
    if ($filename === null) return null;
    $filename = trim((string)$filename);
    if ($filename === '') return null;
    if (preg_match('~^https?://~i', $filename)) return $filename;

    $filename = str_replace('\\', '/', $filename);
    $filename = ltrim($filename, '/');
    if (stripos($filename, 'uploads/') === 0) {
        $filename = substr($filename, 8);
    }

    if ($filename === '' || strpos($filename, '..') !== false) {
        return null;
    }

    return $filename;
}

/**
 * Retorna caminho absoluto do arquivo local em uploads.
 */
function uploadFilePath(?string $filename): ?string {
    $normalized = normalizeUploadFilename($filename);
    if ($normalized === null) return null;
    if (preg_match('~^https?://~i', $normalized)) return null;

    return rtrim(UPLOAD_DIR, '/\\') . '/' . $normalized;
}

/**
 * Verifica se o arquivo local realmente existe no diretório uploads.
 */
function uploadFileExists(?string $filename): bool {
    $path = uploadFilePath($filename);
    return $path ? is_file($path) : false;
}

/**
 * Retorna URL pública do arquivo de upload somente quando existir.
 */
function uploadFileUrl(?string $filename): ?string {
    $normalized = normalizeUploadFilename($filename);
    if ($normalized === null) return null;
    if (preg_match('~^https?://~i', $normalized)) return $normalized;
    if (!uploadFileExists($normalized)) return null;

    $segments = array_map('rawurlencode', explode('/', $normalized));
    return rtrim(UPLOAD_URL, '/') . '/' . implode('/', $segments);
}

/**
 * Enviar email simples usando mail() (fallback logs). Retorna true se enviado.
 */
function sendNotificationEmail(string $subject, string $body): bool {
    // Buscar email configurado
    $to = getConfig('notify_email');
    if (empty($to)) return false;

    $from = getConfig('site_email') ?: ('no-reply@' . preg_replace('#^https?://#', '', BASE_URL));
    $headers = "From: " . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=utf-8\r\n";

    try {
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent) {
            error_log("sendNotificationEmail: mail() falhou ao enviar para {$to}");
        }
        return $sent;
    } catch (Exception $e) {
        error_log("sendNotificationEmail Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Notificar sobre falha de job (email + Sentry se disponível)
 */
function notifyJobFailure(array $jobData, string $message): void {
    // Email
    $subject = "[Washiviana] Falha na geração de vídeo (job #" . ($jobData['id'] ?? '?') . ")";
    $body = "Falha ao processar job de vídeo:\n\n" . print_r($jobData, true) . "\n\nErro:\n" . $message;
    sendNotificationEmail($subject, $body);

    // Se o Sentry SDK estiver disponível, capture a exceção/message
    if (function_exists('\\Sentry\\captureMessage')) {
        try {
            \Sentry\captureMessage("Video job failed: " . substr($message, 0, 1024));
        } catch (Exception $e) {
            // ignore
            error_log("notifyJobFailure: Sentry capture failed: " . $e->getMessage());
        }
    }
}

/**
 * Atualizar configuração (PostgreSQL)
 */
function setConfig($chave, $valor) {
    global $pdo;

    if (DB_DRIVER === 'pgsql') {
        $stmt = $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
             ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = CURRENT_TIMESTAMP"
        );
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = CURRENT_TIMESTAMP"
        );
    }
    $stmt->execute([$chave, $valor]);
    
    // Limpar cache
    if (isset($_SESSION['config'][$chave])) {
        unset($_SESSION['config'][$chave]);
    }
    
    return true;
}

/**
 * Verificar se usuário está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Verificar autenticação (usar em páginas admin)
 */
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }

    // Encerrar sessão inativa
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/admin/index.php?timeout=1');
        exit;
    }

    // Atualizar timestamp de atividade
    $_SESSION['last_activity'] = $now;
}

/**
 * Gerar slug a partir de string
 */
function generateSlug($text) {
    // Substituir caracteres especiais
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    return empty($text) ? 'n-a' : $text;
}

/**
 * Sanitizar input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Gerar token CSRF
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Resposta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    // Limpar qualquer output anterior
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Limpar qualquer header já enviado
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/**
 * Limitar texto
 */
function limitText($text, $limit = 100) {
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return $text;
    }
    return mb_substr($text, 0, $limit, 'UTF-8') . '...';
}

/**
 * Formatar data (localizado)
 * Suporta tokens simples em estilo PHP (d, j, m, n, M, F, Y, y, H, G, h, i, s)
 * Usa IntlDateFormatter quando disponível para retornar nomes de mês localizados
 */
function formatDate($date, $format = 'd/m/Y') {
    $ts = strtotime($date);
    if (!$ts) return '';

    // Determinar locale a partir do idioma atual
    $lang = normalizeLang(defined('CURRENT_LANG') ? CURRENT_LANG : currentLang());
    $locale = $lang === 'pt' ? 'pt_BR' : ($lang === 'es' ? 'es_ES' : 'en_US');

    // Se IntlDateFormatter disponível, converter o formato PHP -> ICU e formatar
    if (class_exists('IntlDateFormatter')) {
        // Agrupar sequências de caracteres escapadas (ex.: \d\e => 'de')
        $fmt = preg_replace_callback('/(?:\\.)+/', function($m) {
            $s = $m[0];
            // remover backslashes
            $chars = preg_replace('/\\\\/', '', $s);
            // escapar apostrófes para ICU
            $chars = str_replace("'", "''", $chars);
            return "'" . $chars . "'";
        }, $format);

        // Mapear tokens PHP para ICU
        $map = [
            'd' => 'dd',
            'j' => 'd',
            'm' => 'MM',
            'n' => 'M',
            'M' => 'MMM',
            'F' => 'MMMM',
            'Y' => 'yyyy',
            'y' => 'yy',
            'H' => 'HH',
            'G' => 'H',
            'h' => 'hh',
            'i' => 'mm',
            's' => 'ss'
        ];

        // Substituir tokens simples (letras) pelo equivalente ICU
        $pattern = preg_replace_callback('/(d|j|m|n|M|F|Y|y|H|G|h|i|s)/', function($m) use($map) {
            return $map[$m[1]] ?? $m[1];
        }, $fmt);

        try {
            $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, date_default_timezone_get(), IntlDateFormatter::GREGORIAN, $pattern);
            $result = $formatter->format($ts);
            if ($result !== false) return $result;
        } catch (Throwable $e) {
            // Falhar de forma graciosa para fallback
        }
    }

    // Fallback: usar date() (pode retornar mês em inglês para M/F). Se necessário, substituir nomes de meses para o idioma atual
    $out = date($format, $ts);

    // Se estamos em pt ou es e o formato inclui mês textual, substituir nomes em inglês por traduções
    if (in_array($lang, ['pt', 'es'], true) && preg_match('/\b(F|M)\b/', $format)) {
        $months_en_full = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        $months_en_short = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

        $months_pt_full = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $months_pt_short = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

        $months_es_full = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $months_es_short = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        if ($lang === 'pt') {
            $out = str_replace($months_en_full, $months_pt_full, $out);
            $out = str_replace($months_en_short, $months_pt_short, $out);
        } else {
            $out = str_replace($months_en_full, $months_es_full, $out);
            $out = str_replace($months_en_short, $months_es_short, $out);
        }
    }

    return $out;
}

/**
 * Upload de arquivo
 */
function uploadFile($file, $prefix = 'img') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erro no upload do arquivo.'];
    }

    // Validar extensão
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
    }

    // Verificar se é imagem/vídeo e validar tamanho
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    $isVideo = in_array($extension, ['mp4', 'webm'], true);
    $maxBytes = $isImage ? MAX_IMAGE_FILE_SIZE : ($isVideo ? MAX_MEDIA_FILE_SIZE : MAX_IMAGE_FILE_SIZE);
    if ($file['size'] > $maxBytes) {
        $maxMB = (int)floor($maxBytes / (1024 * 1024));
        return ['success' => false, 'message' => "Arquivo muito grande. Máximo: {$maxMB}MB."];
    }

    // Gerar nome único (sempre .jpg para imagens otimizadas)
    $filename = $prefix . '_' . uniqid() . ($isImage ? '.jpg' : '.' . $extension);
    $destination = UPLOAD_DIR . $filename;

    // Criar diretório se não existir
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Mover arquivo
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Otimizar imagens automaticamente
        if ($isImage) {
            require_once __DIR__ . '/image_optimizer.php';
            $resultado = otimizarImagemParaRedesSociais($destination, $destination, 1200, 630, 500, 85);
            if ($resultado['success']) {
                error_log("Imagem otimizada: {$resultado['sizeKB']}KB, {$resultado['dimensions']['width']}x{$resultado['dimensions']['height']}");
            } else {
                error_log("Aviso: Não foi possível otimizar imagem: {$resultado['message']}");
            }
        }

        return [
            'success' => true,
            'filename' => $filename,
            'url' => UPLOAD_URL . $filename
        ];
    }

    return ['success' => false, 'message' => 'Erro ao salvar arquivo.'];
}

function isVideoFilename(?string $filename): bool {
    if (empty($filename)) return false;
    $ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'webm'], true);
}

/**
 * Deletar arquivo
 */
function deleteFile($filename) {
    $filepath = UPLOAD_DIR . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

