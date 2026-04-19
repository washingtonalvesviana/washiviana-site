<?php
/**
 * Worker para processar video_jobs
 * - Busca jobs com status 'pending'
 * - Marca como 'processing', gera vídeo com FFmpeg
 * - Em sucesso: atualiza status='success' e salva output_file
 * - Em falha: incrementa attempts e marca 'failed' após max attempts
 */
require_once __DIR__ . '/../api/config.php';

// Limites e configurações
$MAX_ATTEMPTS = 3;
$SLEEP_BETWEEN = 2; // segundos (backoff simples)

function logmsg($m) {
    echo date('[Y-m-d H:i:s] ') . $m . PHP_EOL;
}

function httpJsonRequest($url, $payload = null, $headers = [], $method = 'POST', $timeout = 120) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers
    ];

    $m = strtoupper($method);
    if ($m === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
    } elseif ($m !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = $m;
        if ($payload !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'status' => 0, 'error' => $err, 'data' => null, 'raw' => null];
    }

    $decoded = json_decode((string)$response, true);
    if ($code < 200 || $code >= 300) {
        $message = $decoded['error']['message'] ?? $decoded['message'] ?? ('HTTP ' . $code);
        return ['success' => false, 'status' => $code, 'error' => $message, 'data' => $decoded, 'raw' => $response];
    }

    return ['success' => true, 'status' => $code, 'error' => null, 'data' => $decoded, 'raw' => $response];
}

function downloadBinaryFile($url, $destPath, $headers = [], $timeout = 300) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)$timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $binary = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    if ($httpCode < 200 || $httpCode >= 300 || !$binary) {
        return ['success' => false, 'error' => 'HTTP ' . $httpCode . ' ao baixar vídeo'];
    }

    file_put_contents($destPath, $binary);
    if (!file_exists($destPath) || filesize($destPath) < 1024) {
        return ['success' => false, 'error' => 'Arquivo de vídeo inválido após download'];
    }
    return ['success' => true];
}

function extractGeminiVideoUrlFromOperation($opData) {
    $candidates = [
        $opData['response']['generatedVideos'][0]['video']['uri'] ?? null,
        $opData['response']['generated_videos'][0]['video']['uri'] ?? null,
        $opData['response']['videos'][0]['uri'] ?? null,
        $opData['response']['video']['uri'] ?? null,
        $opData['generatedVideos'][0]['video']['uri'] ?? null,
        $opData['videos'][0]['uri'] ?? null,
    ];
    foreach ($candidates as $v) {
        if (is_string($v) && $v !== '') return $v;
    }
    return null;
}

function collectUrlsRecursive($value, &$urls) {
    if (is_string($value)) {
        if (preg_match('#^https?://#i', $value)) {
            $urls[] = $value;
        }
        return;
    }
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            if (is_string($v) && preg_match('#^https?://#i', $v)) {
                $urls[] = $v;
            }
            collectUrlsRecursive($v, $urls);
        }
    }
}

function extractOpenAICompatibleVideoUrl($data) {
    $candidates = [
        $data['video_url'] ?? null,
        $data['output'][0]['video_url'] ?? null,
        $data['data'][0]['url'] ?? null,
        $data['data'][0]['video_url'] ?? null,
        $data['result']['url'] ?? null,
        $data['response']['url'] ?? null,
    ];

    foreach ($candidates as $v) {
        if (is_string($v) && $v !== '') {
            return $v;
        }
    }

    $urls = [];
    collectUrlsRecursive($data, $urls);
    $urls = array_values(array_unique(array_filter($urls)));

    foreach ($urls as $u) {
        if (stripos($u, '.mp4') !== false || stripos($u, 'video') !== false) {
            return $u;
        }
    }

    return $urls[0] ?? null;
}

