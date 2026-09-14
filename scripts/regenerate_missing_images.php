#!/usr/bin/env php
<?php
/**
 * Regenera imagens faltantes de artigos usando IA (provider de imagem configurado).
 *
 * Uso:
 *   php scripts/regenerate_missing_images.php                 # dry-run (nao gera nada)
 *   php scripts/regenerate_missing_images.php --apply         # gera e grava
 *   php scripts/regenerate_missing_images.php --apply --id=4,5,6
 *   php scripts/regenerate_missing_images.php --apply --limit=3
 *
 * Preenche/imagem_1x1 com uma nova imagem baseada em prompt_imagem (ou titulo+resumo).
 * Se imagem_principal estiver apontando para arquivo ausente, atualiza para a nova imagem.
 */

declare(strict_types=1);

define('WASHIVIANA_SKIP_DISPATCH', true);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/gemini.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute via CLI.\n");
    exit(1);
}

$apply = false;
$limit = 0;
$ids = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (strpos($arg, '--limit=') === 0) {
        $v = substr($arg, 8);
        if (ctype_digit($v)) $limit = (int)$v;
    } elseif (strpos($arg, '--id=') === 0) {
        foreach (explode(',', substr($arg, 5)) as $part) {
            $part = trim($part);
            if (ctype_digit($part)) $ids[] = (int)$part;
        }
    }
}

function logLine(string $m): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL);
}

/**
 * Monta um prompt de imagem a partir do conteudo do artigo.
 */
function buildImagePrompt(array $a): string {
    $prompt = trim((string)($a['prompt_imagem'] ?? ''));
    if ($prompt !== '') {
        return $prompt;
    }

    $titulo = trim((string)($a['titulo'] ?? ''));
    $resumo = trim((string)($a['resumo'] ?? ''));
    $base = $titulo;
    if ($resumo !== '') {
        $base .= '. ' . mb_substr($resumo, 0, 400);
    }

    return 'Imagem editorial de capa, estilo tecnológico moderno, alta qualidade, sem texto, '
        . 'representando o tema: ' . $base;
}

// Selecionar candidatos: imagem_1x1 preenchida mas arquivo ausente
$sql = "SELECT id, titulo, resumo, prompt_imagem, imagem_1x1, imagem_principal, imagem_9x16
        FROM artigos WHERE imagem_1x1 IS NOT NULL AND imagem_1x1 <> '' ORDER BY id";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$targets = [];
foreach ($rows as $r) {
    if ($ids && !in_array((int)$r['id'], $ids, true)) continue;
    if (uploadFileExists($r['imagem_1x1'])) continue; // arquivo existe, nada a fazer
    $targets[] = $r;
}

if ($limit > 0) {
    $targets = array_slice($targets, 0, $limit);
}

logLine(($apply ? 'APLICANDO' : 'DRY-RUN') . ' — ' . count($targets) . ' artigo(s) com imagem ausente.');

if (!$targets) {
    exit(0);
}

$ok = 0;
$fail = 0;

foreach ($targets as $r) {
    $id = (int)$r['id'];
    $prompt = buildImagePrompt($r);
    $promptPreview = mb_substr(str_replace(["\n", "\r"], ' ', $prompt), 0, 90);

    if (!$apply) {
        logLine("planejado #{$id} -> imagem_1x1={$r['imagem_1x1']}");
        logLine("           prompt: {$promptPreview}...");
        continue;
    }

    logLine("gerando #{$id} ...");
    $_POST['prompt'] = $prompt;
    $GLOBALS['WASHIVIANA_CAPTURE_JSON'] = true;

    try {
        gerarImagem();
        $fail++;
        logLine("FALHA #{$id}: sem resposta do gerador.");
        continue;
    } catch (WashivianaCapturedResponse $e) {
        $res = $e->payload;
    } catch (Throwable $e) {
        $fail++;
        logLine("FALHA #{$id}: excecao: " . $e->getMessage());
        continue;
    }

    if (empty($res['success']) || empty($res['filename'])) {
        $fail++;
        logLine("FALHA #{$id}: " . (string)($res['message'] ?? 'erro desconhecido'));
        continue;
    }

    $novo = (string)$res['filename'];

    try {
        $sets = "imagem_1x1 = ?";
        $params = [$novo];

        // Se imagem_principal estava apontando para arquivo ausente, reaproveita a nova imagem
        if (!empty($r['imagem_principal']) && !uploadFileExists($r['imagem_principal'])) {
            $sets .= ", imagem_principal = ?";
            $params[] = $novo;
        }

        $params[] = $id;
        $up = $pdo->prepare("UPDATE artigos SET {$sets} WHERE id = ?");
        $up->execute($params);

        // limpar arquivo antigo ausente nao faz sentido (nao existe)
        $ok++;
        logLine("OK    #{$id} -> {$novo}");
    } catch (Throwable $e) {
        $fail++;
        logLine("ERRO  #{$id} ao gravar no banco: " . $e->getMessage());
    }

    sleep(1); // gentileza com a API
}

logLine("Concluido: {$ok} gerada(s), {$fail} falha(s).");
exit($fail > 0 && $ok === 0 ? 1 : 0);
