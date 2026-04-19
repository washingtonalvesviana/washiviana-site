#!/usr/bin/env php
<?php
/**
 * Worker para publicar artigos agendados no LinkedIn
 * Uso: php scripts/worker_publish_articles.php
 * Recomendado: rodar via cron a cada 1 minuto ou systemd timer
 */
require_once __DIR__ . '/../api/config.php';

// Incluir função de upload de imagem para LinkedIn (se existir)
// ... (Keep existing uploadImagemLinkedIn function and imports) ...
require_once __DIR__ . '/../api/config.php';

// --- Helper: Upload Imagem LinkedIn (Manteve-se inalterada para garantir estabilidade) ---
if (!function_exists('uploadImagemLinkedIn')) {
    function uploadImagemLinkedIn($imagemPath, $authorUrn, $accessToken) {
        // ... (Mesma implementação anterior - omitindo para brevidade no prompt, mas o código final deve conter) ...
        // Como o replace_file_content substitui o arquivo (se eu usar corretamente), preciso GARANTIR que a função esteja lá.
        // Vou assumir que o usuário final quer o código completo.
        // REINSERINDO CÓDIGO DA FUNÇÃO uploadImagemLinkedIn:
        try {
            $imagemAbsoluta = $imagemPath;
            if (!file_exists($imagemAbsoluta)) {
                $possiveisCaminhos = [
                    __DIR__ . '/../uploads/' . basename($imagemPath),
                    __DIR__ . '/../' . $imagemPath,
                    __DIR__ . '/../' . ltrim($imagemPath, '/'),
                ];
                foreach ($possiveisCaminhos as $caminho) {
                    if (file_exists($caminho)) {
                        $imagemAbsoluta = $caminho;
                        break;
                    }
                }
            }
            if (!file_exists($imagemAbsoluta)) {
                error_log("LinkedIn Upload: Arquivo não encontrado: $imagemPath");
                return null;
            }
            $fileSize = filesize($imagemAbsoluta);
            
            // 1. Initialize
            $initPayload = ['initializeUploadRequest' => ['owner' => $authorUrn]];
            $ch = curl_init('https://api.linkedin.com/rest/images?action=initializeUpload');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($initPayload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                    'X-Restli-Protocol-Version: 2.0.0',
                    'LinkedIn-Version: ' . LINKEDIN_API_VERSION
                ]
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) return null;
            $initData = json_decode($response, true);
            $uploadUrl = $initData['value']['uploadUrl'] ?? null;
            $imageUrn = $initData['value']['image'] ?? null;
            if (!$uploadUrl || !$imageUrn) return null;

            // 2. Upload
            $ch = curl_init($uploadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => file_get_contents($imagemAbsoluta),
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/octet-stream']
            ]);
            $uploadResponse = curl_exec($ch);
            $uploadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ($uploadHttpCode >= 200 && $uploadHttpCode < 300) ? $imageUrn : null;
        } catch (Exception $e) {
            error_log("LinkedIn Upload Exception: " . $e->getMessage());
            return null;
        }
    }
}

// --- Handler Functions ---

