<?php
// Teste simples do pipeline de vídeo: enfileira job, roda worker e verifica saída
require_once __DIR__ . '/../api/config.php';

echo "Rodando testes de vídeo...\n";

// Criar artigo e variante (dependendo da existência dos fixtures já existentes)
$pdo->beginTransaction();
$stmt = $pdo->prepare("INSERT INTO artigos (titulo, slug, resumo, conteudo, status_publicacao, created_at) VALUES (?, ?, ?, ?, 'rascunho', NOW()) RETURNING id");
$stmt->execute(['Teste Vídeo', 'teste-video-' . time(), 'Resumo', 'Conteúdo']);
$artigoId = $stmt->fetchColumn();

$stmt = $pdo->prepare("INSERT INTO artigos_social_variants (artigo_id, rede, titulo, caption, hashtags, media_type, image_1x1, image_9x16, status, created_at) VALUES (?, 'instagram', ?, ?, ?, 'imagem', '', '', 'rascunho', NOW()) RETURNING id");
$stmt->execute([$artigoId, 'Teste', 'Legenda de teste', ''] );
$variantId = $stmt->fetchColumn();
$pdo->commit();

echo "Artigo criado: {$artigoId}\nVariante criada: {$variantId}\n";

// Anexar imagem de teste à variante/artigo (usar uploads existentes)
$sampleArticleImage = 'galeria_693d8dc619b0a.jpg';
$sampleVariantImage = 'ai_9x16_6934fd9348220.png';
$pdo->prepare("UPDATE artigos SET imagem_principal = ? WHERE id = ?")->execute([$sampleArticleImage, $artigoId]);
$pdo->prepare("UPDATE artigos_social_variants SET image_9x16 = ? WHERE id = ?")->execute([$sampleVariantImage, $variantId]);

// Limpar jobs anteriores (teste isolado)
$pdo->exec("DELETE FROM video_jobs");

// Enfileirar diretamente (evitar dependência de sessão/auth no teste)
$stmt = $pdo->prepare("INSERT INTO video_jobs (variant_id, params, created_at, updated_at) VALUES (?, ?::jsonb, now(), now()) RETURNING id");
$params = json_encode(['duration_per_image' => 2, 'music_file' => null, 'resolution' => '1080x1920']);
$stmt->execute([$variantId, $params]);
$jobId = $stmt->fetchColumn();
echo "Job enfileirado (direct DB): {$jobId}\n";

// Rodar worker manualmente (checar ffmpeg primeiro)
$ffmpegPath = trim(shell_exec('command -v ffmpeg 2>/dev/null'));
if (empty($ffmpegPath)) {
    echo "ffmpeg não encontrado no PATH. Pulando execução do worker (teste ignorado em ambiente sem ffmpeg).\n";
    echo "Job permanece em pending.\n";
    exit(0);
}

exec('php ' . __DIR__ . '/../scripts/video_worker.php 2>&1', $out, $rc);
echo implode("\n", $out) . "\n";

// Verificar status do job
$stmt = $pdo->prepare('SELECT status, output_file, last_error FROM video_jobs WHERE id = ?');
$stmt->execute([$jobId]);
$j = $stmt->fetch();
if (!$j) { echo "Job não encontrado\n"; exit(1); }

if ($j['status'] === 'success') {
    echo "Job succeeded. Output: " . ($j['output_file'] ?? '') . "\n";
    if (!file_exists(__DIR__ . '/../uploads/' . $j['output_file'])) {
        echo "Arquivo de saída não encontrado no uploads/\n";
        exit(1);
    }
    echo "Teste concluído com sucesso.\n";
    exit(0);
} else {
    echo "Job finalizado com status: {$j['status']} erro: {$j['last_error']}\n";
    exit(1);
}
