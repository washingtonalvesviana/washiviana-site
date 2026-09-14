<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-squares-four"></i></span>
            <span class="sidebar-text">Dashboard</span>
        </a>
        
        <a href="projetos.php" class="sidebar-link <?php echo $current_page == 'projetos.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-briefcase"></i></span>
            <span class="sidebar-text">Projetos</span>
        </a>
        
        <a href="artigos.php" class="sidebar-link <?php echo $current_page == 'artigos.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-newspaper"></i></span>
            <span class="sidebar-text">Conteúdos</span>
        </a>
        
        <a href="calendario.php" class="sidebar-link <?php echo $current_page == 'calendario.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-calendar-blank"></i></span>
            <span class="sidebar-text">Calendário</span>
        </a>

        <a href="radar.php" class="sidebar-link <?php echo $current_page == 'radar.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-broadcast"></i></span>
            <span class="sidebar-text">Radar</span>
        </a>
        
        <a href="categorias.php" class="sidebar-link <?php echo $current_page == 'categorias.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-folders"></i></span>
            <span class="sidebar-text">Categorias</span>
        </a>
        
        <a href="configuracoes.php" class="sidebar-link <?php echo $current_page == 'configuracoes.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-gear"></i></span>
            <span class="sidebar-text">Configurações</span>
        </a>
        
        <a href="redes-sociais.php" class="sidebar-link <?php echo $current_page == 'redes-sociais.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-share-network"></i></span>
            <span class="sidebar-text">Redes Sociais</span>
        </a>
        
        <a href="senha.php" class="sidebar-link <?php echo $current_page == 'senha.php' ? 'active' : ''; ?>">
            <span class="sidebar-icon"><i class="ph ph-lock-key"></i></span>
            <span class="sidebar-text">Segurança</span>
        </a>
        
        <hr class="sidebar-divider">
        
        <a href="../index.php" class="sidebar-link" target="_blank">
            <span class="sidebar-icon"><i class="ph ph-globe"></i></span>
            <span class="sidebar-text">Ver Site</span>
        </a>
    </nav>
</aside>