function tryGenerateVideoWithOpenAICompatible($baseUrl, $apiKey, $model, $prompt, $resolution, $extraHeaders = []) {
    if ($apiKey === '' || $model === '') {
        return ['success' => false, 'error' => 'API key/model ausentes'];
    }

    $size = '1080x1920';
    if ($resolution === '1920x1080') $size = '1920x1080';
    if ($resolution === '1080x1080') $size = '1024x1024';

    $headers = array_merge([
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ], $extraHeaders);

    $base = rtrim($baseUrl, '/');
    $attempts = [
        [
            'url' => $base . '/videos/generations',
            'payload' => ['model' => $model, 'prompt' => $prompt, 'size' => $size],
            'poll' => $base . '/videos/'
        ],
        [
            'url' => $base . '/video/generations',
            'payload' => ['model' => $model, 'prompt' => $prompt, 'size' => $size],
            'poll' => $base . '/video/'
        ],
        [
            'url' => $base . '/responses',
            'payload' => [
                'model' => $model,
                'input' => $prompt,
                'modalities' => ['video', 'text']
            ],
            'poll' => $base . '/responses/'
        ],
    ];

    $lastErr = 'Falha ao iniciar geração de vídeo';
    foreach ($attempts as $attempt) {
        $start = httpJsonRequest($attempt['url'], $attempt['payload'], $headers, 'POST', 180);
        if (!$start['success']) {
            $lastErr = $start['error'] ?? $lastErr;
            continue;
        }

        $data = $start['data'] ?? [];
        $videoUrl = extractOpenAICompatibleVideoUrl($data);
        if ($videoUrl) {
            $filename = 'video_job_provider_' . time() . '_' . uniqid() . '.mp4';
            $dest = UPLOAD_DIR . $filename;
            $dl = downloadBinaryFile($videoUrl, $dest, $headers, 300);
            if ($dl['success']) {
                return ['success' => true, 'filename' => $filename, 'source_url' => $videoUrl];
            }
            $lastErr = 'Falha ao baixar vídeo: ' . ($dl['error'] ?? 'erro');
        }

        $status = strtolower((string)($data['status'] ?? ''));
        $id = trim((string)($data['id'] ?? ''));
        if ($id === '' || !in_array($status, ['queued', 'in_progress', 'processing', 'pending', 'running', ''], true)) {
            $lastErr = 'Resposta sem vídeo e sem id para polling';
            continue;
        }

        $pollUrl = $attempt['poll'] . rawurlencode($id);
        $maxPoll = 48;
        $pollData = null;
        for ($i = 0; $i < $maxPoll; $i++) {
            $poll = httpJsonRequest($pollUrl, null, $headers, 'GET', 90);
            if (!$poll['success']) {
                $lastErr = 'Polling falhou: ' . ($poll['error'] ?? 'erro');
                sleep(2);
                continue;
            }
            $pollData = $poll['data'] ?? [];
            $videoUrl = extractOpenAICompatibleVideoUrl($pollData);
            if ($videoUrl) {
                $filename = 'video_job_provider_' . time() . '_' . uniqid() . '.mp4';
                $dest = UPLOAD_DIR . $filename;
                $dl = downloadBinaryFile($videoUrl, $dest, $headers, 300);
                if ($dl['success']) {
                    return ['success' => true, 'filename' => $filename, 'source_url' => $videoUrl];
                }
                $lastErr = 'Falha ao baixar vídeo (poll): ' . ($dl['error'] ?? 'erro');
                break;
            }

            $st = strtolower((string)($pollData['status'] ?? ''));
            if (in_array($st, ['failed', 'cancelled', 'canceled', 'error'], true)) {
                $errMsg = $pollData['error']['message'] ?? $pollData['message'] ?? 'erro na operação';
                $lastErr = 'Operação falhou: ' . $errMsg;
                break;
            }
            if (in_array($st, ['completed', 'succeeded', 'done'], true)) {
                $lastErr = 'Operação concluída sem URL de vídeo';
                break;
            }

            sleep(5);
        }
    }

    return ['success' => false, 'error' => $lastErr];
}

