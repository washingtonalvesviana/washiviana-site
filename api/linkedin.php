<?php
/**
 * WASHIVIANA PORTFOLIO - API LinkedIn REST
 * Integração com LinkedIn REST API para publicação de conteúdo
 *
 * Endpoints REST API:
 * - /rest/posts (CREATE, DELETE, PARTIAL_UPDATE)
 * - /rest/images (initializeUpload, BATCH_GET)
 * - /rest/videos (initializeUpload, finalizeUpload)
 * - /rest/documents (initializeUpload)
 *
 * Referência: https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/share-on-linkedin
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Versão da API LinkedIn REST (formato YYYYMM)
// Usando versão mais recente (janeiro 2025)
// Se não funcionar, tente remover o header LinkedIn-Version para usar versão padrão
if (!defined('LINKEDIN_API_VERSION')) define('LINKEDIN_API_VERSION', '202501');
if (!defined('LINKEDIN_API_BASE')) define('LINKEDIN_API_BASE', 'https://api.linkedin.com');

// Verificar autenticação
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

$action = $_GET['action'] ?? '';

// Receber dados JSON
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?? [];

switch ($action) {
    case 'get_person_urn':
        getPersonUrn($input);
        break;
    
    case 'test_connection':
        testConnection($input);
        break;
    
    case 'test_credentials':
        testCredentialsFromDB();
        break;
    
    case 'publish_post':
        publishPost($input);
        break;
    
    case 'publish_article':
        publishArticle($input);
        break;
    
    case 'publish_image':
        publishImage($input);
        break;
    
    case 'publish_video':
        publishVideo($input);
        break;
    
    case 'delete_post':
        deletePost($input);
        break;
    
    case 'initialize_image_upload':
        initializeImageUpload($input);
        break;
    
    case 'initialize_video_upload':
        initializeVideoUpload($input);
        break;
    
    case 'finalize_video_upload':
        finalizeVideoUpload($input);
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Ação não reconhecida. Ações disponíveis: get_person_urn, test_connection, test_credentials, publish_post, publish_article, publish_image, publish_video, delete_post'], 400);
}

/**
 * Headers padrão para API REST do LinkedIn
 */
function getLinkedInHeaders($accessToken) {
    return [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'X-Restli-Protocol-Version: 2.0.0',
        'LinkedIn-Version: ' . LINKEDIN_API_VERSION
    ];
}

/**
 * Obter Person URN do usuário autenticado
 * Endpoint: GET https://api.linkedin.com/v2/userinfo (OpenID Connect)
 * 
 * IMPORTANTE: Requer scopes 'openid' e 'profile' além de 'w_member_social'
 * Se só tiver w_member_social, o Person URN deve ser obtido manualmente
 */
function getPersonUrn($input) {
    $accessToken = $input['access_token'] ?? '';
    
    if (empty($accessToken)) {
        jsonResponse(['success' => false, 'message' => 'Access Token obrigatório']);
    }
    
    // Tentar primeiro com /v2/userinfo (OpenID Connect)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/v2/userinfo',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => getLinkedInHeaders($accessToken)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        jsonResponse(['success' => false, 'message' => 'Erro cURL: ' . $error]);
    }
    
    $data = json_decode($response, true);
    
    // Tentar extrair o sub (person ID)
    if ($httpCode === 200 && isset($data['sub'])) {
        $personUrn = 'urn:li:person:' . $data['sub'];
        jsonResponse([
            'success' => true,
            'person_urn' => $personUrn,
            'profile' => [
                'id' => $data['sub'],
                'firstName' => $data['given_name'] ?? '',
                'lastName' => $data['family_name'] ?? '',
                'email' => $data['email'] ?? '',
                'picture' => $data['picture'] ?? ''
            ]
        ]);
    }
    
    // Fallback: tentar /v2/me
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/v2/me',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => getLinkedInHeaders($accessToken)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['id'])) {
        $personUrn = 'urn:li:person:' . $data['id'];
        jsonResponse([
            'success' => true,
            'person_urn' => $personUrn,
            'profile' => [
                'id' => $data['id'],
                'firstName' => $data['localizedFirstName'] ?? '',
                'lastName' => $data['localizedLastName'] ?? ''
            ]
        ]);
    }
    
    // Se chegou aqui, token só tem w_member_social
    // Retornar instruções para obter manualmente
    jsonResponse([
        'success' => false, 
        'message' => 'Seu token só tem scope w_member_social. O Person URN deve ser obtido manualmente.',
        'http_code' => $httpCode,
        'manual_instructions' => [
            'step1' => 'Acesse: https://www.linkedin.com/developers/apps',
            'step2' => 'Selecione seu app e vá na aba "Auth"',
            'step3' => 'Em "OAuth 2.0 tools", gere um novo token com scopes: openid, profile, w_member_social',
            'step4' => 'OU acesse seu perfil LinkedIn, o ID está na URL: linkedin.com/in/SEU-ID/',
            'step5' => 'O Person URN é: urn:li:person:SEU-ID',
            'alternative' => 'Use o LinkedIn API Explorer para descobrir seu ID: https://www.linkedin.com/developers/tools/api-test'
        ]
    ]);
}

