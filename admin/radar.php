<?php
/**
 * WASHIVIANA - Admin Radar (Pesquisa + Ideias)
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

// Categorias de artigos (para mapear temas -> categoria)
$categorias = [];
try {
    $stmt = $pdo->query("SELECT id, nome FROM categorias_artigos WHERE ativo = true ORDER BY ordem ASC, nome ASC");
    $categorias = $stmt->fetchAll();
} catch (Exception $e) {
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radar - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        textarea.code { font-family: monospace; font-size: 12px; }
        .row-actions { display:flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body class="admin-page">
<?php include 'includes/header.php'; ?>

<div class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-content">
        <div class="page-header">
            <div>
                <h1><i class="ph ph-broadcast"></i> Radar</h1>
                <p class="muted">Pesquisa (RSS/scraping) <i class="ph ph-arrow-right"></i> Itens coletados <i class="ph ph-arrow-right"></i> Ideias com IA <i class="ph ph-arrow-right"></i> Rascunho em Conteúdos</p>
            </div>
        </div>

        <div id="messageDiv" class="message" style="display:none;"></div>

        <div class="tabs">
            <button class="tab-btn active" data-tab="temas"><i class="ph ph-hash"></i> Temas</button>
            <button class="tab-btn" data-tab="fontes"><i class="ph ph-rss"></i> Fontes</button>
            <button class="tab-btn" data-tab="coleta"><i class="ph ph-tray"></i> Coleta</button>
            <button class="tab-btn" data-tab="ideias"><i class="ph ph-lightbulb"></i> Ideias</button>
        </div>

        <section class="tab-panel active" id="tab-temas">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><h3>Novo tema</h3></div>
                    <div class="card-body">
                        <form id="topicForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="id" value="">
                            <div class="form-group">
                                <label>Nome</label>
                                <input type="text" name="nome" placeholder="Ex.: Automação & IA aplicada" required>
                            </div>
                            <div class="form-group">
                                <label>Descrição (opcional)</label>
                                <textarea name="descricao" rows="3" placeholder="Contexto/objetivo do tema"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Keywords (opcional)</label>
                                <textarea name="keywords" rows="3" placeholder="Ex.: agents, RPA, LLM, workflows, n8n"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Idiomas</label>
                                <input type="text" name="idiomas" value="pt,en" placeholder="pt,en">
                                <small class="form-text">Formato: lista separada por vírgula (ex.: pt,en)</small>
                            </div>
                            <div class="form-group">
                                <label>Regiões</label>
                                <input type="text" name="regioes" value="br,us,eu" placeholder="br,us,eu">
                                <small class="form-text">A “região” é um filtro lógico; na prática você controla pelas fontes.</small>
                            </div>
                            <div class="form-group">
                                <label>Categoria (Conteúdos)</label>
                                <select name="categoria_artigos_id">
                                    <option value="">(sem categoria padrão)</option>
                                    <?php foreach ($categorias as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="ativo" checked> Ativo
                                </label>
                            </div>
                            <div class="row-actions">
                                <button type="submit" class="btn btn-primary btn-xs">Salvar</button>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="resetTopicForm()">Limpar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>Temas cadastrados</h3></div>
                    <div class="card-body">
                        <div class="muted" style="margin-bottom:10px;">Dica: crie temas por área e depois associe fontes (RSS/scrape) para cada um.</div>
                        <div style="overflow:auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Idiomas</th>
                                        <th>Regiões</th>
                                        <th>Categoria</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="topicsTable"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="tab-panel" id="tab-fontes">
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><h3>Nova fonte</h3></div>
                    <div class="card-body">
                        <form id="sourceForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="id" value="">
                            <div class="form-group">
                                <label>Nome</label>
                                <input type="text" name="nome" placeholder="Ex.: TechCrunch (RSS)" required>
                            </div>
                            <div class="form-group">
                                <label>Tipo</label>
                                <select name="tipo" id="sourceTipo">
                                    <option value="rss">RSS</option>
                                    <option value="scrape">Scrape (página)</option>
                                    <option value="api">API (JSON)</option>
                                </select>
                                <small id="apiConfigHelp" class="muted" style="display:none; margin-top:8px; display:block;">Exemplo (Reddit r/technology):
                                <code style="white-space:pre; display:block; padding:6px; background:#f8f9fa; border:1px solid var(--border); border-radius:6px; margin-top:6px;">{
  "method": "GET",
  "headers": { "User-Agent": "WashivianaRadar/1.0 (+https://washiviana.com)" },
  "items_path": "data.children",
  "url_path": "data.url",
  "title_path": "data.title",
  "description_path": "data.selftext",
  "date_path": "data.created_utc"
}</code>
                                </small>
                            </div>
                            <div class="form-group">
                                <label>URL</label>
                                <input type="url" name="url" placeholder="https://..." required>
                            </div>
                            <div class="form-group">
                                <label>Config JSON (opcional)</label>
                                <textarea class="code" name="config" rows="5" placeholder='{"timeout":20,"maxBytes":2000000}'></textarea>
                                <small class="form-text">No MVP: timeout/maxBytes. O sistema bloqueia URLs internas por segurança (SSRF).</small>
                            </div>
                            <div class="form-group">
                                <label><input type="checkbox" name="ativo" checked> Ativo</label>
                            </div>
                            <div class="row-actions">
                                <button type="submit" class="btn btn-primary btn-xs">Salvar</button>
                                <button type="button" class="btn btn-secondary btn-xs" onclick="resetSourceForm()">Limpar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>Fontes cadastradas</h3></div>
                    <div class="card-body">
                        <div style="overflow:auto;">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Tipo</th>
                                    <th>URL</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                                </thead>
                                <tbody id="sourcesTable"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3><i class="ph ph-link"></i> Associar fontes ao tema</h3></div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Selecione um tema</label>
                        <select id="topicSelectForSources"></select>
                    </div>
                    <div class="form-group">
                        <label>Fontes (marque as que entram no tema)</label>
                        <div id="topicSourcesChecklist" style="display:flex; gap:10px; flex-wrap:wrap;"></div>
                        <small class="form-text">Você pode reutilizar uma fonte em vários temas.</small>
                    </div>
                    <div class="row-actions">
                        <button class="btn btn-primary btn-xs" onclick="saveTopicSources()">Salvar associação</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="tab-panel" id="tab-coleta">
            <div class="card">
                <div class="card-header"><h3><i class="ph ph-play-circle"></i> Rodar coleta (manual)</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-col-6">
                            <div class="form-group">
                                <label>Tema</label>
                                <select id="collectTopicSelect"></select>
                            </div>
                        </div>
                        <div class="form-col-6" style="display:flex; align-items:flex-end; gap:10px;">
                            <button class="btn btn-primary btn-xs" onclick="runCollect()">Rodar agora</button>
                            <button class="btn btn-secondary btn-xs" onclick="runAnalyzeHype()">⚡ Analisar Hype</button>
                            <button class="btn btn-secondary btn-xs" onclick="loadItems()">Atualizar itens</button>
                            <button class="btn btn-danger btn-xs" id="btnDeleteItems" onclick="deleteSelectedItems()" disabled style="margin-left:6px;">Excluir selecionados</button>
                        </div>
                    </div>
                    <div class="form-row" style="margin-top:10px;">
                        <div class="form-col-6">
                            <div class="form-group">
                                <label>Filtro (url contains)</label>
                                <input type="text" id="filterUrlInput" placeholder="ex.: nytimes.com" style="width:100%;">
                            </div>
                        </div>
                        <div class="form-col-6" style="display:flex; align-items:flex-end; gap:10px;">
                            <button class="btn btn-danger btn-xs" id="btnDeleteFilter" onclick="deleteFilteredItems()" disabled>Excluir filtrados</button>
                            <small class="muted">Digite parte da URL e clique para excluir todos os resultados que contenham o filtro.</small>
                        </div>
                    </div>
                    <div class="muted">A coleta usa RSS primeiro; scrape é fallback (metadados). Itens duplicados são deduplicados por URL normalizada.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>Itens coletados</h3></div>
                <div class="card-body">
                    <div style="overflow:auto;">
                        <table class="table">
                            <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllItems"></th>
                                <th>Score</th>
                                <th>Hype (Vel)</th>
                                <th>Título</th>
                                <th>Fonte</th>
                                <th>Data</th>
                                <th>Link</th>
                            </tr>
                            </thead>
                            <tbody id="itemsTable"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="tab-panel" id="tab-ideias">
            <div class="card">
                <div class="card-header"><h3><i class="ph ph-brain"></i> Gerar ideias com IA</h3></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-col-6">
                            <div class="form-group">
                                <label>Tema</label>
                                <select id="ideasTopicSelect"></select>
                            </div>
                        </div>
                        <div class="form-col-3">
                            <div class="form-group">
                                <label>Quantidade</label>
                                <input type="number" id="numIdeas" min="3" max="15" value="8">
                            </div>
                        </div>
                        <div class="form-col-3" style="display:flex; align-items:flex-end; gap:10px;">
                            <button class="btn btn-primary btn-xs" onclick="generateIdeas()">Gerar</button>
                            <button class="btn btn-secondary btn-xs" onclick="loadIdeas()">Atualizar</button>
                        </div>
                    </div>
                    <div class="muted">As ideias são baseadas nos itens com maior score. A IA é instruída a não copiar trechos.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>Painel de ideias</h3></div>
                <div class="card-body">
                    <div style="overflow:auto;">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Tema</th>
                                <th>Título</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                            </thead>
                            <tbody id="ideasTable"></tbody>
                        </table>
                    </div>
                    <small class="form-text">Ao clicar “Virar rascunho”, um conteúdo é criado em `admin/artigos.php` como rascunho.</small>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
<script>
    const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

    function setMessage(type, text, loading = false) {
        const div = document.getElementById('messageDiv');
        div.style.display = 'block';
        div.className = 'message ' + (type === 'success' ? 'message-success' : 'message-error') + (loading ? ' loading' : '');
        div.innerHTML = '';
        if (loading) {
            const sp = document.createElement('span');
            sp.className = 'spinner';
            div.appendChild(sp);
        }
        const msg = document.createElement('span');
        msg.innerHTML = text; // Allow HTML for icons
        div.appendChild(msg);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function apiPost(action, payload) {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', csrfToken);
        Object.entries(payload || {}).forEach(([k, v]) => fd.append(k, v));
        return fetch('../api/radar.php', { method: 'POST', body: fd }).then(r => r.json());
    }
    function apiGet(params) {
        const qs = new URLSearchParams(params).toString();
        return fetch('../api/radar.php?' + qs).then(r => r.json());
    }

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const tab = btn.dataset.tab;
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
        });
    });

    // Topics
    function resetTopicForm() {
        const f = document.getElementById('topicForm');
        f.reset();
        f.querySelector('input[name="id"]').value = '';
        f.querySelector('input[name="idiomas"]').value = 'pt,en';
        f.querySelector('input[name="regioes"]').value = 'br,us,eu';
        f.querySelector('input[name="ativo"]').checked = true;
    }

    function fillTopicForm(t) {
        const f = document.getElementById('topicForm');
        f.querySelector('input[name="id"]').value = t.id || '';
        f.querySelector('input[name="nome"]').value = t.nome || '';
        f.querySelector('textarea[name="descricao"]').value = t.descricao || '';
        f.querySelector('textarea[name="keywords"]').value = t.keywords || '';
        f.querySelector('input[name="idiomas"]').value = t.idiomas || 'pt,en';
        f.querySelector('input[name="regioes"]').value = t.regioes || 'br,us,eu';
        f.querySelector('select[name="categoria_artigos_id"]').value = t.categoria_artigos_id || '';
        f.querySelector('input[name="ativo"]').checked = (t.ativo === true || t.ativo === 't' || t.ativo === 1);
        document.querySelector('[data-tab="temas"]').click();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.getElementById('topicForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const payload = {
            id: f.id.value,
            nome: f.nome.value,
            descricao: f.descricao.value,
            keywords: f.keywords.value,
            idiomas: f.idiomas.value,
            regioes: f.regioes.value,
            categoria_artigos_id: f.categoria_artigos_id.value,
            ativo: f.ativo.checked ? 1 : 0
        };
        const res = await apiPost('topics_save', payload);
        if (res.success) {
            setMessage('success', '<i class="ph ph-check-circle"></i> ' + res.message);
            resetTopicForm();
            await loadTopics();
            await refreshTopicSelects();
        } else {
            setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro'));
        }
    });

    async function deleteTopic(id) {
        if (!confirm('Remover este tema? Isso remove as associações e ideias relacionadas.')) return;
        const res = await apiPost('topics_delete', { id });
        if (res.success) {
            setMessage('success', '<i class="ph ph-check-circle"></i> ' + res.message);
            await loadTopics();
            await refreshTopicSelects();
        } else setMessage('error', '<i class="ph ph-x-circle"></i> ' + res.message);
    }

    async function loadTopics() {
        const res = await apiGet({ action: 'topics_list' });
        const tb = document.getElementById('topicsTable');
        tb.innerHTML = '';
        if (!res.success) {
            tb.innerHTML = '<tr><td colspan="6">Erro ao carregar temas</td></tr>';
            return;
        }
        (res.topics || []).forEach(t => {
            const statusBadge = (t.ativo === true || t.ativo === 't' || t.ativo === 1)
                ? '<span class="badge badge-success">ativo</span>'
                : '<span class="badge badge-danger">inativo</span>';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${escapeHtml(t.nome || '')}</strong><div class="muted">${escapeHtml((t.descricao || '').slice(0,120))}</div></td>
                <td>${escapeHtml(t.idiomas || '')}</td>
                <td>${escapeHtml(t.regioes || '')}</td>
                <td>${escapeHtml(t.categoria_nome || '')}</td>
                <td>${statusBadge}</td>
                <td class="row-actions">
                    <button class="btn-icon edit" title="Editar-Topic" onclick='fillTopicForm(${JSON.stringify(t).replace(/'/g,"&#39;")})'><i class="ph ph-pencil-simple"></i></button>
                    <button class="btn-icon delete" title="Remover-Topic" onclick="deleteTopic(${t.id})"><i class="ph ph-trash"></i></button>
                </td>
            `;
            tb.appendChild(tr);
        });
    }

    // Sources
    function resetSourceForm() {
        const f = document.getElementById('sourceForm');
        f.reset();
        f.querySelector('input[name="id"]').value = '';
        f.querySelector('select[name="tipo"]').value = 'rss';
        f.querySelector('input[name="ativo"]').checked = true;
        updateSourceConfigHelp();
    }
    function fillSourceForm(s) {
        const f = document.getElementById('sourceForm');
        f.querySelector('input[name="id"]').value = s.id || '';
        f.querySelector('input[name="nome"]').value = s.nome || '';
        f.querySelector('select[name="tipo"]').value = s.tipo || 'rss';
        f.querySelector('input[name="url"]').value = s.url || '';
        f.querySelector('textarea[name="config"]').value = s.config || '';
        f.querySelector('input[name="ativo"]').checked = (s.ativo === true || s.ativo === 't' || s.ativo === 1);
        updateSourceConfigHelp();
        document.querySelector('[data-tab="fontes"]').click();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Mostrar/ocultar ajuda de config para API
    function updateSourceConfigHelp() {
        const tipo = document.querySelector('select[name="tipo"]').value;
        const help = document.getElementById('apiConfigHelp');
        if (help) help.style.display = (tipo === 'api') ? 'block' : 'none';
    }
    document.querySelector('select[name="tipo"]').addEventListener('change', updateSourceConfigHelp);
    document.getElementById('sourceForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const payload = {
            id: f.id.value,
            nome: f.nome.value,
            tipo: f.tipo.value,
            url: f.url.value,
            config: f.config.value,
            ativo: f.ativo.checked ? 1 : 0
        };
        const res = await apiPost('sources_save', payload);
        if (res.success) {
            setMessage('success', '<i class="ph ph-check-circle"></i> ' + res.message);
            resetSourceForm();
            await loadSources();
            await refreshSourceChecklist();
        } else setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro'));
    });
    async function deleteSource(id) {
        if (!confirm('Remover esta fonte?')) return;
        const res = await apiPost('sources_delete', { id });
        if (res.success) {
            setMessage('success', '<i class="ph ph-check-circle"></i> ' + res.message);
            await loadSources();
            await refreshSourceChecklist();
        } else setMessage('error', '<i class="ph ph-x-circle"></i> ' + res.message);
    }
    async function loadSources() {
        const res = await apiGet({ action: 'sources_list' });
        const tb = document.getElementById('sourcesTable');
        tb.innerHTML = '';
        if (!res.success) {
            tb.innerHTML = '<tr><td colspan="5">Erro ao carregar fontes</td></tr>';
            return;
        }
        (res.sources || []).forEach(s => {
            const statusBadge = (s.ativo === true || s.ativo === 't' || s.ativo === 1)
                ? '<span class="badge badge-success">ativa</span>'
                : '<span class="badge badge-danger">inativa</span>';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${escapeHtml(s.nome || '')}</strong></td>
                <td>${escapeHtml(s.tipo || '')}</td>
                <td>${s.url ? `<a class="link" href="${escapeAttr(s.url)}" target="_blank" rel="noreferrer">${escapeHtml(s.url).slice(0,60)}</a>` : ''}</td>
                <td>${statusBadge}</td>
                <td class="row-actions">
                    <button class="btn-icon edit" title="Editar-Source" onclick='fillSourceForm(${JSON.stringify(s).replace(/'/g,"&#39;")})'><i class="ph ph-pencil-simple"></i></button>
                    <button class="btn-icon delete" title="Remover-Source" onclick="deleteSource(${s.id})"><i class="ph ph-trash"></i></button>
                </td>
            `;
            tb.appendChild(tr);
        });
    }

    // Topic <-> sources association
    let cachedTopics = [];
    let cachedSources = [];

    async function refreshTopicSelects() {
        const res = await apiGet({ action: 'topics_list' });
        cachedTopics = res.success ? (res.topics || []) : [];
        const selects = [document.getElementById('topicSelectForSources'), document.getElementById('collectTopicSelect'), document.getElementById('ideasTopicSelect')];
        selects.forEach(sel => {
            const oldVal = sel.value;
            sel.innerHTML = '';
            if (!cachedTopics.length) {
                sel.innerHTML = '<option value="">(cadastre um tema)</option>';
                return;
            }
            cachedTopics.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.nome;
                if (t.id == oldVal) opt.selected = true;
                sel.appendChild(opt);
            });
        });
        // Carregar fontes associadas para o tema selecionado no select de associação
        await loadTopicSources();
    }

    // Listener para carregar fontes quando mudar o tema
    document.getElementById('topicSelectForSources').addEventListener('change', loadTopicSources);

    async function loadTopicSources() {
        const topicId = document.getElementById('topicSelectForSources').value;
        if (!topicId) return;
        
        // Desmarcar todos primeiro
        document.querySelectorAll('#topicSourcesChecklist input[type="checkbox"]').forEach(i => i.checked = false);
        
        const res = await apiGet({ action: 'topic_sources_get', topic_id: topicId });
        if (res.success && res.source_ids) {
            res.source_ids.forEach(sid => {
                const chk = document.getElementById('src_' + sid);
                if (chk) chk.checked = true;
            });
        }
    }

    async function refreshSourceChecklist() {
        const res = await apiGet({ action: 'sources_list' });
        cachedSources = res.success ? (res.sources || []) : [];
        const box = document.getElementById('topicSourcesChecklist');
        box.innerHTML = '';
        if (!cachedSources.length) {
            box.innerHTML = '<span class="muted">(cadastre uma fonte)</span>';
            return;
        }
        cachedSources.forEach(s => {
            const id = 'src_' + s.id;
            const wrap = document.createElement('label');
            wrap.style.display = 'inline-flex';
            wrap.style.alignItems = 'center';
            wrap.style.gap = '8px';
            wrap.style.border = '1px solid var(--border)';
            wrap.style.borderRadius = '999px';
            wrap.style.padding = '8px 12px';
            wrap.style.cursor = 'pointer';
            wrap.innerHTML = `<input type="checkbox" id="${id}" value="${s.id}"> <span>${escapeHtml(s.nome)} (${escapeHtml(s.tipo)})</span>`;
            box.appendChild(wrap);
        });
        // Recarregar os marcados
        await loadTopicSources();
    }

    async function saveTopicSources() {
        const topicId = document.getElementById('topicSelectForSources').value;
        if (!topicId) return setMessage('error', 'Selecione um tema');
        const ids = Array.from(document.querySelectorAll('#topicSourcesChecklist input[type="checkbox"]'))
            .filter(i => i.checked)
            .map(i => parseInt(i.value, 10));
        const res = await apiPost('topic_sources_set', { topic_id: topicId, source_ids: JSON.stringify(ids) });
        if (res.success) setMessage('success', '<i class="ph ph-check-circle"></i> ' + res.message);
        else setMessage('error', '<i class="ph ph-x-circle"></i> ' + res.message);
    }

    // Collect
    async function runCollect() {
        const topicId = document.getElementById('collectTopicSelect').value;
        if (!topicId) return setMessage('error', 'Selecione um tema');
        
        WVProgress.show('Coletando dados...', 'Buscando atualizações e cruzando fontes.');
        WVProgress.animateTo(85, 8000); // Simulação

        const res = await apiPost('collect_run', { topic_id: topicId });
        WVProgress.hide();
        
        if (res.success) {
            setMessage('success', `<i class="ph ph-check-circle"></i> Coleta finalizada. Itens salvos: ${res.saved_total || 0}`);
            await loadItems();
        } else {
            setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro'));
        }
    }

    async function runAnalyzeHype() {
        WVProgress.show('Analisando tendências...', 'Calculando velocidade e agrupando clusters.');
        WVProgress.animateTo(90, 4000);

        const res = await apiPost('analyze_hype', {});
        WVProgress.hide();

        if (res.success) {
            if (res.clusters_found > 0) {
                setMessage('success', `<i class="ph ph-lightning"></i> Hype analisado! Clusters: ${res.clusters_found}, Itens atualizados: ${res.items_updated}`);
            } else {
                setMessage('info', `<i class="ph ph-info"></i> Análise concluída: nenhum padrão de tendência detectado no momento.`);
            }
            await loadItems();
        } else {
            setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || res.error || 'Erro na análise'));
        }
    }

    async function loadItems() {
        const topicId = document.getElementById('collectTopicSelect').value;
        const res = await apiGet({ action: 'items_list', topic_id: topicId || '', limit: 50 });
        const tb = document.getElementById('itemsTable');
        tb.innerHTML = '';
        if (!res.success) {
            tb.innerHTML = '<tr><td colspan="6">Erro ao carregar itens</td></tr>';
            return;
        }
        (res.items || []).forEach(it => {
            let hypeBadge = '';
            if (it.raw) {
                try {
                    const raw = (typeof it.raw === 'string') ? JSON.parse(it.raw) : it.raw;
                    if (raw.velocity > 0) {
                        hypeBadge = `<span class="badge ${raw.is_trending ? 'badge-danger' : 'badge-secondary'}" title="Vel: ${raw.velocity}/h, Cluster: ${raw.cluster_size}">⚡ ${raw.hype_score}</span>`;
                    }
                } catch(e){}
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="checkbox" class="item-checkbox" value="${it.id || ''}"></td>
                <td><span class="badge ${Number(it.score||0) >= 60 ? 'badge-success' : (Number(it.score||0) >= 35 ? 'badge-warning' : 'badge-secondary')}">${Number(it.score||0).toFixed(0)}</span></td>
                <td>${hypeBadge}</td>
                <td><strong>${escapeHtml((it.titulo || '').slice(0,120))}</strong><div class="muted">${escapeHtml((it.descricao || '').slice(0,140))}</div></td>
                <td>${escapeHtml(it.source_nome || '')} <span class="muted">(${escapeHtml(it.source_tipo || '')})</span></td>
                <td class="muted">${formatDateBR(it.published_at || it.fetched_at || '')}</td>
                <td>${it.url ? `<a class="link" href="${escapeAttr(it.url)}" target="_blank" rel="noreferrer">Abrir</a>` : ''}</td>
            `;
            tb.appendChild(tr);
        });

        // Reset select all and wire listeners
        const selectAll = document.getElementById('selectAllItems');
        if (selectAll) {
            selectAll.checked = false;
            if (!selectAll.dataset.listener) {
                selectAll.addEventListener('change', function(){
                    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = this.checked);
                    updateDeleteButton();
                });
                selectAll.dataset.listener = '1';
            }
        }

        document.querySelectorAll('.item-checkbox').forEach(cb => cb.addEventListener('change', updateDeleteButton));
        updateDeleteButton();
    }

    // Helpers: update delete button state and delete selected items
    function updateDeleteButton() {
        const btn = document.getElementById('btnDeleteItems');
        if (!btn) return;
        const any = document.querySelectorAll('.item-checkbox:checked').length > 0;
        btn.disabled = !any;
    }

    async function deleteSelectedItems() {
        const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => parseInt(cb.value, 10)).filter(Boolean);
        if (!ids.length) return;
        if (!confirm('Remover ' + ids.length + ' itens selecionados? Será gerado backup antes da exclusão.')) return;
        setMessage('success', 'Removendo itens...');
        const res = await apiPost('items_delete', { ids: JSON.stringify(ids) });
        if (res.success) {
            let msg = '<i class="ph ph-check-circle"></i> ' + (res.message || 'Itens removidos');
            if (res.backup_file) msg += ' (backup: ' + res.backup_file + ')';
            setMessage('success', msg);
            await loadItems();
        } else {
            setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro ao remover'));
        }
    }

    // Excluir por filtro (url_like)
    async function deleteFilteredItems() {
        const filter = (document.getElementById('filterUrlInput').value || '').trim();
        if (!filter || filter.length < 3) return setMessage('error', 'Digite um filtro com pelo menos 3 caracteres.');
        // Contagem aproximada não disponível client-side; pedir confirmação forte
        const confirmation = prompt(`Digite DELETE para confirmar a remoção de itens cuja URL contenha: "${filter}"`);
        if (confirmation !== 'DELETE') return setMessage('error', 'Confirmação incorreta. A ação foi cancelada.');
        setMessage('success', 'Removendo itens filtrados...');
        const res = await apiPost('items_delete', { url_like: filter });
        if (res.success) {
            let msg = '<i class="ph ph-check-circle"></i> ' + (res.message || 'Itens removidos');
            if (res.backup_file) msg += ' (backup: ' + res.backup_file + ')';
            setMessage('success', msg);
            document.getElementById('filterUrlInput').value = '';
            await loadItems();
        } else {
            setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro ao remover'));
        }
    }

    // Habilitar botão excluir filtrado quando houver texto
    document.getElementById('filterUrlInput').addEventListener('input', function(){
        const btn = document.getElementById('btnDeleteFilter');
        btn.disabled = !(this.value && this.value.trim().length >= 3);
    });

    // Ideas
    async function generateIdeas() {
        const topicId = document.getElementById('ideasTopicSelect').value;
        const n = document.getElementById('numIdeas').value || 8;
        if (!topicId) return setMessage('error', 'Selecione um tema');
        
        WVProgress.show('Gerando ideias com IA...', 'Isso pode levar até 30 segundos devido à análise profunda.');
        WVProgress.animateTo(95, 20000);

        const res = await apiPost('ideas_generate', { topic_id: topicId, num_ideas: n });
        WVProgress.hide();

        if (res.success) {
            setMessage('success', '<i class="ph ph-check-circle"></i> Ideias geradas');
            await loadIdeas();
        } else {
            setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro'));
        }
    }

    async function discardIdea(id) {
        if (!confirm('Excluir esta ideia? (Ação permanente)')) return;
        const res = await apiPost('idea_discard', { id });
        if (res.success) {
            setMessage('success', '<i class="ph ph-check-circle"></i> ' + res.message);
            await loadIdeas();
        } else setMessage('error', '<i class="ph ph-x-circle"></i> ' + res.message);
    }

    function showModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.style.display = 'flex';
    }
    function hideModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.style.display = 'none';
    }

    function viewIdea(id) {
        const idea = window.ideasCache && window.ideasCache[id];
        if (!idea) return alert('Ideia não encontrada');
        
        document.getElementById('ideaModalTitle').textContent = idea.titulo || '';
        document.getElementById('ideaModalAngle').textContent = idea.angulo || '';
        document.getElementById('ideaModalSummary').textContent = idea.resumo || '';
        document.getElementById('ideaModalOutline').innerHTML = '';
        
        if (idea.outline) {
            const lines = idea.outline.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
            if (lines.length) {
                const ul = document.createElement('ul');
                ul.style.paddingLeft = '18px';
                ul.style.margin = '0';
                lines.forEach(l => { 
                    const li = document.createElement('li'); 
                    li.textContent = l.replace(/^[-*]\s*/, ''); 
                    ul.appendChild(li); 
                });
                document.getElementById('ideaModalOutline').appendChild(ul);
            }
        }
        
        document.getElementById('ideaModalTags').textContent = idea.tags || '';
        
        // Metadata
        const dateStr = idea.created_at ? new Date(idea.created_at).toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '';
        document.getElementById('ideaModalMeta').innerHTML = `
            <span style="font-weight:600; color:var(--primary);">${escapeHtml(idea.topic_nome || 'Tema')}</span> 
            <span style="margin: 0 8px; opacity: 0.3;">|</span> 
            ${dateStr} 
            ${idea.ai_model ? `<span style="margin: 0 8px; opacity: 0.3;">|</span> <span style="font-style:italic; font-size:11px;">${escapeHtml(idea.ai_model)}</span>` : ''}
        `;

        // Fetch Sources
        const sourcesDiv = document.getElementById('ideaModalSources');
        sourcesDiv.innerHTML = '<div class="muted" style="font-size:12px;">Buscando fontes...</div>';
        apiGet({ action: 'idea_sources', id: id }).then(res => {
            if (res.success && res.sources && res.sources.length) {
                sourcesDiv.innerHTML = '';
                res.sources.forEach(s => {
                    const item = document.createElement('a');
                    item.href = s.url;
                    item.target = '_blank';
                    item.className = 'source-link';
                    item.style.cssText = 'display:flex; align-items:center; gap:8px; font-size:12px; color:var(--info); text-decoration:none; padding:4px 0;';
                    item.innerHTML = `<i class="ph ph-link" style="font-size:14px;"></i> <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(s.titulo || s.url)}</span>`;
                    sourcesDiv.appendChild(item);
                });
            } else {
                sourcesDiv.innerHTML = '<div class="muted" style="font-size:12px; opacity:0.6;">Nenhuma fonte específica vinculada.</div>';
            }
        });

        // Setup Copy Button
        document.getElementById('btnCopyIdea').onclick = () => {
            const textToCopy = `TÍTULO: ${idea.titulo}\n\nÂNGULO: ${idea.angulo}\n\nRESUMO: ${idea.resumo}\n\nOUTLINE:\n${idea.outline}\n\nTAGS: ${idea.tags}`;
            navigator.clipboard.writeText(textToCopy).then(() => {
                const btn = document.getElementById('btnCopyIdea');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="ph ph-check"></i> Copiado!';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-light');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-light');
                }, 2000);
            });
        };

        document.getElementById('ideaModalActionAI').onclick = () => { hideModal('ideaModal'); ideaToDraft(id, 'ai'); };
        document.getElementById('ideaModalActionSimple').onclick = () => { hideModal('ideaModal'); ideaToDraft(id, 'simple'); };
        document.getElementById('ideaModalDiscard').onclick = () => { hideModal('ideaModal'); discardIdea(id); };
        
        showModal('ideaModal');
    }

    async function ideaToDraft(id, mode='ai') {
        if (!confirm(mode === 'simple' ? 'Criar rascunho simples na seção Conteúdos (sem chamar IA)?' : 'Criar rascunho com IA (gerar conteúdo automaticamente)?')) return;
        
        const title = mode === 'ai' ? 'Gerando rascunho com IA...' : 'Criando rascunho...';
        const st = mode === 'ai' ? 'A IA está escrevendo o conteúdo completo baseado na ideia.' : 'Salvando rascunho nos Conteúdos.';
        
        WVProgress.show(title, st);
        WVProgress.animateTo(90, mode === 'ai' ? 15000 : 2000);

        const res = await apiPost('idea_to_draft', { id, mode });
        WVProgress.hide();

        if (res.success) {
            setMessage('success', `<i class="ph ph-check-circle"></i> ${res.message} (ID: ${res.result && res.result.artigo_id ? res.result.artigo_id : '?'})`);
            await loadIdeas();
        } else setMessage('error', '<i class="ph ph-x-circle"></i> ' + (res.message || 'Erro'));
    }

    function openDraftModal(id) {
        const idea = window.ideasCache && window.ideasCache[id];
        if (!idea) return alert('Ideia não encontrada');
        // Populate modal and show options
        viewIdea(id);
        document.getElementById('ideaModalActionAI').disabled = false;
        document.getElementById('ideaModalActionSimple').disabled = false;
        showModal('ideaModal');
    }

    async function loadIdeas() {
        const topicId = document.getElementById('ideasTopicSelect').value;
        const res = await apiGet({ action: 'ideas_list', topic_id: topicId || '', limit: 80 });
        const tb = document.getElementById('ideasTable');
        tb.innerHTML = '';
        if (!res.success) {
            tb.innerHTML = '<tr><td colspan="4">Erro ao carregar ideias</td></tr>';
            return;
        }
        window.ideasCache = window.ideasCache || {};
        (res.ideas || []).forEach(i => {
            window.ideasCache[i.id] = i;
            const st = String(i.status || 'nova');
            let badge = `<span class="badge">${escapeHtml(st)}</span>`;
            if (st === 'nova') badge = `<span class="badge warning">nova</span>`;
            if (st === 'virou_artigo') badge = `<span class="badge success">virou_artigo</span>`;
            if (st === 'descartada') badge = `<span class="badge danger">descartada</span>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(i.topic_nome || '')}</td>
                <td>
                    <strong>${escapeHtml(i.titulo || '')}</strong>
                    <div class="muted">${escapeHtml((i.angulo || '').slice(0,140))}</div>
                </td>
                <td>${badge}</td>
                <td class="row-actions">
                    <button class="btn btn-sm btn-light" onclick="viewIdea(${i.id})">Ver</button>
                    <button class="btn btn-primary btn-xs" onclick="openDraftModal(${i.id})" ${st !== 'nova' ? 'disabled' : ''}>Virar rascunho</button>
                    <button class="btn btn-secondary btn-xs" onclick="discardIdea(${i.id})" ${st !== 'nova' ? 'disabled' : ''}>Excluir</button>
                </td>
            `;
            tb.appendChild(tr);
        });
    }

    // Utils
    function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
    function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }

    async function init() {
        await loadTopics();
        await loadSources();
        await refreshTopicSelects();
        await refreshSourceChecklist();
        await loadItems();
        await loadIdeas();
    }
    init();
