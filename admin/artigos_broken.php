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

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
                                        <i class="ph ph-text-t"></i> Prompt para Texto <span class="required">*</span>
                                    </label>
                                    <textarea id="prompt_texto" name="prompt_texto" rows="4" 
                                        placeholder="Descreva em detalhes o conteúdo que deseja criar...

Ex: Escreva um artigo sobre como a IA está revolucionando os negócios. O público-alvo são CEOs e gerentes. Use tom profissional mas acessível, com exemplos práticos."><?php echo htmlspecialchars($artigo['prompt_texto'] ?? $_GET['prompt'] ?? ''); ?></textarea>
                                    <small class="text-muted">Este prompt será usado para gerar o texto do post.</small>
                                </div>
                                
                                <div class="ai-buttons" style="margin-bottom: 20px;">
                                    <button type="button" class="btn-ai" id="btnGerarTexto" onclick="gerarConteudoIA()">
                                        <span id="btnGerarTextoLabel"><i class="ph ph-sparkle"></i> Gerar Texto</span>
                                        <span id="btnGerarTextoLoader" style="display:none;"><span class="spinner"></span> Gerando texto...</span>
                                    </button>
                                </div>
                                
                                <hr style="border-color: #e0e7ff; margin: 20px 0;">
                                
                                <!-- Prompt para Imagem -->
                                <div class="form-group">
                                    <label for="prompt_imagem">
                                        <i class="ph ph-image"></i> Prompt para Imagem <span class="text-muted">(opcional)</span>
                                    </label>
                                    <textarea id="prompt_imagem" name="prompt_imagem" rows="3" 
                                        placeholder="Deixe em branco para usar o mesmo prompt do texto, ou descreva a imagem desejada...

Ex: Imagem minimalista de um executivo usando tablet com gráficos de IA, cores corporativas azul e branco, estilo profissional"><?php echo htmlspecialchars($artigo['prompt_imagem'] ?? ''); ?></textarea>
                                    <small class="text-muted">
                                        Se vazio, usará o prompt de texto. A imagem será gerada em 2 formatos: 1:1 (feed) e 9:16 (stories/reels).
                                    </small>
                                </div>
                                
                                <div class="ai-buttons">
                                    <button type="button" class="btn-ai" id="btnGerarImagem" onclick="gerarImagemIA()">
                                        <span id="btnGerarImagemLabel"><i class="ph ph-image"></i> Gerar Imagem</span>
                                        <span id="btnGerarImagemLoader" style="display:none;"><span class="spinner"></span> Gerando...</span>
                                    </button>
                                </div>
                                
                                <!-- Preview da Imagem Gerada -->
                                <div class="image-preview-container" id="imagePreviewContainer" style="<?php echo ($artigo && $artigo['imagem_1x1']) ? '' : 'display:none;'; ?>">
                                    <div class="image-preview-box" onclick="abrirImagemModal()" title="Clique para ver em tamanho real">
                                        <?php if ($artigo['imagem_1x1'] ?? false): ?>
                                            <img src="<?php echo UPLOAD_URL . $artigo['imagem_1x1']; ?>" alt="Imagem do post" id="preview-imagem-ia">
                                        <?php else: ?>