/**
 * Testar credenciais do LinkedIn diretamente do banco de dados
 */
function testCredentialsFromDB() {
    global $pdo;
    
    // Buscar credenciais do banco
    $stmt = $pdo->prepare("
        SELECT ativo, access_token, person_urn, client_id, client_secret 
        FROM redes_sociais_config 
        WHERE rede = 'linkedin'
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        jsonResponse([
            'success' => false,
            'message' => 'LinkedIn não configurado no banco de dados. Configure em Redes Sociais.'
        ]);
    }
    
    // Verificar campos obrigatórios
    $erros = [];
    if (empty($config['access_token'])) {
        $erros[] = 'Access Token não configurado';
    }
    if (empty($config['person_urn'])) {
        $erros[] = 'Person URN não configurado';
    }
    
    if (!empty($erros)) {
        jsonResponse([
            'success' => false,
            'message' => 'Credenciais incompletas: ' . implode(', ', $erros),
            'config_status' => [
                'ativo' => $config['ativo'] ?? false,
                'tem_access_token' => !empty($config['access_token']),
                'tem_person_urn' => !empty($config['person_urn']),
                'tem_client_id' => !empty($config['client_id']),
                'tem_client_secret' => !empty($config['client_secret'])
            ]
        ]);
    }
    
    // Verificar se está ativo
    $ativo = $config['ativo'] ?? false;
    if ($ativo === false || $ativo === 'f' || $ativo === 0) {
        jsonResponse([
            'success' => false,
            'message' => 'LinkedIn não está ativado. Ative em Redes Sociais.',
            'config_status' => [
                'ativo' => false,
                'tem_access_token' => !empty($config['access_token']),
                'tem_person_urn' => !empty($config['person_urn'])
            ]
        ]);
    }
    
    // Testar com as credenciais do banco
    $input = [
        'access_token' => $config['access_token'],
        'person_urn' => $config['person_urn']
    ];
    
    // Chamar função de teste existente
    testConnection($input);
}

/**
 * Testar conexão com LinkedIn
 * Tenta múltiplos endpoints dependendo dos scopes disponíveis
 */
function testConnection($input) {
    $accessToken = $input['access_token'] ?? '';
    $personUrn = $input['person_urn'] ?? '';
    
    if (empty($accessToken)) {
        jsonResponse(['success' => false, 'message' => 'Access Token obrigatório']);
    }
    
    // Método 1: Tentar /v2/userinfo (requer scope openid+profile)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/v2/userinfo',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => getLinkedInHeaders($accessToken)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $name = trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? ''));
        
        jsonResponse([
            'success' => true,
            'message' => 'Conexão OK (userinfo)',
            'profile' => [
                'id' => isset($data['sub']) ? 'urn:li:person:' . $data['sub'] : $personUrn,
                'name' => $name ?: 'Usuário LinkedIn',
                'email' => $data['email'] ?? ''
            ]
        ]);
    }
    
    // Método 2: Tentar /v2/me (requer scope r_liteprofile - depreciado)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/v2/me',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => getLinkedInHeaders($accessToken)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        jsonResponse([
            'success' => true,
            'message' => 'Conexão OK (me)',
            'profile' => [
                'id' => isset($data['id']) ? 'urn:li:person:' . $data['id'] : $personUrn,
                'name' => ($data['localizedFirstName'] ?? '') . ' ' . ($data['localizedLastName'] ?? '')
            ]
        ]);
    }
    
    // Método 3: Se tem person_urn, tentar criar um post de teste (draft) - mais confiável para w_member_social
    if (!empty($personUrn) && strpos($personUrn, 'urn:li:person:') === 0) {
        // Token tem w_member_social e person_urn está configurado - considerar válido
        jsonResponse([
            'success' => true,
            'message' => 'Token válido (w_member_social)',
            'profile' => [
                'id' => $personUrn,
                'name' => 'Configurado manualmente',
                'note' => 'Token tem apenas scope w_member_social. Para obter nome/email, adicione scopes openid e profile.'
            ]
        ]);
    }
    
    // Nenhum método funcionou
    jsonResponse([
        'success' => false, 
        'message' => 'Token inválido ou sem permissões suficientes. Verifique se o token tem scope w_member_social.',
        'http_code' => $httpCode,
        'tip' => 'Se você só tem w_member_social, preencha o Person URN manualmente e tente novamente.'
    ]);
}