</script>

<!-- Idea Modal -->
<div id="ideaModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:800px;">
        <div class="modal-header">
            <h2 id="ideaModalTitle"></h2>
            <button onclick="hideModal('ideaModal')" class="modal-close"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div id="ideaModalMeta" style="font-size: 12px; color: #64748b;"></div>
                <button id="btnCopyIdea" class="btn btn-sm btn-light">
                    <i class="ph ph-copy"></i> Copiar Ideia
                </button>
            </div>

            <div class="ai-insight-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 20px;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Ângulo Estratégico</label>
                    <div id="ideaModalAngle" style="font-size: 15px; line-height: 1.6; color: #1e293b;"></div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Resumo Executivo</label>
                    <div id="ideaModalSummary" style="font-size: 15px; line-height: 1.6; color: #1e293b;"></div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Estrutura Sugerida (Outline)</label>
                    <div id="ideaModalOutline" style="background: #fff; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px; font-size: 14px; line-height: 1.6; color: #334155;"></div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Palavras-chave</label>
                    <div id="ideaModalTags" style="font-size: 13px; font-weight: 600; color: #0f172a;"></div>
                </div>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #e2e8f0;">
                    <label style="display: block; font-weight: 700; color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Fontes de Inspiração</label>
                    <div id="ideaModalSources" style="display: flex; flex-direction: column; gap: 6px;">
                        <div class="muted" style="font-size: 11px;">Carregando fontes...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="ideaModalActionSimple" class="btn btn-secondary" style="background:#6BD1B4;">Aprovar e criar rascunho (sem IA)</button>
            <button id="ideaModalActionAI" class="btn btn-primary">Aprovar e criar com IA</button>
            <button id="ideaModalDiscard" class="btn btn-danger">Excluir</button>
        </div>
    </div>
</div>

</body>
</html>
