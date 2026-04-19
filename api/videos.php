<?php
/**
 * API para geração de vídeos (FFmpeg) a partir de variantes/galeria
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Aumentar tempo de execução para operações de vídeo/imagem
set_time_limit(300);

// CSRF para POST (pular se for CLI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && php_sapi_name() !== 'cli') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
}

switch ($action) {
    case 'enqueue_from_variant':
        $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
        if (!$id) jsonResponse(['success' => false, 'message' => 'id não fornecido']);

        $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE id = ?");
        $stmt->execute([$id]);
        $v = $stmt->fetch();
        if (!$v) jsonResponse(['success' => false, 'message' => 'Variante não encontrada']);

        // Params possíveis: duration_per_image (seg), music_file (nome em uploads), resolution
        $videoProvider = strtolower(trim((string)(getConfig('llm_video_provider') ?: 'gemini')));
        $videoModel = trim((string)(getConfig('llm_video_model') ?: ''));

        // Engine em modo automático: tenta provedor/modelo quando aplicável e cai para ffmpeg com segurança.
        $params = [
            'duration_per_image' => intval($_POST['duration_per_image'] ?? 3),
            'music_file' => $_POST['music_file'] ?? null,
            'resolution' => $_POST['resolution'] ?? '1080x1920',
            'video_provider' => $videoProvider,
            'video_model' => $videoModel,
            'video_engine' => 'auto'
        ];

        // Verificar se existem imagens associadas à variante; se não, tentar gerar automaticamente
        $stmt = $pdo->prepare('SELECT image_1x1, image_9x16 FROM artigos_social_variants WHERE id = ?');
        $stmt->execute([$id]);
        $v = $stmt->fetch();
        $hasImages = !empty($v['image_9x16']) || !empty($v['image_1x1']);

        if (!$hasImages) {
            // Tentar encontrar o binário do PHP de forma robusta
            $php = 'php';
            if (defined('PHP_BINARY') && PHP_BINARY && (strpos(PHP_BINARY, 'fpm') === false) && (strpos(PHP_BINARY, 'cgi') === false)) {
                $php = PHP_BINARY;
            } elseif (@is_executable('/usr/bin/php')) {
                $php = '/usr/bin/php';
            }

            $script = __DIR__ . '/../scripts/generate_images_for_variant.php';
            $cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($id) . ' 2>&1';
            exec($cmd, $out, $rc);
            $outStr = implode("\n", $out);
            if ($rc !== 0) {
                // Registrar e informar usuário
                $logFile = __DIR__ . '/../.api_video_error.log';
                $phpBinary = PHP_BINARY;
                file_put_contents($logFile, "FAILED: {$cmd}\nRC: {$rc}\nPHP_BINARY: {$phpBinary}\nOUTPUT:\n{$outStr}\n", FILE_APPEND);
                error_log("generate_images_for_variant failed for variant {$id}: rc={$rc} output=" . substr($outStr,0,2000));
                jsonResponse(['success' => false, 'message' => 'Não foi possível gerar imagens automaticamente para esta variante. Tente gerar manualmente ou tente novamente mais tarde.'], 500);
            }
            // Recarregar variante
            $stmt = $pdo->prepare('SELECT image_1x1, image_9x16 FROM artigos_social_variants WHERE id = ?');
            $stmt->execute([$id]);
            $v = $stmt->fetch();
            $hasImages = !empty($v['image_9x16']) || !empty($v['image_1x1']);
            if (!$hasImages) {
                error_log("generate_images_for_variant did not populate images for variant {$id}. Output: " . substr($outStr,0,2000));
                jsonResponse(['success' => false, 'message' => 'Não foi possível gerar imagens para esta variante.'], 500);
            }
        }

        // Inserir job após garantir imagens
        $stmt = $pdo->prepare("INSERT INTO video_jobs (variant_id, params, created_at, updated_at) VALUES (?, ?::jsonb, now(), now()) RETURNING id");
        $stmt->execute([$id, json_encode($params)]);
        $jobId = $stmt->fetchColumn();

        jsonResponse([
            'success' => true,
            'job_id' => intval($jobId),
            'provider' => $videoProvider,
            'model' => $videoModel,
            'engine' => 'auto',
            'message' => 'Job enfileirado'
        ]);
        break;

    case 'job_status':
        $jobId = !empty($_GET['job_id']) ? intval($_GET['job_id']) : null;
        if (!$jobId) jsonResponse(['success' => false, 'message' => 'job_id não fornecido']);

        $stmt = $pdo->prepare("SELECT id, variant_id, status, attempts, last_error, output_file, params, created_at, updated_at FROM video_jobs WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job) jsonResponse(['success' => false, 'message' => 'Job não encontrado']);

        if ($job['output_file']) $job['output_url'] = UPLOAD_URL . $job['output_file'];

        $paramsDecoded = json_decode($job['params'] ?? '{}', true) ?: [];
        $job['provider'] = $paramsDecoded['video_provider'] ?? null;
        $job['model'] = $paramsDecoded['video_model'] ?? null;
        $job['engine'] = $paramsDecoded['video_engine'] ?? 'ffmpeg';
        jsonResponse(['success' => true, 'job' => $job]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Ação não especificada']);
}