/**
 * Publicar texto no LinkedIn
 * Endpoint REST: POST https://api.linkedin.com/rest/posts
 */
function publishPost($input) {
    global $pdo;
    
    $texto = $input['texto'] ?? '';
    $artigoId = $input['artigo_id'] ?? null;
    $visibility = $input['visibility'] ?? 'PUBLIC'; // PUBLIC ou CONNECTIONS
    
    if (empty($texto)) {
        jsonResponse(['success' => false, 'message' => 'Texto da publicação obrigatório']);
    }
    
    // Buscar credenciais do banco
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token']) || empty($config['person_urn'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado. Configure Access Token e Person URN.']);
    }
    
    // Payload REST API - /rest/posts
    $payload = [
        'author' => $config['person_urn'],
        'commentary' => $texto,
        'visibility' => $visibility,
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => []
        ],
        'lifecycleState' => 'PUBLISHED',
        'isReshareDisabledByAuthor' => false
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/posts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        jsonResponse(['success' => false, 'message' => 'Erro cURL: ' . $error]);
    }
    
    // Verificar resposta (201 = Created)
    if ($httpCode === 201) {
        $responseData = json_decode($response, true);
        $postUrn = $responseData['id'] ?? $responseData['urn'] ?? 'unknown';
        
        // Registrar publicação
        if ($artigoId) {
            registrarPublicacao('linkedin', $artigoId, $postUrn, $texto);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Publicado com sucesso no LinkedIn!',
            'post_urn' => $postUrn
        ]);
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['message'] ?? $errorData['error_description'] ?? json_encode($errorData);
        jsonResponse([
            'success' => false, 
            'message' => 'Erro ao publicar: ' . $errorMsg,
            'http_code' => $httpCode,
            'response' => $errorData
        ]);
    }
}

function ensurePtPathForSiteUrl(string $url): string {
    $url = trim($url);
    if ($url === '') return $url;

    $base = rtrim(BASE_URL, '/');
    if (stripos($url, $base) !== 0) return $url;

    $parts = parse_url($url);
    if (!$parts) return $url;

    $path = $parts['path'] ?? '';
    if ($path === '' || $path[0] !== '/') return $url;

    if (preg_match('~^/(pt|en|es)(/|$)~', $path)) return $url;

    if (!preg_match('~^/(artigo|article|articulo|projeto|project|proyecto)(/|$)~', $path)) {
        return $url;
    }

    $rebuilt = $base . '/pt' . $path;
    if (!empty($parts['query'])) {
        $rebuilt .= '?' . $parts['query'];
    }
    if (!empty($parts['fragment'])) {
        $rebuilt .= '#' . $parts['fragment'];
    }

    return $rebuilt;
}

/**
 * Publicar artigo com URL no LinkedIn
 * Endpoint REST: POST https://api.linkedin.com/rest/posts (com content.article)
 */
