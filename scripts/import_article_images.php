#!/usr/bin/env php
<?php
/**
 * Importa imagens de artigos a partir de uma pasta de staging.
 *
 * Os arquivos devem seguir o padrao: img_conteudo_<id>_.(png|jpg|jpeg|webp)
 * O script otimiza (1200x630, jpg, <=500KB) e grava em uploads/ usando os
 * nomes que o banco ja referencia (imagem_1x1 e, se ausente, imagem_principal),
 * sem alterar o banco.
 *
 * Uso:
 *   php scripts/import_article_images.php                       # dry-run
 *   php scripts/import_article_images.php --apply
 *   php scripts/import_article_images.php --apply --src=assets/imgs/faltantes
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/image_optimizer.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute via CLI.\n");
    exit(1);
}

$apply = false;
$srcDir = __DIR__ . '/../assets/imgs/faltantes';
$maxKB = 500;
$quality = 85;
$width = 1200;
$height = 630;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') $apply = true;
    elseif (strpos($arg, '--src=') === 0) $srcDir = rtrim(substr($arg, 6), '/');
    elseif (strpos($arg, '--maxkb=') === 0) { $v = substr($arg, 8); if (ctype_digit($v)) $maxKB = (int)$v; }
    elseif (strpos($arg, '--quality=') === 0) { $v = substr($arg, 10); if (ctype_digit($v)) $quality = (int)$v; }
    elseif (strpos($arg, '--width=') === 0) { $v = substr($arg, 8); if (ctype_digit($v)) $width = (int)$v; }
    elseif (strpos($arg, '--height=') === 0) { $v = substr($arg, 9); if (ctype_digit($v)) $height = (int)$v; }
}

function logLine(string $m): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL);
}

if (!is_dir($srcDir)) {
    logLine("Pasta de origem nao encontrada: {$srcDir}");
    exit(1);
}

// Mapear arquivos img_conteudo_<id>_.ext
$files = [];
foreach (scandir($srcDir) as $f) {
    if ($f === '.' || $f === '..') continue;
    if (preg_match('/^img_conteudo_(\d+)_\.(png|jpe?g|webp)$/i', $f, $m)) {
        $files[(int)$m[1]] = $srcDir . '/' . $f;
    }
}

if (!$files) {
    logLine("Nenhum arquivo img_conteudo_<id>_.ext encontrado em {$srcDir}");
    exit(1);
}

ksort($files);
logLine(($apply ? 'APLICANDO' : 'DRY-RUN') . ' — ' . count($files) . ' arquivo(s) — ' . $srcDir);

$ok = 0;
$fail = 0;

foreach ($files as $id => $src) {
    $stmt = $pdo->prepare("SELECT id, imagem_1x1, imagem_principal FROM artigos WHERE id = ?");
    $stmt->execute([$id]);
    $art = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$art) {
        logLine("FALHA #{$id}: artigo nao encontrado.");
        $fail++;
        continue;
    }

    // Destino 1: imagem_1x1 (nome ja referenciado no banco, se existir)
    $dest1 = trim((string)$art['imagem_1x1']);
    if ($dest1 === '') {
        $dest1 = 'img_conteudo_' . $id . '.jpg';
    }
    $dest1Path = UPLOAD_DIR . $dest1;

    // Destino 2: imagem_principal (se estiver definida e ausente no disco)
    $dest2 = '';
    $p = trim((string)$art['imagem_principal']);
    if ($p !== '' && !uploadFileExists($p)) {
        $dest2 = $p;
    }

    if (!$apply) {
        logLine("planejado #{$id}: {$src} -> uploads/{$dest1}" . ($dest2 !== '' ? " + uploads/{$dest2}" : ''));
        continue;
    }

    $res = otimizarImagemParaRedesSociais($src, $dest1Path, $width, $height, $maxKB, $quality);
    if (empty($res['success'])) {
        logLine("FALHA #{$id}: " . ($res['message'] ?? 'erro ao otimizar'));
        $fail++;
        continue;
    }

    // Copia tambem para o nome da imagem_principal ausente
    if ($dest2 !== '') {
        @copy($dest1Path, UPLOAD_DIR . $dest2);
    }

    $exists = uploadFileExists($dest1);
    $sizeKB = $exists ? (int)round(filesize(UPLOAD_DIR . $dest1) / 1024) : 0;
    logLine("OK #{$id}: uploads/{$dest1} ({$sizeKB}KB)" . ($dest2 !== '' ? " + uploads/{$dest2}" : '') . ($exists ? '' : ' [ATENCAO: arquivo nao confirmado]'));

    if ($exists) $ok++; else $fail++;
}

logLine("Concluido: {$ok} importada(s), {$fail} falha(s).");
exit($fail > 0 && $ok === 0 ? 1 : 0);
