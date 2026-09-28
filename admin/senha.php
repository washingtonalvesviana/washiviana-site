<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Segurança (alterar senha)
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segurança - Admin Washiviana</title>
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
                <h1><i class="ph ph-lock-key"></i> Segurança</h1>
            </div>

            <div class="card" style="max-width: 560px;">
                <div class="card-header">
                    <h3>Alterar senha</h3>
                </div>
                <div class="card-body">
                    <form id="senhaForm" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken()); ?>">

                        <div class="form-group">
                            <label>Senha atual</label>
                            <input type="password" name="current_password" required autocomplete="current-password">
                        </div>

                        <div class="form-group">
                            <label>Nova senha</label>
                            <input type="password" name="new_password" minlength="8" required autocomplete="new-password">
                            <small class="text-muted">Mínimo de 8 caracteres. Não use a senha padrão.</small>
                        </div>

                        <div class="form-group">
                            <label>Confirmar nova senha</label>
                            <input type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <span id="btnSenhaText"><i class="ph ph-floppy-disk"></i> Salvar nova senha</span>
                            <span id="btnSenhaLoader" style="display:none;"><span class="spinner" style="vertical-align:middle;margin-right:6px;"></span>Salvando...</span>
                        </button>

                        <div id="senhaMsg" style="margin-top:12px;"></div>
                    </form>
                </div>
            </div>

            <script>
                document.getElementById('senhaForm').addEventListener('submit', function (e) {
                    e.preventDefault();
                    var form = e.target;
                    var msg = document.getElementById('senhaMsg');
                    var btn = form.querySelector('button[type="submit"]');
                    var btnText = document.getElementById('btnSenhaText');
                    var btnLoader = document.getElementById('btnSenhaLoader');
                    var fd = new FormData(form);
                    fd.append('action', 'change_password');

                    msg.textContent = '';
                    btn.disabled = true;
                    if (btnText) btnText.style.display = 'none';
                    if (btnLoader) btnLoader.style.display = 'inline';

                    fetch('../api/auth.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                if (!response.ok || !data.success) throw new Error(data.message || 'Erro ao alterar a senha.');
                                return data;
                            });
                        })
                        .then(function (data) {
                            msg.style.color = '#059669';
                            msg.textContent = '✅ ' + data.message;
                            form.reset();
                        })
                        .catch(function (err) {
                            msg.style.color = '#dc2626';
                            msg.textContent = '❌ ' + err.message;
                        })
                        .finally(function () {
                            btn.disabled = false;
                            if (btnText) btnText.style.display = 'inline';
                            if (btnLoader) btnLoader.style.display = 'none';
                        });
                });
            </script>
        </main>
    </div>
</body>
</html>
