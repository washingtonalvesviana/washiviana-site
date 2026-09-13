<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Projetos
 * Gerenciamento de projetos com IA Copilot
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

$action = $_GET['action'] ?? 'list';
$projetoId = intval($_GET['id'] ?? 0);

// Buscar categorias
$stmt = $pdo->query("SELECT * FROM categorias WHERE ativo = true ORDER BY nome ASC");
$categorias = $stmt->fetchAll();

// Se estiver editando, buscar projeto
$projeto = null;
$projetoI18n = ['pt' => null, 'en' => null, 'es' => null];
$postsLinkedin = [];
if ($action == 'edit' && $projetoId) {
    $stmt = $pdo->prepare("SELECT * FROM projetos WHERE id = ?");
    $stmt->execute([$projetoId]);
    $projeto = $stmt->fetch();
    
    if ($projeto) {
        $projeto['imagens_galeria'] = json_decode($projeto['imagens_galeria'], true) ?: [];
        
        // Buscar posts do LinkedIn
        $stmt = $pdo->prepare("SELECT * FROM posts_linkedin WHERE projeto_id = ? ORDER BY gerado_em DESC");
        $stmt->execute([$projetoId]);
        $postsLinkedin = $stmt->fetchAll();

        // Status i18n/SEO (EN/ES)
        try {
            $stmt = $pdo->prepare("SELECT lang, slug, status_traducao, generated_at, updated_at FROM projetos_i18n WHERE projeto_id = ? AND lang IN ('pt','en','es')");
            $stmt->execute([$projetoId]);
            foreach ($stmt->fetchAll() as $row) {
                $projetoI18n[$row['lang']] = $row;
            }
        } catch (Exception $e) {
            // ignore
        }
    }
}

