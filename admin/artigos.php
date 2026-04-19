<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Conteúdos/Artigos
 * Com suporte a agendamento, múltiplos formatos de imagem e redes sociais
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$filtroStatus = $_GET['status'] ?? '';

// Buscar categorias de artigos
$stmt = $pdo->query("SELECT * FROM categorias_artigos WHERE ativo = TRUE ORDER BY ordem ASC");
$categorias = $stmt->fetchAll();

// Buscar configurações de redes sociais
$stmt = $pdo->query("SELECT * FROM config_redes_formatos WHERE ativo = true ORDER BY ordem ASC");
$redesSociais = $stmt->fetchAll();

// Buscar artigo para edição
$artigo = null;
$artigoI18n = ['pt' => null, 'en' => null, 'es' => null];
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM artigos WHERE id = ?");
    $stmt->execute([$id]);
    $artigo = $stmt->fetch();
    
    // Decodificar redes_destino se existir
    if ($artigo && $artigo['redes_destino']) {
        $artigo['redes_destino_array'] = json_decode($artigo['redes_destino'], true) ?? [];
    }

    // Status i18n/SEO (EN/ES)
    if ($artigo) {
        try {
            $stmt = $pdo->prepare("SELECT lang, slug, status_traducao, generated_at, updated_at FROM artigos_i18n WHERE artigo_id = ? AND lang IN ('pt','en','es')");
            $stmt->execute([$artigo['id']]);
            foreach ($stmt->fetchAll() as $row) {
                $artigoI18n[$row['lang']] = $row;
            }
        } catch (Exception $e) {
            // ignore if table not present
        }
    }
}

// Buscar lista de artigos com filtro
$artigos = [];
$artigosI18nMap = []; // [artigo_id][lang] => status_traducao
$artigosMissing = ['pt' => [], 'en' => [], 'es' => []];
if ($action === 'list') {
    $sql = "SELECT a.*, ca.nome as categoria_nome 
            FROM artigos a 
            LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id 
            WHERE 1=1";
    $params = [];
    
    if ($filtroStatus) {
        $sql .= " AND a.status_publicacao = ?";
        $params[] = $filtroStatus;
    }
    
    $sql .= " ORDER BY 
        CASE a.status_publicacao 
            WHEN 'agendado' THEN 1 
            WHEN 'rascunho' THEN 2 
            WHEN 'publicado' THEN 3 
            WHEN 'falha' THEN 4 
        END,
        a.data_agendamento ASC NULLS LAST,
        a.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artigos = $stmt->fetchAll();

    // Mapear status EN/ES para badges na listagem
    try {
        $stmt = $pdo->query("SELECT artigo_id, lang, status_traducao FROM artigos_i18n WHERE lang IN ('pt','en','es')");
        foreach ($stmt->fetchAll() as $row) {
            $artigosI18nMap[(int)$row['artigo_id']][$row['lang']] = $row['status_traducao'];
        }
    } catch (Exception $e) {
        $artigosI18nMap = [];
    }

    foreach ($artigos as $a) {
        if (($a['status_publicacao'] ?? '') !== 'publicado') continue;
        $st = $artigosI18nMap[(int)$a['id']] ?? [];
        if (empty($st['pt'])) $artigosMissing['pt'][] = (int)$a['id'];
        if (empty($st['en'])) $artigosMissing['en'][] = (int)$a['id'];
        if (empty($st['es'])) $artigosMissing['es'][] = (int)$a['id'];
    }
}

// Contadores por status
$contadores = [
    'total' => 0,
    'rascunho' => 0,
    'agendado' => 0,
    'publicado' => 0,
    'falha' => 0
];
$stmtCount = $pdo->query("SELECT status_publicacao, COUNT(*) as total FROM artigos GROUP BY status_publicacao");
while ($row = $stmtCount->fetch()) {
    $status = $row['status_publicacao'] ?? 'rascunho';
    $contadores[$status] = (int)$row['total'];
    $contadores['total'] += (int)$row['total'];
}