</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">
                            <span id="btnSaveText"><i class="ph ph-floppy-disk"></i> Salvar Conteúdo</span>
                            <span id="btnSaveLoader" style="display:none;">Salvando...</span>
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

        async function postI18nSeoBulkArtigos(id, lang) {
            const formData = new FormData();
            formData.append('csrf_token', csrfTokenArtigosBulk);
            formData.append('entity', 'artigo');
            formData.append('id', String(id));
            formData.append('langs[]', lang);
            const resp = await fetch('../api/i18n_seo.php', { method: 'POST', body: formData });
            const text = await resp.text();
            let data = null;
            try { data = JSON.parse(text); } catch (e) {}
            if (!resp.ok) {
                const msg = (data && (data.message || data.details)) ? (data.message || data.details) : text;
                throw new Error(`HTTP ${resp.status} - ${msg}`);
            }
            if (!data || !data.success) {
                const msg = (data && (data.message || data.details)) ? (data.message || data.details) : 'Erro ao gerar i18n/SEO.';
                throw new Error(msg);
            }
            return data;
        }

        async function gerarI18nSeoBulkArtigos() {
            const btn = document.getElementById('btnBulkI18nArtigos');
            if (!btn) return;
            
            const queue = [];
            for (const lang of ['pt', 'en', 'es']) {
                const ids = artigosMissing[lang] || [];
                for (const id of ids) {
                    queue.push({id, lang});
                }
            }
            const total = queue.length;
            if (!total) {
                alert('Nada para gerar.');
                return;
            }

            btn.disabled = true;
            WVProgress.show('Otimizando conteúdos...', `0/${total} concluídos.`);
            
            let done = 0;
            let failed = false;
            try {
                for (const item of queue) {
                    if (failed) break;
                    done++;
                    WVProgress.update((done / total) * 100, `Processando conteúdo #${item.id} (${item.lang.toUpperCase()}) - ${done}/${total}...`);
                    
                    try {
                        await postI18nSeoBulkArtigos(item.id, item.lang);
                        await new Promise(r => setTimeout(r, 800));
                    } catch (itemErr) {
                        failed = true;
                        throw itemErr;
                    }
                }
                WVProgress.hide();
                if (failed) {
                    alert('⚠️ Processo interrompido. Alguns conteúdos podem não ter sido processados.');
                } else {
                    alert('✅ i18n/SEO gerado para conteúdos publicados faltantes.');
                }
                window.location.reload();
            } catch (err) {
                WVProgress.hide();
                btn.disabled = false;
                alert('❌ Erro: ' + (err?.message || String(err)) + '\n\nTente novamente mais tarde.');
            }
        }

        // Gerar slug a partir do título
        function gerarSlug() {
            var titulo = document.getElementById('titulo').value;
            var slug = titulo.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            document.getElementById('slug').value = slug;
        }
        
        // Selecionar tipo de mídia
        function selecionarTipoMidia(tipo) {
            document.querySelectorAll('.media-type-option').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('tipo_midia').value = tipo;
            
            if (tipo === 'imagem') {
                document.getElementById('upload-imagem').style.display = 'block';
                document.getElementById('upload-video').style.display = 'none';
                document.getElementById('btnGerarImagem').style.display = 'inline-block';
            } else {
                document.getElementById('upload-imagem').style.display = 'none';
                document.getElementById('upload-video').style.display = 'block';
                document.getElementById('btnGerarImagem').style.display = 'none';
            }
        }
        
        // Selecionar status
        function selecionarStatus(status) {
            document.querySelectorAll('.status-option').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('status_publicacao').value = status;
            
            // Mostrar/esconder agendamento
            var schedulingSection = document.getElementById('schedulingSection');
            if (status === 'agendado') {
                schedulingSection.style.display = 'block';
            } else {
                schedulingSection.style.display = 'none';
            }
        }
        
        // Toggle recorrência
        function toggleRecorrencia() {
            var tipoEl = document.getElementById('recorrencia_tipo');
            if (!tipoEl) return;
            var tipo = tipoEl.value;
            var weekdays = document.getElementById('weekdaysOptions');
            if (weekdays) weekdays.style.display = tipo === 'semanal' ? 'block' : 'none';
            var monthDay = document.getElementById('monthDayOptions');
            if (monthDay) monthDay.style.display = tipo === 'mensal' ? 'block' : 'none';
        }
        
        // Toggle dia da semana
        function toggleWeekday(btn) {
            btn.classList.toggle('selected');
            updateWeekdays();
        }
        
        // Atualizar dias selecionados
        function updateWeekdays() {
            var dias = [];
            document.querySelectorAll('.weekday-btn.selected').forEach(btn => {
                dias.push(btn.dataset.day);
            });
            var diasEl = document.getElementById('recorrencia_dias');
            if (diasEl) diasEl.value = dias.join(',');
        }
        
        // Toggle rede social
        function toggleRede(element, rede) {
            element.classList.toggle('selected');
            var checkbox = element.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = element.classList.contains('selected');
            updateRedesDestino();
        }
        
        // Atualizar redes destino JSON
        function updateRedesDestino() {
            var redes = [];
            document.querySelectorAll('.rede-option.selected input').forEach(input => {
                redes.push({
                    rede: input.value,
                    nome: input.dataset.nome,
                    formato: input.dataset.formato,
                    icone: input.dataset.icone
                });
            });
            var redesEl = document.getElementById('redes_destino');
            if (redesEl) redesEl.value = JSON.stringify(redes);
        }
        
        // Inicializar recorrência ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            toggleRecorrencia();
            updateRedesDestino();
        });
        
        // Preview de mídia
        function previewMedia(input, tipo) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                if (tipo === 'imagem') {
                    reader.onload = function(e) {
                        var preview = document.getElementById('preview-imagem');
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                } else {
                    reader.onload = function(e) {
                        var container = document.getElementById('preview-video');
                        container.innerHTML = '<video controls style="max-width:100%;max-height:300px;border-radius:8px;"><source src="' + e.target.result + '"></video>';
                    };
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Gerar conteúdo com IA - Usa APENAS as instruções configuradas no Agente
        function gerarConteudoIA() {
            var prompt = document.getElementById('prompt_texto').value.trim();
            if (!prompt) {
                alert('Por favor, preencha o Prompt para Texto');
                return;
            }
            
            WVProgress.show('Gerando conteúdo...', 'A IA está escrevendo o artigo completo baseado no seu prompt.');
            WVProgress.animateTo(92, 25000);
            
            var formData = new FormData();
            formData.append('action', 'generate_article');
            formData.append('tema', prompt);
            var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
            if (csrf) formData.append('csrf_token', csrf);
            
            fetch('../api/gemini.php', {
                method: 'POST',
                body: formData
            })
            .then(parseApiJsonResponse)
            .then(data => {
                WVProgress.hide();
                
                console.log('Resposta da IA:', data);
                console.log('Texto recebido (' + (data.texto ? data.texto.length : 0) + ' chars):', data.texto);
                
                if (data.success) {
                    var texto = data.texto || '';
                    
                    if (texto) {
                        // Extrair campos do texto gerado pela IA
                        var campos = extrairCamposIA(texto);
                        console.log('Campos extraídos:', campos);
                        
                        var preenchidos = [];
                        
                        // Preencher Título
                        if (campos.titulo) {
                            document.getElementById('titulo').value = campos.titulo;
                            preenchidos.push('Título');
                            gerarSlug(); // Gerar slug automaticamente
                        }
                        
                        // Preencher Slug (se veio específico da IA)
                        if (campos.slug) {
                            document.getElementById('slug').value = campos.slug;
                            preenchidos.push('Slug');
                        }
                        
                        // Preencher Categoria
                        if (campos.categoria) {
                            var selectCat = document.getElementById('categoria_id');
                            var catEncontrada = false;
                            for (var i = 0; i < selectCat.options.length; i++) {
                                if (selectCat.options[i].text.toLowerCase().includes(campos.categoria.toLowerCase())) {
                                    selectCat.selectedIndex = i;
                                    catEncontrada = true;
                                    preenchidos.push('Categoria');
                                    break;
                                }
                            }
                        }
                        
                        // Preencher Resumo
                        if (campos.resumo) {
                            document.getElementById('resumo').value = campos.resumo;
                            preenchidos.push('Resumo');
                        }
                        
                        // Preencher Conteúdo
                        if (campos.conteudo) {
                            document.getElementById('conteudo').value = campos.conteudo;
                            preenchidos.push('Conteúdo (' + campos.conteudo.split(/\s+/).length + ' palavras)');
                        } else {
                            // Se não conseguiu extrair estrutura, usa texto completo
                            document.getElementById('conteudo').value = texto;
                            preenchidos.push('Conteúdo (texto completo)');
                        }
                        
                        alert('✅ Conteúdo gerado!\n\nCampos preenchidos:\n• ' + preenchidos.join('\n• ') + '\n\nModelo: ' + data.fonte_ia + '\n\nTotal: ' + texto.length + ' caracteres');
                    } else {
                        alert('⚠️ IA retornou resposta vazia.');
                    }
                } else {
                    alert('❌ Erro: ' + (data.message || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro de conexão: ' + error.message);
            });
        }
        
        // Extrair campos estruturados do texto da IA
        function extrairCamposIA(texto) {
            console.log('=== INICIANDO EXTRAÇÃO ===');
            console.log('Texto original (' + texto.length + ' chars)');
            
            var campos = {
                titulo: '',
                slug: '',
                categoria: '',
                resumo: '',
                conteudo: ''
            };
            
            // Remover texto introdutório da IA (ex: "Claro. Assumindo a persona...")
            var textoLimpo = texto.replace(/^[\s\S]*?(?=T[ií]tulo\s*:)/im, '');
            console.log('Após limpar intro (' + textoLimpo.length + ' chars)');
            
            // Extrair título (primeira linha após "Título:")
            var matchTitulo = textoLimpo.match(/T[ií]tulo\s*:\s*(.+?)(?=\n|$)/im);
            if (matchTitulo) campos.titulo = matchTitulo[1].trim();
            
            // Extrair slug
            var matchSlug = textoLimpo.match(/Slug\s*:\s*(.+?)(?=\n|$)/im);
            if (matchSlug) campos.slug = matchSlug[1].trim();
            
            // Extrair categoria
            var matchCat = textoLimpo.match(/Categoria\s*:\s*(.+?)(?=\n|$)/im);
            if (matchCat) campos.categoria = matchCat[1].trim();
            
            // Extrair resumo (texto entre "Resumo:" e "Conteúdo:")
            var matchResumo = textoLimpo.match(/Resumo\s*:\s*([\s\S]+?)(?=\nConte[uú]do\s*:)/im);
            if (matchResumo) campos.resumo = matchResumo[1].trim();
            
            // Extrair conteúdo (TUDO após "Conteúdo:")
            var matchConteudo = textoLimpo.match(/Conte[uú]do\s*:\s*([\s\S]+)$/im);
            if (matchConteudo) campos.conteudo = matchConteudo[1].trim();
            
            console.log('Título extraído:', campos.titulo.substring(0, 50));
            console.log('Slug extraído:', campos.slug);
            console.log('Categoria extraída:', campos.categoria);
            console.log('Resumo extraído (' + campos.resumo.length + ' chars)');
            console.log('Conteúdo extraído (' + campos.conteudo.length + ' chars)');
            
            // Função para limpar markdown
            function limparMarkdown(str) {
                if (!str) return '';
                return str
                    .replace(/\*\*([^*]+)\*\*/g, '$1')
                    .replace(/\*([^*]+)\*/g, '$1')
                    .replace(/^#{1,6}\s*/gm, '')
                    .replace(/^>\s*/gm, '')
                    .replace(/^-{3,}\s*$/gm, '')
                    .replace(/^["']+|["']+$/g, '')
                    .trim();
            }
            
            // Limpar todos os campos
            campos.titulo = limparMarkdown(campos.titulo);
            campos.slug = limparMarkdown(campos.slug).toLowerCase().replace(/\s+/g, '-');
            campos.categoria = limparMarkdown(campos.categoria);
            campos.resumo = limparMarkdown(campos.resumo);
            campos.conteudo = limparMarkdown(campos.conteudo);
            
            console.log('Conteúdo final (' + campos.conteudo.length + ' chars, ~' + campos.conteudo.split(/\s+/).length + ' palavras)');
            
            return campos;
        }
        
        // Função para limpar texto da IA
        function limparTexto(texto) {
            if (!texto) return '';
            
            // Converter string se necessário
            texto = String(texto);
            
            // Converter \n literais para quebras de linha reais
            texto = texto.replace(/\\n\\n/g, '\n\n');
            texto = texto.replace(/\\n/g, '\n');
            texto = texto.replace(/\\r/g, '');
            
            // Remover aspas extras no início/fim
            texto = texto.replace(/^["']|["']$/g, '');
            
            // Normalizar múltiplas quebras de linha (máximo 2)
            texto = texto.replace(/\n{3,}/g, '\n\n');
            
            // Trim
            texto = texto.trim();
            
            return texto;
        }
        
        // Atualizar redes sociais destino
        function atualizarRedesDestino() {
            var redes = [];
            var checkboxes = document.querySelectorAll('.rede-checkbox:checked');
            
            checkboxes.forEach(function(cb) {
                var redeId = cb.dataset.rede || cb.value;
                if (redeId) {
                    redes.push({
                        rede: redeId,
                        formato: '1:1'  // Formato fixo
                    });
                }
            });
            
            var jsonRedes = JSON.stringify(redes);
            var redesEl = document.getElementById('redes_destino');
            if (redesEl) redesEl.value = jsonRedes;
            console.log('Redes selecionadas:', redes);
            console.log('JSON enviado:', jsonRedes);
        }
        
        // Inicializar redes destino ao carregar
        document.addEventListener('DOMContentLoaded', function() {
            atualizarRedesDestino();
        });

        function parseApiJsonResponse(response) {
            return response.text().then(function(raw) {
                var contentType = (response.headers.get('content-type') || '').toLowerCase();
                var trimmed = (raw || '').trim();
                var isJson = contentType.indexOf('application/json') !== -1 ||
                    trimmed.startsWith('{') || trimmed.startsWith('[');

                if (!isJson) {
                    if (response.url && response.url.indexOf('/admin/index.php') !== -1) {
                        throw new Error('Sessao expirada. Faca login novamente no admin e tente de novo.');
                    }
                    var htmlPreview = trimmed.replace(/\s+/g, ' ').slice(0, 200);
                    throw new Error('Resposta inesperada do servidor (HTML/nao-JSON, HTTP ' + response.status + '): ' + htmlPreview);
                }

                var data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    var badPreview = trimmed.replace(/\s+/g, ' ').slice(0, 200);
                    throw new Error('Falha ao interpretar JSON da API: ' + badPreview);
                }

                if (!response.ok) {
                    var msg = data && data.message ? data.message : ('Erro HTTP ' + response.status);
                    throw new Error(msg);
                }

                return data;
            });
        }
        
        // Modal para visualização de imagem em tamanho real
        function abrirImagemModal() {
            var modal = document.getElementById('imagemModal');
            var modalImg = document.getElementById('imagemModalImg');
            var caption = document.getElementById('imagemModalCaption');
            
            var img = document.querySelector('#imagePreviewContainer img');
            
            if (img && img.src) {
                modal.style.display = 'block';
                modalImg.src = img.src;
                caption.textContent = 'Imagem do post (1:1)';
                document.body.style.overflow = 'hidden';
            }
        }
        
        function fecharImagemModal() {
            var modal = document.getElementById('imagemModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                fecharImagemModal();
            }
        });
        
        // Gerar imagem com IA (formato 1:1)
        function gerarImagemIA() {
            // Usar prompt_imagem se preenchido, senão usar prompt_texto
            var promptImagem = document.getElementById('prompt_imagem').value.trim();
            var promptTexto = document.getElementById('prompt_texto').value.trim();
            var titulo = document.getElementById('titulo').value.trim();
            
            var prompt = promptImagem || promptTexto || titulo;
            
            if (!prompt) {
                alert('Por favor, preencha o Prompt para Texto ou Prompt para Imagem primeiro');
                return;
            }
            
            WVProgress.show('Gerando imagem...', 'Criando visual exclusivo com IA para o seu post.');
            WVProgress.animateTo(90, 15000);
            
            var formData = new FormData();
            formData.append('action', 'generate_image');
            formData.append('prompt', prompt);
            var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
            if (csrf) formData.append('csrf_token', csrf);
            
            fetch('../api/gemini.php', {
                method: 'POST',
                body: formData
            })
            .then(parseApiJsonResponse)
            .then(data => {
                WVProgress.hide();
                
                console.log('Resposta geração imagem:', data);
                
                if (data.success) {
                    var container = document.getElementById('imagePreviewContainer');
                    container.style.display = 'block';
                    
                    // Atualizar preview na seção de IA
                    var previewBox = container.querySelector('.image-preview-box');
                    previewBox.innerHTML = '<img src="' + data.url + '" alt="Imagem do post" id="preview-imagem-ia">';
                    document.getElementById('imagem_1x1').value = data.filename;
                    
                    // SINCRONIZAR com a seção de Mídia abaixo
                    var previewMidia = document.getElementById('preview-imagem');
                    if (previewMidia) {
                        previewMidia.src = data.url;
                        previewMidia.style.display = 'block';
                        // Atualizar a área de upload para mostrar a imagem
                        var uploadArea = previewMidia.closest('.media-upload-area');
                        if (uploadArea) {
                            uploadArea.style.padding = '10px';
                            // Garantir que mostra o texto de substituir
                            var smallText = uploadArea.querySelector('small');
                            if (smallText) {
                                smallText.textContent = '📷 Clique para substituir a imagem';
                                smallText.style.display = 'block';
                                smallText.style.textAlign = 'center';
                                smallText.style.marginTop = '10px';
                            }
                            // Esconder os textos de "Clique para selecionar"
                            var divTexto = uploadArea.querySelector('div:not(.media-preview)');
                            if (divTexto && divTexto.textContent.includes('Clique para selecionar')) {
                                divTexto.style.display = 'none';
                            }
                        }
                    }
                    // Atualizar campo hidden de imagem_atual se existir
                    var imagemAtualInput = document.querySelector('input[name="imagem_atual"]');
                    if (imagemAtualInput) {
                        imagemAtualInput.value = data.filename;
                    }
                    
                    alert('✅ Imagem gerada com sucesso!');
                } else {
                    var mensagem = data.message || 'Não foi possível gerar a imagem';
                    alert('❌ ' + mensagem + '\n\n💡 Dica: Você pode fazer upload manual clicando na área de mídia abaixo.');
                }
            })
            .catch(error => {
                btn.classList.remove('loading');
                btnLabel.style.display = 'inline';
                btnLoader.style.display = 'none';
                console.error('Erro:', error);
                alert('❌ Erro de conexão: ' + error.message);
            });
        }
        
        // Salvar artigo
        document.getElementById('artigoForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var form = this;
            var formData = new FormData(form);
            formData.append('action', formData.get('id') ? 'update' : 'create');
            formData.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
            
            var btnSaveText = document.getElementById('btnSaveText');
            var btnSaveLoader = document.getElementById('btnSaveLoader');
            var messageDiv = document.getElementById('messageDiv');
            
            btnSaveText.style.display = 'none';
            btnSaveLoader.style.display = 'inline';
            
    WVProgress.show('Salvando artigo...', 'Enviando dados e mídias para o servidor.');

    fetch('../api/artigos.php', {
        method: 'POST',
        body: formData
    })
    .then(parseApiJsonResponse)
    .then(data => {
        WVProgress.hide();
        
        messageDiv.style.display = 'block';
        if (data.success) {
            messageDiv.className = 'message message-success';
            messageDiv.textContent = '✅ ' + data.message;
            
            if (data.artigo_id && !formData.get('id')) {
                setTimeout(function() {
                    window.location.href = '?action=edit&id=' + data.artigo_id;
                }, 1000);
            }
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
        
        // Deletar artigo - abre modal com opções
        function deletarArtigo(id) {
            // Buscar publicações do artigo nas redes sociais
            fetch('../api/artigos.php?action=get_publicacoes&id=' + id)
                .then(response => response.json())
                .then(data => {
                    abrirModalExclusao(id, data.publicacoes || [], data.linkedin_post_id || null);
                })
                .catch(error => {
                    // Se falhar ao buscar publicações, abre modal sem elas
                    abrirModalExclusao(id, [], null);
                });
        }
        
        // Abrir modal de exclusão
        function abrirModalExclusao(artigoId, publicacoes, linkedinPostId) {
            var modal = document.getElementById('modalExclusao');
            var publicacoesContainer = document.getElementById('publicacoesRedes');
            var opcaoRedes = document.getElementById('opcaoRedesSociais');

            // Armazenar ID do artigo e linkedin_post_id
            modal.dataset.artigoId = artigoId;
            modal.dataset.linkedinPostId = linkedinPostId || '';

            // Verificar se tem publicações ou linkedin_post_id
            var temLinkedIn = linkedinPostId && linkedinPostId.length > 0;
            var temPublicacoes = publicacoes.length > 0 || temLinkedIn;

            if (temPublicacoes) {
                opcaoRedes.style.display = 'block';
                publicacoesContainer.innerHTML = '';

                // Se tem linkedin_post_id, adicionar checkbox para ele
                if (temLinkedIn) {
                    var linkedinExists = publicacoes.some(p => p.rede === 'linkedin');
                    if (!linkedinExists) {
                        publicacoes.unshift({
                            id: null,
                            rede: 'linkedin',
                            post_id: linkedinPostId,
                            publicado_em: null
                        });
                    }
                }

                publicacoes.forEach(function(pub) {
                    var icon = pub.rede === 'linkedin' ? '💼' : '📸';
                    var checkbox = document.createElement('label');
                    checkbox.className = 'social-delete-option';
                    checkbox.innerHTML = `
                        <input type="checkbox" name="redes_deletar[]" value="${pub.id || 'linkedin'}" data-rede="${pub.rede}" data-post-id="${pub.post_id || linkedinPostId}">
                        <span class="icon">${icon}</span>
                        <span class="info">
                            <strong>${pub.rede.charAt(0).toUpperCase() + pub.rede.slice(1)}</strong>
                            <small>${pub.publicado_em ? 'Publicado em ' + pub.publicado_em : 'Publicado ✓'}</small>
                        </span>
                    `;
                    publicacoesContainer.appendChild(checkbox);
                });
            } else {
                opcaoRedes.style.display = 'none';
            }

            // Reset form
            document.getElementById('opcaoExclusao').value = 'website';
            document.querySelectorAll('input[name="redes_deletar[]"]').forEach(cb => cb.checked = false);

            modal.style.display = 'flex';
        }
        
        // Fechar modal
        function fecharModalExclusao() {
            document.getElementById('modalExclusao').style.display = 'none';
        }
        
        // Toggle opções de redes
        function toggleOpcoesRedes() {
            var opcao = document.getElementById('opcaoExclusao').value;
            var redesOptions = document.getElementById('redesOptions');
            
            if (opcao === 'website_e_redes') {
                redesOptions.style.display = 'block';
            } else {
                redesOptions.style.display = 'none';
            }
        }
        
        // Confirmar exclusão
        function confirmarExclusao() {
            var modal = document.getElementById('modalExclusao');
            var artigoId = modal.dataset.artigoId;
            var linkedinPostId = modal.dataset.linkedinPostId || '';
            var opcao = document.getElementById('opcaoExclusao').value;

            // Coletar redes selecionadas para deletar
            var redesDeletar = [];
            var deletarLinkedIn = false;

            if (opcao === 'website_e_redes') {
                document.querySelectorAll('input[name="redes_deletar[]"]:checked').forEach(function(cb) {
                    if (cb.dataset.rede === 'linkedin') {
                        deletarLinkedIn = true;
                    }
                    redesDeletar.push({
                        id: cb.value,
                        rede: cb.dataset.rede,
                        post_id: cb.dataset.postId
                    });
                });
            }

            // Mostrar loading
            var btnConfirmar = document.getElementById('btnConfirmarExclusao');
            btnConfirmar.disabled = true;
            btnConfirmar.innerHTML = '⏳ Excluindo...';

            var formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', artigoId);
            formData.append('deletar_redes', opcao === 'website_e_redes' ? '1' : '0');
            formData.append('redes_deletar', JSON.stringify(redesDeletar));
            formData.append('deletar_linkedin', deletarLinkedIn ? '1' : '0');
            var csrfEl = document.querySelector('#artigoForm input[name="csrf_token"]');
            var csrf = csrfEl ? csrfEl.value : '';

            // Se o token parece inválido (ex.: extraído incorretamente) ou muito curto, tente renovar
            var csrfSeemsInvalid = !csrf || csrf.indexOf('([') !== -1 || csrf.indexOf('([^') !== -1 || csrf.length < 10;

            function performConfirmDeletion(fd) {
                WVProgress.show('Excluindo conteúdo...', 'Processando a remoção do website e das redes sociais.');

                // Antes de tentar excluir, checar autenticação para evitar 403 por sessão expirada
                fetch('../api/auth.php', { method: 'POST', body: new URLSearchParams({ action: 'check' }), credentials: 'same-origin' })
                .then(r => r.json())
                .then(auth => {
                    if (!auth.authenticated) {
                        WVProgress.hide();
                        btnConfirmar.disabled = false;
                        btnConfirmar.innerHTML = '🗑️ Confirmar Exclusão';
                        if (confirm('⚠️ Sua sessão expirou. Deseja abrir a tela de login em outra aba para fazer login novamente?')) {
                            window.open('index.php', '_blank');
                        }
                        return Promise.reject({ type: 'session_expired' });
                    }
                    // Faz a requisição de exclusão
                    return fetch('../api/artigos.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 403) {
                            // Sessão expirada / CSRF inválido
                            throw { type: 'session_expired', message: 'Sessão expirada. Faça login novamente.' };
                        }
                        throw { type: 'http_error', status: response.status };
                    }
                    return response.json();
                })
                .then(data => {
                    WVProgress.hide();
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '🗑️ Confirmar Exclusão';

                    if (data.success) {
                        fecharModalExclusao();

                        var mensagem = '✅ Conteúdo excluído do website!';
                        if (data.redes_deletadas && data.redes_deletadas.length > 0) {
                            mensagem += '\n\n📱 Posts deletados das redes:\n';
                            data.redes_deletadas.forEach(function(r) {
                                mensagem += '- ' + r.rede + ': ' + (r.success ? '✅ Sucesso' : '❌ ' + r.error) + '\n';
                            });
                        }
                        if (data.linkedin_deletado) {
                            mensagem += '\n💼 LinkedIn: ✅ Post deletado com sucesso';
                        }

                        alert(mensagem);
                        window.location.href = 'artigos.php';
                    } else {
                        alert('❌ Erro ao excluir: ' + data.message);
                    }
                })
                .catch(err => {
                    WVProgress.hide();
                    btnConfirmar.disabled = false;
                    btnConfirmar.innerHTML = '🗑️ Confirmar Exclusão';
                    if (err && err.type === 'session_expired') {
                        alert('❌ Erro ao excluir: Sessão expirada. Atualize a página e tente novamente.');
                    } else if (err && err.type === 'http_error') {
                        alert('❌ Erro HTTP ao excluir: ' + (err.status || 'Unknown'));
                    } else {
                        alert('❌ Erro de conexão: ' + (err.message || err));
                    }
                });
            }

            var csrfSeemsInvalid = !csrf || csrf.indexOf('([') !== -1 || csrf.indexOf('([^') !== -1 || csrf.length < 10;

            if (csrfSeemsInvalid) {
                fetch('../api/csrf.php', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.csrf_token) {
                        csrf = data.csrf_token;
                        document.querySelectorAll('input[name="csrf_token"]').forEach(function(i){ i.value = csrf; });
                        formData.append('csrf_token', csrf);
                        performConfirmDeletion(formData);
                    } else {
                        alert('⚠️ CSRF inválido e não foi possível renovar automaticamente. Recarregue a página e tente novamente.');
                    }
                })
                .catch(err => {
                    alert('⚠️ Erro ao renovar CSRF automaticamente. Recarregue a página e tente novamente.');
                });
                return;
            }

            formData.append('csrf_token', csrf);
            performConfirmDeletion(formData);
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('modalExclusao')?.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalExclusao();
            }
        });

        // i18n + SEO (Gemini)
        function gerarI18nSeo(entity, id, langs) {
            var form = document.getElementById('artigoForm');
            var csrf = form?.querySelector('input[name="csrf_token"]')?.value || '';
            if (!csrf) {
                alert('⚠️ CSRF inválido. Recarregue a página.');
                return;
            }

            var formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('entity', entity);
            formData.append('id', String(id));
            (langs || []).forEach(function(l) { formData.append('langs[]', l); });

            WVProgress.show('Gerando i18n & SEO...', 'A IA está traduzindo o conteúdo e gerando meta tags.');
            WVProgress.animateTo(90, 10000);

            fetch('../api/i18n_seo.php', { method: 'POST', body: formData })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    WVProgress.hide();
                    if (data.success) {
                        alert('✅ ' + (data.message || 'Gerado com sucesso.'));
                        window.location.reload();
                    } else {
                        var details = data.details ? ('\n\nDetalhes:\n' + data.details) : '';
                        alert('❌ ' + (data.message || 'Erro ao gerar.') + details);
                    }
                })
                .catch(function(err){
                    WVProgress.hide();
                    alert('❌ Erro de conexão: ' + err.message);
                });
        }

        function marcarI18nRevisado(entity, id, lang) {
            var form = document.getElementById('artigoForm');
            var csrf = form?.querySelector('input[name="csrf_token"]')?.value || '';
            if (!csrf) {
                alert('⚠️ CSRF inválido. Recarregue a página.');
                return;
            }
            var formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('entity', entity);
            formData.append('id', String(id));
            formData.append('lang', lang);
            formData.append('status', 'reviewed');

            WVProgress.show('Atualizando status...', 'Marcando tradução como revisada no banco de dados.');
            fetch('../api/i18n_status.php', { method: 'POST', body: formData })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    WVProgress.hide();
                    if (data.success) {
                        alert('✅ Marcado como revisado.');
                        window.location.reload();
                    } else {
                        alert('❌ ' + (data.message || 'Erro ao atualizar status.'));
                    }
                })
                .catch(function(err){
                    WVProgress.hide();
                    alert('❌ Erro de conexão: ' + err.message);
                });
        }

        function publicarAgoraLinkedIn(artigoId) {
            if (!artigoId) {
                alert('ID do artigo inválido.');
                return;
            }

            if (!confirm('Publicar este artigo agora no LinkedIn?')) {
                return;
            }

            var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
            if (!csrf) {
                alert('⚠️ CSRF inválido. Recarregue a página.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'publish_now_linkedin');
            formData.append('artigo_id', String(artigoId));
            formData.append('csrf_token', csrf);

            WVProgress.show('Publicando no LinkedIn...', 'Enviando conteúdo para a API do LinkedIn.');

            fetch('../api/artigos.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response){
                return response.text().then(function(raw){
                    var contentType = (response.headers.get('content-type') || '').toLowerCase();
                    var isJson = contentType.indexOf('application/json') !== -1;

                    if (!response.ok) {
                        if (response.status === 401 || response.status === 403) {
                            throw new Error('Sessão expirada ou sem autorização. Recarregue a página e faça login novamente.');
                        }
                        if (!isJson) {
                            var preview = (raw || '').replace(/\s+/g, ' ').slice(0, 180);
                            throw new Error('Servidor retornou HTML (HTTP ' + response.status + '). ' + preview);
                        }
                    }

                    // Detectar redirecionamento para login (fetch segue redirect e devolve HTML)
                    if (!isJson) {
                        if (response.url && response.url.indexOf('/admin/index.php') !== -1) {
                            throw new Error('Sessão expirada. Faça login novamente no admin e tente publicar de novo.');
                        }
                        var htmlPreview = (raw || '').replace(/\s+/g, ' ').slice(0, 180);
                        throw new Error('Resposta inesperada do servidor (não JSON): ' + htmlPreview);
                    }

                    try {
                        return JSON.parse(raw);
                    } catch (e) {
                        var jsonPreview = (raw || '').replace(/\s+/g, ' ').slice(0, 180);
                        throw new Error('Falha ao interpretar resposta JSON: ' + jsonPreview);
                    }
                });
            })
            .then(function(data){
                WVProgress.hide();
                if (data.success) {
                    alert('✅ ' + (data.message || 'Publicado no LinkedIn com sucesso!'));
                    window.location.reload();
                } else {
                    var extra = data.post_urn ? ('\n\nPost URN: ' + data.post_urn) : '';
                    alert('❌ ' + (data.message || 'Erro ao publicar no LinkedIn.') + extra);
                }
            })
            .catch(function(err){
                WVProgress.hide();
                alert('❌ Erro de conexão: ' + err.message);
            });
        }
    </script>
    
    <!-- Modal de Exclusão -->
    <div id="modalExclusao" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Excluir Conteúdo</h3>
                <button type="button" class="modal-close" onclick="fecharModalExclusao()">&times;</button>
            </div>
            
            <div class="modal-body">
                <p class="modal-warning">
                    ⚠️ <strong>Atenção:</strong> Esta ação não pode ser desfeita.
                </p>
                
                <div class="form-group">
                    <label>O que deseja excluir?</label>
                    <select id="opcaoExclusao" class="form-control" onchange="toggleOpcoesRedes()">
                        <option value="website">📄 Apenas do Website</option>
                        <option value="website_e_redes">📄📱 Website + Redes Sociais</option>
                    </select>
                </div>
                
                <div id="opcaoRedesSociais" style="display:none;">
                    <div id="redesOptions" style="display:none; margin-top: 15px;">
                        <label>Selecione as redes para excluir o post:</label>
                        <div id="publicacoesRedes" class="social-delete-list">
                            <!-- Publicações serão inseridas aqui dinamicamente -->
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalExclusao()">Cancelar</button>
                <button type="button" id="btnConfirmarExclusao" class="btn btn-danger" onclick="confirmarExclusao()">
                    🗑️ Confirmar Exclusão
                </button>
            </div>
        </div>
    </div>
    <div id="modalSeo" class="modal" style="display:none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="ph ph-eye"></i> Visualizar SEO (<span id="seoModalLang"></span>)</h3>
                <button type="button" class="modal-close" onclick="fecharModalSeo()">&times;</button>
            </div>
            <div class="modal-body" id="seoModalBody">
                <div class="loading-seo" style="text-align:center; padding: 20px;">
                    <i class="ph ph-circle-notch animate-spin"></i> Carregando dados...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="fecharModalSeo()">Fechar</button>
            </div>
        </div>
    </div>

    <script>
        function verSeo(entity, id, lang) {
            const modal = document.getElementById('modalSeo');
            const body = document.getElementById('seoModalBody');
            const langSpan = document.getElementById('seoModalLang');
            
            langSpan.innerText = lang.toUpperCase();
            body.innerHTML = '<div style="text-align:center; padding: 40px;"><i class="ph ph-circle-notch animate-spin" style="font-size: 32px;"></i><p>Buscando dados no banco...</p></div>';
            modal.style.display = 'flex';

            fetch(`../api/get_i18n_seo.php?entity=${entity}&id=${id}&lang=${lang}`)
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        const d = res.data;
                        const keywords = d.keywords ? d.keywords.split(',') : [];
                        const keywordsHtml = keywords.map(k => `<span class="badge badge-secondary" style="margin-right:5px; margin-bottom:5px;">${k.trim()}</span>`).join('');
                        
                        body.innerHTML = `
                            <div class="seo-review-grid">
                                <div class="seo-field">
                                    <label>Meta Title</label>
                                    <div class="seo-value">${d.meta_title || '—'}</div>
                                    <small class="seo-count">${(d.meta_title || '').length} / 60 caracteres</small>
                                </div>
                                <div class="seo-field">
                                    <label>Meta Description</label>
                                    <div class="seo-value">${d.meta_description || '—'}</div>
                                    <small class="seo-count">${(d.meta_description || '').length} / 160 caracteres</small>
                                </div>
                                <div class="seo-field">
                                    <label>Open Graph Title</label>
                                    <div class="seo-value">${d.og_title || '—'}</div>
                                </div>
                                <div class="seo-field">
                                    <label>Open Graph Description</label>
                                    <div class="seo-value">${d.og_description || '—'}</div>
                                </div>
                                <div class="seo-field">
                                    <label>Keywords</label>
                                    <div class="seo-value" style="background: none; border: none; padding: 0;">${keywordsHtml || '—'}</div>
                                </div>
                                <div class="seo-field">
                                    <label>Slug</label>
                                    <div class="seo-value"><code>${d.slug || '—'}</code></div>
                                </div>
                                ${d.schema_jsonld ? `
                                <div class="seo-field">
                                    <label>Schema.org (JSON-LD)</label>
                                    <pre class="seo-code">${JSON.stringify(JSON.parse(d.schema_jsonld), null, 2)}</pre>
                                </div>
                                ` : ''}
                            </div>
                            <style>
                                .seo-review-grid { display: grid; gap: 20px; }
                                .seo-field label { display: block; font-weight: 700; font-size: 12px; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 5px; }
                                .seo-value { background: var(--surface-ground); border: 1px solid var(--border); padding: 12px; border-radius: var(--radius-md); color: var(--text-primary); }
                                .seo-count { float: right; margin-top: 4px; color: var(--text-tertiary); font-size: 11px; }
                                .seo-code { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; font-size: 12px; overflow-x: auto; max-height: 200px; }
                            </style>
                        `;
                    } else {
                        body.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
                    }
                })
                .catch(err => {
                    body.innerHTML = `<div class="alert alert-danger">Erro ao carregar SEO: ${err.message}</div>`;
                });
        }

        function fecharModalSeo() {
            document.getElementById('modalSeo').style.display = 'none';
        }

        // Fechar modal ao clicar fora
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('modalSeo');
            if (e.target === modal) fecharModalSeo();
        });
    </script>
    </main>
</div>
</body>
</html>
