<?php
/**
 * CLI runner: Radar coleta (cron)
 *
 * Uso:
 *   php scripts/radar_run.php --topic=ID
 *   php scripts/radar_run.php --all
 *   php scripts/radar_run.php --all --ideas=8
 */

require_once __DIR__ . '/../api/radar_lib.php';

function arg(string $name): ?string {
    global $argv;
    foreach ($argv as $a) {
        if (str_starts_with($a, $name . '=')) return substr($a, strlen($name) + 1);
    }
    return null;
}

$topicId = arg('--topic');
$all = in_array('--all', $argv, true);
$ideasN = arg('--ideas');
$ideasN = $ideasN !== null ? max(3, min(15, (int)$ideasN)) : null;

if (!$all && !$topicId) {
    fwrite(STDERR, "Use --all ou --topic=ID\n");
    exit(2);
}

global $pdo;

// Resolve topics
$topics = [];
if ($all) {
    $topics = $pdo->query("SELECT id FROM radar_topics WHERE ativo = true ORDER BY id ASC")->fetchAll();
} else {
    $topics = [['id' => (int)$topicId]];
}

foreach ($topics as $t) {
    $tid = (int)$t['id'];
    echo "== Topic {$tid} ==\n";
    $stmt = $pdo->prepare("SELECT source_id FROM radar_topic_sources ts JOIN radar_sources s ON s.id = ts.source_id WHERE ts.topic_id = ? AND s.ativo = true");
    $stmt->execute([$tid]);
    $sourceIds = array_map(fn($r) => (int)$r['source_id'], $stmt->fetchAll());
    if (!$sourceIds) {
        echo "  (no active sources)\n";
        continue;
    }

    $savedTotal = 0;
    foreach ($sourceIds as $sid) {
        $r = radarCollectSource($sid, [$tid]);
        if ($r['success']) {
            $savedTotal += (int)($r['saved'] ?? 0);
            echo "  source {$sid}: saved={$r['saved']}\n";
        } else {
            echo "  source {$sid}: ERROR {$r['error']}\n";
        }
    }
    echo "  saved_total={$savedTotal}\n";

    if ($ideasN !== null) {
        echo "  generating ideas...\n";
        $r = radarGenerateIdeasForTopic($tid, 20, $ideasN);
        if ($r['success']) {
            echo "  ideas_saved={$r['saved']} model={$r['model']}\n";
        } else {
            echo "  ideas ERROR {$r['error']}\n";
        }
    }
}