function tryGenerateVideoWithGemini($apiKey, $model, $prompt, $resolution, $tmpDir) {
    if ($apiKey === '' || $model === '') {
        return ['success' => false, 'error' => 'API key/model Gemini ausentes'];
    }

    $aspect = '9:16';
    if ($resolution === '1920x1080') $aspect = '16:9';
    if ($resolution === '1080x1080') $aspect = '1:1';

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateVideos?key=' . urlencode($apiKey);
    $payloads = [
        [
            'prompt' => ['text' => $prompt],
            'config' => ['aspectRatio' => $aspect]
        ],
        [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['aspectRatio' => $aspect]
        ],
    ];

    $opName = null;
    $lastErr = 'Falha desconhecida ao iniciar operação';
    foreach ($payloads as $payload) {
        $resp = httpJsonRequest($url, $payload, ['Content-Type: application/json'], 'POST', 120);
        if ($resp['success']) {
            $opName = $resp['data']['name'] ?? null;
            if ($opName) break;
            $lastErr = 'Resposta sem operation name';
            continue;
        }
        $lastErr = $resp['error'] ?? $lastErr;
    }

    if (!$opName) {
        return ['success' => false, 'error' => 'Gemini não iniciou geração de vídeo: ' . $lastErr];
    }

    $opUrl = 'https://generativelanguage.googleapis.com/v1beta/' . ltrim($opName, '/') . '?key=' . urlencode($apiKey);
    $maxPoll = 60;
    $sleepSec = 5;
    $opData = null;

    for ($i = 0; $i < $maxPoll; $i++) {
        $poll = httpJsonRequest($opUrl, null, ['Content-Type: application/json'], 'GET', 60);
        if (!$poll['success']) {
            $lastErr = 'Falha no polling Gemini: ' . ($poll['error'] ?? 'erro');
            sleep(2);
            continue;
        }

        $opData = $poll['data'] ?? [];
        if (!empty($opData['error'])) {
            $msg = $opData['error']['message'] ?? 'erro na operação';
            return ['success' => false, 'error' => 'Gemini operation error: ' . $msg];
        }

        if (!empty($opData['done'])) {
            break;
        }

        sleep($sleepSec);
    }

    if (empty($opData) || empty($opData['done'])) {
        return ['success' => false, 'error' => 'Timeout aguardando vídeo do Gemini'];
    }

    $videoUrl = extractGeminiVideoUrlFromOperation($opData);
    if (!$videoUrl) {
        return ['success' => false, 'error' => 'Gemini concluiu sem URL de vídeo'];
    }

    $filename = 'video_job_provider_' . time() . '_' . uniqid() . '.mp4';
    $dest = UPLOAD_DIR . $filename;
    $headers = [];

    if (strpos($videoUrl, 'key=') === false && strpos($videoUrl, 'googleapis.com') !== false) {
        $videoUrl .= (strpos($videoUrl, '?') === false ? '?' : '&') . 'key=' . urlencode($apiKey);
    }

    $dl = downloadBinaryFile($videoUrl, $dest, $headers, 300);
    if (!$dl['success']) {
        return ['success' => false, 'error' => 'Falha ao baixar vídeo Gemini: ' . $dl['error']];
    }

    return ['success' => true, 'filename' => $filename, 'source_url' => $videoUrl];
}

logmsg('Iniciando video worker...');

// Verificar pré-requisitos
$rc = null; exec('command -v ffmpeg 2>/dev/null', $out_ff, $rc);
if ($rc !== 0) {
    logmsg('ERRO: ffmpeg não encontrado no PATH. Instale ffmpeg (ex.: sudo apt install ffmpeg).');
    exit(1);
}