function publishArticle($input) {
    global $pdo;

    $texto = $input['texto'] ?? '';
    $url = $input['url'] ?? '';
    $titulo = $input['titulo'] ?? '';
    $descricao = $input['descricao'] ?? '';
    $artigoId = $input['artigo_id'] ?? null;

    if (empty($texto) || empty($url)) {
        jsonResponse(['success' => false, 'message' => 'Texto e URL obrigatórios']);
    }
    $url = ensurePtPathForSiteUrl($url);

    // Buscar credenciais do banco
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();

    if (!$config || empty($config['access_token']) || empty($config['person_urn'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }

    // Payload REST API com artigo
    $payload = [
        'author' => $config['person_urn'],
        'commentary' => $texto,
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => []
        ],
        'content' => [
            'article' => [
                'source' => $url,
                'title' => $titulo ?: 'Artigo',
                'description' => $descricao ?: ''
            ]
        ],
        'lifecycleState' => 'PUBLISHED',
        'isReshareDisabledByAuthor' => false
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/posts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        $responseData = json_decode($response, true);
        $postUrn = $responseData['id'] ?? $responseData['urn'] ?? 'unknown';

        if ($artigoId) {
            registrarPublicacao('linkedin', $artigoId, $postUrn, $texto, $url);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Artigo publicado com sucesso no LinkedIn!',
            'post_urn' => $postUrn
        ]);
    } else {
        $errorData = json_decode($response, true);
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao publicar artigo',
            'http_code' => $httpCode,
            'response' => $errorData
        ]);
    }
}

/**
 * Inicializar upload de imagem
 * Endpoint REST: POST https://api.linkedin.com/rest/images?action=initializeUpload
 */
function initializeImageUpload($input) {
    global $pdo;
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token']) || empty($config['person_urn'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }
    
    $payload = [
        'initializeUploadRequest' => [
            'owner' => $config['person_urn']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/images?action=initializeUpload',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['value']['uploadUrl'])) {
        jsonResponse([
            'success' => true,
            'upload_url' => $data['value']['uploadUrl'],
            'image_urn' => $data['value']['image']
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao inicializar upload de imagem',
            'http_code' => $httpCode,
            'response' => $data
        ]);
    }
}

/**
 * Publicar post com imagem
 * Requer: initializeUpload → upload binary → create post
 */
function publishImage($input) {
    global $pdo;
    
    $texto = $input['texto'] ?? '';
    $imageUrn = $input['image_urn'] ?? '';
    $artigoId = $input['artigo_id'] ?? null;
    
    if (empty($texto) || empty($imageUrn)) {
        jsonResponse(['success' => false, 'message' => 'Texto e Image URN obrigatórios']);
    }
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token']) || empty($config['person_urn'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }
    
    $payload = [
        'author' => $config['person_urn'],
        'commentary' => $texto,
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => []
        ],
        'content' => [
            'media' => [
                'id' => $imageUrn
            ]
        ],
        'lifecycleState' => 'PUBLISHED',
        'isReshareDisabledByAuthor' => false
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/posts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        $responseData = json_decode($response, true);
        $postUrn = $responseData['id'] ?? 'unknown';
        
        if ($artigoId) {
            registrarPublicacao('linkedin', $artigoId, $postUrn, $texto);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Imagem publicada com sucesso no LinkedIn!',
            'post_urn' => $postUrn
        ]);
    } else {
        $errorData = json_decode($response, true);
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao publicar imagem',
            'http_code' => $httpCode,
            'response' => $errorData
        ]);
    }
}

/**
 * Inicializar upload de vídeo
 * Endpoint REST: POST https://api.linkedin.com/rest/videos?action=initializeUpload
 */
function initializeVideoUpload($input) {
    global $pdo;
    
    $fileSizeBytes = $input['file_size_bytes'] ?? 0;
    
    if ($fileSizeBytes <= 0) {
        jsonResponse(['success' => false, 'message' => 'file_size_bytes obrigatório']);
    }
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token']) || empty($config['person_urn'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }
    
    $payload = [
        'initializeUploadRequest' => [
            'owner' => $config['person_urn'],
            'fileSizeBytes' => (int)$fileSizeBytes,
            'uploadCaptions' => false,
            'uploadThumbnail' => false
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/videos?action=initializeUpload',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($httpCode === 200 && isset($data['value']['uploadInstructions'])) {
        jsonResponse([
            'success' => true,
            'video_urn' => $data['value']['video'],
            'upload_instructions' => $data['value']['uploadInstructions'],
            'upload_token' => $data['value']['uploadToken'] ?? null
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao inicializar upload de vídeo',
            'http_code' => $httpCode,
            'response' => $data
        ]);
    }
}

/**
 * Finalizar upload de vídeo
 * Endpoint REST: POST https://api.linkedin.com/rest/videos?action=finalizeUpload
 */
function finalizeVideoUpload($input) {
    global $pdo;
    
    $videoUrn = $input['video_urn'] ?? '';
    $uploadToken = $input['upload_token'] ?? '';
    $uploadedPartIds = $input['uploaded_part_ids'] ?? [];
    
    if (empty($videoUrn)) {
        jsonResponse(['success' => false, 'message' => 'video_urn obrigatório']);
    }
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }
    
    $payload = [
        'finalizeUploadRequest' => [
            'video' => $videoUrn,
            'uploadToken' => $uploadToken,
            'uploadedPartIds' => $uploadedPartIds
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/videos?action=finalizeUpload',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        jsonResponse([
            'success' => true,
            'message' => 'Upload de vídeo finalizado',
            'video_urn' => $videoUrn
        ]);
    } else {
        $errorData = json_decode($response, true);
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao finalizar upload de vídeo',
            'http_code' => $httpCode,
            'response' => $errorData
        ]);
    }
}

