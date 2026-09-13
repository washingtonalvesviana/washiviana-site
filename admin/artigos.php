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
$bulkI18nQueue = [];
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

    // Fila para gerar traduções em lote (um artigo por vez), com os idiomas faltantes de cada um
    $titulosPorId = [];
    foreach ($artigos as $a) {
        $titulosPorId[(int)$a['id']] = (string)($a['titulo'] ?? '');
    }
    $missingPorArtigo = [];
    foreach (['pt', 'en', 'es'] as $lang) {
        foreach ($artigosMissing[$lang] as $aid) {
            $missingPorArtigo[(int)$aid][] = $lang;
        }
    }
    foreach ($missingPorArtigo as $aid => $langs) {
        $bulkI18nQueue[] = [
            'id' => (int)$aid,
            'titulo' => $titulosPorId[$aid] ?? ('#' . $aid),
            'langs' => array_values($langs),
        ];
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
                                        $thumbUrl = uploadFileUrl($art['imagem_1x1'] ?? null)
                                            ?: uploadFileUrl($art['imagem_principal'] ?? null);
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($thumbUrl): ?>
                                                    <img src="<?php echo htmlspecialchars($thumbUrl); ?>" 
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
                
                <?php
                    // Ações da listagem (excluir e gerar traduções em lote) precisam de CSRF e da fila
                    // de traduções faltantes. O formulário possui o seu próprio script de ações.
                    $bulkI18nJson = json_encode(
                        $bulkI18nQueue,
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    );
                ?>
                <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">
                <script>
                    window.bulkI18nQueue = <?php echo $bulkI18nJson !== false ? $bulkI18nJson : '[]'; ?>;

                    function listagemCsrfToken() {
                        var el = document.getElementById('csrfToken')
                            || document.querySelector('#artigoForm input[name="csrf_token"]')
                            || document.querySelector('input[name="csrf_token"]');
                        return el ? (el.value || '') : '';
                    }

                    function deletarArtigo(id) {
                        if (!confirm('Tem certeza que deseja excluir este conteúdo?')) return;
                        var csrf = listagemCsrfToken();
                        if (!csrf) { alert('CSRF inválido. Recarregue a página.'); return; }
                        var fd = new FormData();
                        fd.append('action', 'delete');
                        fd.append('id', String(id));
                        fd.append('csrf_token', csrf);
                        fetch('../api/artigos.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                            .then(function(response) {
                                return response.json().then(function(data) {
                                    if (!response.ok || !data.success) throw new Error(data.message || 'Erro ao excluir.');
                                    return data;
                                });
                            })
                            .then(function() { window.location.href = 'artigos.php'; })
                            .catch(function(err) { alert('❌ ' + err.message); });
                    }

                    function postI18nSeoListagem(id, langs, timeoutMs) {
                        var csrf = listagemCsrfToken();
                        var fd = new FormData();
                        fd.append('csrf_token', csrf);
                        fd.append('entity', 'artigo');
                        fd.append('id', String(id));
                        (langs || []).forEach(function(lang) { fd.append('langs[]', lang); });

                        var controller = new AbortController();
                        var timer = setTimeout(function() { controller.abort(); }, timeoutMs || 180000);
                        return fetch('../api/i18n_seo.php', { method: 'POST', body: fd, credentials: 'same-origin', signal: controller.signal })
                            .finally(function() { clearTimeout(timer); })
                            .then(function(response) {
                                return response.text().then(function(text) {
                                    var data;
                                    try { data = JSON.parse(text); } catch (e) { throw new Error('Resposta inválida do servidor.'); }
                                    if (!response.ok || !data.success) throw new Error(data.message || ('Erro HTTP ' + response.status));
                                    return data;
                                });
                            });
                    }

                    async function gerarI18nSeoBulkArtigos() {
                        var fila = Array.isArray(window.bulkI18nQueue) ? window.bulkI18nQueue : [];
                        if (!fila.length) { alert('Nenhuma tradução pendente.'); return; }
                        var total = fila.length;
                        if (!confirm('Gerar traduções para ' + total + ' conteúdo(s)? O processamento é sequencial e pode demorar.')) return;

                        var btn = document.getElementById('btnBulkI18nArtigos');
                        var originalHtml = btn ? btn.innerHTML : '';
                        if (btn) btn.disabled = true;

                        var ok = 0;
                        var falhas = [];
                        for (var i = 0; i < total; i++) {
                            var item = fila[i];
                            if (btn) btn.innerHTML = '<i class="ph ph-spinner"></i> ' + (i + 1) + '/' + total + ' — gerando...';
                            try {
                                await postI18nSeoListagem(item.id, item.langs, 180000);
                                ok++;
                            } catch (err) {
                                falhas.push('#' + item.id + ' ' + (item.titulo || '') + ': ' + (err && err.message ? err.message : 'erro'));
                            }
                        }

                        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }

                        var msg = 'Traduções concluídas: ' + ok + ' de ' + total + '.';
                        if (falhas.length) {
                            msg += '\n\nFalhas (' + falhas.length + '):\n' + falhas.slice(0, 10).join('\n') + (falhas.length > 10 ? '\n...' : '');
                        }
                        alert(msg);
                        if (ok > 0) window.location.reload();
                    }
                </script>

