<?php
/**
 * Audita referencias de midia em projetos e valida existencia fisica em uploads.
 *
 * Uso:
 *   php scripts/audit_project_media.php
 *   php scripts/audit_project_media.php --only-missing
 *   php scripts/audit_project_media.php --json
 *   php scripts/audit_project_media.php --limit=50
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

$onlyMissing = false;
$asJson = false;
$limit = 0;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--only-missing') {
        $onlyMissing = true;
        continue;
    }
    if ($arg === '--json') {
        $asJson = true;
        continue;
    }
    if (strpos($arg, '--limit=') === 0) {
        $value = substr($arg, 8);
        if (ctype_digit($value)) {
            $limit = (int)$value;
        }
        continue;
    }
}

function parseGalleryRefs($raw): array {
    if (is_array($raw)) {
        return $raw;
    }

    if ($raw === null) {
        return [];
    }

    $text = trim((string)$raw);
    if ($text === '') {
        return [];
    }

    $decoded = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    // Fallback para formatos antigos tipo "a.jpg,b.jpg"
    $parts = preg_split('/[,;]+/', $text) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }

    return $out;
}

$sql = "SELECT id, titulo, imagem_principal, imagens_galeria FROM projetos ORDER BY id ASC";
if ($limit > 0) {
    $sql .= " LIMIT " . (int)$limit;
}

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$report = [];
$summary = [
    'total_projects' => 0,
    'projects_with_any_missing_media' => 0,
    'principal_ok' => 0,
    'principal_missing' => 0,
    'gallery_ok' => 0,
    'gallery_missing' => 0,
    'projects_without_any_media_ref' => 0,
];

foreach ($rows as $row) {
    $summary['total_projects']++;

    $principalRef = normalizeUploadFilename($row['imagem_principal'] ?? null);
    $principalExists = false;
    if ($principalRef !== null && !preg_match('~^https?://~i', $principalRef)) {
        $principalExists = uploadFileExists($principalRef);
        if ($principalExists) {
            $summary['principal_ok']++;
        } else {
            $summary['principal_missing']++;
        }
    }

    $galleryRefsRaw = parseGalleryRefs($row['imagens_galeria'] ?? null);
    $galleryOk = [];
    $galleryMissing = [];

    foreach ($galleryRefsRaw as $galleryRef) {
        $normalized = normalizeUploadFilename((string)$galleryRef);
        if ($normalized === null) {
            continue;
        }

        if (preg_match('~^https?://~i', $normalized)) {
            $galleryOk[] = $normalized;
            $summary['gallery_ok']++;
            continue;
        }

        if (uploadFileExists($normalized)) {
            $galleryOk[] = $normalized;
            $summary['gallery_ok']++;
        } else {
            $galleryMissing[] = $normalized;
            $summary['gallery_missing']++;
        }
    }

    $hasMissing = (!$principalExists && $principalRef !== null && !preg_match('~^https?://~i', $principalRef))
        || count($galleryMissing) > 0;

    $hasAnyRef = ($principalRef !== null) || count($galleryRefsRaw) > 0;
    if (!$hasAnyRef) {
        $summary['projects_without_any_media_ref']++;
    }

    if ($hasMissing) {
        $summary['projects_with_any_missing_media']++;
    }

    $item = [
        'id' => (int)$row['id'],
        'titulo' => (string)$row['titulo'],
        'principal' => [
            'ref' => $principalRef,
            'exists' => $principalExists,
        ],
        'gallery' => [
            'ok' => $galleryOk,
            'missing' => $galleryMissing,
        ],
        'has_missing' => $hasMissing,
    ];

    if (!$onlyMissing || $hasMissing) {
        $report[] = $item;
    }
}

if ($asJson) {
    echo json_encode([
        'generated_at' => date('c'),
        'summary' => $summary,
        'projects' => $report,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($summary['projects_with_any_missing_media'] > 0 ? 2 : 0);
}

echo "== AUDITORIA DE MIDIA DE PROJETOS ==\n";
echo "Gerado em: " . date('Y-m-d H:i:s') . "\n\n";

echo "Resumo:\n";
echo "- Total de projetos: " . $summary['total_projects'] . "\n";
echo "- Projetos com alguma midia faltando: " . $summary['projects_with_any_missing_media'] . "\n";
echo "- Principal OK: " . $summary['principal_ok'] . "\n";
echo "- Principal faltando: " . $summary['principal_missing'] . "\n";
echo "- Galeria OK: " . $summary['gallery_ok'] . "\n";
echo "- Galeria faltando: " . $summary['gallery_missing'] . "\n";
echo "- Projetos sem nenhuma referencia de midia: " . $summary['projects_without_any_media_ref'] . "\n\n";

echo "Detalhes" . ($onlyMissing ? " (somente com falta)" : "") . ":\n";
echo str_pad('ID', 6) . str_pad('PRINCIPAL', 12) . str_pad('GAL_OK', 8) . str_pad('GAL_MISS', 10) . "TITULO\n";
echo str_repeat('-', 80) . "\n";

foreach ($report as $item) {
    $principalState = 'N/A';
    if (!empty($item['principal']['ref'])) {
        $principalState = $item['principal']['exists'] ? 'OK' : 'MISSING';
    }

    echo str_pad((string)$item['id'], 6)
        . str_pad($principalState, 12)
        . str_pad((string)count($item['gallery']['ok']), 8)
        . str_pad((string)count($item['gallery']['missing']), 10)
        . $item['titulo']
        . "\n";

    if (!empty($item['gallery']['missing'])) {
        foreach ($item['gallery']['missing'] as $missingRef) {
            echo "      - galeria faltando: " . $missingRef . "\n";
        }
    }

    if (!empty($item['principal']['ref']) && !$item['principal']['exists']) {
        echo "      - principal faltando: " . $item['principal']['ref'] . "\n";
    }
}

exit($summary['projects_with_any_missing_media'] > 0 ? 2 : 0);