// Configurações de IA
$iaInstrucoes = getConfig('ia_instrucoes') ?? '';
$geminiApiKey = getConfig('gemini_api_key') ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conteúdos - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .social-preview-caption { background: #fff; border: 1px solid #e2e8f0; color: #1e293b; pointer-events: none; }
    </style>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <?php if ($action === 'list'): ?>
                <!-- LISTAGEM DE CONTEÚDOS -->
                <div class="page-header">
                    <h1><i class="ph ph-newspaper"></i> Conteúdos</h1>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <?php if (!empty($artigosMissing['pt']) || !empty($artigosMissing['en']) || !empty($artigosMissing['es'])): ?>
                            <button type="button" class="btn btn-secondary" id="btnBulkI18nArtigos" onclick="gerarI18nSeoBulkArtigos()">
                                <i class="ph ph-globe"></i> Gerar traduções (um por vez)
                            </button>
                        <?php endif; ?>
                        <a href="?action=new" class="btn btn-primary"><i class="ph ph-plus-circle"></i> Novo Conteúdo</a>
                    </div>
                </div>
                
                <!-- Filtros por Status -->
                <div class="status-filters">
                    <a href="?action=list" class="status-filter <?php echo !$filtroStatus ? 'active' : ''; ?>">
                        Todos <span class="count"><?php echo $contadores['total']; ?></span>
                    </a>
                    <a href="?action=list&status=rascunho" class="status-filter <?php echo $filtroStatus === 'rascunho' ? 'active' : ''; ?>">
                        Rascunhos <span class="count"><?php echo $contadores['rascunho']; ?></span>
                    </a>
                    <a href="?action=list&status=agendado" class="status-filter <?php echo $filtroStatus === 'agendado' ? 'active' : ''; ?>">
                        Agendados <span class="count"><?php echo $contadores['agendado']; ?></span>
                    </a>
                    <a href="?action=list&status=publicado" class="status-filter <?php echo $filtroStatus === 'publicado' ? 'active' : ''; ?>">
                        Publicados <span class="count"><?php echo $contadores['publicado']; ?></span>
                    </a>
                    <?php if ($contadores['falha'] > 0): ?>
                    <a href="?action=list&status=falha" class="status-filter <?php echo $filtroStatus === 'falha' ? 'active' : ''; ?>" style="border-color: #dc3545;">
                        Com Falha <span class="count"><?php echo $contadores['falha']; ?></span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="calendario.php" class="status-filter" style="margin-left: auto; background: var(--primary); color: white; border-color: var(--primary);">
                        <i class="ph ph-calendar"></i> Calendário
                    </a>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($artigos)): ?>
                            <div class="empty-state">
                                <p>Nenhum conteúdo cadastrado ainda.</p>
                                <a href="?action=new" class="btn btn-primary">Criar Primeiro Conteúdo</a>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
	                                    <tr>
	                                        <th style="width: 80px;">Mídia</th>
	                                        <th>Título</th>
	                                        <th>Categoria</th>
	                                        <th style="width: 120px;">Idiomas</th>
	                                        <th>Status</th>
	                                        <th>Agendamento</th>
	                                        <th>Redes</th>
	                                        <th style="width: 120px;">Ações</th>
	                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($artigos as $art): 
                                        $statusPub = $art['status_publicacao'] ?? 'rascunho';
                                        $redesDestino = json_decode($art['redes_destino'] ?? '[]', true);
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($art['imagem_1x1']): ?>
                                                    <img src="<?php echo UPLOAD_URL . $art['imagem_1x1']; ?>" 
                                                         alt="<?php echo htmlspecialchars($art['titulo']); ?>" 
                                                         class="table-thumb">
                                                <?php elseif ($art['imagem_principal']): ?>
                                                    <img src="<?php echo UPLOAD_URL . $art['imagem_principal']; ?>" 
                                                         alt="<?php echo htmlspecialchars($art['titulo']); ?>" 
                                                         class="table-thumb">
                                                <?php elseif ($art['tipo_midia'] === 'video'): ?>
                                                    <div class="table-thumb" style="background: #000; display: flex; align-items: center; justify-content: center;">
                                                        <span style="font-size: 24px;"><i class="ph ph-film-strip" style="color:white;"></i></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="table-thumb-placeholder"><i class="ph ph-camera"></i></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $art['id']; ?>">
                                                    <?php echo htmlspecialchars($art['titulo']); ?>
                                                </a>
                                                <?php if ($art['fonte_ia']): ?>
                                                    <span title="Gerado por IA"><i class="ph ph-robot"></i></span>
                                                <?php endif; ?>
                                            </td>
	                                            <td><?php echo htmlspecialchars($art['categoria_nome'] ?? '-'); ?></td>
	                                            <td>
	                                                <?php
	                                                    $st = $artigosI18nMap[(int)$art['id']] ?? [];
	                                                    $pt = $st['pt'] ?? null;
	                                                    $en = $st['en'] ?? null;
	                                                    $es = $st['es'] ?? null;
	                                                ?>
	                                                <span class="badge badge-<?php echo $pt ? 'success' : 'secondary'; ?>" title="PT-BR">
	                                                    PT<?php echo $pt ? '' : ' -'; ?>
	                                                </span>
	                                                <span class="badge badge-<?php echo $en ? 'success' : 'secondary'; ?>" title="EN">
	                                                    EN<?php echo $en ? '' : ' -'; ?>
	                                                </span>
	                                                <span class="badge badge-<?php echo $es ? 'success' : 'secondary'; ?>" title="ES">
	                                                    ES<?php echo $es ? '' : ' -'; ?>
	                                                </span>
	                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $statusPub; ?>">
                                                    <?php 
                                                    $statusLabels = [
                                                        'rascunho' => 'Rascunho',
                                                        'agendado' => 'Agendado',
                                                        'publicado' => 'Publicado',
                                                        'falha' => 'Falha'
                                                    ];
                                                    echo $statusLabels[$statusPub] ?? $statusPub;
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($art['data_agendamento']): ?>
                                                    <small><?php echo date('d/m/Y H:i', strtotime($art['data_agendamento'])); ?></small>
                                                    <?php if ($art['recorrencia_tipo'] && $art['recorrencia_tipo'] !== 'nenhuma'): ?>
                                                        <br><span class="badge badge-secondary" style="font-size: 10px;"><i class="ph ph-arrows-clockwise"></i> <?php echo ucfirst($art['recorrencia_tipo']); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($redesDestino)): 
                                                    $nomes = [];
                                                    foreach ($redesDestino as $rede) {
                                                        $redeId = $rede['rede'] ?? '';
                                                        if ($redeId === 'linkedin') $nomes[] = 'LinkedIn';
                                                        elseif ($redeId === 'instagram') $nomes[] = 'Instagram';
                                                        else $nomes[] = ucfirst($redeId);
                                                    }
                                                ?>
                                                    <small><?php echo implode(', ', $nomes); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $art['id']; ?>" class="btn-icon edit" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                                <button type="button" onclick="deletarArtigo(<?php echo $art['id']; ?>)" class="btn-icon delete" title="Excluir"><i class="ph ph-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
