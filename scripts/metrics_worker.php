#!/usr/bin/env php
<?php
/**
 * Worker para coletar metricas de publicacoes sociais.
 *
 * Uso:
 *   php scripts/metrics_worker.php
 *   php scripts/metrics_worker.php --rede=linkedin
 *   php scripts/metrics_worker.php --limit=20
 *
 * Recomendado: rodar via cron/systemd timer (ex.: a cada 6 horas).
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/metrics_lib.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

function metricsLog(string $msg): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);
}

$onlyRede = '';
$limit = 0;

foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--rede=') === 0) {
        $onlyRede = strtolower(trim(substr($arg, 7)));
    } elseif (strpos($arg, '--limit=') === 0) {
        $v = substr($arg, 8);
        if (ctype_digit($v)) $limit = (int)$v;
    }
}

$sql = "SELECT id, artigo_id, rede, post_id
        FROM publicacoes_redes
        WHERE status = 'publicado' AND post_id IS NOT NULL AND post_id <> ''";
$params = [];

if ($onlyRede !== '') {
    $sql .= " AND rede = ?";
    $params[] = $onlyRede;
}

$sql .= " ORDER BY publicado_em DESC NULLS LAST, id DESC";
if ($limit > 0) {
    $sql .= " LIMIT " . $limit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$publicacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$publicacoes) {
    metricsLog('Nenhuma publicacao elegivel encontrada.');
    exit(0);
}

metricsLog('Coletando metricas de ' . count($publicacoes) . ' publicacao(oes)...');

$ok = 0;
$falhas = 0;

foreach ($publicacoes as $pub) {
    $res = collectMetricsForPublication($pub);

    if (!empty($res['success'])) {
        try {
            storeMetricsSnapshot((int)$pub['id'], $res['metrics']);
            $ok++;
            metricsLog("OK  #{$pub['id']} {$pub['rede']} ({$pub['post_id']})");
        } catch (Exception $e) {
            $falhas++;
            metricsLog("ERRO #{$pub['id']} {$pub['rede']}: falha ao gravar snapshot - " . $e->getMessage());
        }
    } else {
        $falhas++;
        metricsLog("FALHA #{$pub['id']} {$pub['rede']}: " . ($res['error'] ?? 'erro desconhecido'));
    }
}

metricsLog("Concluido: {$ok} sucesso(s), {$falhas} falha(s).");
exit(($ok === 0 && $falhas > 0) ? 1 : 0);