<?php else: ?>
                <!-- FORMULÁRIO DE CRIAÇÃO/EDIÇÃO -->
                <div class="page-header">
                    <h1><?php echo $artigo ? '<i class="ph ph-pencil-simple"></i> Editar Conteúdo' : '<i class="ph ph-plus-circle"></i> Novo Conteúdo'; ?></h1>
                    <a href="artigos.php" class="btn btn-secondary">← Voltar</a>
                </div>
                
                <div id="messageDiv" class="message" style="display: none;"></div>
                
                <form id="artigoForm" method="post" action="../api/artigos.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" value="<?php echo $artigo['id'] ?? ''; ?>">
                    <input type="hidden" name="action" value="<?php echo !empty($artigo['id']) ? 'update' : 'create'; ?>">
                    
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
                                
                                <?php $imagemIaUrl = uploadFileUrl($artigo['imagem_1x1'] ?? null); ?>
                                <div class="image-preview-container" id="imagePreviewContainer" style="<?php echo $imagemIaUrl ? '' : 'display:none;'; ?>">
                                    <div class="image-preview-box">
                                        <?php if ($imagemIaUrl): ?>
                                            <img src="<?php echo htmlspecialchars($imagemIaUrl); ?>" alt="Imagem do post" id="preview-imagem-ia">
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
                                <?php
                                    $imagemAtual = (!empty($artigo['imagem_principal'])) ? $artigo['imagem_principal'] : ($artigo['imagem_1x1'] ?? '');
                                    $imagemAtualUrl = uploadFileUrl($imagemAtual);
                                ?>
                                <div id="upload-imagem" style="<?php echo ($artigo['tipo_midia'] ?? 'imagem') === 'video' ? 'display:none;' : ''; ?>">
                                    <div class="form-group">
                                        <label>Imagem</label>
                                        <div class="media-upload-area" onclick="document.getElementById('imagem_principal').click()">
                                            <?php if ($imagemAtualUrl): ?>
                                                <img src="<?php echo htmlspecialchars($imagemAtualUrl); ?>" class="media-preview" style="display:block; max-width:100%;">
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
                
                <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
                <script>
                document.querySelectorAll('.form-tab').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.form-tab').forEach(function(b) { b.classList.remove('active'); });
                        document.querySelectorAll('.form-tab-content').forEach(function(c) { c.classList.remove('active'); });
                        btn.classList.add('active');
                        var target = document.getElementById(btn.dataset.tab);
                        if (target) target.classList.add('active');
                    });
                });

                function parseApiJsonResponse(response) {
                    return response.text().then(function(text) {
                        var data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            throw new Error('Resposta inválida do servidor.');
                        }
                        if (!response.ok) {
                            throw new Error(data.message || ('Erro HTTP ' + response.status));
                        }
                        return data;
                    });
                }

                function selecionarTipoMidia(tipo) {
                    document.getElementById('tipo_midia').value = tipo;
                    document.querySelectorAll('.media-type-option').forEach(function(el) { el.classList.remove('active'); });
                    var options = document.querySelectorAll('.media-type-option');
                    if (tipo === 'imagem' && options[0]) options[0].classList.add('active');
                    if (tipo === 'video' && options[1]) options[1].classList.add('active');

                    var uploadImagem = document.getElementById('upload-imagem');
                    var uploadVideo = document.getElementById('upload-video');
                    if (uploadImagem) uploadImagem.style.display = (tipo === 'imagem') ? '' : 'none';
                    if (uploadVideo) uploadVideo.style.display = (tipo === 'video') ? '' : 'none';
                }

                function selecionarStatus(status) {
                    document.getElementById('status_publicacao').value = status;
                    document.querySelectorAll('.status-option').forEach(function(el) { el.classList.remove('active'); });
                    var selected = document.querySelector('.status-option.' + status);
                    if (selected) selected.classList.add('active');

                    var scheduling = document.getElementById('schedulingSection');
                    if (scheduling) scheduling.style.display = (status === 'agendado') ? '' : 'none';
                }

                function toggleRecorrencia() {
                    // Mantido por compatibilidade de UI.
                }

                function previewMedia(input, tipo) {
                    if (!input || !input.files || !input.files[0]) return;
                    var file = input.files[0];
                    var area = input.closest('.form-group') ? input.closest('.form-group').querySelector('.media-upload-area') : null;
                    if (!area) return;

                    if (tipo === 'imagem' && file.type.indexOf('image/') === 0) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            area.innerHTML = '<img src="' + e.target.result + '" class="media-preview" style="display:block; max-width:100%;"><small>Clique para substituir</small>';
                        };
                        reader.readAsDataURL(file);
                    } else if (tipo === 'video' && file.type.indexOf('video/') === 0) {
                        area.innerHTML = '<div><i class="ph ph-film-strip"></i> ' + file.name + '</div><small>Vídeo selecionado</small>';
                    }
                }

                function atualizarRedesDestino() {
                    var checks = document.querySelectorAll('input[name="redes_check[]"]:checked');
                    var redes = [];
                    checks.forEach(function(chk) {
                        redes.push({ rede: chk.value, formato: '1:1' });
                    });
                    var hidden = document.getElementById('redes_destino');
                    if (hidden) hidden.value = JSON.stringify(redes);
                }

                function gerarSlug() {
                    var titulo = document.getElementById('titulo');
                    var slug = document.getElementById('slug');
                    if (!titulo || !slug || slug.dataset.touched === '1') return;
                    var s = (titulo.value || '')
                        .toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9\s-]/g, '')
                        .trim()
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                    slug.value = s;
                }

                (function() {
                    var slug = document.getElementById('slug');
                    if (slug) {
                        slug.addEventListener('input', function() { slug.dataset.touched = '1'; });
                    }
                    atualizarRedesDestino();
                })();

                document.getElementById('artigoForm')?.addEventListener('submit', function(e) {
                    e.preventDefault();

                    var form = this;
                    var formData = new FormData(form);
                    formData.set('action', formData.get('id') ? 'update' : 'create');

                    var btnSaveText = document.getElementById('btnSaveText');
                    var btnSaveLoader = document.getElementById('btnSaveLoader');
                    var messageDiv = document.getElementById('messageDiv');

                    if (btnSaveText) btnSaveText.style.display = 'none';
                    if (btnSaveLoader) btnSaveLoader.style.display = 'inline';

                    fetch('../api/artigos.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(parseApiJsonResponse)
                    .then(function(data) {
                        messageDiv.style.display = 'block';
                        if (data.success) {
                            messageDiv.className = 'message message-success';
                            messageDiv.textContent = '✅ ' + (data.message || 'Conteúdo salvo com sucesso.');
                            if (data.artigo_id && !formData.get('id')) {
                                setTimeout(function() {
                                    window.location.href = '?action=edit&id=' + encodeURIComponent(data.artigo_id);
                                }, 700);
                            }
                        } else {
                            messageDiv.className = 'message message-error';
                            messageDiv.textContent = '❌ ' + (data.message || 'Falha ao salvar conteúdo.');
                        }
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    })
                    .catch(function(error) {
                        messageDiv.style.display = 'block';
                        messageDiv.className = 'message message-error';
                        messageDiv.textContent = '❌ Erro de conexão: ' + error.message;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    })
                    .finally(function() {
                        if (btnSaveText) btnSaveText.style.display = 'inline';
                        if (btnSaveLoader) btnSaveLoader.style.display = 'none';
                    });
                });

                function deletarArtigo(id) {
                    if (!confirm('Tem certeza que deseja excluir este conteúdo?')) return;
                    var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value
                        || document.querySelector('input[name="csrf_token"]')?.value
                        || '';
                    if (!csrf) {
                        alert('CSRF inválido. Recarregue a página.');
                        return;
                    }
                    var fd = new FormData();
                    fd.append('action', 'delete');
                    fd.append('id', String(id));
                    fd.append('csrf_token', csrf);
                    fetch('../api/artigos.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Erro ao excluir.');
                            window.location.href = 'artigos.php';
                        })
                        .catch(function(err) { alert(err.message); });
                }

                function publicarAgoraLinkedIn(artigoId) {
                    var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
                    if (!csrf) {
                        alert('CSRF inválido. Recarregue a página.');
                        return;
                    }
                    var fd = new FormData();
                    fd.append('action', 'publish_now_linkedin');
                    fd.append('id', String(artigoId));
                    fd.append('csrf_token', csrf);
                    fetch('../api/artigos.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Falha ao publicar no LinkedIn.');
                            alert('✅ Publicação enviada ao LinkedIn.');
                        })
                        .catch(function(err) { alert('❌ ' + err.message); });
                }

                function gerarConteudoIA() {
                    var prompt = (document.getElementById('prompt_texto')?.value || '').trim();
                    if (!prompt) {
                        alert('Preencha o prompt para gerar conteúdo.');
                        return;
                    }

                    var fd = new FormData();
                    fd.append('action', 'generate_article');
                    fd.append('tema', prompt);
                    var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
                    if (csrf) fd.append('csrf_token', csrf);

                    fetch('../api/gemini.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Falha ao gerar conteúdo.');

                            var titulo = data.titulo || '';
                            var resumo = data.resumo || '';
                            var conteudo = data.conteudo || data.texto || '';

                            if (titulo && document.getElementById('titulo')) document.getElementById('titulo').value = titulo;
                            if (resumo && document.getElementById('resumo')) document.getElementById('resumo').value = resumo;
                            if (conteudo && document.getElementById('conteudo')) document.getElementById('conteudo').value = conteudo;
                            gerarSlug();
                            alert('✅ Conteúdo gerado com sucesso.');
                        })
                        .catch(function(err) {
                            alert('❌ ' + err.message);
                        });
                }

                function gerarImagemIA() {
                    var prompt = (document.getElementById('prompt_imagem')?.value || '').trim()
                        || (document.getElementById('prompt_texto')?.value || '').trim()
                        || (document.getElementById('titulo')?.value || '').trim();
                    if (!prompt) {
                        alert('Preencha um prompt para gerar imagem.');
                        return;
                    }

                    var fd = new FormData();
                    fd.append('action', 'generate_image');
                    fd.append('prompt', prompt);
                    var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
                    if (csrf) fd.append('csrf_token', csrf);

                    fetch('../api/gemini.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Falha ao gerar imagem.');
                            var url = data.url || data.image_url || '';
                            if (!url) throw new Error('Resposta sem URL da imagem.');

                            var container = document.getElementById('imagePreviewContainer');
                            if (container) container.style.display = '';
                            var preview = document.getElementById('preview-imagem-ia');
                            if (preview && preview.tagName.toLowerCase() === 'img') {
                                preview.src = url;
                            } else {
                                var box = container ? container.querySelector('.image-preview-box') : null;
                                if (box) box.innerHTML = '<img src="' + url + '" alt="Imagem do post" id="preview-imagem-ia">';
                            }
                            if (data.filename && document.getElementById('imagem_1x1')) {
                                document.getElementById('imagem_1x1').value = data.filename;
                            }
                            alert('✅ Imagem gerada com sucesso.');
                        })
                        .catch(function(err) {
                            alert('❌ ' + err.message);
                        });
                }

                function gerarI18nSeo(entity, id, langs) {
                    var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
                    var fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('entity', entity);
                    fd.append('id', String(id));
                    (langs || []).forEach(function(lang) { fd.append('langs[]', lang); });

                    fetch('../api/i18n_seo.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Falha ao gerar i18n/SEO.');
                            alert('✅ i18n/SEO gerado com sucesso.');
                            window.location.reload();
                        })
                        .catch(function(err) {
                            alert('❌ ' + err.message);
                        });
                }

                function marcarI18nRevisado(entity, id, lang) {
                    var csrf = document.querySelector('#artigoForm input[name="csrf_token"]')?.value || '';
                    var fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('entity', entity);
                    fd.append('id', String(id));
                    fd.append('lang', lang);
                    fd.append('status', 'reviewed');

                    fetch('../api/i18n_status.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Falha ao atualizar status.');
                            window.location.reload();
                        })
                        .catch(function(err) {
                            alert('❌ ' + err.message);
                        });
                }

                function verSeo(entity, id, lang) {
                    fetch('../api/get_i18n_seo.php?entity=' + encodeURIComponent(entity) + '&id=' + encodeURIComponent(id) + '&lang=' + encodeURIComponent(lang), {
                        credentials: 'same-origin'
                    })
                        .then(parseApiJsonResponse)
                        .then(function(data) {
                            if (!data.success) throw new Error(data.message || 'Falha ao carregar SEO.');
                            alert('SEO ' + lang.toUpperCase() + '\n\n' + JSON.stringify(data.data || {}, null, 2));
                        })
                        .catch(function(err) {
                            alert('❌ ' + err.message);
                        });
                }
                </script>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