<?php else: ?>
                <!-- FORMULÁRIO DE CRIAÇÃO/EDIÇÃO -->
                <div class="page-header">
                    <h1><?php echo $artigo ? '<i class="ph ph-pencil-simple"></i> Editar Conteúdo' : '<i class="ph ph-plus-circle"></i> Novo Conteúdo'; ?></h1>
                    <a href="artigos.php" class="btn btn-secondary">← Voltar</a>
                </div>
                
                <div id="messageDiv" class="message" style="display: none;"></div>
                
                <form id="artigoForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" value="<?php echo $artigo['id'] ?? ''; ?>">
                    
                    <!-- Abas de Navegação -->
                    <div class="form-tabs">
                        <button type="button" class="form-tab active" data-tab="tab-ia">
                            <i class="ph ph-sparkle"></i> IA Assistant
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-info">
                            <i class="ph ph-info"></i> Informações
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-midias">
                            <i class="ph ph-image"></i> Mídia
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-traduzir">
                            <i class="ph ph-globe"></i> Traduções & SEO
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-publicar">
                            <i class="ph ph-rocket-launch"></i> Publicação
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-redes">
                            <i class="ph ph-share-network"></i> Redes
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-opcoes">
                            <i class="ph ph-gear"></i> Opções
                        </button>
                    </div>
                    
                    <!-- Tab 1: IA Assistant -->
                    <div class="form-tab-content active" id="tab-ia">
                        <?php if ($geminiApiKey): ?>
                        <div class="card">
                            <div class="card-header"><h3>Assistente de IA</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="prompt_texto"><i class="ph ph-text-t"></i> Prompt para Texto</label>
                                    <textarea id="prompt_texto" name="prompt_texto" rows="4" placeholder="Descreva o conteúdo que deseja criar..."><?php echo htmlspecialchars($artigo['prompt_texto'] ?? $_GET['prompt'] ?? ''); ?></textarea>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="gerarConteudoIA()">
                                    <span id="btnGerarTextoLabel"><i class="ph ph-sparkle"></i> Gerar Texto</span>
                                    <span id="btnGerarTextoLoader" style="display:none;">Gerando...</span>
                                </button>
                                
                                <hr>
                                
                                <div class="form-group">
                                    <label for="prompt_imagem"><i class="ph ph-image"></i> Prompt para Imagem</label>
                                    <textarea id="prompt_imagem" name="prompt_imagem" rows="3" placeholder="Descreva a imagem desejada..."><?php echo htmlspecialchars($artigo['prompt_imagem'] ?? ''); ?></textarea>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="gerarImagemIA()">
                                    <span id="btnGerarImagemLabel"><i class="ph ph-image"></i> Gerar Imagem</span>
                                    <span id="btnGerarImagemLoader" style="display:none;">Gerando...</span>
                                </button>
                                
                                <div class="image-preview-container" id="imagePreviewContainer" style="<?php echo ($artigo && $artigo['imagem_1x1']) ? '' : 'display:none;'; ?>">
                                    <div class="image-preview-box">
                                        <?php if ($artigo['imagem_1x1'] ?? false): ?>
                                            <img src="<?php echo UPLOAD_URL . $artigo['imagem_1x1']; ?>" alt="Imagem do post" id="preview-imagem-ia">
                                        <?php else: ?>
                                            <span class="placeholder" id="preview-imagem-ia"><i class="ph ph-image"></i></span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="imagem_1x1" id="imagem_1x1" value="<?php echo htmlspecialchars($artigo['imagem_1x1'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            ⚠️ Configure sua API Key do Gemini em <a href="configuracoes.php">Configurações</a> para usar o assistente de IA.
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab 2: Informações -->
                    <div class="form-tab-content" id="tab-info">
                        <div class="card">
                            <div class="card-header"><h3>Informações do Conteúdo</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="titulo">Título *</label>
                                    <input type="text" id="titulo" name="titulo" required value="<?php echo htmlspecialchars($artigo['titulo'] ?? ''); ?>" onkeyup="gerarSlug()">
                                </div>
                                <div class="form-group">
                                    <label for="slug">Slug (URL)</label>
                                    <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($artigo['slug'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="categoria_id">Categoria</label>
                                    <select id="categoria_id" name="categoria_id">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($artigo['categoria_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="resumo">Resumo</label>
                                    <textarea id="resumo" name="resumo" rows="3"><?php echo htmlspecialchars($artigo['resumo'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="conteudo">Conteúdo *</label>
                                    <textarea id="conteudo" name="conteudo" rows="10"><?php echo htmlspecialchars($artigo['conteudo'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">
                            <span id="btnSaveText"><i class="ph ph-floppy-disk"></i> Salvar Conteúdo</span>
                            <span id="btnSaveLoader" style="display:none;">Salvando...</span>
                        </button>
                    </div>
                    
                    <!-- Tab 3: Mídia -->
                    <div class="form-tab-content" id="tab-midias">
                        <div class="card">
                            <div class="card-header"><h3>Tipo de Mídia</h3></div>
                            <div class="card-body">
                                <div class="media-type-selector">
                                    <div class="media-type-option <?php echo ($artigo['tipo_midia'] ?? 'imagem') === 'imagem' ? 'active' : ''; ?>" onclick="selecionarTipoMidia('imagem')">
                                        <div class="icon"><i class="ph ph-image"></i></div>
                                        <div class="label">Imagem</div>
                                    </div>
                                    <div class="media-type-option <?php echo ($artigo['tipo_midia'] ?? '') === 'video' ? 'active' : ''; ?>" onclick="selecionarTipoMidia('video')">
                                        <div class="icon"><i class="ph ph-film-strip"></i></div>
                                        <div class="label">Vídeo</div>
                                    </div>
                                </div>
                                <input type="hidden" id="tipo_midia" name="tipo_midia" value="<?php echo $artigo['tipo_midia'] ?? 'imagem'; ?>">
                            </div>
                        </div>
                        
                        <div class="card" style="margin-top:16px;">
                            <div class="card-header"><h3>Upload de Mídia</h3></div>
                            <div class="card-body">
                                <?php $imagemAtual = (!empty($artigo['imagem_principal'])) ? $artigo['imagem_principal'] : ($artigo['imagem_1x1'] ?? ''); ?>
                                <div id="upload-imagem" style="<?php echo ($artigo['tipo_midia'] ?? 'imagem') === 'video' ? 'display:none;' : ''; ?>">
                                    <div class="form-group">
                                        <label>Imagem</label>
                                        <div class="media-upload-area" onclick="document.getElementById('imagem_principal').click()">
                                            <?php if ($imagemAtual): ?>
                                                <img src="<?php echo UPLOAD_URL . $imagemAtual; ?>" class="media-preview" style="display:block; max-width:100%;">
                                                <small>Clique para substituir</small>
                                            <?php else: ?>
                                                <div><i class="ph ph-camera"></i> Clique para selecionar</div>
                                                <small>JPG, PNG ou WebP</small>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="imagem_atual" value="<?php echo htmlspecialchars($imagemAtual); ?>">
                                        <input type="file" id="imagem_principal" name="imagem_principal" accept="image/*" style="display:none;" onchange="previewMedia(this, 'imagem')">
                                    </div>
                                </div>
                                
                                <div id="upload-video" style="<?php echo ($artigo['tipo_midia'] ?? 'imagem') === 'imagem' ? 'display:none;' : ''; ?>">
                                    <div class="form-group">
                                        <label>Vídeo</label>
                                        <div class="media-upload-area" onclick="document.getElementById('video_arquivo').click()">
                                            <div><i class="ph ph-film-strip"></i> Clique para selecionar</div>
                                            <small>MP4, WebM ou MOV</small>
                                        </div>
                                        <input type="file" id="video_arquivo" name="video_arquivo" accept="video/*" style="display:none;" onchange="previewMedia(this, 'video')">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">Salvar</button>
                    </div>
                    
                    <!-- Tab 4: Traduções & SEO -->
                    <div class="form-tab-content" id="tab-traduzir">
                        <div class="card">
                            <div class="card-header"><h3>Idiomas & SEO</h3></div>
                            <div class="card-body">
                                <?php if (empty($artigo['id'])): ?>
                                    <p class="text-muted">Salve o conteúdo primeiro para gerar traduções.</p>
                                <?php else: ?>
                                    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                                        <button type="button" class="btn btn-secondary" onclick="gerarI18nSeo('artigo', <?php echo (int)$artigo['id']; ?>, ['pt'])"><i class="ph ph-sparkle"></i> SEO PT</button>
                                        <button type="button" class="btn btn-primary" onclick="gerarI18nSeo('artigo', <?php echo (int)$artigo['id']; ?>, ['en','es'])"><i class="ph ph-sparkle"></i> EN + ES</button>
                                        <button type="button" class="btn btn-secondary" onclick="gerarI18nSeo('artigo', <?php echo (int)$artigo['id']; ?>, ['pt','en','es'])">Todos</button>
                                    </div>
                                    <table class="table">
                                        <thead><tr><th>Lang</th><th>Status</th><th>Slug</th><th>Atualizado</th></tr></thead>
                                        <tbody>
                                            <?php foreach (['pt' => 'PT-BR', 'en' => 'EN', 'es' => 'ES'] as $lang => $label): ?>
                                                <?php $row = $artigoI18n[$lang] ?? null; ?>
                                                <tr>
                                                    <td><strong><?php echo $label; ?></strong></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $row ? (($row['status_traducao'] ?? '') === 'reviewed' ? 'warning' : 'success') : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($row['status_traducao'] ?? '—'); ?>
                                                        </span>
                                                        <?php if ($row && ($row['status_traducao'] ?? '') !== 'reviewed'): ?>
                                                            <button type="button" class="btn btn-sm btn-secondary" onclick="marcarI18nRevisado('artigo', <?php echo (int)$artigo['id']; ?>, '<?php echo $lang; ?>')">✓</button>
                                                        <?php endif; ?>
                                                        <?php if ($row): ?>
                                                            <button type="button" class="btn btn-sm btn-info" onclick="verSeo('artigo', <?php echo (int)$artigo['id']; ?>, '<?php echo $lang; ?>')">👁</button>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><code><?php echo htmlspecialchars($row['slug'] ?? '—'); ?></code></td>
                                                    <td><?php echo !empty($row['updated_at']) ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '—'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 5: Publicação -->
                    <div class="form-tab-content" id="tab-publicar">
                        <div class="card">
                            <div class="card-header"><h3>Status</h3></div>
                            <div class="card-body">
                                <div class="status-selector">
                                    <div class="status-option rascunho <?php echo ($artigo['status_publicacao'] ?? 'rascunho') === 'rascunho' ? 'active' : ''; ?>" onclick="selecionarStatus('rascunho')">
                                        <i class="ph ph-pencil-simple"></i> Rascunho
                                    </div>
                                    <div class="status-option agendado <?php echo ($artigo['status_publicacao'] ?? '') === 'agendado' ? 'active' : ''; ?>" onclick="selecionarStatus('agendado')">
                                        <i class="ph ph-calendar-plus"></i> Agendar
                                    </div>
                                    <div class="status-option publicado <?php echo ($artigo['status_publicacao'] ?? '') === 'publicado' ? 'active' : ''; ?>" onclick="selecionarStatus('publicado')">
                                        <i class="ph ph-rocket-launch"></i> Publicar
                                    </div>
                                </div>
                                <input type="hidden" id="status_publicacao" name="status_publicacao" value="<?php echo $artigo['status_publicacao'] ?? 'rascunho'; ?>">
                            </div>
                        </div>
                        
                        <div class="scheduling-section" id="schedulingSection" style="<?php echo ($artigo['status_publicacao'] ?? '') === 'agendado' ? '' : 'display:none;'; ?>">
                            <div class="card" style="margin-top:16px;">
                                <div class="card-header"><h3>Agendamento</h3></div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Data e Hora</label>
                                        <input type="datetime-local" name="data_agendamento" id="data_agendamento" value="<?php echo $artigo['data_agendamento'] ? date('Y-m-d\TH:i', strtotime($artigo['data_agendamento'])) : ''; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Recorrência</label>
                                        <select name="recorrencia_tipo" id="recorrencia_tipo" onchange="toggleRecorrencia()">
                                            <option value="nenhuma" <?php echo ($artigo['recorrencia_tipo'] ?? '') === 'nenhuma' ? 'selected' : ''; ?>>Sem repetição</option>
                                            <option value="diaria" <?php echo ($artigo['recorrencia_tipo'] ?? '') === 'diaria' ? 'selected' : ''; ?>>Diária</option>
                                            <option value="semanal" <?php echo ($artigo['recorrencia_tipo'] ?? '') === 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                                            <option value="mensal" <?php echo ($artigo['recorrencia_tipo'] ?? '') === 'mensal' ? 'selected' : ''; ?>>Mensal</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Repetir até:</label>
                                        <input type="date" name="recorrencia_fim" value="<?php echo $artigo['recorrencia_fim'] ?? ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card" style="margin-top:16px;">
                            <div class="card-header"><h3>Redes Sociais</h3></div>
                            <div class="card-body">
                                <?php 
                                $redesSelecionadas = $artigo['redes_destino_array'] ?? [];
                                $redesIds = array_column($redesSelecionadas, 'rede');
                                $redesConfig = ['linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'];
                                foreach ($redesConfig as $redeId => $redeNome): 
                                    $isSelected = in_array($redeId, $redesIds);
                                ?>
                                <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                                    <input type="checkbox" name="redes_check[]" value="<?php echo $redeId; ?>" <?php echo $isSelected ? 'checked' : ''; ?> onchange="atualizarRedesDestino()">
                                    <span><?php echo $redeNome; ?></span>
                                </label>
                                <?php endforeach; ?>
                                <input type="hidden" name="redes_destino" id="redes_destino" value="<?php echo htmlspecialchars($artigo['redes_destino'] ?? '[]'); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">Salvar</button>
                    </div>
                    
                    <!-- Tab 6: Redes Sociais -->
                    <div class="form-tab-content" id="tab-redes">
                        <div class="card">
                            <div class="card-header"><h3>Versões para Redes</h3></div>
                            <div class="card-body">
                                <p class="text-muted">Crie versões otimizadas para Instagram e Facebook.</p>
                                <div class="form-group">
                                    <label>Rede</label>
                                    <select id="social_rede_select" class="form-control">
                                        <option value="instagram">Instagram</option>
                                        <option value="facebook">Facebook</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Prompt</label>
                                    <textarea id="social_prompt" rows="2" class="form-control" placeholder="Resuma em 2 frases..."></textarea>
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="button" class="btn btn-secondary" onclick="gerarSocialCaption()">Gerar legenda</button>
                                    <button type="button" class="btn btn-secondary" onclick="gerarSocialImages()">Gerar imagens</button>
                                    <button type="button" class="btn btn-secondary" onclick="carregarSocialVariants()">Carregar</button>
                                </div>
                                <div id="social_generation_result" style="display:none; margin-top:16px;">
                                    <label>Preview:</label>
                                    <div id="social_preview_caption" style="background:var(--surface-ground); padding:12px; border-radius:8px; margin-bottom:8px;"></div>
                                    <div id="social_preview_images" style="display:flex; gap:8px;"></div>
                                    <button type="button" class="btn btn-primary" style="margin-top:8px;" onclick="salvarSocialVariant()">Salvar Variante</button>
                                </div>
                                <hr>
                                <h4>Variantes</h4>
                                <div id="social_variants_list">(Nenhuma)</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 7: Opções -->
                    <div class="form-tab-content" id="tab-opcoes">
                        <div class="card">
                            <div class="card-header"><h3>Informações</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="autor">Autor</label>
                                    <input type="text" id="autor" name="autor" value="<?php echo htmlspecialchars($artigo['autor'] ?? 'Washington Viana'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="destaque" value="1" <?php echo ($artigo['destaque'] ?? false) ? 'checked' : ''; ?>>
                                        Destacar na homepage
                                    </label>
                                </div>
                                <?php if ($artigo): ?>
                                    <hr>
                                    <p><small>Criado: <?php echo formatDate($artigo['created_at'], 'd/m/Y H:i'); ?></p>
                                    <?php if ($artigo['fonte_ia']): ?>
                                        <p><small>🤖 IA: <?php echo htmlspecialchars($artigo['fonte_ia']); ?></small></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($artigo): ?>
                        <div class="card" style="margin-top:16px;">
                            <div class="card-header"><h3>Ações</h3></div>
                            <div class="card-body">
                                <button type="button" onclick="publicarAgoraLinkedIn(<?php echo (int)$artigo['id']; ?>)" class="btn btn-secondary btn-block">
                                    <i class="ph ph-linkedin-logo"></i> Publicar Agora no LinkedIn
                                </button>
                                <button type="button" onclick="deletarArtigo(<?php echo $artigo['id']; ?>)" class="btn btn-danger btn-block" style="margin-top:10px;">
                                    <i class="ph ph-trash"></i> Excluir Conteúdo
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
<button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">
                            <span id="btnSaveText"><i class="ph ph-floppy-disk"></i> Salvar</span>
                        </button>
                    </div>
                </form>

                <style>
                .form-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--border); margin-bottom: 20px; overflow-x: auto; }
                .form-tabs .form-tab { 
                    background: #1e1e1e; border: none; padding: 12px 16px; cursor: pointer; 
                    color: #9ca3af; font-size: 14px; font-weight: 500;
                    white-space: nowrap; transition: all 0.2s; border-radius: 6px 6px 0 0;
                }
                .form-tabs .form-tab:hover { color: #fff; background: #2d2d2d; }
                .form-tabs .form-tab.active { 
                    color: #22c55e !important; background: #1a1a1a; 
                    border-bottom: 2px solid #22c55e; margin-bottom: -2px;
                }
                .form-tabs .form-tab.active i { color: #22c55e !important; }
                .form-tab-content { display: none; }
                .form-tab-content.active { display: block; animation: fadeIn 0.2s; }
                @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
                </style>
                
                <script>
                document.querySelectorAll('.form-tab').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.form-tab').forEach(function(b) { b.classList.remove('active'); });
                        document.querySelectorAll('.form-tab-content').forEach(function(c) { c.classList.remove('active'); });
                        btn.classList.add('active');
                        document.getElementById(btn.dataset.tab).classList.add('active');
                    });
                });
</script>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
