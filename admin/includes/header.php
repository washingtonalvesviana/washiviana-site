<?php
// Garantir token CSRF disponível para formulários renderizados após o header
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<header class="admin-header">
    <div class="header-left" style="display: flex; align-items: center; gap: 12px;">
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Menu">
            <i class="ph ph-list"></i>
        </button>
        <h1 class="header-logo">
            <a href="dashboard.php">WV Admin</a>
        </h1>
    </div>
    <div class="header-right">
        <span class="header-user">
            <i class="ph ph-user"></i> <?php echo htmlspecialchars($_SESSION['user_nome']); ?>
        </span>
        <a href="logout.php" class="btn btn-sm btn-secondary"><i class="ph ph-sign-out"></i></a>
    </div>
</header>
<div class="mobile-menu-overlay" onclick="toggleMobileMenu()"></div>

