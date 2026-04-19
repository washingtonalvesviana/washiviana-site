<?php
/**
 * Testar publicação simples usando dados salvos em redes_sociais_config
 */
require_once __DIR__ . '/../api/config.php';

try {
    $stmt = $pdo->prepare("SELECT access_token, person_urn, publish_target, organization_urn FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfg) {
        echo "LinkedIn não configurado no banco\n";
        exit(1);
    }

    $token = $cfg['access_token'];
    $author = $cfg['publish_target'] === 'organization' && !empty($cfg['organization_urn']) ? $cfg['organization_urn'] : $cfg['person_urn'];

    echo "Usando author: $author\n";

    $payload = [
        'author' => $author,
        'commentary' => 'Teste de publicação automática (não importante) - ' . date('Y-m-d H:i:s'),
        'visibility' => 'PUBLIC',
        'lifecycleState' => 'PUBLISHED',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED'
        ]
    ];

    $ch = curl_init('https://api.linkedin.com/rest/posts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'LinkedIn-Version: ' . LINKEDIN_API_VERSION,
            'X-Restli-Protocol-Version: 2.0.0',
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    curl_close($ch);

    echo "HTTP: $httpCode\n";
    echo "Headers:\n" . $headers . "\n";
    echo "Body:\n" . $body . "\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
