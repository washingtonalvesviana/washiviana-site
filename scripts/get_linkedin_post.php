<?php
require_once __DIR__ . '/../api/config.php';
$postUrn = $argv[1] ?? null;
if (!$postUrn) { echo "Usage: php get_linkedin_post.php <post_urn>\n"; exit(1); }
$stmt = $pdo->prepare("SELECT access_token FROM redes_sociais_config WHERE rede = 'linkedin'");
$stmt->execute();
$cfg = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cfg || empty($cfg['access_token'])) { echo "No token\n"; exit(1); }
$token = $cfg['access_token'];

$encoded = urlencode($postUrn);
// Try REST posts first
$urls = [
    'https://api.linkedin.com/rest/posts/' . $encoded,
    'https://api.linkedin.com/v2/ugcPosts/' . $encoded
];
foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Restli-Protocol-Version: 2.0.0',
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION
        ],
        CURLOPT_TIMEOUT => 10
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "GET $url -> HTTP $http\n";
    echo $resp . "\n\n";
}
