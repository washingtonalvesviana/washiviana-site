<?php
/**
 * WASHIVIANA PORTFOLIO - API de Redes Sociais
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Ação opcional
$action = $_GET['action'] ?? '';

// Verificar autenticação
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

// CSRF para POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Permitir GET para iniciar OAuth (redirecionamento) com action=start_oauth
    if ($action === 'start_oauth') {
        header('Content-Type: application/json; charset=utf-8');
        $rede = $_GET['rede'] ?? 'instagram';
        // Recuperar client_id do banco
        $stmt = $pdo->prepare("SELECT client_id FROM redes_sociais_config WHERE rede = ? LIMIT 1");
        $stmt->execute([$rede]);
        $cfg = $stmt->fetch();
        $clientId = $cfg['client_id'] ?? '';
        if (empty($clientId)) {
            echo json_encode(['success' => false, 'message' => 'App não configurado (client_id ausente)']);
            exit;
        }
        session_start();
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $redirectUri = BASE_URL . '/api/oauth_callback.php?rede=' . urlencode($rede);
        
        $authUrl = '';
        if ($rede === 'instagram' || $rede === 'facebook') {
            $scopes = 'instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement,pages_manage_posts,public_profile';
            $authUrl = 'https://www.facebook.com/v17.0/dialog/oauth?' . http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'state' => $state,
                'scope' => $scopes,
                'response_type' => 'code'
            ]);
        }
        
        echo json_encode(['success' => true, 'auth_url' => $authUrl]);
        exit;
    }

    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

if ($action === 'update_publicacao_url') {
    $publicacaoId = (int)($_POST['publicacao_id'] ?? 0);
    $urlPost = trim((string)($_POST['url_post'] ?? ''));

    if ($publicacaoId <= 0 || $urlPost === '') {
        jsonResponse(['success' => false, 'message' => 'Publicação e URL são obrigatórias.'], 422);
    }

    if (!preg_match('#^https?://#i', $urlPost)) {
        jsonResponse(['success' => false, 'message' => 'A URL deve começar com http:// ou https://'], 422);
    }

    if (strlen($urlPost) > 500) {
        $urlPost = substr($urlPost, 0, 500);
    }

    try {
        $stmt = $pdo->prepare("UPDATE publicacoes_redes SET url_post = ? WHERE id = ?");
        $stmt->execute([$urlPost, $publicacaoId]);
        jsonResponse(['success' => true, 'message' => 'URL da publicação atualizada com sucesso.']);
    } catch (Exception $e) {
        error_log("Update publicacao URL error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Erro ao atualizar URL.'], 500);
    }
}

try {
    // ---------------------------------------------------------
    // PROCESSAMENTO DE SALVAMENTO (TODAS AS REDES)
    // ---------------------------------------------------------

    $networks = ['linkedin', 'facebook', 'instagram', 'tiktok', 'youtube'];
    
    foreach ($networks as $rede) {
        // Verificar se dados para esta rede foram enviados (pelo menos o flag _ativo ou algum campo chave)
        if (!isset($_POST[$rede . '_ativo']) && !isset($_POST[$rede . '_client_id'])) {
            continue;
        }

        $ativo = isset($_POST[$rede . '_ativo']) && $_POST[$rede . '_ativo'] ? 'true' : 'false';
        $clientId = trim($_POST[$rede . '_client_id'] ?? '');
        $clientSecret = trim($_POST[$rede . '_client_secret'] ?? '');
        $accessToken = trim($_POST[$rede . '_access_token'] ?? '');
        
        if ($rede === 'linkedin') {
            $personUrn = trim($_POST['linkedin_person_urn'] ?? '');
            $orgUrn = trim($_POST['linkedin_organization_urn'] ?? '');
            $target = trim($_POST['linkedin_publish_target'] ?? 'person');
            
            // LinkedIn tem colunas dedicadas
            $stmt = $pdo->prepare("
                UPDATE redes_sociais_config SET 
                    ativo = ?::boolean,
                    client_id = ?,
                    client_secret = ?,
                    access_token = ?,
                    person_urn = ?,
                    organization_urn = ?,
                    publish_target = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE rede = 'linkedin'
            ");
            $stmt->execute([$ativo, $clientId, $clientSecret, $accessToken, $personUrn, $orgUrn, $target]);
            
        } else {
            // Outras redes (Facebook, Instagram, TikTok, YouTube)
            
            // Carregar dados_extras atuais para não perder info
            $stmtExt = $pdo->prepare("SELECT dados_extras FROM redes_sociais_config WHERE rede = ? LIMIT 1");
            $stmtExt->execute([$rede]);
            $curr = $stmtExt->fetch();
            $currentExtras = [];
            if ($curr && !empty($curr['dados_extras'])) {
                $decoded = json_decode($curr['dados_extras'], true);
                if (is_array($decoded)) $currentExtras = $decoded;
            }
            
            // Mapear campos específicos
            if ($rede === 'facebook') {
                $currentExtras['page_id'] = trim($_POST['facebook_page_id'] ?? '');
            } elseif ($rede === 'instagram') {
                $currentExtras['account_id'] = trim($_POST['instagram_account_id'] ?? '');
                // Instruction do agente social
                if (isset($_POST['social_agent_instruction'])) {
                   $currentExtras['social_agent_instruction'] = trim($_POST['social_agent_instruction']);
                }
            } elseif ($rede === 'tiktok') {
                $currentExtras['client_key'] = $clientId; // TikTok usa client_key
                // TikTok access token logic might be different, keeping standard for now
            } elseif ($rede === 'youtube') {
                $currentExtras['channel_id'] = trim($_POST['youtube_channel_id'] ?? '');
                if (isset($_POST['youtube_refresh_token'])) {
                    $currentExtras['refresh_token'] = trim($_POST['youtube_refresh_token']);
                }
            }

            // Atualizar
            $dadosExtrasJson = json_encode((object)$currentExtras, JSON_UNESCAPED_UNICODE);
            
            $stmt = $pdo->prepare("
                UPDATE redes_sociais_config SET 
                    ativo = ?::boolean,
                    client_id = ?,
                    client_secret = ?,
                    access_token = ?,
                    dados_extras = ?::jsonb,
                    updated_at = CURRENT_TIMESTAMP
                WHERE rede = ?
            ");
            $stmt->execute([$ativo, $clientId, $clientSecret, $accessToken, $dadosExtrasJson, $rede]);
        }
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Configurações das redes sociais salvas com sucesso!'
    ]);
    
} catch (Exception $e) {
    error_log("Redes Sociais API Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()], 500);
}