function publishToLinkedin($artigo, $config, $dryRun = false) {
    global $pdo;

    if (empty($config['access_token'])) return ['success' => false, 'message' => 'Token ausente'];
    
    $author = ($config['publish_target'] ?? 'person') === 'organization' && !empty($config['organization_urn']) 
        ? $config['organization_urn'] 
        : $config['person_urn'];
        
    if (empty($author)) return ['success' => false, 'message' => 'URN do autor não configurado'];

    $siteUrl = getConfig('site_url') ?: BASE_URL;
    $siteBase = filter_var($siteUrl, FILTER_VALIDATE_URL) ? rtrim($siteUrl, '/') : 'https://washiviana.com';
    $url = $siteBase . '/pt/artigo/' . urlencode(trim($artigo['slug']));
    
    $titulo = trim($artigo['titulo']);
    $resumo = trim(strip_tags($artigo['resumo'] ?: $artigo['conteudo']));
    if (mb_strlen($resumo) > 250) $resumo = mb_substr($resumo, 0, 247) . '...';
    
    $commentary = "$titulo\n\n$resumo\n\n$url";
    
    // Image handling
    $image = $artigo['imagem_1x1'] ?: ($artigo['imagem_principal'] ?? null);
    $imageUrn = null;
    if ($image && !$dryRun) {
        $imageUrn = uploadImagemLinkedIn($image, $author, $config['access_token']);
    }

    $payload = [
        'author' => $author,
        'lifecycleState' => 'PUBLISHED',
        'visibility' => 'PUBLIC',
        'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
        'commentary' => $commentary,
        'content' => [
            'article' => [
                'source' => $url,
                'title' => $titulo,
                'description' => $resumo
            ]
        ]
    ];
    if ($imageUrn) $payload['content']['article']['thumbnail'] = $imageUrn;

    if ($dryRun) {
        echo "[LinkedIn DryRun] Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
        return ['success' => true, 'id' => 'DRY_RUN_ID', 'url' => null];
    }

    // Call API
    $ch = curl_init('https://api.linkedin.com/rest/posts');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['access_token'],
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

    if (in_array($httpCode, [200, 201])) {
        $postId = null;
        if (preg_match('/x-restli-id:\s*(.+)/i', $headers, $matches)) $postId = trim($matches[1]);
        else { $json = json_decode($body, true); $postId = $json['id'] ?? null; }
        
        return ['success' => true, 'id' => $postId, 'url' => null]; // LinkedIn REST sometimes creates async
    } else {
        return ['success' => false, 'message' => "HTTP $httpCode: $body"];
    }
}

function publishToFacebook($artigo, $config, $dryRun = false) {
    if (empty($config['page_id']) || empty($config['access_token'])) 
        return ['success' => false, 'message' => 'Page ID ou Access Token ausente'];

    // TODO: Implement Facebook Graph API (Page Feed)
    // POST /{page-id}/feed
    return ['success' => false, 'message' => 'Not implemented yet'];
}

function publishToInstagram($artigo, $config, $dryRun = false) {
    if (empty($config['account_id']) || empty($config['access_token'])) 
        return ['success' => false, 'message' => 'Account ID ou Access Token ausente'];

    // TODO: Implement Instagram Graph API (Content Publishing)
    // 1. POST /{ig-user-id}/media (create container)
    // 2. POST /{ig-user-id}/media_publish (publish container)
    return ['success' => false, 'message' => 'Not implemented yet'];
}

function publishToTiktok($artigo, $config, $dryRun = false) {
    return ['success' => false, 'message' => 'TikTok API publishing currently requires Content Posting API which is complex. Not implemented.'];
}

function publishToYoutube($artigo, $config, $dryRun = false) {
    return ['success' => false, 'message' => 'YouTube publishing not implemented.'];
}

// --- Main Worker Logic ---

$pdo = $pdo ?? null;
if (!$pdo) die("Database connection failed.\n");

echo "Iniciando Worker de Publicação Multi-rede...\n";
$dryRun = getenv('DRY_RUN') === '1' || in_array('--dry-run', $argv ?? []);

