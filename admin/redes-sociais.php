<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Redes Sociais
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

// Buscar configurações das redes
$stmt = $pdo->query("SELECT * FROM redes_sociais_config ORDER BY rede");
$redes = [];
while ($row = $stmt->fetch()) {
    $redes[$row['rede']] = $row;
}

// Valores padrão se não existirem
$linkedin = $redes['linkedin'] ?? ['ativo' => false, 'client_id' => '', 'client_secret' => '', 'access_token' => '', 'person_urn' => ''];
$instagram = $redes['instagram'] ?? ['ativo' => false, 'client_id' => '', 'client_secret' => '', 'access_token' => '', 'page_id' => ''];
// Decodificar dados_extras JSONB se existir
$instagram_dados = [];
if (!empty($instagram['dados_extras'])) {
    $instagram_dados = json_decode($instagram['dados_extras'], true) ?: [];
}
$social_agent_instruction_value = $instagram_dados['social_agent_instruction'] ?? '';

// Mensagens de callback OAuth
$successMsg = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$errorMsg = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// -----------------------------
// Métricas do site (query segura)
// -----------------------------
$totalPublicacoes = 0;
$siteVisitsTotal = 0;
$siteVisitsToday = 0;
$siteVisitsPeriod = 0;
$views = 0; $likes = 0; $shares = 0;
$topPages = [];
$topPagesPeriod = [];
$linkedinPublicacoes = [];
$publicacoesMetricas = [];

$periodFrom = $_GET['from'] ?? '';
$periodTo = $_GET['to'] ?? '';

try {
    $fromDate = $periodFrom ? DateTime::createFromFormat('Y-m-d', $periodFrom) : null;
    $toDate = $periodTo ? DateTime::createFromFormat('Y-m-d', $periodTo) : null;
} catch (Exception $e) {
    $fromDate = null;
    $toDate = null;
}

if (!$fromDate || !$toDate) {
    $fromDate = new DateTime('today -6 days');
    $toDate = new DateTime('today');
    $periodFrom = $fromDate->format('Y-m-d');
    $periodTo = $toDate->format('Y-m-d');
}