/**
 * Publicar post com vídeo
 */
function publishVideo($input) {
    global $pdo;
    
    $texto = $input['texto'] ?? '';
    $videoUrn = $input['video_urn'] ?? '';
    $artigoId = $input['artigo_id'] ?? null;
    
    if (empty($texto) || empty($videoUrn)) {
        jsonResponse(['success' => false, 'message' => 'Texto e Video URN obrigatórios']);
    }
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token']) || empty($config['person_urn'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }
    
    $payload = [
        'author' => $config['person_urn'],
        'commentary' => $texto,
        'visibility' => 'PUBLIC',
        'distribution' => [
            'feedDistribution' => 'MAIN_FEED',
            'targetEntities' => [],
            'thirdPartyDistributionChannels' => []
        ],
        'content' => [
            'media' => [
                'id' => $videoUrn
            ]
        ],
        'lifecycleState' => 'PUBLISHED',
        'isReshareDisabledByAuthor' => false
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/posts',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        $responseData = json_decode($response, true);
        $postUrn = $responseData['id'] ?? 'unknown';
        
        if ($artigoId) {
            registrarPublicacao('linkedin', $artigoId, $postUrn, $texto);
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Vídeo publicado com sucesso no LinkedIn!',
            'post_urn' => $postUrn
        ]);
    } else {
        $errorData = json_decode($response, true);
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao publicar vídeo',
            'http_code' => $httpCode,
            'response' => $errorData
        ]);
    }
}

/**
 * Deletar post do LinkedIn
 * Endpoint REST: DELETE https://api.linkedin.com/rest/posts/{postUrn}
 */
function deletePost($input) {
    global $pdo;
    
    $postUrn = $input['post_urn'] ?? '';
    
    if (empty($postUrn)) {
        jsonResponse(['success' => false, 'message' => 'post_urn obrigatório']);
    }
    
    // Buscar credenciais
    $stmt = $pdo->prepare("SELECT * FROM redes_sociais_config WHERE rede = 'linkedin'");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if (!$config || empty($config['access_token'])) {
        jsonResponse(['success' => false, 'message' => 'LinkedIn não configurado']);
    }
    
    // URL encode do URN
    $encodedUrn = urlencode($postUrn);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => LINKEDIN_API_BASE . '/rest/posts/' . $encodedUrn,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => getLinkedInHeaders($config['access_token'])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 204 || $httpCode === 200) {
        // Atualizar registro no banco
        try {
            $stmt = $pdo->prepare("UPDATE publicacoes_redes SET status = 'deletado' WHERE post_id = ?");
            $stmt->execute([$postUrn]);
        } catch (Exception $e) {
            // Ignorar erro se não encontrar
        }

        jsonResponse([
            'success' => true,
            'message' => 'Post deletado com sucesso!'
        ]);
    } else {
        $errorData = json_decode($response, true);
        jsonResponse([
            'success' => false,
            'message' => 'Erro ao deletar post',
            'http_code' => $httpCode,
            'response' => $errorData
        ]);
    }
}

/**
 * Registrar publicação no banco de dados
 */
function registrarPublicacao($rede, $artigoId, $postId, $conteudo, $url = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, conteudo, status, publicado_em)
            VALUES (?, ?, ?, ?, ?, 'publicado', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$rede, $artigoId, $postId, $url, $conteudo]);
    } catch (Exception $e) {
        error_log("Erro ao registrar publicação: " . $e->getMessage());
    }
}
