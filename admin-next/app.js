/* Washiviana Admin (Fase 2c) — SPA sem build, consome /go-api (backend Go). */
(function () {
  'use strict';

  var API = '/go-api';
  var state = { user: null, csrf: '', view: 'dashboard', loading: false };

  var app = document.getElementById('app');

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  async function api(path, opts) {
    opts = opts || {};
    var headers = {};
    if (opts.body !== undefined) headers['Content-Type'] = 'application/json';
    if (opts.csrf && state.csrf) headers['X-CSRF-Token'] = state.csrf;
    var res = await fetch(API + path, {
      method: opts.method || 'GET',
      headers: headers,
      credentials: 'same-origin',
      body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined
    });
    var data = {};
    try { data = await res.json(); } catch (e) { /* ignore */ }
    return { status: res.status, data: data };
  }

  function parseIA(text) {
    var order = ['Título', 'Titulo', 'Slug', 'Categoria', 'Resumo', 'Conteúdo', 'Conteudo'];
    var positions = [];
    order.forEach(function (label) {
      var re = new RegExp('(?:^|\\n)\\s*' + label + '\\s*:\\s*', 'i');
      var m = re.exec(text);
      if (m) positions.push({ label: label.toLowerCase(), start: m.index + m[0].length });
    });
    positions.sort(function (a, b) { return a.start - b.start; });
    var out = { titulo: '', slug: '', categoria: '', resumo: '', conteudo: '' };
    for (var i = 0; i < positions.length; i++) {
      var end = i + 1 < positions.length ? positions[i + 1].start : text.length;
      var value = text.slice(positions[i].start, end).trim().replace(/^-+\s*/, '');
      var key = positions[i].label;
      if (key.indexOf('titu') === 0) out.titulo = value;
      else if (key === 'slug') out.slug = value;
      else if (key === 'categoria') out.categoria = value;
      else if (key === 'resumo') out.resumo = value;
      else if (key.indexOf('conte') === 0) out.conteudo = value;
    }
    return out;
  }

  async function uploadFile(file, prefix) {
    var fd = new FormData();
    fd.append('prefix', prefix);
    fd.append('file', file);
    var res = await fetch(API + '/api/v1/upload', {
      method: 'POST',
      headers: state.csrf ? { 'X-CSRF-Token': state.csrf } : {},
      credentials: 'same-origin',
      body: fd
    });
    var data = {};
    try { data = await res.json(); } catch (e) { /* ignore */ }
    return data;
  }

  function setMsg(text, kind) {
    var el = document.getElementById('msg');
    if (!el) return;
    el.className = 'msg show ' + (kind === 'err' ? 'err' : 'ok');
    el.textContent = text;
  }

  /* ---------------- Login ---------------- */

  function renderLogin() {
    app.innerHTML =
      '<div class="login-wrap"><form class="login-card" id="loginForm">' +
      '<h1>Washiviana Admin</h1><p class="sub">Acesso restrito</p>' +
      '<div id="msg" class="msg"></div>' +
      '<div class="field"><label>E-mail</label><input type="email" id="email" autocomplete="username" required></div>' +
      '<div class="field"><label>Senha</label><input type="password" id="password" autocomplete="current-password" required></div>' +
      '<button class="btn primary" type="submit" id="loginBtn" style="width:100%">Entrar</button>' +
      '</form></div>';

    document.getElementById('loginForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      var btn = document.getElementById('loginBtn');
      btn.disabled = true; btn.textContent = 'Entrando...';
      var r = await api('/api/v1/auth/login', {
        method: 'POST',
        body: { email: document.getElementById('email').value, password: document.getElementById('password').value }
      });
      btn.disabled = false; btn.textContent = 'Entrar';
      if (r.data && r.data.success) {
        state.user = r.data.user; state.csrf = r.data.csrf_token;
        state.view = 'dashboard';
        render();
      } else {
        setMsg((r.data && r.data.message) || 'Falha ao entrar.', 'err');
      }
    });
  }

  /* ---------------- Shell ---------------- */

  var NAV = [
    { id: 'dashboard', label: 'Dashboard', ico: '▦' },
    { id: 'artigos', label: 'Conteúdos', ico: '📄' },
    { id: 'projetos', label: 'Projetos', ico: '🧩' },
    { id: 'categorias', label: 'Categorias', ico: '🏷' },
    { id: 'redes', label: 'Redes sociais', ico: '🔗' },
    { id: 'configuracoes', label: 'Configurações', ico: '⚙' }
  ];

  function renderShell() {
    var nav = NAV.map(function (n) {
      return '<button data-view="' + n.id + '" class="' + (state.view === n.id ? 'active' : '') + '">' +
        '<span class="ico">' + n.ico + '</span>' + esc(n.label) + '</button>';
    }).join('');

    app.innerHTML =
      '<div class="layout">' +
      '<aside class="sidebar"><div class="brand">WV ADMIN</div><nav class="nav">' + nav + '</nav>' +
      '<div class="spacer"></div><div class="versions">admin-next · api go</div></aside>' +
      '<div class="backdrop" id="backdrop"></div>' +
      '<div class="main">' +
      '<header class="topbar"><button class="hamburger" id="hamburger">☰</button>' +
      '<h2>' + esc((NAV.filter(function (n) { return n.id === state.view; })[0] || {}).label || '') + '</h2>' +
      '<span class="user-chip">' + esc(state.user ? state.user.email : '') + '</span>' +
      '<button class="btn sm" id="logoutBtn">Sair</button></header>' +
      '<main class="content"><div id="msg" class="msg"></div><div id="view">Carregando…</div></main>' +
      '</div></div>';

    app.querySelectorAll('.nav button').forEach(function (b) {
      b.addEventListener('click', function () {
        state.view = b.getAttribute('data-view');
        document.body.classList.remove('nav-open');
        render();
      });
    });
    var ham = document.getElementById('hamburger');
    if (ham) ham.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
    var bd = document.getElementById('backdrop');
    if (bd) bd.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
    document.getElementById('logoutBtn').addEventListener('click', async function () {
      await api('/api/v1/auth/logout', { method: 'POST', csrf: true });
      state.user = null; state.csrf = ''; render();
    });
  }

  /* ---------------- Dashboard ---------------- */

  async function viewDashboard() {
    var out = '<div class="grid-cards">';
    var a = await api('/api/v1/artigos');
    var p = await api('/api/v1/projetos');
    var c = await api('/api/v1/categorias?target=artigos');
    var counts = [
      ['Conteúdos', (a.data.artigos || []).length],
      ['Projetos', (p.data.projetos || []).length],
      ['Categorias', (c.data.categorias || []).length]
    ];
    counts.forEach(function (it) {
      out += '<div class="stat"><div class="n">' + it[1] + '</div><div class="l">' + esc(it[0]) + '</div></div>';
    });
    out += '</div>';
    document.getElementById('view').innerHTML = out;
  }

  /* ---------------- Categorias (artigos) ---------------- */

  async function viewCategorias() {
    var r = await api('/api/v1/categorias?target=artigos');
    var cats = r.data.categorias || [];
    var rows = cats.map(function (c) {
      return '<tr><td>' + esc(c.nome) + '</td><td class="muted">' + esc(c.slug) + '</td>' +
        '<td>' + (c.total_items || 0) + '</td>' +
        '<td><button class="btn sm danger" data-del="' + c.id + '">Excluir</button></td></tr>';
    }).join('');

    document.getElementById('view').innerHTML =
      '<div class="card"><h3>Categorias de conteúdo</h3>' +
      '<div class="field"><label>Nova categoria</label>' +
      '<div class="row"><input id="catNome" placeholder="Nome da categoria">' +
      '<button class="btn primary" id="catAdd" style="flex:0 0 auto">Adicionar</button></div></div>' +
      '<div class="table-scroll"><table><thead><tr><th>Nome</th><th>Slug</th><th>Itens</th><th></th></tr></thead>' +
      '<tbody>' + (rows || '<tr><td colspan="4" class="muted">Nenhuma categoria.</td></tr>') + '</tbody></table></div></div>';

    document.getElementById('catAdd').addEventListener('click', async function () {
      var nome = document.getElementById('catNome').value.trim();
      if (!nome) return setMsg('Informe o nome.', 'err');
      var res = await api('/api/v1/categorias', { method: 'POST', csrf: true, body: { nome: nome, target: 'artigos' } });
      if (res.data.success) { setMsg(res.data.message, 'ok'); viewCategorias(); }
      else setMsg(res.data.message || 'Erro.', 'err');
    });
    document.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        if (!confirm('Excluir esta categoria?')) return;
        var res = await api('/api/v1/categorias/' + b.getAttribute('data-del') + '?target=artigos', { method: 'DELETE', csrf: true });
        if (res.data.success) { setMsg(res.data.message, 'ok'); viewCategorias(); }
        else setMsg(res.data.message || 'Erro.', 'err');
      });
    });
  }

  /* ---------------- Artigos ---------------- */

  async function viewArtigos(editId) {
    var cats = (await api('/api/v1/categorias?target=artigos')).data.categorias || [];
    var r = await api('/api/v1/artigos');
    var artigos = r.data.artigos || [];

    var optCat = cats.map(function (c) { return '<option value="' + c.id + '">' + esc(c.nome) + '</option>'; }).join('');
    var rows = artigos.map(function (a) {
      return '<tr><td>' + esc(a.titulo) + '</td>' +
        '<td class="muted">' + esc(a.status_publicacao || '') + '</td>' +
        '<td><button class="btn sm" data-edit="' + a.id + '">Editar</button> ' +
        '<button class="btn sm danger" data-del="' + a.id + '">Excluir</button></td></tr>';
    }).join('');

    var form = '<div class="card" id="artForm"><h3>' + (editId ? 'Editar conteúdo' : 'Novo conteúdo') + '</h3>' +
      '<div class="field"><label>Prompt de IA (tema/solicitação)</label>' +
      '<textarea id="fPromptIA" style="min-height:90px" placeholder="Descreva o tema do conteúdo..."></textarea>' +
      '<div class="actions"><button class="btn" id="fGerarIA" type="button">Gerar conteúdo com IA</button>' +
      (editId ? '<button class="btn" id="fGerarSEO" type="button">Gerar traduções/SEO (pt/en/es)</button>' : '') +
      '</div></div>' +
      '<div class="field"><label>Título *</label><input id="fTitulo"></div>' +
      '<div class="row"><div class="field"><label>Resumo</label><input id="fResumo"></div>' +
      '<div class="field"><label>Categoria</label><select id="fCategoria"><option value="">—</option>' + optCat + '</select></div></div>' +
      '<div class="field"><label>Imagem principal</label><input type="file" id="fImagem" accept="image/*"></div>' +
      '<div class="field"><label>Prompt de imagem (IA)</label>' +
      '<div class="row"><input id="fPromptImagem" placeholder="Descreva a imagem...">' +
      '<button class="btn" id="fGerarImagemIA" type="button" style="flex:0 0 auto">Gerar imagem</button></div>' +
      '<div class="muted" id="fImagemIAInfo"></div></div>' +
      '<div class="field"><label>Conteúdo *</label><textarea id="fConteudo"></textarea></div>' +
      '<div class="row"><div class="field"><label>Autor</label><input id="fAutor" value="Washington Viana"></div>' +
      '<div class="field"><label>Status</label><select id="fStatus"><option value="rascunho">Rascunho</option>' +
      '<option value="agendado">Agendado</option><option value="publicado">Publicado</option></select></div></div>' +
      '<div class="actions"><button class="btn primary" id="fSave">Salvar</button>' +
      '<button class="btn" id="fCancel">Cancelar</button></div></div>';

    document.getElementById('view').innerHTML = form +
      '<div class="card"><h3>Conteúdos</h3><div class="table-scroll"><table>' +
      '<thead><tr><th>Título</th><th>Status</th><th></th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="3" class="muted">Nenhum conteúdo.</td></tr>') + '</tbody></table></div></div>';

    var cancel = document.getElementById('fCancel');
    cancel.addEventListener('click', function () { document.getElementById('artForm').remove(); });

    document.getElementById('fGerarIA').addEventListener('click', async function () {
      var btn = this;
      var tema = document.getElementById('fPromptIA').value.trim();
      if (!tema) { setMsg('Informe o tema/prompt para a IA.', 'err'); return; }
      btn.disabled = true; btn.textContent = 'Gerando...';
      var res = await api('/api/v1/ai/article', { method: 'POST', csrf: true, body: { tema: tema } });
      btn.disabled = false; btn.textContent = 'Gerar conteúdo com IA';
      if (!res.data.success) { setMsg(res.data.message || 'Erro na IA.', 'err'); return; }
      var c = parseIA(res.data.texto || '');
      if (c.titulo) document.getElementById('fTitulo').value = c.titulo;
      if (c.resumo) document.getElementById('fResumo').value = c.resumo;
      if (c.conteudo) document.getElementById('fConteudo').value = c.conteudo;
      setMsg('Conteúdo gerado pela IA' + (res.data.fonte_ia ? ' (' + res.data.fonte_ia + ')' : '') + '.', 'ok');
    });

    var seoBtn = document.getElementById('fGerarSEO');
    if (seoBtn) seoBtn.addEventListener('click', async function () {
      var b = this;
      b.disabled = true; b.textContent = 'Gerando traduções...';
      var res = await api('/api/v1/i18n/generate', { method: 'POST', csrf: true, body: { entity: 'artigo', id: editId, langs: ['pt', 'en', 'es'] } });
      b.disabled = false; b.textContent = 'Gerar traduções/SEO (pt/en/es)';
      if (res.data.success) setMsg('Traduções/SEO gerados: ' + (res.data.saved || []).join(', ') + '.', 'ok');
      else setMsg(res.data.message || 'Erro ao gerar traduções.', 'err');
    });

    var generatedImage = '';
    document.getElementById('fGerarImagemIA').addEventListener('click', async function () {
      var btn = this;
      var promptImagem = document.getElementById('fPromptImagem').value.trim();
      if (!promptImagem) { setMsg('Informe o prompt da imagem.', 'err'); return; }
      btn.disabled = true; btn.textContent = 'Gerando...';
      var res = await api('/api/v1/ai/image', { method: 'POST', csrf: true, body: { prompt: promptImagem } });
      btn.disabled = false; btn.textContent = 'Gerar imagem';
      if (res.data.success) {
        generatedImage = res.data.filename;
        document.getElementById('fImagemIAInfo').textContent = 'Imagem gerada: ' + res.data.filename;
      } else {
        setMsg(res.data.message || 'Erro ao gerar imagem.', 'err');
      }
    });

    document.getElementById('fSave').addEventListener('click', async function () {
      var btn = document.getElementById('fSave');
      btn.disabled = true; btn.textContent = 'Salvando...';
      var body = {
        titulo: document.getElementById('fTitulo').value,
        resumo: document.getElementById('fResumo').value,
        conteudo: document.getElementById('fConteudo').value,
        categoria_id: document.getElementById('fCategoria').value ? parseInt(document.getElementById('fCategoria').value, 10) : null,
        autor: document.getElementById('fAutor').value,
        status_publicacao: document.getElementById('fStatus').value
      };
      var imgInput = document.getElementById('fImagem');
      if (imgInput && imgInput.files && imgInput.files[0]) {
        var up = await uploadFile(imgInput.files[0], 'artigo');
        if (up && up.success) { body.imagem_principal = up.filename; }
        else { btn.disabled = false; btn.textContent = 'Salvar'; setMsg((up && up.message) || 'Falha no upload da imagem.', 'err'); return; }
      } else if (generatedImage) {
        body.imagem_principal = generatedImage;
      }
      var res = editId
        ? await api('/api/v1/artigos/' + editId, { method: 'PUT', csrf: true, body: body })
        : await api('/api/v1/artigos', { method: 'POST', csrf: true, body: body });
      if (res.data.success) { setMsg(res.data.message, 'ok'); viewArtigos(); }
      else { btn.disabled = false; btn.textContent = 'Salvar'; setMsg(res.data.message || 'Erro ao salvar.', 'err'); }
    });

    if (editId) {
      var cur = artigos.filter(function (a) { return a.id === editId; })[0];
      if (cur) {
        document.getElementById('fTitulo').value = cur.titulo || '';
        document.getElementById('fResumo').value = cur.resumo || '';
        document.getElementById('fConteudo').value = cur.conteudo || '';
        document.getElementById('fAutor').value = cur.autor || 'Washington Viana';
        document.getElementById('fStatus').value = cur.status_publicacao || 'rascunho';
        if (cur.categoria_id) document.getElementById('fCategoria').value = String(cur.categoria_id);
      }
    }

    document.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { viewArtigos(parseInt(b.getAttribute('data-edit'), 10)); window.scrollTo({ top: 0, behavior: 'smooth' }); });
    });
    document.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        if (!confirm('Excluir este conteúdo?')) return;
        var res = await api('/api/v1/artigos/' + b.getAttribute('data-del'), { method: 'DELETE', csrf: true });
        if (res.data.success) { setMsg(res.data.message, 'ok'); viewArtigos(); }
        else setMsg(res.data.message || 'Erro.', 'err');
      });
    });
  }

  /* ---------------- Projetos ---------------- */

  async function viewProjetos(editId) {
    var cats = (await api('/api/v1/categorias?target=projetos')).data.categorias || [];
    var r = await api('/api/v1/projetos?limit=200');
    var projetos = r.data.projetos || [];

    var optCat = cats.map(function (c) { return '<option value="' + c.id + '">' + esc(c.nome) + '</option>'; }).join('');
    var rows = projetos.map(function (p) {
      return '<tr><td>' + esc(p.titulo) + '</td>' +
        '<td class="muted">' + esc(p.categoria_nome || '') + '</td>' +
        '<td>' + (p.ativo ? 'Ativo' : '—') + ' ' + (p.destaque ? '★' : '') + '</td>' +
        '<td><button class="btn sm" data-edit="' + p.id + '">Editar</button> ' +
        '<button class="btn sm" data-tg="' + p.id + '">Ativo</button> ' +
        '<button class="btn sm" data-td="' + p.id + '">Destaque</button> ' +
        '<button class="btn sm danger" data-del="' + p.id + '">Excluir</button></td></tr>';
    }).join('');

    document.getElementById('view').innerHTML =
      '<div class="card" id="projForm"><h3>' + (editId ? 'Editar projeto' : 'Novo projeto') + '</h3>' +
      '<div class="field"><label>Título *</label><input id="pTitulo"></div>' +
      '<div class="row"><div class="field"><label>Categoria *</label><select id="pCategoria"><option value="">—</option>' + optCat + '</select></div>' +
      '<div class="field"><label>URL do projeto</label><input id="pUrl"></div></div>' +
      '<div class="field"><label>Imagem principal</label><input type="file" id="pImagem" accept="image/*"></div>' +
      '<div class="field"><label>Prompt de imagem (IA)</label>' +
      '<div class="row"><input id="pPromptImagem" placeholder="Descreva a imagem...">' +
      '<button class="btn" id="pGerarImagemIA" type="button" style="flex:0 0 auto">Gerar imagem</button></div>' +
      '<div class="muted" id="pImagemIAInfo"></div></div>' +
      '<div class="field"><label>Descrição</label><textarea id="pDescricao" style="min-height:120px"></textarea></div>' +
      '<div class="field"><label>Tecnologias</label><input id="pTecnologias"></div>' +
      '<div class="row"><label><input type="checkbox" id="pAtivo" style="width:auto"> Ativo</label>' +
      '<label><input type="checkbox" id="pDestaque" style="width:auto"> Destaque</label></div>' +
      '<div class="actions"><button class="btn primary" id="pSave">Salvar</button>' +
      '<button class="btn" id="pCancel">Cancelar</button>' +
      (editId ? '<button class="btn" id="pGerarSEO" type="button">Gerar traduções/SEO (pt/en/es)</button>' : '') +
      '</div></div>' +
      '<div class="card"><h3>Projetos</h3><div class="table-scroll"><table>' +
      '<thead><tr><th>Título</th><th>Categoria</th><th>Estado</th><th></th></tr></thead><tbody>' +
      (rows || '<tr><td colspan="4" class="muted">Nenhum projeto.</td></tr>') + '</tbody></table></div></div>';

    document.getElementById('pAtivo').checked = true;
    document.getElementById('pCancel').addEventListener('click', function () { document.getElementById('projForm').remove(); });

    var pGeneratedImage = '';
    document.getElementById('pGerarImagemIA').addEventListener('click', async function () {
      var btn = this;
      var promptImagem = document.getElementById('pPromptImagem').value.trim();
      if (!promptImagem) { setMsg('Informe o prompt da imagem.', 'err'); return; }
      btn.disabled = true; btn.textContent = 'Gerando...';
      var res = await api('/api/v1/ai/image', { method: 'POST', csrf: true, body: { prompt: promptImagem } });
      btn.disabled = false; btn.textContent = 'Gerar imagem';
      if (res.data.success) {
        pGeneratedImage = res.data.filename;
        document.getElementById('pImagemIAInfo').textContent = 'Imagem gerada: ' + res.data.filename;
      } else {
        setMsg(res.data.message || 'Erro ao gerar imagem.', 'err');
      }
    });

    document.getElementById('pSave').addEventListener('click', async function () {
      var btn = document.getElementById('pSave');
      btn.disabled = true; btn.textContent = 'Salvando...';
      var body = {
        titulo: document.getElementById('pTitulo').value,
        categoria_id: document.getElementById('pCategoria').value ? parseInt(document.getElementById('pCategoria').value, 10) : null,
        descricao: document.getElementById('pDescricao').value,
        tecnologias: document.getElementById('pTecnologias').value,
        url_projeto: document.getElementById('pUrl').value,
        ativo: document.getElementById('pAtivo').checked,
        destaque: document.getElementById('pDestaque').checked
      };
      var imgInput = document.getElementById('pImagem');
      if (imgInput && imgInput.files && imgInput.files[0]) {
        var up = await uploadFile(imgInput.files[0], 'projeto');
        if (up && up.success) { body.imagem_principal = up.filename; }
        else { btn.disabled = false; btn.textContent = 'Salvar'; setMsg((up && up.message) || 'Falha no upload da imagem.', 'err'); return; }
      } else if (pGeneratedImage) {
        body.imagem_principal = pGeneratedImage;
      }
      var res = editId
        ? await api('/api/v1/projetos/' + editId, { method: 'PUT', csrf: true, body: body })
        : await api('/api/v1/projetos', { method: 'POST', csrf: true, body: body });
      if (res.data.success) { setMsg(res.data.message, 'ok'); viewProjetos(); }
      else { btn.disabled = false; btn.textContent = 'Salvar'; setMsg(res.data.message || 'Erro ao salvar.', 'err'); }
    });

    var pSeoBtn = document.getElementById('pGerarSEO');
    if (pSeoBtn) pSeoBtn.addEventListener('click', async function () {
      var b = this;
      b.disabled = true; b.textContent = 'Gerando traduções...';
      var res = await api('/api/v1/i18n/generate', { method: 'POST', csrf: true, body: { entity: 'projeto', id: editId, langs: ['pt', 'en', 'es'] } });
      b.disabled = false; b.textContent = 'Gerar traduções/SEO (pt/en/es)';
      if (res.data.success) setMsg('Traduções/SEO gerados: ' + (res.data.saved || []).join(', ') + '.', 'ok');
      else setMsg(res.data.message || 'Erro ao gerar traduções.', 'err');
    });

    if (editId) {
      var cur = projetos.filter(function (p) { return p.id === editId; })[0];
      if (cur) {
        document.getElementById('pTitulo').value = cur.titulo || '';
        document.getElementById('pDescricao').value = cur.descricao || '';
        document.getElementById('pTecnologias').value = cur.tecnologias || '';
        document.getElementById('pUrl').value = cur.url_projeto || '';
        document.getElementById('pAtivo').checked = !!cur.ativo;
        document.getElementById('pDestaque').checked = !!cur.destaque;
        if (cur.categoria_id) document.getElementById('pCategoria').value = String(cur.categoria_id);
      }
    }

    document.querySelectorAll('[data-edit]').forEach(function (b) {
      b.addEventListener('click', function () { viewProjetos(parseInt(b.getAttribute('data-edit'), 10)); window.scrollTo({ top: 0, behavior: 'smooth' }); });
    });
    document.querySelectorAll('[data-tg]').forEach(function (b) {
      b.addEventListener('click', async function () {
        var res = await api('/api/v1/projetos/' + b.getAttribute('data-tg') + '/toggle-status', { method: 'POST', csrf: true, body: {} });
        if (res.data.success) viewProjetos(); else setMsg(res.data.message || 'Erro.', 'err');
      });
    });
    document.querySelectorAll('[data-td]').forEach(function (b) {
      b.addEventListener('click', async function () {
        var res = await api('/api/v1/projetos/' + b.getAttribute('data-td') + '/toggle-destaque', { method: 'POST', csrf: true, body: {} });
        if (res.data.success) viewProjetos(); else setMsg(res.data.message || 'Erro.', 'err');
      });
    });
    document.querySelectorAll('[data-del]').forEach(function (b) {
      b.addEventListener('click', async function () {
        if (!confirm('Excluir este projeto?')) return;
        var res = await api('/api/v1/projetos/' + b.getAttribute('data-del'), { method: 'DELETE', csrf: true });
        if (res.data.success) { setMsg(res.data.message, 'ok'); viewProjetos(); }
        else setMsg(res.data.message || 'Erro.', 'err');
      });
    });
  }

  /* ---------------- Redes sociais ---------------- */

  async function viewRedes() {
    var r = await api('/api/v1/redes-sociais');
    var redes = r.data.redes || [];
    var rows = redes.map(function (n) {
      return '<tr><td>' + esc(n.rede) + '</td><td>' + (n.ativo ? 'Ativa' : '—') + '</td>' +
        '<td>' + (n.has_token ? '✓' : '—') + '</td>' +
        '<td class="muted">' + esc(n.token_expires_at || '') + '</td></tr>';
    }).join('');
    document.getElementById('view').innerHTML =
      '<div class="card"><h3>Redes sociais</h3>' +
      '<div class="table-scroll"><table><thead><tr><th>Rede</th><th>Status</th><th>Token</th><th>Expira em</th></tr></thead>' +
      '<tbody>' + (rows || '<tr><td colspan="4" class="muted">Nenhuma rede configurada.</td></tr>') + '</tbody></table></div>' +
      '<p class="muted">Segredos não são exibidos. Publicação via Go ainda não habilitada (requer tokens OAuth válidos).</p></div>';
  }

  /* ---------------- Configurações ---------------- */

  async function viewConfiguracoes() {
    var r = await api('/api/v1/configuracoes');
    var c = r.data.configuracoes || {};
    var fields = [
      ['site_titulo', 'Título do site'],
      ['site_subtitulo', 'Subtítulo'],
      ['home_frase_impacto', 'Frase de impacto'],
      ['mini_bio', 'Mini bio'],
      ['site_email', 'E-mail'],
      ['site_telefone', 'Telefone'],
      ['site_linkedin', 'LinkedIn'],
      ['site_instagram', 'Instagram'],
      ['site_github', 'GitHub']
    ];
    var inputs = fields.map(function (f) {
      return '<div class="field"><label>' + esc(f[1]) + '</label>' +
        '<input data-cfg="' + f[0] + '" value="' + esc(c[f[0]] || '') + '"></div>';
    }).join('');

    document.getElementById('view').innerHTML =
      '<div class="card"><h3>Configurações do site</h3>' + inputs +
      '<div class="actions"><button class="btn primary" id="cfgSave">Salvar</button></div>' +
      '<p class="muted">Chaves de IA e integrações continuam pela API/CLI. Esta tela cobre os campos do site.</p></div>';

    document.getElementById('cfgSave').addEventListener('click', async function () {
      var body = {};
      document.querySelectorAll('[data-cfg]').forEach(function (inp) { body[inp.getAttribute('data-cfg')] = inp.value; });
      var res = await api('/api/v1/configuracoes', { method: 'POST', csrf: true, body: body });
      if (res.data.success) setMsg(res.data.message, 'ok');
      else setMsg(res.data.message || 'Erro.', 'err');
    });
  }

  /* ---------------- Render ---------------- */

  function render() {
    if (!state.user) { renderLogin(); return; }
    renderShell();
    var v = document.getElementById('view');
    v.innerHTML = '<span class="muted">Carregando…</span>';
    if (state.view === 'dashboard') viewDashboard();
    else if (state.view === 'artigos') viewArtigos();
    else if (state.view === 'projetos') viewProjetos();
    else if (state.view === 'categorias') viewCategorias();
    else if (state.view === 'redes') viewRedes();
    else if (state.view === 'configuracoes') viewConfiguracoes();
  }

  async function boot() {
    try {
      var r = await api('/api/v1/auth/me');
      if (r.data && r.data.success) { state.user = r.data.user; state.csrf = r.data.csrf_token; }
    } catch (e) { /* sem sessão */ }
    render();
  }

  boot();
})();
