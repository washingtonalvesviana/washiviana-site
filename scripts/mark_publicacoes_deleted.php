<?php
require_once __DIR__ . '/../api/config.php';
$urns = array_slice($argv, 1);
if (empty($urns)) {
    echo "Usage: php mark_publicacoes_deleted.php <post_urn> [post_urn ...]\n";
    exit(1);
}
foreach ($urns as $urn) {
    echo "Marking $urn as deleted in DB...\n";
    try {
        $stmt = $pdo->prepare("UPDATE publicacoes_redes SET status = 'deletado', erro_mensagem = NULL WHERE post_id = ?");
        $stmt->execute([$urn]);
        echo "  Updated publicacoes_redes rows: " . $stmt->rowCount() . "\n";
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
    try {
        $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = NULL WHERE linkedin_post_id = ?");
        $stmt->execute([$urn]);
        echo "  Cleared artigos.linkedin_post_id rows: " . $stmt->rowCount() . "\n";
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
