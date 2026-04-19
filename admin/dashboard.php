<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Dashboard
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

// Buscar estatísticas
try {
    // Projetos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projetos");
    $totalProjetos = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projetos WHERE ativo = TRUE");
    $projetosAtivos = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM projetos WHERE destaque = TRUE");
    $projetosDestaque = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categorias");
    $totalCategorias = $stmt->fetch()['total'];
    
    // Conteúdos/Artigos
    $totalArtigos = 0;
    $artigosAtivos = 0;
    $ultimosArtigos = [];
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM artigos");
        $totalArtigos = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM artigos WHERE ativo = TRUE");
        $artigosAtivos = $stmt->fetch()['total'];
        
        // Últimos artigos
        $stmt = $pdo->query("SELECT a.*, ca.nome as categoria_nome 
                            FROM artigos a 
                            LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id 
                            ORDER BY a.created_at DESC LIMIT 5");
        $ultimosArtigos = $stmt->fetchAll();
    } catch (Exception $e) {
        // Tabela artigos pode não existir ainda
    }
    
    // Últimos projetos
    $stmt = $pdo->query("SELECT p.*, c.nome as categoria_nome 
                        FROM projetos p 
                        LEFT JOIN categorias c ON p.categoria_id = c.id 
                        ORDER BY p.created_at DESC LIMIT 5");
    $ultimosProjetos = $stmt->fetchAll();
    
    // Projetos por categoria
    $stmt = $pdo->query("SELECT c.nome, COUNT(p.id) as total 
                        FROM categorias c 
                        LEFT JOIN projetos p ON p.categoria_id = c.id 
                        GROUP BY c.id, c.nome 
                        ORDER BY total DESC");
    $projetosPorCategoria = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header animate-enter">
                <div>
                    <h1>Dashboard</h1>
                    <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['user_nome']); ?>!</p>
                </div>
            </div>
            
            <div class="stats-grid animate-enter delay-100">
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-briefcase"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalProjetos; ?></h3>
                        <p>Projetos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-article"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalArtigos; ?></h3>
                        <p>Conteúdos</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $projetosAtivos + $artigosAtivos; ?></h3>
                        <p>Publicados</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="ph ph-folder"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $totalCategorias; ?></h3>
                        <p>Categorias</p>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-row animate-enter delay-200">
                <div class="dashboard-col">
                    <!-- Últimos Projetos -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Últimos Projetos</h2>
                            <a href="projetos.php" class="btn btn-sm btn-primary">Ver Todos</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($ultimosProjetos)): ?>
                                <p class="text-muted">Nenhum projeto cadastrado ainda.</p>
                                <a href="projetos.php?action=new" class="btn btn-primary">Criar Primeiro Projeto</a>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Imagem</th>
                                            <th>Título</th>
                                            <th>Categoria</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosProjetos as $projeto): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($projeto['imagem_principal'])): ?>
                                                        <?php if (isVideoFilename($projeto['imagem_principal'])): ?>
                                                            <div class="table-thumb-placeholder"><i class="ph ph-film-strip"></i></div>
                                                        <?php else: ?>
                                                            <img src="<?php echo UPLOAD_URL . $projeto['imagem_principal']; ?>" 
                                                                 alt="<?php echo htmlspecialchars($projeto['titulo']); ?>" 
                                                                 class="table-thumb">
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="table-thumb-placeholder"><i class="ph ph-image"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="projetos.php?action=edit&id=<?php echo $projeto['id']; ?>">
                                                        <?php echo htmlspecialchars($projeto['titulo']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($projeto['categoria_nome']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $projeto['ativo'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $projeto['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($projeto['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Últimos Conteúdos -->
                    <div class="card">
                        <div class="card-header">
                            <h2>Últimos Conteúdos</h2>
                            <a href="artigos.php" class="btn btn-sm btn-primary">Ver Todos</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($ultimosArtigos)): ?>
                                <p class="text-muted">Nenhum conteúdo cadastrado ainda.</p>
                                <a href="artigos.php?action=new" class="btn btn-primary">Criar Primeiro Conteúdo</a>
                            <?php else: ?>
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Imagem</th>
                                            <th>Título</th>
                                            <th>Categoria</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimosArtigos as $artigo): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($artigo['imagem_1x1'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . $artigo['imagem_1x1']; ?>" 
                                                             alt="<?php echo htmlspecialchars($artigo['titulo']); ?>" 
                                                             class="table-thumb">
                                                    <?php elseif (!empty($artigo['imagem_principal'])): ?>
                                                        <img src="<?php echo UPLOAD_URL . $artigo['imagem_principal']; ?>" 
                                                             alt="<?php echo htmlspecialchars($artigo['titulo']); ?>" 
                                                             class="table-thumb">
                                                    <?php else: ?>
                                                        <div class="table-thumb-placeholder"><i class="ph ph-newspaper"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="artigos.php?action=edit&id=<?php echo $artigo['id']; ?>">
                                                        <?php echo htmlspecialchars($artigo['titulo']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($artigo['categoria_nome'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $artigo['ativo'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $artigo['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                    </span>
                                                    <?php if (!empty($artigo['fonte_ia'])): ?>
                                                        <span class="badge badge-info" title="Gerado por IA"><i class="ph ph-robot"></i></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDate($artigo['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-col">
                    <div class="card">
                        <div class="card-header">
                            <h2>Projetos por Categoria</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($projetosPorCategoria)): ?>
                                <p class="text-muted">Nenhuma categoria cadastrada.</p>
                            <?php else: ?>
                                <ul class="stat-list">
                                    <?php foreach ($projetosPorCategoria as $cat): ?>
                                        <li>
                                            <span><?php echo htmlspecialchars($cat['nome']); ?></span>
                                            <strong><?php echo $cat['total']; ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>Ações Rápidas</h2>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="projetos.php?action=new" class="btn btn-primary btn-block"><i class="ph ph-plus-circle"></i> Novo Projeto</a>
                                <a href="artigos.php?action=new" class="btn btn-primary btn-block"><i class="ph ph-note-pencil"></i> Novo Conteúdo</a>
                                <a href="radar.php" class="btn btn-secondary btn-block"><i class="ph ph-broadcast"></i> Radar (Pesquisa & Ideias)</a>
                                <a href="categorias.php" class="btn btn-secondary btn-block"><i class="ph ph-folders"></i> Gerenciar Categorias</a>
                                <a href="configuracoes.php" class="btn btn-secondary btn-block"><i class="ph ph-gear"></i> Configurações</a>
                                <a href="../index.php" class="btn btn-secondary btn-block" target="_blank"><i class="ph ph-globe"></i> Ver Site</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>