try {
    // Buscar próximo job pending (lock via update)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id FROM video_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->commit();
        logmsg('Nenhum job pendente no momento.');
        exit(0);
    }

    $jobId = intval($row['id']);
    // Marcar como processing
    $stmt = $pdo->prepare("UPDATE video_jobs SET status = 'processing', attempts = attempts + 1, updated_at = now() WHERE id = ?");
    $stmt->execute([$jobId]);
    $pdo->commit();

    // Recarregar job
    $stmt = $pdo->prepare("SELECT * FROM video_jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    logmsg("Processando job #{$jobId} (variante={$job['variant_id']}) attempt={$job['attempts']}");

    // Parse params
    $params = json_decode($job['params'], true) ?? [];
    $duration = max(1, intval($params['duration_per_image'] ?? 3));
    $music = $params['music_file'] ?? null;
    $resolution = $params['resolution'] ?? '1080x1920';
    $videoProvider = strtolower(trim((string)($params['video_provider'] ?? 'gemini')));
    $videoModel = trim((string)($params['video_model'] ?? ''));
    $engineRequested = trim((string)($params['video_engine'] ?? 'auto'));
    $engineUsed = 'ffmpeg';
    $engineNote = null;
    $providerVideoFilename = null;

    if (in_array($engineRequested, ['auto', 'provider'], true) && $videoModel !== '') {
        $stmt = $pdo->prepare('SELECT caption, titulo FROM artigos_social_variants WHERE id = ?');
        $stmt->execute([$job['variant_id']]);
        $vp = $stmt->fetch();
        $videoPrompt = trim((string)($vp['caption'] ?? ''));
        if ($videoPrompt === '') {
            $videoPrompt = trim((string)($vp['titulo'] ?? ''));
        }
        if ($videoPrompt === '') {
            $videoPrompt = 'Vídeo curto vertical para redes sociais com estilo profissional';
        }

        if ($videoProvider === 'gemini') {
            $geminiKey = trim((string)getConfig('gemini_api_key'));
            $providerResult = tryGenerateVideoWithGemini($geminiKey, $videoModel, $videoPrompt, $resolution, sys_get_temp_dir());
            if ($providerResult['success']) {
                $providerVideoFilename = $providerResult['filename'];
                $engineUsed = 'provider';
                $engineNote = 'provider_video_generation_success';
                logmsg("Vídeo gerado via provider Gemini/model {$videoModel}: {$providerVideoFilename}");
            } else {
                $engineNote = 'provider_video_failed_fallback_ffmpeg: ' . ($providerResult['error'] ?? 'erro desconhecido');
                logmsg('Aviso: geração por provider falhou, fallback para FFmpeg. ' . ($providerResult['error'] ?? 'erro desconhecido'));
            }
        } elseif ($videoProvider === 'openai') {
            $openaiKey = trim((string)getConfig('openai_api_key'));
            $providerResult = tryGenerateVideoWithOpenAICompatible('https://api.openai.com/v1', $openaiKey, $videoModel, $videoPrompt, $resolution);
            if ($providerResult['success']) {
                $providerVideoFilename = $providerResult['filename'];
                $engineUsed = 'provider';
                $engineNote = 'provider_video_generation_success';
                logmsg("Vídeo gerado via provider OpenAI/model {$videoModel}: {$providerVideoFilename}");
            } else {
                $engineNote = 'provider_video_failed_fallback_ffmpeg: ' . ($providerResult['error'] ?? 'erro desconhecido');
                logmsg('Aviso: geração por provider OpenAI falhou, fallback para FFmpeg. ' . ($providerResult['error'] ?? 'erro desconhecido'));
            }
        } elseif ($videoProvider === 'openrouter') {
            $openrouterKey = trim((string)getConfig('openrouter_api_key'));
            $providerResult = tryGenerateVideoWithOpenAICompatible(
                'https://openrouter.ai/api/v1',
                $openrouterKey,
                $videoModel,
                $videoPrompt,
                $resolution,
                [
                    'HTTP-Referer: https://washiviana.com',
                    'X-Title: Washiviana Admin'
                ]
            );
            if ($providerResult['success']) {
                $providerVideoFilename = $providerResult['filename'];
                $engineUsed = 'provider';
                $engineNote = 'provider_video_generation_success';
                logmsg("Vídeo gerado via provider OpenRouter/model {$videoModel}: {$providerVideoFilename}");
            } else {
                $engineNote = 'provider_video_failed_fallback_ffmpeg: ' . ($providerResult['error'] ?? 'erro desconhecido');
                logmsg('Aviso: geração por provider OpenRouter falhou, fallback para FFmpeg. ' . ($providerResult['error'] ?? 'erro desconhecido'));
            }
        } else {
            $engineNote = 'provider_not_supported_yet_fallback_ffmpeg';
            logmsg("Info: provider de vídeo {$videoProvider} ainda não suportado no worker; usando FFmpeg.");
        }
    }

    $parts = [];
    $concatFile = null;
    $tmpDir = null;

    if ($providerVideoFilename) {
        $outFinal = UPLOAD_DIR . $providerVideoFilename;
    } else {
        // Buscar imagens para a variante
        $stmt = $pdo->prepare('SELECT image_9x16, image_1x1 FROM artigos_social_variants WHERE id = ?');
        $stmt->execute([$job['variant_id']]);
        $v = $stmt->fetch();
        $images = [];
        if (!empty($v['image_9x16'])) $images[] = UPLOAD_DIR . $v['image_9x16'];
        if (!empty($v['image_1x1'])) $images[] = UPLOAD_DIR . $v['image_1x1'];

        // fallback to article main image
        if (empty($images)) {
            $stmt = $pdo->prepare('SELECT imagem_principal FROM artigos WHERE id = (SELECT artigo_id FROM artigos_social_variants WHERE id = ?)');
            $stmt->execute([$job['variant_id']]);
            $ar = $stmt->fetch();
            if (!empty($ar['imagem_principal'])) $images[] = UPLOAD_DIR . $ar['imagem_principal'];
        }

        if (empty($images)) {
            throw new Exception('Nenhuma imagem disponível para gerar vídeo');
        }

        // Preparar tmp dir
        $tmpDir = sys_get_temp_dir() . '/video_job_' . $jobId . '_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        foreach ($images as $idx => $img) {
            if (!file_exists($img)) {
                logmsg("Aviso: imagem não encontrada: {$img}");
                continue;
            }
            $out = $tmpDir . '/part_' . $idx . '.mp4';
            // Gerar segmento com fade in/out, scale/pad para manter 9:16
            $cmd = "ffmpeg -y -loop 1 -i " . escapeshellarg($img) . " -vf \"scale=1080:1920:force_original_aspect_ratio=decrease,pad=1080:1920:(ow-iw)/2:(oh-ih)/2:black,fade=t=in:st=0:d=0.5,fade=t=out:st=" . ($duration - 0.6) . ":d=0.5\" -c:v libx264 -t " . $duration . " -pix_fmt yuv420p -preset fast " . escapeshellarg($out) . " 2>&1";
            logmsg("Executando: " . $cmd);
            exec($cmd, $o, $rc);
            if ($rc !== 0 || !file_exists($out)) {
                logmsg("Erro ao gerar segmento para imagem {$img}. rc={$rc}");
                continue;
            }
            $parts[] = $out;
        }

        if (empty($parts)) {
            throw new Exception('Falha ao gerar segmentos de vídeo (todos falharam)');
        }

        // Criar arquivo de concat
        $concatFile = $tmpDir . '/files.txt';
        $f = fopen($concatFile, 'w');
        foreach ($parts as $p) fwrite($f, "file '" . addslashes($p) . "'\n");
        fclose($f);

        $filename = 'video_job_' . $jobId . '_' . time() . '.mp4';
        $outFinal = UPLOAD_DIR . $filename;

        // Concatenar com stream copy quando possível
        $cmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($concatFile) . " -c:v libx264 -pix_fmt yuv420p " . escapeshellarg($outFinal) . " 2>&1";
        logmsg("Concatenando: " . $cmd);
        exec($cmd, $o, $rc);
        if ($rc !== 0 || !file_exists($outFinal)) {
            throw new Exception('Erro ao concatenar segmentos (verificar ffmpeg).');
        }
    }

    // Se houver música, mixar audio
    if ($music) {
        $musicPath = UPLOAD_DIR . $music;
        if (file_exists($musicPath)) {
            $tmpAudioDir = $tmpDir;
            if (empty($tmpAudioDir) || !is_dir($tmpAudioDir)) {
                $tmpAudioDir = sys_get_temp_dir() . '/video_job_audio_' . $jobId . '_' . uniqid();
                @mkdir($tmpAudioDir, 0755, true);
            }
            $tmpOut = $tmpAudioDir . '/final_with_audio_' . uniqid() . '.mp4';
            // Ajustar duração da música para não exceder vídeo (usando -shortest)
            $cmd = "ffmpeg -y -i " . escapeshellarg($outFinal) . " -i " . escapeshellarg($musicPath) . " -c:v copy -c:a aac -b:a 192k -shortest " . escapeshellarg($tmpOut) . " 2>&1";
            logmsg("Adicionando música: " . $cmd);
            exec($cmd, $o, $rc);
            if ($rc === 0 && file_exists($tmpOut)) {
                // mover para final
                rename($tmpOut, $outFinal);
                @rmdir($tmpAudioDir);
            } else {
                logmsg('Aviso: falha ao adicionar música ao vídeo');
            }
        } else {
            logmsg('Aviso: music_file não encontrada: ' . $musicPath);
        }
    }

    // Atualizar DB: status success
    $stmt = $pdo->prepare("UPDATE video_jobs SET status = 'success', output_file = ?, last_error = NULL, updated_at = now() WHERE id = ?");
    $stmt->execute([basename($outFinal), $jobId]);

    // Atualizar variante com referência ao vídeo e metadados de engine/provedor/modelo
    $videoMeta = [
        'generated_at' => date('c'),
        'job_id' => (int)$jobId,
        'provider_requested' => $videoProvider,
        'model_requested' => $videoModel,
        'engine_requested' => $engineRequested,
        'engine_used' => $engineUsed,
        'resolution' => $resolution,
        'duration_per_image' => $duration
    ];
    if ($engineNote) {
        $videoMeta['note'] = $engineNote;
    }

    $stmt = $pdo->prepare("UPDATE artigos_social_variants SET video_file = ?, video_meta = ?::jsonb, updated_at = now() WHERE id = ?");
    $stmt->execute([basename($outFinal), json_encode($videoMeta), $job['variant_id']]);

    logmsg("Job {$jobId} concluído com sucesso: " . basename($outFinal));

    // Cleanup temp
    if (!empty($parts)) {
        foreach ($parts as $p) @unlink($p);
    }
    if (!empty($concatFile)) @unlink($concatFile);
    if (!empty($tmpDir)) @rmdir($tmpDir);

} catch (Exception $e) {
    // Update attempts and status
    logmsg('Erro processing job: ' . $e->getMessage());
    $err = $e->getMessage();
    $attempts = intval($job['attempts'] ?? 1);
    if ($attempts >= $MAX_ATTEMPTS) {
        $stmt = $pdo->prepare("UPDATE video_jobs SET status = 'failed', last_error = ?, updated_at = now() WHERE id = ?");
        $stmt->execute([$err, $jobId]);
        logmsg("Job {$jobId} marcado como failed após {$attempts} tentativas");

        // Notificar por email / Sentry se configurado
        try {
            $stmt = $pdo->prepare("SELECT * FROM video_jobs WHERE id = ?");
            $stmt->execute([$jobId]);
            $jobRow = $stmt->fetch();
            if ($jobRow) {
                notifyJobFailure($jobRow, $err);
            }
        } catch (Exception $e) {
            error_log('Erro ao notificar falha de job: ' . $e->getMessage());
        }

    } else {
        // reset to pending for retry (simple backoff via sleep)
        sleep($SLEEP_BETWEEN * $attempts);
        $stmt = $pdo->prepare("UPDATE video_jobs SET status = 'pending', last_error = ?, updated_at = now() WHERE id = ?");
        $stmt->execute([$err, $jobId]);
        logmsg("Job {$jobId} agendado para retry (attempts={$attempts})");
    }
    // cleanup tmp if present
    if (!empty($tmpDir) && is_dir($tmpDir)) {
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }
}
