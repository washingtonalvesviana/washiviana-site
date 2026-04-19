<?php
/**
 * Script para deletar posts do LinkedIn e atualizar registros no banco
 * Usage: php delete_linkedin_posts.php urn:li:share:7419452043775332352 urn:li:share:7419459170032209920
 */
require_once __DIR__ . '/../api/config.php';

$urns = array_slice($argv, 1);
if (empty($urns)) {
    echo "Usage: php delete_linkedin_posts.php <post_urn> [post_urn ...]\n";
    exit(1);
}

$stmt = $pdo->query("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$cfg = $stmt->fetch(PDO::FETCH_ASSOC);
$token = $cfg['access_token'] ?? null;
if (empty($token)) {
    echo "LinkedIn token not configured\n";
    exit(1);
}

foreach ($urns as $urn) {
    echo "Deleting $urn...\n";
    $encoded = urlencode($urn);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.linkedin.com/rest/posts/' . $encoded,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        echo "  cURL error: $curlErr\n";
        continue;
    }

    if ($httpCode === 204 || $httpCode === 200) {
        echo "  Deleted on LinkedIn (HTTP $httpCode)\n";
        // Atualizar publicacoes_redes
        try {
            $stmt = $pdo->prepare("UPDATE publicacoes_redes SET status = 'deletado', erro_mensagem = NULL WHERE post_id = ?");
            $stmt->execute([$urn]);
        } catch (Exception $e) {
            echo "  Warning updating publicacoes_redes: " . $e->getMessage() . "\n";
        }
        try {
            $stmt = $pdo->prepare("UPDATE artigos SET linkedin_post_id = NULL WHERE linkedin_post_id = ?");
            $stmt->execute([$urn]);
        } catch (Exception $e) {
            echo "  Warning updating artigos: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  Failed to delete (HTTP $httpCode). Response: $response\n";
    }
}