try {
    // 1. Fetch configs
    $stmt = $pdo->query("SELECT * FROM redes_sociais_config");
    $allConfigs = [];
    while ($row = $stmt->fetch()) {
        if (!empty($row['dados_extras'])) {
            $extras = json_decode($row['dados_extras'], true);
            if (is_array($extras)) $row = array_merge($row, $extras);
        }
        $allConfigs[$row['rede']] = $row;
    }

    // 2. Fetch scheduled articles
    $pdo->beginTransaction();
    $sql = "SELECT id FROM artigos 
            WHERE status_publicacao = 'agendado' 
            AND data_agendamento IS NOT NULL 
            AND data_agendamento <= NOW() 
            LIMIT 10 FOR UPDATE SKIP LOCKED";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $ids = array_column($stmt->fetchAll(), 'id');
    $pdo->commit();

    if (empty($ids)) {
        echo "Nenhum artigo agendado para agora.\n";
        exit(0);
    }

    foreach ($ids as $id) {
        $stmt = $pdo->prepare("SELECT * FROM artigos WHERE id = ?");
        $stmt->execute([$id]);
        $artigo = $stmt->fetch();
        if (!$artigo) continue;

        echo "Processando Artigo #$id: {$artigo['titulo']}\n";
        
        $destinos = json_decode($artigo['redes_destino'] ?? '[]', true);
        if ($artigo['publicar_linkedin'] ?? false) { // Backwards compat
            $hasLi = false;
            foreach ($destinos as $d) if (($d['rede']??'') === 'linkedin') $hasLi = true;
            if (!$hasLi) $destinos[] = ['rede' => 'linkedin'];
        }

        if (empty($destinos)) {
            echo "  - Sem redes de destino. Marcando como publicado (apenas site).\n";
            $pdo->prepare("UPDATE artigos SET status_publicacao = 'publicado', data_publicacao = NOW() WHERE id = ?")->execute([$id]);
            continue;
        }

        $allSuccess = true;
        
        foreach ($destinos as $dest) {
            $rede = $dest['rede'] ?? '';
            if (!$rede) continue;
            
            // Verifica se já publicou nesta rede
            $stmtChk = $pdo->prepare("SELECT id FROM publicacoes_redes WHERE artigo_id = ? AND rede = ? AND status = 'publicado'");
            $stmtChk->execute([$id, $rede]);
            if ($stmtChk->fetch()) {
                echo "  - Já publicado em: $rede. Pulando.\n";
                continue;
            }

            echo "  - Publicando em: $rede...\n";
            $cfg = $allConfigs[$rede] ?? null;
            if (!$cfg) {
                echo "    [ERRO] Configuração não encontrada para $rede\n";
                $allSuccess = false;
                continue;
            }

            $res = ['success' => false, 'message' => 'Handler not defined'];
            switch ($rede) {
                case 'linkedin': $res = publishToLinkedin($artigo, $cfg, $dryRun); break;
                case 'facebook': $res = publishToFacebook($artigo, $cfg, $dryRun); break;
                case 'instagram': $res = publishToInstagram($artigo, $cfg, $dryRun); break;
                case 'tiktok': $res = publishToTiktok($artigo, $cfg, $dryRun); break;
                case 'youtube': $res = publishToYoutube($artigo, $cfg, $dryRun); break;
            }

            if ($res['success']) {
                echo "    [OK] Publicado! ID: " . ($res['id'] ?? 'N/A') . "\n";
                // Registrar publicação
                $stmtPub = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, status, publicado_em) VALUES (?, ?, ?, ?, 'publicado', NOW())");
                $stmtPub->execute([$rede, $id, $res['id'] ?? null, $res['url'] ?? null]);
                
                // Se for LinkedIn, atualizar coluna legado
                if ($rede === 'linkedin' && !empty($res['id'])) {
                    $pdo->prepare("UPDATE artigos SET linkedin_post_id = ? WHERE id = ?")->execute([$res['id'], $id]);
                }
            } else {
                echo "    [FALHA] " . ($res['message'] ?? 'Erro desconhecido') . "\n";
                $allSuccess = false;
                // Logar erro
                $msgErro = "$rede: " . ($res['message'] ?? 'Erro');
                $pdo->prepare("UPDATE artigos SET erro_publicacao = ?, ultima_tentativa = NOW() WHERE id = ?")->execute([$msgErro, $id]);
            }
        }

        if ($allSuccess) {
             $pdo->prepare("UPDATE artigos SET status_publicacao = 'publicado', data_publicacao = NOW(), erro_publicacao = NULL WHERE id = ?")->execute([$id]);
             echo "  > Artigo #$id finalizado com sucesso.\n";
        } else {
            echo "  > Artigo #$id finalizado com erros (pendente retomada).\n";
        }
    }

} catch (Exception $e) {
    error_log("Worker Exception: " . $e->getMessage());
    echo "Erro fatal: " . $e->getMessage() . "\n";
}