$fromDate->setTime(0, 0, 0);
$toDate->setTime(23, 59, 59);
try {
    if (isset($pdo)) {
        // publicações registradas
        $stmt = $pdo->query("SELECT COUNT(*) FROM publicacoes_redes");
        $totalPublicacoes = (int)$stmt->fetchColumn();

        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM site_accesses");
            $siteVisitsTotal = (int)$stmt->fetchColumn();
            $stmt = $pdo->query("SELECT COUNT(*) FROM site_accesses WHERE created_at >= date_trunc('day', now())");
            $siteVisitsToday = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_accesses WHERE created_at BETWEEN ? AND ?");
            $stmt->execute([$fromDate->format('Y-m-d H:i:s'), $toDate->format('Y-m-d H:i:s')]);
            $siteVisitsPeriod = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COALESCE(SUM(visualizacoes),0) AS v, COALESCE(SUM(curtidas),0) AS l, COALESCE(SUM(compartilhamentos),0) AS s FROM metricas_publicacoes");
            $m = $stmt->fetch();
            $views = (int)($m['v'] ?? 0);
            $likes = (int)($m['l'] ?? 0);
            $shares = (int)($m['s'] ?? 0);

            $stmt = $pdo->query("SELECT path, COUNT(*) AS cnt FROM site_accesses WHERE created_at >= now() - interval '7 days' GROUP BY path ORDER BY cnt DESC LIMIT 5");
            $topPages = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT path, COUNT(*) AS cnt FROM site_accesses WHERE created_at BETWEEN ? AND ? GROUP BY path ORDER BY cnt DESC LIMIT 5");
            $stmt->execute([$fromDate->format('Y-m-d H:i:s'), $toDate->format('Y-m-d H:i:s')]);
            $topPagesPeriod = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT pr.id, pr.artigo_id, pr.post_id, pr.url_post, pr.publicado_em, a.titulo, a.slug FROM publicacoes_redes pr LEFT JOIN artigos a ON a.id = pr.artigo_id WHERE pr.rede = 'linkedin' ORDER BY pr.publicado_em DESC NULLS LAST, pr.id DESC LIMIT 10");
            $linkedinPublicacoes = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT pr.id, pr.rede, pr.artigo_id, pr.post_id, pr.publicado_em, a.titulo, a.slug,
                    m.data_coleta, m.visualizacoes, m.curtidas, m.comentarios, m.compartilhamentos, m.cliques, m.alcance, m.engajamento
                FROM publicacoes_redes pr
                LEFT JOIN artigos a ON a.id = pr.artigo_id
                LEFT JOIN metricas_publicacoes m ON m.id = (
                    SELECT m2.id FROM metricas_publicacoes m2
                    WHERE m2.publicacao_id = pr.id
                    ORDER BY m2.data_coleta DESC, m2.id DESC
                    LIMIT 1
                )
                WHERE pr.status = 'publicado'
                ORDER BY pr.publicado_em DESC NULLS LAST, pr.id DESC
                LIMIT 50");
            $publicacoesMetricas = $stmt->fetchAll();
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM site_accesses");
            $siteVisitsTotal = (int)$stmt->fetchColumn();
            $stmt = $pdo->query("SELECT COUNT(*) FROM site_accesses WHERE date(created_at) = CURDATE()");
            $siteVisitsToday = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_accesses WHERE created_at BETWEEN ? AND ?");
            $stmt->execute([$fromDate->format('Y-m-d H:i:s'), $toDate->format('Y-m-d H:i:s')]);
            $siteVisitsPeriod = (int)$stmt->fetchColumn();

            $stmt = $pdo->query("SELECT IFNULL(SUM(visualizacoes),0) AS v, IFNULL(SUM(curtidas),0) AS l, IFNULL(SUM(compartilhamentos),0) AS s FROM metricas_publicacoes");
            $m = $stmt->fetch();
            $views = (int)($m['v'] ?? 0);
            $likes = (int)($m['l'] ?? 0);
            $shares = (int)($m['s'] ?? 0);

            $stmt = $pdo->query("SELECT path, COUNT(*) AS cnt FROM site_accesses WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY path ORDER BY cnt DESC LIMIT 5");
            $topPages = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT path, COUNT(*) AS cnt FROM site_accesses WHERE created_at BETWEEN ? AND ? GROUP BY path ORDER BY cnt DESC LIMIT 5");
            $stmt->execute([$fromDate->format('Y-m-d H:i:s'), $toDate->format('Y-m-d H:i:s')]);
            $topPagesPeriod = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT pr.id, pr.artigo_id, pr.post_id, pr.url_post, pr.publicado_em, a.titulo, a.slug FROM publicacoes_redes pr LEFT JOIN artigos a ON a.id = pr.artigo_id WHERE pr.rede = 'linkedin' ORDER BY (pr.publicado_em IS NULL), pr.publicado_em DESC, pr.id DESC LIMIT 10");
            $linkedinPublicacoes = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT pr.id, pr.rede, pr.artigo_id, pr.post_id, pr.publicado_em, a.titulo, a.slug,
                    m.data_coleta, m.visualizacoes, m.curtidas, m.comentarios, m.compartilhamentos, m.cliques, m.alcance, m.engajamento
                FROM publicacoes_redes pr
                LEFT JOIN artigos a ON a.id = pr.artigo_id
                LEFT JOIN metricas_publicacoes m ON m.id = (
                    SELECT m2.id FROM metricas_publicacoes m2
                    WHERE m2.publicacao_id = pr.id
                    ORDER BY m2.data_coleta DESC, m2.id DESC
                    LIMIT 1
                )
                WHERE pr.status = 'publicado'
                ORDER BY (pr.publicado_em IS NULL), pr.publicado_em DESC, pr.id DESC
                LIMIT 50");
            $publicacoesMetricas = $stmt->fetchAll();
        }
    }
} catch (Exception $e) {
    error_log("Metrics query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redes Sociais - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .network-card.linkedin { border-left-color: #0077B5; }
        .network-card.instagram { border-left-color: #E4405F; }
        .network-card.active { background: rgba(0, 188, 212, 0.05); }
        
        .network-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .network-icon {
            font-size: 40px;
        }
        .network-title h3 {
            margin: 0;
        }
        .network-title p {
            margin: 5px 0 0;
            color: var(--text-muted);
            font-size: 13px;
        }
        .network-toggle {
            margin-left: auto;
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 30px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: var(--success);
        }
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-indicator.connected {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        .status-indicator.disconnected {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .help-section {
            background: #f8f9ff;
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .help-section h4 {
            margin: 0 0 15px;
            color: var(--primary-dark);
        }
        .help-section ol {
            margin: 0;
            padding-left: 20px;
        }
        .help-section li {
            margin-bottom: 10px;
        }
        .help-section a {
            color: var(--primary);
        }
        
        .metrics-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .metric-box {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .metric-box .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        .metric-box .label {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Password visibility toggle */
        .password-container {
            position: relative;
            display: flex; align-items: center;
        }
        .password-container input {
            padding-right: 45px !important;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            user-select: none;
            font-size: 18px;
            opacity: 0.6;
            transition: opacity 0.2s;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .password-toggle:hover {
            opacity: 1;
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>📱 Redes Sociais</h1>
            </div>
            
            <div id="messageDiv" class="message" style="display: none;"></div>
            
            <?php if ($successMsg): ?>
            <div class="message message-success" style="margin-bottom: 20px;">
                ✅ <?php echo $successMsg; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($errorMsg): ?>
            <div class="message message-error" style="margin-bottom: 20px;">
                ❌ <?php echo $errorMsg; ?>
            </div>
            <?php endif; ?>
            
            <form id="redesForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <!-- LinkedIn -->
                <div class="card network-card linkedin <?php echo $linkedin['ativo'] ? 'active' : ''; ?>">
                    <div class="card-body">
                        <div class="network-header">
                            <div class="network-icon"><i class="ph ph-linkedin-logo"></i></div>
                            <div class="network-title">
                                <h3>LinkedIn</h3>
                                <p>Publique artigos automaticamente no LinkedIn</p>
                            </div>
                            <div class="network-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="linkedin_ativo" value="1" 
                                           <?php echo $linkedin['ativo'] ? 'checked' : ''; ?>
                                           onchange="toggleNetwork('linkedin', this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="linkedin-config">
                            <div class="form-row">
                                <div class="form-col-6">
                                    <div class="form-group">
                                        <label>Client ID</label>
                                        <input type="text" name="linkedin_client_id" 
                                               value="<?php echo htmlspecialchars($linkedin['client_id'] ?? ''); ?>"
                                               placeholder="Ex: 77xxxxxxxxxxxxx">
                                    </div>
                                </div>
                                <div class="form-col-6">
                                    <div class="form-group">
                                        <label>Client Secret</label>
                                        <div class="password-container">
                                            <input type="password" name="linkedin_client_secret" 
                                                   value="<?php echo htmlspecialchars($linkedin['client_secret'] ?? ''); ?>"
                                                   placeholder="Ex: xxxxxxxxx">
                                            <span class="password-toggle" onclick="togglePasswordVisibility(this, 'linkedin_client_secret')"><i class="ph ph-eye"></i></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Access Token <span class="required">*</span></label>
                                <textarea name="linkedin_access_token" rows="2" 
                                          placeholder="Token de acesso (gerado após autenticação OAuth)"><?php echo htmlspecialchars($linkedin['access_token'] ?? ''); ?></textarea>
                                <small class="text-muted">Token com scope <code>w_member_social</code> (obrigatório para publicar)</small>
                            </div>
                            
                            <!-- Escolha onde publicar -->
                            <div class="form-group">
                                <label>📍 Publicar em:</label>
                            <div class="publish-target-selector">
                                    <label class="publish-target-option">
                                        <input type="radio" name="linkedin_publish_target" value="person" 
                                               <?php echo ($linkedin['publish_target'] ?? 'person') === 'person' ? 'checked' : ''; ?>
                                               onchange="toggleLinkedInTarget()">
                                        <i class="ph ph-user"></i>
                                        <strong>Perfil Pessoal</strong><br>
                                        <small class="text-muted">Publicar no seu perfil</small>
                                    </label>
                                    <label class="publish-target-option">
                                        <input type="radio" name="linkedin_publish_target" value="organization" 
                                               <?php echo ($linkedin['publish_target'] ?? '') === 'organization' ? 'checked' : ''; ?>
                                               onchange="toggleLinkedInTarget()">
                                        <i class="ph ph-buildings"></i>
                                        <strong>Página da Empresa</strong><br>
                                        <small class="text-muted">Publicar na Company Page</small>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Person URN (para perfil pessoal) -->
                            <div class="form-group" id="person-urn-group">
                                <label>Person URN (ID do Perfil) <span class="required">*</span></label>
                                <input type="text" name="linkedin_person_urn" 
                                       value="<?php echo htmlspecialchars($linkedin['person_urn'] ?? ''); ?>"
                                       placeholder="Ex: urn:li:person:38583269">
                                <small class="text-muted">
                                    <strong>💡 Como obter:</strong> Na URL do seu perfil <code>linkedin.com/in/seu-nome-<strong>38583269</strong>/</code> → Person URN = <code>urn:li:person:<strong>38583269</strong></code>
                                </small>
                            </div>
                            
                            <!-- Organization URN (para página da empresa) -->
                            <div class="form-group" id="organization-urn-group" style="display: none;">
                                <label>Organization URN (ID da Página) <span class="required">*</span></label>
                                <input type="text" name="linkedin_organization_urn" 
                                       value="<?php echo htmlspecialchars($linkedin['organization_urn'] ?? ''); ?>"
                                       placeholder="Ex: urn:li:organization:110352846">
                                <small class="text-muted">
                                    <strong>💡 Como obter:</strong> Na URL da página <code>linkedin.com/company/<strong>110352846</strong>/</code> → Organization URN = <code>urn:li:organization:<strong>110352846</strong></code>
                                    <br>
                                    <strong>⚠️ Importante:</strong> Você precisa ser admin da página para publicar nela.
                                </small>
                            </div>
                            
                            <div class="form-group" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <span class="status-indicator <?php echo (!empty($linkedin['access_token']) && !empty($linkedin['person_urn'])) ? 'connected' : 'disconnected'; ?>">
                                    <?php echo (!empty($linkedin['access_token']) && !empty($linkedin['person_urn'])) ? '✅ Configurado' : '❌ Incompleto'; ?>
                                </span>
                                
                                <?php if (!empty($linkedin['client_id']) && !empty($linkedin['client_secret'])): ?>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="autenticarLinkedin()">
                                        🔑 Gerar Token (Manual)
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (!empty($linkedin['access_token'])): ?>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="obterPersonUrn()">
                                        👤 Obter Person URN
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="testarLinkedIn()">
                                        🧪 Testar Conexão
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="help-section">
                                <h4>📖 Como configurar o LinkedIn (OAuth 2.0)</h4>
                                <ol>
                                    <li>Acesse <a href="https://www.linkedin.com/developers/" target="_blank">LinkedIn Developers</a> e crie um app</li>
                                    <li>Em <strong>"Products"</strong>, solicite acesso ao <strong>"Share on LinkedIn"</strong> (concede scope <code>w_member_social</code>)</li>
                                    <li>Copie o <strong>Client ID</strong> e <strong>Client Secret</strong> da aba "Auth"</li>
                                    <li>Adicione a URL de callback: <code><?php echo BASE_URL; ?>/api/oauth/linkedin-callback.php</code></li>
                                    <li>Clique em <strong>"Autenticar via OAuth"</strong> para gerar o Access Token</li>
                                    <li>Clique em <strong>"Obter Person URN"</strong> para buscar seu ID automaticamente</li>
                                </ol>
                                <p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                                    <strong>⚠️ Importante:</strong> O token de acesso expira em 60 dias. Renove periodicamente.
                                    <br>
                                    <strong>📚 Referência:</strong> <a href="https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/share-on-linkedin" target="_blank">Documentação Oficial - Share on LinkedIn</a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Facebook -->
                <?php 
                    $fb = $redes['facebook'] ?? ['ativo' => false, 'client_id' => '', 'client_secret' => '', 'access_token' => '']; 
                    $fbExtras = !empty($fb['dados_extras']) ? json_decode($fb['dados_extras'], true) : [];
                ?>
                <div class="card network-card facebook <?php echo $fb['ativo'] ? 'active' : ''; ?>" style="margin-top: 20px;">
                    <div class="card-body">
                        <div class="network-header">
                            <div class="network-icon"><i class="ph ph-facebook-logo"></i></div>
                            <div class="network-title">
                                <h3>Facebook</h3>
                                <p>Publique em páginas do Facebook</p>
                            </div>
                            <div class="network-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="facebook_ativo" value="1" 
                                           <?php echo $fb['ativo'] ? 'checked' : ''; ?>
                                           onchange="toggleNetwork('facebook', this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>App ID</label>
                                    <input type="text" name="facebook_client_id" value="<?php echo htmlspecialchars($fb['client_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>App Secret</label>
                                    <div class="password-container">
                                        <input type="password" name="facebook_client_secret" value="<?php echo htmlspecialchars($fb['client_secret'] ?? ''); ?>">
                                        <span class="password-toggle" onclick="togglePasswordVisibility(this, 'facebook_client_secret')"><i class="ph ph-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                             <label>Page ID (ID da Página)</label>
                             <input type="text" name="facebook_page_id" value="<?php echo htmlspecialchars($fbExtras['page_id'] ?? ''); ?>" placeholder="Ex: 1234567890">
                        </div>
                        <div class="form-group">
                            <label>Page Access Token</label>
                            <textarea name="facebook_access_token" rows="2"><?php echo htmlspecialchars($fb['access_token'] ?? ''); ?></textarea>
                            <small class="text-muted">Token de acesso da página (não do usuário). Deve ter permissão <code>pages_manage_posts</code>.</small>
                        </div>
                    </div>
                </div>

                <!-- Instagram -->
                <?php 
                    $insta = $redes['instagram'] ?? ['ativo' => false, 'client_id' => '', 'client_secret' => '', 'access_token' => ''];
                    $instaExtras = !empty($insta['dados_extras']) ? json_decode($insta['dados_extras'], true) : [];
                    $social_agent_instruction_value = $instaExtras['social_agent_instruction'] ?? '';
                ?>
                <div class="card network-card instagram <?php echo $insta['ativo'] ? 'active' : ''; ?>" style="margin-top: 20px;">
                    <div class="card-body">
                        <div class="network-header">
                            <div class="network-icon"><i class="ph ph-instagram-logo"></i></div>
                            <div class="network-title">
                                <h3>Instagram</h3>
                                <p>Publique posts e reels no Instagram Business</p>
                            </div>
                            <div class="network-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="instagram_ativo" value="1" 
                                           <?php echo $insta['ativo'] ? 'checked' : ''; ?>
                                           onchange="toggleNetwork('instagram', this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                         <div class="form-row">
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>App ID (Facebook App)</label>
                                    <input type="text" name="instagram_client_id" value="<?php echo htmlspecialchars($insta['client_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>App Secret</label>
                                    <div class="password-container">
                                        <input type="password" name="instagram_client_secret" value="<?php echo htmlspecialchars($insta['client_secret'] ?? ''); ?>">
                                        <span class="password-toggle" onclick="togglePasswordVisibility(this, 'instagram_client_secret')"><i class="ph ph-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                             <label>Instagram Account ID (Business Account ID)</label>
                             <input type="text" name="instagram_account_id" value="<?php echo htmlspecialchars($instaExtras['account_id'] ?? ''); ?>" placeholder="Ex: 178414000...">
                             <small class="text-muted">ID da conta Business do Instagram (vinculada à página do Facebook).</small>
                        </div>
                        <div class="form-group">
                            <label>Access Token</label>
                            <textarea name="instagram_access_token" rows="2"><?php echo htmlspecialchars($insta['access_token'] ?? ''); ?></textarea>
                            <small class="text-muted">Mesmo token do Facebook, com permissões <code>instagram_basic</code>, <code>instagram_content_publish</code>.</small>
                        </div>

                        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

                        <div class="form-group">
                            <label>🤖 Instrução para Agente Social (IA)</label>
                            <textarea name="social_agent_instruction" rows="3" placeholder="Tom de voz, hashtags padrão, estilo..."><?php echo htmlspecialchars($social_agent_instruction_value); ?></textarea>
                            <small class="form-text">Instrução base para a IA gerar legendas para Instagram e Facebook.</small>
                        </div>
                    </div>
                </div>

                <!-- TikTok -->
                <?php 
                    $tiktok = $redes['tiktok'] ?? ['ativo' => false, 'client_id' => '', 'client_secret' => '', 'access_token' => ''];
                ?>
                <div class="card network-card tiktok <?php echo $tiktok['ativo'] ? 'active' : ''; ?>" style="margin-top: 20px; border-left-color: #000;">
                    <div class="card-body">
                         <div class="network-header">
                            <div class="network-icon"><i class="ph ph-tiktok-logo"></i></div>
                            <div class="network-title">
                                <h3>TikTok</h3>
                                <p>Publique vídeos no TikTok</p>
                            </div>
                            <div class="network-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="tiktok_ativo" value="1" 
                                           <?php echo $tiktok['ativo'] ? 'checked' : ''; ?>
                                           onchange="toggleNetwork('tiktok', this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                         <div class="form-row">
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>Client Key</label>
                                    <input type="text" name="tiktok_client_id" value="<?php echo htmlspecialchars($tiktok['client_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>Client Secret</label>
                                    <div class="password-container">
                                        <input type="password" name="tiktok_client_secret" value="<?php echo htmlspecialchars($tiktok['client_secret'] ?? ''); ?>">
                                        <span class="password-toggle" onclick="togglePasswordVisibility(this, 'tiktok_client_secret')"><i class="ph ph-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Access Token</label>
                            <textarea name="tiktok_access_token" rows="2"><?php echo htmlspecialchars($tiktok['access_token'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- YouTube -->
                <?php 
                    $yt = $redes['youtube'] ?? ['ativo' => false, 'client_id' => '', 'client_secret' => '', 'access_token' => ''];
                    $ytExtras = !empty($yt['dados_extras']) ? json_decode($yt['dados_extras'], true) : [];
                ?>
                <div class="card network-card youtube <?php echo $yt['ativo'] ? 'active' : ''; ?>" style="margin-top: 20px; border-left-color: #FF0000;">
                    <div class="card-body">
                         <div class="network-header">
                            <div class="network-icon"><i class="ph ph-youtube-logo"></i></div>
                            <div class="network-title">
                                <h3>YouTube</h3>
                                <p>Publique vídeos e shorts</p>
                            </div>
                            <div class="network-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="youtube_ativo" value="1" 
                                           <?php echo $yt['ativo'] ? 'checked' : ''; ?>
                                           onchange="toggleNetwork('youtube', this.checked)">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                         <div class="form-row">
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>Client ID</label>
                                    <input type="text" name="youtube_client_id" value="<?php echo htmlspecialchars($yt['client_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-col-6">
                                <div class="form-group">
                                    <label>Client Secret</label>
                                    <div class="password-container">
                                        <input type="password" name="youtube_client_secret" value="<?php echo htmlspecialchars($yt['client_secret'] ?? ''); ?>">
                                        <span class="password-toggle" onclick="togglePasswordVisibility(this, 'youtube_client_secret')"><i class="ph ph-eye"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                             <label>Broadcast ID (Opcional) / Channel ID</label>
                             <input type="text" name="youtube_channel_id" value="<?php echo htmlspecialchars($ytExtras['channel_id'] ?? ''); ?>">
                        </div>
                         <div class="form-group">
                            <label>Refresh Token <span class="required">*</span></label>
                            <input type="password" name="youtube_refresh_token" value="<?php echo htmlspecialchars($ytExtras['refresh_token'] ?? ''); ?>">
                            <small class="text-muted">Necessário para publicar offline (o Access Token expira rápido).</small>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span id="btnSaveText">💾 Salvar Configurações</span>
                        <span id="btnSaveLoader" style="display:none;">Salvando...</span>
                    </button>
                </div>
            </form>
            
            <!-- Preview de Métricas -->
            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3>Métricas</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Aqui você encontra um resumo das publicações, interações sociais e acessos ao site.</p>
                    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end; margin-top:10px;">
                        <div class="form-group" style="margin:0;">
                            <label>De</label>
                            <input type="date" name="from" value="<?php echo htmlspecialchars($periodFrom); ?>">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Até</label>
                            <input type="date" name="to" value="<?php echo htmlspecialchars($periodTo); ?>">
                        </div>
                        <div style="margin:0 0 4px;">
                            <button type="submit" class="btn btn-sm btn-secondary">Filtrar período</button>
                        </div>
                    </form>
                    <div class="metrics-preview">
                        <div class="metric-box">
                            <div class="value"><?php echo number_format($totalPublicacoes); ?></div>
                            <div class="label">Publicações</div>
                        </div>
                        <div class="metric-box">
                            <div class="value"><?php echo number_format($siteVisitsTotal); ?></div>
                            <div class="label">Acessos (total)</div>
                        </div>
                        <div class="metric-box">
                            <div class="value"><?php echo number_format($siteVisitsToday); ?></div>
                            <div class="label">Acessos (hoje)</div>
                        </div>
                        <div class="metric-box">
                            <div class="value"><?php echo number_format($siteVisitsPeriod); ?></div>
                            <div class="label">Acessos (período)</div>
                        </div>
                        <div class="metric-box">
                            <div class="value"><?php echo number_format($likes); ?></div>
                            <div class="label">Curtidas</div>
                        </div>
                    </div>

                    <?php if (!empty($topPages)): ?>
                        <div style="margin-top:12px;">
                            <strong>Top páginas (7d)</strong>
                            <ol style="margin:8px 0 0 18px; padding:0;">
                                <?php foreach ($topPages as $p): ?>
                                    <li><?php echo htmlspecialchars($p['path']); ?> — <?php echo (int)$p['cnt']; ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($topPagesPeriod)): ?>
                        <div style="margin-top:12px;">
                            <strong>Top páginas (período)</strong>
                            <ol style="margin:8px 0 0 18px; padding:0;">
                                <?php foreach ($topPagesPeriod as $p): ?>
                                    <li><?php echo htmlspecialchars($p['path']); ?> — <?php echo (int)$p['cnt']; ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3>Métricas por publicação</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Último snapshot coletado por publicação (worker <code>scripts/metrics_worker.php</code>).</p>
                    <?php if (!empty($publicacoesMetricas)): ?>
                        <div style="overflow-x:auto;">
                            <table class="admin-table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Rede</th>
                                        <th style="text-align:left; padding:8px;">Artigo</th>
                                        <th style="text-align:left; padding:8px;">Publicado</th>
                                        <th style="text-align:right; padding:8px;">Alcance</th>
                                        <th style="text-align:right; padding:8px;">Views</th>
                                        <th style="text-align:right; padding:8px;">Curtidas</th>
                                        <th style="text-align:right; padding:8px;">Coment.</th>
                                        <th style="text-align:right; padding:8px;">Compart.</th>
                                        <th style="text-align:left; padding:8px;">Coleta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($publicacoesMetricas as $pm): ?>
                                        <?php $temMetrica = !empty($pm['data_coleta']); ?>
                                        <tr>
                                            <td style="padding:8px;"><?php echo htmlspecialchars(ucfirst((string)$pm['rede'])); ?></td>
                                            <td style="padding:8px;">
                                                <?php if (!empty($pm['slug'])): ?>
                                                    <a href="../artigo.php?slug=<?php echo urlencode($pm['slug']); ?>" target="_blank"><?php echo htmlspecialchars($pm['titulo'] ?? ('#' . $pm['artigo_id'])); ?></a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($pm['titulo'] ?? ('#' . $pm['artigo_id'])); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:8px;"><?php echo $pm['publicado_em'] ? htmlspecialchars(formatDate($pm['publicado_em'], 'd/m/Y H:i')) : '-'; ?></td>
                                            <td style="padding:8px; text-align:right;"><?php echo $temMetrica ? number_format((int)$pm['alcance']) : '-'; ?></td>
                                            <td style="padding:8px; text-align:right;"><?php echo $temMetrica ? number_format((int)$pm['visualizacoes']) : '-'; ?></td>
                                            <td style="padding:8px; text-align:right;"><?php echo $temMetrica ? number_format((int)$pm['curtidas']) : '-'; ?></td>
                                            <td style="padding:8px; text-align:right;"><?php echo $temMetrica ? number_format((int)$pm['comentarios']) : '-'; ?></td>
                                            <td style="padding:8px; text-align:right;"><?php echo $temMetrica ? number_format((int)$pm['compartilhamentos']) : '-'; ?></td>
                                            <td style="padding:8px;"><?php echo $temMetrica ? htmlspecialchars(formatDate($pm['data_coleta'], 'd/m/Y H:i')) : 'sem coleta'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Nenhuma publicação encontrada.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-top: 30px;">
                <div class="card-header">
                    <h3>LinkedIn: atualizar URL do post</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Atualize a URL salva de um post e, se quiser, abra o Post Inspector do LinkedIn para re-scrape do card.</p>
                    <?php if (!empty($linkedinPublicacoes)): ?>
                        <div style="overflow-x:auto;">
                            <table class="admin-table" style="width:100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">ID</th>
                                        <th style="text-align:left; padding:8px;">Artigo</th>
                                        <th style="text-align:left; padding:8px;">URL salva</th>
                                        <th style="text-align:left; padding:8px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($linkedinPublicacoes as $pub): ?>
                                        <?php
                                            $slug = $pub['slug'] ?? '';
                                            $fallbackUrl = $slug ? (BASE_URL . routeArtigo($slug)) : '';
                                            $savedUrl = $pub['url_post'] ?? '';
                                            $inspectUrl = $savedUrl ?: $fallbackUrl;
                                        ?>
                                        <tr>
                                            <td style="padding:8px;"><?php echo (int)$pub['id']; ?></td>
                                            <td style="padding:8px;">
                                                <?php echo htmlspecialchars($pub['titulo'] ?? ('Artigo #' . (int)$pub['artigo_id'])); ?>
                                            </td>
                                            <td style="padding:8px;">
                                                <input type="url" class="url-post-input" data-publicacao-id="<?php echo (int)$pub['id']; ?>"
                                                       value="<?php echo htmlspecialchars($savedUrl ?: $fallbackUrl); ?>"
                                                       style="width:100%; min-width:260px;">
                                            </td>
                                            <td style="padding:8px; display:flex; gap:8px; flex-wrap:wrap;">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="atualizarUrlPublicacao(<?php echo (int)$pub['id']; ?>)">Salvar URL</button>
                                                <?php if (!empty($inspectUrl)): ?>
                                                    <a class="btn btn-sm btn-secondary" target="_blank"
                                                       href="https://www.linkedin.com/post-inspector/inspect/?url=<?php echo urlencode($inspectUrl); ?>">
                                                        Re-scrapear
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Nenhuma publicação do LinkedIn encontrada.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
    <script>
        // Toggle password visibility
        // Toggle password visibility
        function togglePasswordVisibility(btn, inputName) {
            var input = document.querySelector('input[name="' + inputName + '"]');
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'ph ph-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'ph ph-eye';
            }
        }

        // Toggle network config visibility
        function toggleNetwork(rede, ativo) {
            var card = document.querySelector('.network-card.' + rede);
            
            if (ativo) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        }
        
        // Salvar configurações
        document.getElementById('redesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            WVProgress.show('Salvando configurações...', 'Aguarde um momento enquanto os dados são processados.');
            
            fetch('../api/redes-sociais.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                WVProgress.hide();
                
                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.className = 'message message-success';
                    messageDiv.textContent = '✅ ' + data.message;
                } else {
                    messageDiv.className = 'message message-error';
                    messageDiv.textContent = '❌ ' + data.message;
                }
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(error => {
                WVProgress.hide();
                
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-error';
                messageDiv.textContent = '❌ Erro de conexão: ' + error.message;
            });
        });
        
        // Autenticação LinkedIn via OAuth 2.0
        function autenticarLinkedin() {
            var clientId = document.querySelector('input[name="linkedin_client_id"]').value;
            var clientSecret = document.querySelector('input[name="linkedin_client_secret"]').value;
            
            if (!clientId || !clientSecret) {
                alert('⚠️ Preencha o Client ID e Client Secret antes de autenticar.');
                return;
            }
            
            // Mostrar instruções para gerar token manualmente
            var instrucoes = `📋 COMO GERAR O ACCESS TOKEN:

1. Acesse: https://www.linkedin.com/developers/apps
2. Selecione seu app "${clientId.substring(0, 8)}..."
3. Vá na aba "Auth"
4. Role até "OAuth 2.0 tools" 
5. Clique em "Generate token"
6. Selecione os scopes:
   ✅ w_member_social
   ✅ openid  
   ✅ profile
7. Clique em "Request access token"
8. Copie o Access Token gerado
9. Cole no campo "Access Token" aqui

⚠️ O token expira em 60 dias.

Deseja abrir o LinkedIn Developer Portal agora?`;
            
            if (confirm(instrucoes)) {
                window.open('https://www.linkedin.com/developers/apps', '_blank');
            }
        }
        
        // OAuth automático (backup - requer redirect_uri configurado)
        function autenticarLinkedinOAuth() {
            var clientId = document.querySelector('input[name="linkedin_client_id"]').value;
            var clientSecret = document.querySelector('input[name="linkedin_client_secret"]').value;
            
            if (!clientId || !clientSecret) {
                alert('⚠️ Preencha o Client ID e Client Secret antes de autenticar.');
                return;
            }
            
            // Salvar credenciais primeiro
            var formData = new FormData(document.getElementById('redesForm'));
            formData.append('csrf_token', document.querySelector('#redesForm input[name="csrf_token"]').value);
            fetch('../api/redes-sociais.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                // Gerar state seguro
                var array = new Uint32Array(2);
                window.crypto.getRandomValues(array);
                var state = array[0].toString(36) + array[1].toString(36);
                
                // Salvar state em cookie (mais confiável que sessionStorage)
                document.cookie = 'linkedin_oauth_state=' + state + '; path=/; max-age=600; SameSite=Lax';
                
                // URL de callback
                var redirectUri = '<?php echo BASE_URL; ?>/api/oauth/linkedin-callback.php';
                
                // Construir URL de autorização
                var authUrl = 'https://www.linkedin.com/oauth/v2/authorization?' +
                    'response_type=code' +
                    '&client_id=' + encodeURIComponent(clientId) +
                    '&redirect_uri=' + encodeURIComponent(redirectUri) +
                    '&state=' + encodeURIComponent(state) +
                    '&scope=' + encodeURIComponent('openid profile w_member_social');
                
                console.log('OAuth URL:', authUrl);
                console.log('Redirect URI:', redirectUri);
                console.log('State:', state);
                
                window.location.href = authUrl;
            });
        }
        
        // Obter Person URN automaticamente
        function obterPersonUrn() {
            var accessToken = document.querySelector('textarea[name="linkedin_access_token"]').value;
            
            if (!accessToken) {
                alert('⚠️ Access Token necessário para obter o Person URN.');
                return;
            }
            
            WVProgress.show('Buscando URN...', 'Aguarde enquanto consultamos a API do LinkedIn.');
            
            fetch('../api/linkedin.php?action=get_person_urn', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ access_token: accessToken })
            })
            .then(response => response.json())
            .then(data => {
                WVProgress.hide();
                
                if (data.success && data.person_urn) {
                    document.querySelector('input[name="linkedin_person_urn"]').value = data.person_urn;
                    alert('✅ Person URN obtido com sucesso!\n\n' + data.person_urn + '\n\nClique em "Salvar Configurações" para persistir.');
                } else if (data.manual_instructions) {
                    alert(`⚠️ Seu token só tem scope w_member_social.\n\n📋 COMO OBTER O PERSON URN MANUALMENTE:\n\n1. Acesse seu perfil no LinkedIn\n2. Olhe a URL: linkedin.com/in/SEU-ID/\n3. O Person URN é: urn:li:person:SEU-ID\n\nCole o Person URN no campo e salve.`);
                } else {
                    alert('❌ Erro ao obter Person URN:\n' + (data.message || 'Token inválido ou expirado'));
                }
            })
            .catch(error => {
                WVProgress.hide();
                alert('❌ Erro de conexão: ' + error.message);
            });
        }
        
        // Testar conexão LinkedIn
        function testarLinkedIn() {
            var accessToken = document.querySelector('textarea[name="linkedin_access_token"]').value;
            var personUrn = document.querySelector('input[name="linkedin_person_urn"]').value;
            
            if (!accessToken) {
                alert('⚠️ Access Token necessário para testar.');
                return;
            }
            
            if (!personUrn || !personUrn.startsWith('urn:li:person:')) {
                alert('⚠️ Person URN necessário para testar.\n\nFormato: urn:li:person:SEU-ID\n\nObtenha da URL do seu perfil LinkedIn.');
                return;
            }
            
            WVProgress.show('Testando conexão...', 'Validando credenciais com o LinkedIn.');
            
            fetch('../api/linkedin.php?action=test_connection', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ access_token: accessToken, person_urn: personUrn })
            })
            .then(response => response.json())
            .then(data => {
                WVProgress.hide();
                
                if (data.success) {
                    var msg = '✅ Conexão OK!\n\n' +
                        'Método: ' + (data.message || 'Token válido') + '\n' +
                        'Person URN: ' + (data.profile?.id || personUrn) + '\n';
                    
                    if (data.profile?.name && data.profile.name !== 'Configurado manualmente') {
                        msg += 'Nome: ' + data.profile.name + '\n';
                    }
                    if (data.profile?.email) {
                        msg += 'Email: ' + data.profile.email + '\n';
                    }
                    msg += '\n🎉 Pronto para publicar no LinkedIn!';
                    alert(msg);
                } else {
                    alert('❌ Falha na conexão:\n' + (data.message || 'Token inválido') + (data.tip ? '\n\n💡 ' + data.tip : ''));
                }
            })
            .catch(error => {
                WVProgress.hide();
                alert('❌ Erro de conexão: ' + error.message);
            });
        }

        function atualizarUrlPublicacao(publicacaoId) {
            var input = document.querySelector('.url-post-input[data-publicacao-id="' + publicacaoId + '"]');
            if (!input) return;
            var url = input.value.trim();
            if (!url) {
                alert('⚠️ Informe uma URL válida.');
                return;
            }

            WVProgress.show('Atualizando URL...', 'Aguarde um momento.');

            var formData = new FormData();
            formData.append('csrf_token', document.querySelector('#redesForm input[name="csrf_token"]').value);
            formData.append('publicacao_id', publicacaoId);
            formData.append('url_post', url);

            fetch('../api/redes-sociais.php?action=update_publicacao_url', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                WVProgress.hide();
                if (data.success) {
                    alert('✅ ' + data.message);
                } else {
                    alert('❌ ' + (data.message || 'Erro ao atualizar URL'));
                }
            })
            .catch(error => {
                WVProgress.hide();
                alert('❌ Erro de conexão: ' + error.message);
            });
        }
        
        
        // Toggle entre Person URN e Organization URN
        function toggleLinkedInTarget() {
            var target = document.querySelector('input[name="linkedin_publish_target"]:checked').value;
            var personGroup = document.getElementById('person-urn-group');
            var orgGroup = document.getElementById('organization-urn-group');
            
            // Atualizar estilos das opções
            document.querySelectorAll('.publish-target-option').forEach(function(opt) {
                opt.style.borderColor = '#ddd';
                opt.style.background = 'white';
            });
            
            var selectedOption = document.querySelector('input[name="linkedin_publish_target"]:checked').closest('.publish-target-option');
            if (selectedOption) {
                selectedOption.style.borderColor = '#0077B5';
                selectedOption.style.background = 'rgba(0, 119, 181, 0.05)';
            }
            
            if (target === 'person') {
                personGroup.style.display = 'block';
                orgGroup.style.display = 'none';
            } else {
                personGroup.style.display = 'none';
                orgGroup.style.display = 'block';
            }
        }
        
        // Inicializar ao carregar a página
        document.addEventListener('DOMContentLoaded', function() {
            toggleLinkedInTarget();
        });
    </script>
</body>
</html>

