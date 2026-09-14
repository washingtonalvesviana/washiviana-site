<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Categorias
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

// Buscar categorias de projetos
$stmt = $pdo->query("SELECT c.*, COUNT(p.id) as total_projetos 
                    FROM categorias c 
                    LEFT JOIN projetos p ON p.categoria_id = c.id 
                    GROUP BY c.id 
                    ORDER BY c.ordem ASC");
$categorias = $stmt->fetchAll();

// Buscar categorias de artigos
$stmt = $pdo->query("SELECT ca.*, COUNT(a.id) as total_artigos 
                    FROM categorias_artigos ca 
                    LEFT JOIN artigos a ON a.categoria_id = ca.id 
                    GROUP BY ca.id 
                    ORDER BY ca.ordem ASC");
$categoriasArtigos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo assetVersion('assets/css/admin.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1><i class="ph ph-folder"></i> Categorias</h1>
                <button onclick="abrirModalNovaCategoria()" class="btn btn-primary"><i class="ph ph-plus-circle"></i> Nova Categoria</button>
            </div>
            
            <div id="messageDiv" class="message" style="display: none;"></div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Categorias de Projetos</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($categorias)): ?>
                        <p class="text-muted text-center">Nenhuma categoria cadastrada.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Slug</th>
                                    <th>Projetos</th>
                                    <th>Status</th>
                                    <th>Ordem</th>
                                    <th style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="categoriasTable">
                                <?php foreach ($categorias as $cat): ?>
                                    <tr data-id="<?php echo $cat['id']; ?>">
                                        <td><strong><?php echo htmlspecialchars($cat['nome']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                        <td><?php echo $cat['total_projetos']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $cat['ativo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $cat['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $cat['ordem']; ?></td>
                                        <td>
                                            <button type="button" onclick="editarCategoria(<?php echo $cat['id']; ?>)" class="btn-icon edit" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                                            <button type="button" onclick="deletarCategoria(<?php echo $cat['id']; ?>)" class="btn-icon delete" title="Deletar"><i class="ph ph-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Categoria Artigos -->
            <div class="card mt-6">
                <div class="card-header">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>Categorias de Artigos</h3>
                        <button onclick="abrirModalNovaCategoriaArtigo()" class="btn btn-sm btn-primary"><i class="ph ph-plus-circle"></i> Nova Categoria</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($categoriasArtigos)): ?>
                        <p class="text-muted text-center">Nenhuma categoria de artigos cadastrada.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Slug</th>
                                    <th>Artigos</th>
                                    <th>Status</th>
                                    <th>Ordem</th>
                                    <th style="width: 180px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="categoriasArtigosTable">
                                <?php foreach ($categoriasArtigos as $cat): ?>
                                    <tr data-id="<?php echo $cat['id']; ?>">
                                        <td><strong><?php echo htmlspecialchars($cat['nome']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                        <td><?php echo $cat['total_artigos']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $cat['ativo'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $cat['ativo'] ? 'Ativa' : 'Inativa'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $cat['ordem']; ?></td>
                                        <td>
                                            <button type="button" onclick="editarCategoriaArtigo(<?php echo $cat['id']; ?>)" class="btn-icon edit" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                                            <button type="button" onclick="deletarCategoriaArtigo(<?php echo $cat['id']; ?>)" class="btn-icon delete" title="Deletar"><i class="ph ph-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Nova/Editar Categoria -->
    <div id="categoriaModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nova Categoria</h2>
                <button onclick="fecharModal()" class="modal-close"><i class="ph ph-x"></i></button>
            </div>
            <form id="categoriaForm" onsubmit="salvarCategoria(event)">
                <input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <input type="hidden" id="categoria_id">
                    
                    <div class="form-group">
                        <label for="nome">Nome *</label>
                        <input type="text" id="nome" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="ativo" checked>
                            <span>Categoria Ativa</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="btnSaveText">Salvar</span>
                        <span id="btnSaveLoader" style="display:none;">Salvando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Nova/Editar Categoria de Artigos -->
    <div id="categoriaArtigoModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitleArtigo">Nova Categoria de Artigos</h2>
                <button onclick="fecharModalArtigo()" class="modal-close"><i class="ph ph-x"></i></button>
            </div>
            <form id="categoriaArtigoForm" onsubmit="salvarCategoriaArtigo(event)">
                <input type="hidden" id="csrf_token_artigo" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <input type="hidden" id="categoriaArtigo_id">
                    
                    <div class="form-group">
                        <label for="nomeArtigo">Nome *</label>
                        <input type="text" id="nomeArtigo" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="ativoArtigo" checked>
                            <span>Categoria Ativa</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="fecharModalArtigo()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span id="btnSaveTextArtigo">Salvar</span>
                        <span id="btnSaveLoaderArtigo" style="display:none;">Salvando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo assetVersion('assets/js/admin.js'); ?>"></script>
    <script>
        // Script específico de categorias
        const categoriasData = <?php echo json_encode($categorias); ?>;
        const categoriasArtigosData = <?php echo json_encode($categoriasArtigos); ?>;
    </script>
</body>
</html>