// Se for listagem, buscar projetos
$projetos = [];
$projetosI18nMap = [];
$projetosMissing = ['en' => [], 'es' => []];
	if ($action == 'list') {
	    $stmt = $pdo->query("SELECT p.*, c.nome as categoria_nome 
	                        FROM projetos p 
	                        LEFT JOIN categorias c ON p.categoria_id = c.id 
	                        ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC");
	    $projetos = $stmt->fetchAll();

    // Mapear status EN/ES para badges na listagem
    try {
        $stmt = $pdo->query("SELECT projeto_id, lang, status_traducao FROM projetos_i18n WHERE lang IN ('pt','en','es')");
        foreach ($stmt->fetchAll() as $row) {
            $projetosI18nMap[(int)$row['projeto_id']][$row['lang']] = $row['status_traducao'];
        }
    } catch (Exception $e) {
        $projetosI18nMap = [];
    }

    foreach ($projetos as $p) {
        $st = $projetosI18nMap[(int)$p['id']] ?? [];
        if (empty($st['pt'])) $projetosMissing['pt'][] = (int)$p['id'];
        if (empty($st['en'])) $projetosMissing['en'][] = (int)$p['id'];
        if (empty($st['es'])) $projetosMissing['es'][] = (int)$p['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projetos - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <?php if ($action == 'list'): ?>
                <!-- LISTAGEM DE PROJETOS -->
                <div class="page-header">
                    <h1>Projetos</h1>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <?php if (!empty($projetosMissing['pt']) || !empty($projetosMissing['en']) || !empty($projetosMissing['es'])): ?>
                            <button type="button" class="btn btn-secondary" id="btnBulkI18nProjetos" onclick="gerarI18nSeoBulkProjetos()">
                                <i class="ph ph-globe"></i> Gerar traduções (um por vez)
                            </button>
                        <?php endif; ?>
                        <a href="?action=new" class="btn btn-primary"><i class="ph ph-plus-circle"></i> Novo Projeto</a>
                    </div>
                </div>
                
                <div id="messageDiv" class="message" style="display: none;"></div>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($projetos)): ?>
                            <p class="text-muted text-center">Nenhum projeto cadastrado.</p>
                            <div class="text-center">
                                <a href="?action=new" class="btn btn-primary">Criar Primeiro Projeto</a>
                            </div>
                        <?php else: ?>
	                            <table class="table">
	                                <thead>
	                                    <tr>
	                                        <th style="width: 80px;">Imagem</th>
	                                        <th>Título</th>
	                                        <th>Categoria</th>
	                                        <th style="width: 80px;">Ordem</th>
	                                        <th style="width: 120px;">Idiomas</th>
	                                        <th>Status</th>
	                                        <th>Destaque</th>
	                                        <th>Data</th>
	                                        <th style="width: 150px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projetos as $p): ?>
                                        <tr>
	                                            <td>
                                                    <?php $projetoThumbUrl = uploadFileUrl($p['imagem_principal'] ?? null); ?>
	                                                <?php if ($p['imagem_principal']): ?>
	                                                    <?php if (isVideoFilename($p['imagem_principal'])): ?>
	                                                        <div class="table-thumb-placeholder"><i class="ph ph-film-strip"></i></div>
                                                        <?php elseif ($projetoThumbUrl): ?>
                                                            <img src="<?php echo htmlspecialchars($projetoThumbUrl); ?>" 
                                                                 alt="<?php echo htmlspecialchars($p['titulo']); ?>" 
                                                                 class="table-thumb">
	                                                    <?php else: ?>
                                                            <div class="table-thumb-placeholder"><i class="ph ph-image"></i></div>
	                                                    <?php endif; ?>
	                                                <?php else: ?>
	                                                    <div class="table-thumb-placeholder"><i class="ph ph-image"></i></div>
	                                                <?php endif; ?>
	                                            </td>
	                                            <td><strong><?php echo htmlspecialchars($p['titulo']); ?></strong></td>
	                                            <td><?php echo htmlspecialchars($p['categoria_nome']); ?></td>
	                                            <td><?php echo (!empty($p['ordem']) && (int)$p['ordem'] > 0) ? (int)$p['ordem'] : '-'; ?></td>
	                                            <td>
	                                                <?php
                                                    $st = $projetosI18nMap[(int)$p['id']] ?? [];
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
                                                <span class="badge badge-<?php echo $p['ativo'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $p['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($p['destaque']): ?>
                                                    <span class="badge badge-warning"><i class="ph ph-star-fill"></i> Destaque</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($p['created_at']); ?></td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $p['id']; ?>" class="btn-icon edit" title="Editar"><i class="ph ph-pencil-simple"></i></a>
                                                <button type="button" onclick="deletarProjeto(<?php echo $p['id']; ?>)" class="btn-icon delete" title="Deletar"><i class="ph ph-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- CRIAR/EDITAR PROJETO -->
                <div class="page-header">
                    <h1><?php echo $action == 'new' ? 'Novo Projeto' : 'Editar Projeto'; ?></h1>
                    <a href="?action=list" class="btn btn-secondary">← Voltar</a>
                </div>
                
                <div id="messageDiv" class="message" style="display: none;"></div>
                
<form id="projetoForm" class="project-form" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $projeto['id'] ?? ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <!-- Abas de Navegação -->
                    <div class="form-tabs">
                        <button type="button" class="form-tab active" data-tab="tab-info">
                            <i class="ph ph-info"></i> Informações
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-midias">
                            <i class="ph ph-image"></i> Mídia
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-traduzir">
                            <i class="ph ph-globe"></i> Traduções & SEO
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-ia">
                            <i class="ph ph-sparkle"></i> IA Copilot
                        </button>
                        <button type="button" class="form-tab" data-tab="tab-opcoes">
                            <i class="ph ph-gear"></i> Opções
                        </button>
                    </div>
                    
                    <!-- Tab 1: Informações -->
                    <div class="form-tab-content active" id="tab-info">
                        <div class="card">
                            <div class="card-header"><h3>Informações do Projeto</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="titulo">Título *</label>
                                    <input type="text" id="titulo" name="titulo" required 
                                           value="<?php echo htmlspecialchars($projeto['titulo'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="slug">Slug / URL Amigável *</label>
                                    <input type="text" id="slug" name="slug" required 
                                           value="<?php echo htmlspecialchars($projeto['slug'] ?? ''); ?>"
                                           placeholder="titulo-do-projeto">
                                    <small class="form-text">Deixe em branco para gerar automaticamente do título.</small>
                                </div>

                                <div class="form-group">
                                    <label for="categoria_id">Categoria *</label>
                                    <select id="categoria_id" name="categoria_id" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" 
                                                    <?php echo ($projeto['categoria_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="ordem">Ordem (opcional)</label>
                                    <input type="number" id="ordem" name="ordem" min="1" step="1"
                                           value="<?php
                                               $ordemVal = $projeto['ordem'] ?? '';
                                               if ($ordemVal === null || (string)$ordemVal === '0') $ordemVal = '';
                                               echo htmlspecialchars((string)$ordemVal);
                                           ?>">
                                    <small class="form-text">Se vazio, o projeto entra na lista por data de criação (mais recente primeiro).</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="descricao">Descrição</label>
                                    <textarea id="descricao" name="descricao" rows="6"><?php echo htmlspecialchars($projeto['descricao'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tecnologias">Tecnologias Utilizadas</label>
                                    <input type="text" id="tecnologias" name="tecnologias" 
                                           placeholder="Ex: React, Node.js, MongoDB"
                                           value="<?php echo htmlspecialchars($projeto['tecnologias'] ?? ''); ?>">
                                    <small class="form-text">Separe por vírgulas</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="url_projeto">URL do Projeto (opcional)</label>
                                    <input type="url" id="url_projeto" name="url_projeto" 
                                           placeholder="https://"
                                           value="<?php echo htmlspecialchars($projeto['url_projeto'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">
                            <span id="btnSaveText"><?php echo $action == 'new' ? 'Criar Projeto' : 'Salvar Alterações'; ?></span>
                            <span id="btnSaveLoader" style="display:none;">Salvando...</span>
                        </button>
                    </div>
                    
                    <!-- Tab 2: Mídia -->
                    <div class="form-tab-content" id="tab-midias">
                        <div class="card">
                            <div class="card-header"><h3>Mídia Principal</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <input type="file" id="imagem_principal" name="imagem_principal" accept="image/*,video/mp4,video/webm">
                                    <small class="form-text">Aceita imagem (JPG/PNG/WebP/GIF) ou vídeo (MP4/WebM).</small>
                                    <?php if (!empty($projeto['imagem_principal'])): ?>
                                        <?php $principalMediaUrl = uploadFileUrl($projeto['imagem_principal']); ?>
                                        <div class="image-preview">
                                            <?php if (isVideoFilename($projeto['imagem_principal']) && $principalMediaUrl): ?>
                                                <video src="<?php echo htmlspecialchars($principalMediaUrl); ?>" controls class="w-full" style="max-height:320px;"></video>
                                            <?php elseif (!isVideoFilename($projeto['imagem_principal']) && $principalMediaUrl): ?>
                                                <img src="<?php echo htmlspecialchars($principalMediaUrl); ?>" alt="Preview">
                                            <?php else: ?>
                                                <div class="table-thumb-placeholder"><i class="ph ph-image"></i></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card" style="margin-top:16px;">
                            <div class="card-header"><h3>Galeria de Mídias</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <input type="file" id="imagens_galeria" name="imagens_galeria[]" accept="image/*,video/mp4,video/webm" multiple>
                                    <small class="form-text">Selecione múltiplos arquivos (imagens e/ou vídeos).</small>
                                    <?php if (!empty($projeto['imagens_galeria'])): ?>
                                        <div class="gallery-preview">
                                            <?php foreach ($projeto['imagens_galeria'] as $img): ?>
                                                <div class="gallery-item" data-filename="<?php echo htmlspecialchars($img); ?>" style="display:inline-block; position:relative; margin:6px;">
                                                    <?php $galleryMediaUrl = uploadFileUrl($img); ?>
                                                    <?php if ($galleryMediaUrl && isVideoFilename($img)): ?>
                                                        <video src="<?php echo htmlspecialchars($galleryMediaUrl); ?>" controls style="max-width:180px; max-height:120px; display:block;"></video>
                                                    <?php elseif ($galleryMediaUrl): ?>
                                                        <img src="<?php echo htmlspecialchars($galleryMediaUrl); ?>" alt="Gallery" style="max-width:180px; max-height:120px; display:block;">
                                                    <?php else: ?>
                                                        <div class="table-thumb-placeholder" style="width:180px;height:120px;"><i class="ph ph-image"></i></div>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-danger" style="position:absolute; top:6px; right:6px;" onclick="deleteMedia(<?php echo (int)$projeto['id']; ?>, '<?php echo addslashes($img); ?>')">Deletar</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">
                            <span id="btnSaveText2"><?php echo $action == 'new' ? 'Criar Projeto' : 'Salvar Alterações'; ?></span>
                        </button>
                    </div>
                    
                    <!-- Tab 3: Traduções & SEO -->
                    <div class="form-tab-content" id="tab-traduzir">
                        <div class="card">
                            <div class="card-header"><h3>Idiomas & SEO</h3></div>
                            <div class="card-body">
                                <?php if (empty($projeto['id'])): ?>
                                    <p class="text-muted">Salve o projeto primeiro para gerar traduções.</p>
                                <?php else: ?>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                                        <button type="button" class="btn btn-secondary" onclick="gerarI18nSeoProjeto(<?php echo (int)$projeto['id']; ?>, ['pt'])"><i class="ph ph-sparkle"></i> Otimizar SEO PT</button>
                                        <button type="button" class="btn btn-primary" onclick="gerarI18nSeoProjeto(<?php echo (int)$projeto['id']; ?>, ['en','es'])"><i class="ph ph-sparkle"></i> Gerar EN + ES</button>
                                        <button type="button" class="btn btn-secondary" onclick="gerarI18nSeoProjeto(<?php echo (int)$projeto['id']; ?>, ['pt','en','es'])">Gerar Tudo (3)</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table" style="margin:0;">
                                            <thead>
                                                <tr>
                                                    <th style="width:70px;">Lang</th>
                                                    <th style="width:120px;">Status</th>
                                                    <th>Slug</th>
                                                    <th style="width:120px;">Atualizado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lang => $label): ?>
                                                    <?php $row = $projetoI18n[$lang] ?? null; ?>
                                                    <tr>
                                                        <td><strong><?php echo $label; ?></strong></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $row ? (($row['status_traducao'] ?? '') === 'reviewed' ? 'warning' : 'success') : 'secondary'; ?>">
                                                                <?php echo htmlspecialchars($row['status_traducao'] ?? '—'); ?>
                                                            </span>
                                                            <?php if ($row && ($row['status_traducao'] ?? '') !== 'reviewed'): ?>
                                                                <button type="button" class="btn btn-sm btn-secondary" style="margin-left:4px;" onclick="marcarI18nRevisadoProjeto(<?php echo (int)$projeto['id']; ?>, '<?php echo $lang; ?>')">
                                                                    <i class="ph ph-check-circle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($row): ?>
                                                                <button type="button" class="btn btn-sm btn-info" style="margin-left:4px;" onclick="verSeo('projeto', <?php echo (int)$projeto['id']; ?>, '<?php echo $lang; ?>')">
                                                                    <i class="ph ph-eye"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><code><?php echo htmlspecialchars($row['slug'] ?? '—'); ?></code></td>
                                                        <td><?php echo !empty($row['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['updated_at']))) : '—'; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p class="text-muted" style="margin-top:10px;">Gera tradução + meta title/description + slug por idioma (Gemini).</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 4: IA Copilot -->
                    <div class="form-tab-content" id="tab-ia">
                        <div class="card">
                            <div class="card-header">
                                <h3>IA Copilot</h3>
                                <p class="card-subtitle">Geração automática de conteúdo</p>
                            </div>
                            <div class="card-body">
                                <div class="copilot-section">
                                    <h4>Gerar Descrição do Projeto</h4>
                                    <div class="form-group">
                                        <label for="prompt_descricao">Prompt:</label>
                                        <textarea id="prompt_descricao" name="prompt_descricao" rows="3"><?php echo htmlspecialchars($projeto['prompt_descricao'] ?? ''); ?></textarea>
                                    </div>
                                    <button type="button" onclick="gerarDescricao()" class="btn btn-primary">
                                        <span id="btnGerarDescText"><i class="ph ph-sparkle"></i> Gerar</span>
                                        <span id="btnGerarDescLoader" style="display:none;">Gerando...</span>
                                    </button>
                                    <div id="resultadoDescricao" class="copilot-result" style="display:none; margin-top:12px;">
                                        <label>Resultado:</label>
                                        <textarea id="textoDescricaoGerado" rows="4"></textarea>
                                        <button type="button" onclick="aplicarDescricao()" class="btn btn-success" style="margin-top:8px;"><i class="ph ph-check"></i> Aplicar</button>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="copilot-section">
                                    <h4>Gerar Post LinkedIn</h4>
                                    <div class="form-group">
                                        <label for="prompt_linkedin">Prompt:</label>
                                        <textarea id="prompt_linkedin" name="prompt_linkedin" rows="3"><?php echo htmlspecialchars($projeto['prompt_linkedin'] ?? ''); ?></textarea>
                                    </div>
                                    <button type="button" onclick="gerarLinkedin()" class="btn btn-primary">
                                        <span id="btnGerarLinkedText"><i class="ph ph-sparkle"></i> Gerar</span>
                                        <span id="btnGerarLinkedLoader" style="display:none;">Gerando...</span>
                                    </button>
                                    <div id="resultadoLinkedin" class="copilot-result" style="display:none; margin-top:12px;">
                                        <label>Resultado:</label>
                                        <textarea id="textoLinkedinGerado" rows="6"></textarea>
                                        <div style="display:flex; gap:8px; margin-top:8px;">
                                            <button type="button" onclick="salvarPostLinkedin()" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar</button>
                                            <button type="button" onclick="copiarLinkedin()" class="btn btn-secondary"><i class="ph ph-copy"></i> Copiar</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($postsLinkedin)): ?>
                                    <hr>
                                    <div class="copilot-section">
                                        <h4>Histórico de Posts</h4>
                                        <div class="posts-history">
                                            <?php foreach ($postsLinkedin as $post): ?>
                                                <div class="post-history-item">
                                                    <div class="post-history-header">
                                                        <span><?php echo formatDate($post['gerado_em'], 'd/m/Y H:i'); ?></span>
                                                        <button type="button" onclick="reutilizarPost(<?php echo $post['id']; ?>)" class="btn btn-sm btn-secondary">Reutilizar</button>
                                                    </div>
                                                    <div class="post-history-content">
                                                        <?php echo nl2br(limitText($post['conteudo'], 150)); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab 5: Opções -->
                    <div class="form-tab-content" id="tab-opcoes">
                        <div class="card">
                            <div class="card-header"><h3>Opções do Projeto</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="destaque" value="1" 
                                               <?php echo ($projeto['destaque'] ?? 0) ? 'checked' : ''; ?>>
                                        <span>Projeto em Destaque</span>
                                    </label>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="ativo" value="1" 
                                               <?php echo ($projeto['ativo'] ?? 1) ? 'checked' : ''; ?>>
                                        <span>Projeto Ativo</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">
                            <span id="btnSaveText3"><?php echo $action == 'new' ? 'Criar Projeto' : 'Salvar Alterações'; ?></span>
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
                // Tab navigation
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

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
    <script>
        const csrfTokenProjetosBulk = <?php echo json_encode(generateCsrfToken(), JSON_UNESCAPED_UNICODE); ?>;
        window.csrfToken = <?php echo json_encode(generateCsrfToken(), JSON_UNESCAPED_UNICODE); ?>;
        const projetosMissing = <?php echo json_encode($projetosMissing, JSON_UNESCAPED_UNICODE); ?>;

        async function postI18nSeoBulk(entity, id, lang) {
            const formData = new FormData();
            const csrf = document.querySelector('input[name="csrf_token"]');
            formData.append('csrf_token', csrf ? csrf.value : '');
            formData.append('entity', entity);
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

        async function gerarI18nSeoBulkProjetos() {
            const btn = document.getElementById('btnBulkI18nProjetos');
            if (!btn) return;
            
            // Build sequential queue: {id, lang} objects
            const queue = [];
            for (const lang of ['pt', 'en', 'es']) {
                const ids = projetosMissing[lang] || [];
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
            WVProgress.show('Otimizando projetos...', `0/${total} concluídos.`);
            
            let done = 0;
            let failed = false;
            try {
                for (const item of queue) {
                    if (failed) break;
                    done++;
                    WVProgress.update((done / total) * 100, `Processando projeto #${item.id} (${item.lang.toUpperCase()}) - ${done}/${total}...`);
                    
                    try {
                        await postI18nSeoBulk('projeto', item.id, item.lang);
                        // Small delay between requests to avoid rate limits
                        await new Promise(r => setTimeout(r, 800));
                    } catch (itemErr) {
                        failed = true;
                        // Only the last error details
                        throw itemErr;
                    }
                }
                WVProgress.hide();
                if (failed) {
                    alert('⚠️ Processo interrompido devido a erro. Alguns projetos possono não ter sido processados.');
                } else {
                    alert('✅ i18n/SEO gerado para projetos faltantes.');
                }
                window.location.reload();
            } catch (err) {
                WVProgress.hide();
                btn.disabled = false;
                alert('❌ Erro: ' + (err?.message || String(err)) + '\n\nProjeto não processado. Tente novamente mais tarde.');
            }
        }

        window.siteBaseUrl = <?php echo json_encode(BASE_URL, JSON_UNESCAPED_SLASHES); ?>;

        // Definir dados do projeto para JavaScript
        <?php if ($projeto): ?>
            window.projetoAtual = {
                id: <?php echo $projeto['id']; ?>,
                titulo: <?php echo json_encode($projeto['titulo']); ?>,
                slug: <?php echo json_encode($projeto['slug'] ?? ''); ?>,
                categoria_id: <?php echo $projeto['categoria_id']; ?>,
                tecnologias: <?php echo json_encode($projeto['tecnologias']); ?>
            };
        <?php else: ?>
            window.projetoAtual = null;
        <?php endif; ?>
        
        // Inicializar prompts da IA (apenas uma vez)
        let copilotPromptsInitialized = false;
        function initCopilotPromptsOnce() {
            if (copilotPromptsInitialized) return;
            copilotPromptsInitialized = true;
            initCopilotPrompts();
        }
        document.addEventListener('DOMContentLoaded', initCopilotPromptsOnce);

        // i18n + SEO (Gemini)
        function gerarI18nSeoProjeto(id, langs) {
            var form = document.getElementById('projetoForm');
            var csrf = form?.querySelector('input[name="csrf_token"]')?.value || '';
            if (!csrf) {
                alert('⚠️ CSRF inválido. Recarregue a página.');
                return;
            }

            var formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('entity', 'projeto');
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

        function marcarI18nRevisadoProjeto(id, lang) {
            var form = document.getElementById('projetoForm');
            var csrf = form?.querySelector('input[name="csrf_token"]')?.value || '';
            if (!csrf) {
                alert('⚠️ CSRF inválido. Recarregue a página.');
                return;
            }
            var formData = new FormData();
            formData.append('csrf_token', csrf);
            formData.append('entity', 'projeto');
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
    </script>
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
</body>
</html>

