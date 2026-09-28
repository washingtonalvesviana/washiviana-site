/**
 * WASHIVIANA PORTFOLIO - Admin JavaScript
 */

let projetoSaveInProgress = false;

// Toggle Mobile Menu
function toggleMobileMenu() {
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.querySelector('.mobile-menu-overlay');

    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');

        // Prevent body scroll when menu is open
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
}

/**
 * GLOBAL PROGRESS UTILITY
 * Usage: 
 * WVProgress.show('Gerando rascunho...', 'Isso pode levar alguns segundos.');
 * WVProgress.update(50, 'Quase lá...');
 * WVProgress.hide();
 */
const WVProgress = {
    overlay: null,
    bar: null,
    percentage: null,
    title: null,
    status: null,

    init() {
        if (this.overlay) return;

        const html = `
            <div class="progress-overlay" id="globalProgressOverlay">
                <div class="progress-card">
                    <div class="progress-spinner-container">
                        <div class="progress-spinner"></div>
                        <div class="progress-percentage" id="globalProgressPercent">0%</div>
                    </div>
                    <div class="progress-title" id="globalProgressTitle">Processando...</div>
                    <div class="progress-status" id="globalProgressStatus">Aguarde um momento.</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="globalProgressBar"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);

        this.overlay = document.getElementById('globalProgressOverlay');
        this.bar = document.getElementById('globalProgressBar');
        this.percentage = document.getElementById('globalProgressPercent');
        this.title = document.getElementById('globalProgressTitle');
        this.status = document.getElementById('globalProgressStatus');
    },

    show(title = 'Processando...', status = 'Aguarde um momento.') {
        this.init();
        this.title.textContent = title;
        this.status.textContent = status;
        this.update(0);
        this.overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    update(percent, status = null) {
        if (!this.overlay) return;
        const p = Math.min(Math.max(percent, 0), 100);
        this.bar.style.width = p + '%';
        this.percentage.textContent = Math.round(p) + '%';
        if (status) this.status.textContent = status;
    },

    /**
     * Anima a porcentagem automaticamente até um alvo (simulação)
     */
    animateTo(targetPercent, duration = 1000, status = null) {
        if (!this.overlay) return;
        const startPercent = parseFloat(this.bar.style.width) || 0;
        const startTime = performance.now();

        const anim = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = startPercent + (targetPercent - startPercent) * progress;
            this.update(current, status);
            if (progress < 1) requestAnimationFrame(anim);
        };
        requestAnimationFrame(anim);
    },

    hide() {
        if (!this.overlay) return;
        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
        // Pequeno delay para resetar após a transição de saída
        setTimeout(() => this.update(0), 300);
    }
};


// Close menu when clicking on a link (mobile)
document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.sidebar-link');

    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                toggleMobileMenu();
            }
        });
    });

    // Close menu on window resize if screen gets larger
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('admin-sidebar');
            const overlay = document.querySelector('.mobile-menu-overlay');

            if (sidebar && overlay) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        }
    });
});


if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCollapsibleCards);
} else {
    setupCollapsibleCards();
}

// Confirm delete actions
function confirmDelete(message) {
    return confirm(message || 'Tem certeza que deseja excluir este item?');
}

// Image preview for file inputs
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function (e) {
            if (preview) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            }
        };

        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-generate slug from title
function generateSlug(text) {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

// Copy to clipboard
function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(function () {
        const originalText = button.textContent;
        button.textContent = 'Copiado!';
        setTimeout(function () {
            button.textContent = originalText;
        }, 2000);
    });
}

function setupCollapsibleCards() {
    // Disabled as per user request
}

// Formatar data no padrão brasileiro (dd/mm/yyyy HH:mm:ss)
function formatDateBR(dateVal, includeTime = true) {
    if (!dateVal) return '';

    // Tenta converter se for string ISO ou DB
    let date;
    if (typeof dateVal === 'string') {
        // Se já vier formatado ou for inválido, tenta o Date
        date = new Date(dateVal.replace(' ', 'T'));
    } else {
        date = dateVal;
    }

    if (!(date instanceof Date) || isNaN(date.getTime())) {
        return String(dateVal);
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    let formatted = `${day}/${month}/${year}`;

    if (includeTime) {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        formatted += ` ${hours}:${minutes}:${seconds}`;
    }

    return formatted;
}

// Carregar modelos disponíveis do provedor selecionado
function carregarModelosLLM() {
    const btnText = document.getElementById('btnLoadModelsText');
    const btnLoader = document.getElementById('btnLoadModelsLoader');
    const statusDiv = document.getElementById('modelosStatus');
    const textProviderSelect = document.getElementById('llm_text_provider');
    const imageProviderSelect = document.getElementById('llm_image_provider');
    const videoProviderSelect = document.getElementById('llm_video_provider');
    const textModelSelect = document.getElementById('llm_text_model');
    const imageModelSelect = document.getElementById('llm_image_model');
    const videoModelSelect = document.getElementById('llm_video_model');

    const provider = (textProviderSelect ? textProviderSelect.value : 'gemini') || 'gemini';
    const providerForImage = (imageProviderSelect ? imageProviderSelect.value : provider) || provider;
    const providerForVideo = (videoProviderSelect ? videoProviderSelect.value : provider) || provider;

    const providerKeyMap = {
        gemini: 'gemini_api_key',
        openai: 'openai_api_key',
        deepseek: 'deepseek_api_key',
        openrouter: 'openrouter_api_key',
        anthropic: 'anthropic_api_key',
        ollama: 'ollama_api_key'
    };

    const ollamaBaseUrl = String(document.getElementById('ollama_base_url')?.value || '').trim();
    const openaiBaseUrl = String(document.getElementById('openai_base_url')?.value || '').trim();

    const selectedProviders = [provider, providerForImage, providerForVideo]
        .filter((v, idx, arr) => arr.indexOf(v) === idx);

    for (const p of selectedProviders) {
        if (p === 'ollama' && !ollamaBaseUrl) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'alert alert-danger';
            statusDiv.innerHTML = '❌ Informe a URL base do Ollama.';
            return;
        }
        if (p !== 'ollama') {
            const keyFieldId = providerKeyMap[p] || 'gemini_api_key';
            const apiKeyInput = document.getElementById(keyFieldId);
            const apiKey = apiKeyInput ? String(apiKeyInput.value || '').trim() : '';
            if (!apiKey || apiKey.length < 8) {
                statusDiv.style.display = 'block';
                statusDiv.className = 'alert alert-danger';
                statusDiv.innerHTML = '❌ Por favor, insira uma API Key válida para o provedor ' + p.toUpperCase() + '.';
                return;
            }
        }
    }

    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';
    statusDiv.style.display = 'block';
    statusDiv.className = 'alert alert-info';
    statusDiv.innerHTML = '⏳ Carregando modelos disponíveis...';

    // Salvar valores atuais
    const currentTextModel = textModelSelect.value;
    const currentImageModel = imageModelSelect.value;
    const currentVideoModel = videoModelSelect ? videoModelSelect.value : '';

    const fetchModels = function (providerName) {
        const params = new URLSearchParams({ provider: providerName });
        const keyFieldId = providerKeyMap[providerName] || 'gemini_api_key';
        const apiKeyInput = document.getElementById(keyFieldId);
        const apiKey = apiKeyInput ? String(apiKeyInput.value || '').trim() : '';
        if (apiKey) params.append('api_key', apiKey);
        if (ollamaBaseUrl) params.append('ollama_base_url', ollamaBaseUrl);
        if (openaiBaseUrl) params.append('openai_base_url', openaiBaseUrl);
        return fetch('../api/llm-models.php?' + params.toString()).then(response => response.json());
    };

    Promise.all([
        fetchModels(provider),
        fetchModels(providerForImage),
        fetchModels(providerForVideo)
    ])
        .then(([textData, imageData, videoData]) => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';

            if (textData.success && imageData.success && videoData.success) {
                // Limpar selects
                textModelSelect.innerHTML = '';
                imageModelSelect.innerHTML = '';
                if (videoModelSelect) videoModelSelect.innerHTML = '';

                // Adicionar modelos de texto
                if (textData.textModels && textData.textModels.length > 0) {
                    textData.textModels.forEach(function (model) {
                        const option = document.createElement('option');
                        option.value = model.id;
                        option.textContent = model.name;
                        if (model.id === currentTextModel) {
                            option.selected = true;
                        }
                        textModelSelect.appendChild(option);
                    });
                } else {
                    // Fallback se não encontrar modelos
                    textModelSelect.innerHTML = `
                    <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
                    <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                    <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                `;
                }

                // Adicionar modelos de imagem
                if (imageData.imageModels && imageData.imageModels.length > 0) {
                    imageData.imageModels.forEach(function (model) {
                        const option = document.createElement('option');
                        option.value = model.id;
                        option.textContent = model.name;
                        if (model.id === currentImageModel) {
                            option.selected = true;
                        }
                        imageModelSelect.appendChild(option);
                    });
                } else {
                    imageModelSelect.innerHTML = `<option value="">Nenhum modelo de imagem disponível</option>`;
                }

                if (videoModelSelect) {
                    if (videoData.videoModels && videoData.videoModels.length > 0) {
                        videoData.videoModels.forEach(function (model) {
                            const option = document.createElement('option');
                            option.value = model.id;
                            option.textContent = model.name;
                            if (model.id === currentVideoModel) {
                                option.selected = true;
                            }
                            videoModelSelect.appendChild(option);
                        });
                    } else {
                        videoModelSelect.innerHTML = `<option value="">Nenhum modelo de vídeo disponível</option>`;
                    }
                }

                const totalText = (textData.textModels ? textData.textModels.length : 0);
                const totalImage = (imageData.imageModels ? imageData.imageModels.length : 0);
                const totalVideo = (videoData.videoModels ? videoData.videoModels.length : 0);
                statusDiv.className = 'alert alert-success';
                statusDiv.innerHTML = '✅ <strong>' + (totalText + totalImage + totalVideo) + ' modelos encontrados!</strong><br>' +
                    '<small>Modelos de texto: ' + totalText + ' | ' +
                    'Modelos de imagem: ' + totalImage + ' | ' +
                    'Modelos de vídeo: ' + totalVideo + '</small>';

                // Esconder status após 5 segundos
                setTimeout(function () {
                    statusDiv.style.display = 'none';
                }, 5000);
            } else {
                const err = textData.message || imageData.message || videoData.message || 'Falha ao carregar modelos';
                statusDiv.className = 'alert alert-danger';
                statusDiv.innerHTML = '❌ <strong>Erro:</strong> ' + err;
            }
        })
        .catch(error => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            statusDiv.className = 'alert alert-danger';
            statusDiv.innerHTML = '❌ <strong>Erro de conexão:</strong> ' + error.message;
        });
}

function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getProviderInputConfig() {
    const map = {
        gemini: { keyField: 'gemini_api_key', label: 'Gemini' },
        openai: { keyField: 'openai_api_key', label: 'OpenAI' },
        deepseek: { keyField: 'deepseek_api_key', label: 'DeepSeek' },
        openrouter: { keyField: 'openrouter_api_key', label: 'OpenRouter' },
        anthropic: { keyField: 'anthropic_api_key', label: 'Anthropic' },
        ollama: { keyField: 'ollama_api_key', label: 'Ollama' }
    };
    return map;
}

function getSelectedProvidersFromModes() {
    const textProvider = document.getElementById('llm_text_provider')?.value || 'gemini';
    const imageProvider = document.getElementById('llm_image_provider')?.value || textProvider;
    const videoProvider = document.getElementById('llm_video_provider')?.value || imageProvider;
    return [textProvider, imageProvider, videoProvider].filter((v, i, a) => a.indexOf(v) === i);
}

function updateProviderCredentialVisibility() {
    const selected = new Set(getSelectedProvidersFromModes());
    const fields = document.querySelectorAll('.llm-provider-field[data-provider]');
    if (!fields.length) return;

    fields.forEach((field) => {
        const provider = field.getAttribute('data-provider');
        const visible = selected.has(provider);
        field.style.display = visible ? '' : 'none';
    });
}

function fetchProviderModels(providerName) {
    const cfgMap = getProviderInputConfig();
    const cfg = cfgMap[providerName];
    const params = new URLSearchParams({ provider: providerName });
    const keyVal = String(document.getElementById(cfg.keyField)?.value || '').trim();
    const ollamaBaseUrl = String(document.getElementById('ollama_base_url')?.value || '').trim();
    const openaiBaseUrl = String(document.getElementById('openai_base_url')?.value || '').trim();

    if (keyVal) params.append('api_key', keyVal);
    if (ollamaBaseUrl) params.append('ollama_base_url', ollamaBaseUrl);
    if (openaiBaseUrl) params.append('openai_base_url', openaiBaseUrl);

    return fetch('../api/llm-models.php?' + params.toString())
        .then(response => response.json())
        .then(data => ({ provider: providerName, data }));
}

function applyModelsBySelectedProviders(providerResults) {
    const textProvider = document.getElementById('llm_text_provider')?.value || 'gemini';
    const imageProvider = document.getElementById('llm_image_provider')?.value || textProvider;
    const videoProvider = document.getElementById('llm_video_provider')?.value || imageProvider;

    const textSelect = document.getElementById('llm_text_model');
    const imageSelect = document.getElementById('llm_image_model');
    const videoSelect = document.getElementById('llm_video_model');

    const currentText = textSelect?.value || '';
    const currentImage = imageSelect?.value || '';
    const currentVideo = videoSelect?.value || '';

    const resultMap = {};
    providerResults.forEach(item => {
        resultMap[item.provider] = item.data || {};
    });

    const fillSelect = (selectEl, list, currentValue, emptyLabel) => {
        if (!selectEl) return;
        selectEl.innerHTML = '';
        if (Array.isArray(list) && list.length > 0) {
            list.forEach(model => {
                const option = document.createElement('option');
                option.value = model.id;
                const label = (model.name && model.name !== model.id)
                    ? (model.name + ' (' + model.id + ')')
                    : (model.name || model.id);
                option.textContent = label;
                if (model.id === currentValue) option.selected = true;
                selectEl.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = emptyLabel;
            option.selected = true;
            selectEl.appendChild(option);
        }
    };

    const textModels = resultMap[textProvider]?.textModels || [];
    const imageModels = resultMap[imageProvider]?.imageModels || [];
    const videoModels = resultMap[videoProvider]?.videoModels || [];

    fillSelect(textSelect, textModels, currentText, 'Nenhum modelo de texto disponível');
    fillSelect(imageSelect, imageModels, currentImage, 'Nenhum modelo de imagem disponível');
    fillSelect(videoSelect, videoModels, currentVideo, 'Nenhum modelo de vídeo disponível');
}

function validarProvedoresEModelos() {
    const statusDiv = document.getElementById('modelosStatus');
    const resultDiv = document.getElementById('providerValidationResults');
    const providers = getSelectedProvidersFromModes();

    if (!providers.length) {
        if (statusDiv) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'alert alert-warning';
            statusDiv.innerHTML = 'Selecione ao menos um provedor para validar.';
        }
        return;
    }

    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusDiv.className = 'alert alert-info';
        statusDiv.innerHTML = '⏳ Validando provedores e carregando modelos...';
    }
    if (resultDiv) {
        resultDiv.style.display = 'none';
    }

    Promise.all(providers.map(fetchProviderModels))
        .then((results) => {
            applyModelsBySelectedProviders(results);

            const cfgMap = getProviderInputConfig();
            let okCount = 0;
            const lines = results.map(({ provider, data }) => {
                const label = cfgMap[provider].label;
                const success = !!data?.success;
                const textCount = (data?.textModels || []).length;
                const imageCount = (data?.imageModels || []).length;
                const videoCount = (data?.videoModels || []).length;
                if (success) okCount++;
                if (success) {
                    return `✅ <strong>${label}</strong>: OK — texto ${textCount}, imagem ${imageCount}, vídeo ${videoCount}`;
                }
                const msg = escapeHtml(data?.message || 'Não validado');
                return `❌ <strong>${label}</strong>: ${msg}`;
            });

            if (statusDiv) {
                statusDiv.className = okCount === providers.length ? 'alert alert-success' : 'alert alert-warning';
                statusDiv.innerHTML = `Validação concluída (selecionados): ${okCount}/${providers.length} provedores válidos.`;
            }

            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = okCount === providers.length ? 'alert alert-success' : 'alert alert-warning';
                resultDiv.innerHTML = lines.join('<br>');
            }
        })
        .catch((error) => {
            if (statusDiv) {
                statusDiv.className = 'alert alert-danger';
                statusDiv.innerHTML = '❌ Erro ao validar provedores: ' + error.message;
            }
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'alert alert-danger';
                resultDiv.textContent = 'Falha na validação. Verifique conexão e credenciais.';
            }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const selectors = [
        document.getElementById('llm_text_provider'),
        document.getElementById('llm_image_provider'),
        document.getElementById('llm_video_provider')
    ].filter(Boolean);

    if (!selectors.length) return;

    selectors.forEach((selectEl) => {
        selectEl.addEventListener('change', function () {
            updateProviderCredentialVisibility();
        });
    });

    updateProviderCredentialVisibility();
});

function carregarModelos() {
    return carregarModelosLLM();
}

// Testar conexão com provedor configurado
function testarLLM() {
    const btnText = document.getElementById('btnTestText');
    const btnLoader = document.getElementById('btnTestLoader');
    const resultDiv = document.getElementById('testeResult');

    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';
    resultDiv.style.display = 'none';

    const formData = new FormData();
    formData.append('action', 'test');
    const csrf = document.querySelector('input[name="csrf_token"]');
    if (csrf) formData.append('csrf_token', csrf.value);
    const textProvider = document.getElementById('llm_text_provider');
    if (textProvider) {
        formData.append('provider', textProvider.value || 'gemini');
    }

    fetch('../api/gemini.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            resultDiv.style.display = 'block';

            if (data.success) {
                resultDiv.innerHTML = '<div class="message message-success">' +
                    '<strong>✅ ' + data.message + '</strong><br>' +
                    '<small>Modelo: ' + data.model + '</small><br>' +
                    '<em>' + data.response + '</em>' +
                    '</div>';
            } else {
                resultDiv.innerHTML = '<div class="message message-error">' +
                    '<strong>❌ Erro:</strong> ' + data.message +
                    '</div>';
            }
        })
        .catch(error => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="message message-error">' +
                '<strong>❌ Erro de conexão:</strong> ' + error.message +
                '</div>';
        });
}

function testarGemini() {
    return testarLLM();
}

// Salvar configurações
function salvarConfiguracoes(e) {
    e.preventDefault();

    const form = document.getElementById('configForm');
    const formData = new FormData(form);
    const csrfInput = form.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        formData.append('csrf_token', csrfInput.value);
    }
    const messageDiv = document.getElementById('messageDiv');
    const btnSaveText = document.getElementById('btnSaveText');
    const btnSaveLoader = document.getElementById('btnSaveLoader');

    // DEBUG: Verificar se ia_instrucoes está no formData
    console.log('ia_instrucoes no form:', formData.get('ia_instrucoes'));
    console.log('Todos os campos:', [...formData.entries()]);

    btnSaveText.style.display = 'none';
    btnSaveLoader.style.display = 'inline';

    WVProgress.show('Salvando configurações...', 'Aguarde um momento enquanto os dados são processados.');

    fetch('../api/configuracoes.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            WVProgress.hide();

            messageDiv.style.display = 'block';
            if (data.success) {
                messageDiv.className = 'message message-success';
                messageDiv.textContent = '✅ ' + data.message;
            } else {
                messageDiv.className = 'message message-error';
                messageDiv.textContent = '❌ ' + data.message;
            }

            // Scroll to top to show message
            window.scrollTo({ top: 0, behavior: 'smooth' });

            // Hide message after 5 seconds
            setTimeout(function () {
                messageDiv.style.display = 'none';
            }, 5000);
        })
        .catch(error => {
            WVProgress.hide();
            messageDiv.style.display = 'block';
            messageDiv.className = 'message message-error';
            messageDiv.textContent = '❌ Erro de conexão: ' + error.message;
        });
}

// Gerar artigo com IA
function gerarArtigoIA(tema, categoria, callback) {
    const formData = new FormData();
    formData.append('action', 'generate_article');
    formData.append('tema', tema);
    formData.append('categoria', categoria);
    formData.append('tom', 'profissional');
    const csrf = document.querySelector('input[name="csrf_token"]');
    if (csrf) formData.append('csrf_token', csrf.value);

    fetch('../api/gemini.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (callback) callback(data);
        })
        .catch(error => {
            if (callback) callback({ success: false, message: error.message });
        });
}

// Gerar imagem com IA
function gerarImagemIA(prompt, callback) {
    const formData = new FormData();
    formData.append('action', 'generate_image');
    formData.append('prompt', prompt);
    const csrf = document.querySelector('input[name="csrf_token"]');
    if (csrf) formData.append('csrf_token', csrf.value);

    fetch('../api/gemini.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (callback) callback(data);
        })
        .catch(error => {
            if (callback) callback({ success: false, message: error.message });
        });
}

// ========================================
// FUNÇÕES DE IA PARA PROJETOS
// ========================================

// Inicializar prompts do Copilot (apenas uma vez)
let copilotPromptsListenerAdded = false;
function initCopilotPrompts() {
    const tituloInput = document.getElementById('titulo');
    const tecnologiasInput = document.getElementById('tecnologias');
    const promptDescricao = document.getElementById('prompt_descricao');
    const promptLinkedin = document.getElementById('prompt_linkedin');

    if (promptDescricao) {
        const titulo = tituloInput ? tituloInput.value : 'meu projeto';
        const tecnologias = tecnologiasInput ? tecnologiasInput.value : '';

        promptDescricao.value = `Escreva uma descrição profissional e envolvente para um projeto chamado "${titulo}"${tecnologias ? `, desenvolvido com ${tecnologias}` : ''}. 
        
        A descrição deve:
        - Ter entre 150-250 palavras
        - Destacar os benefícios e diferenciais
        - Usar linguagem profissional mas acessível
        - Ser otimizada para portfólio
        
        Retorne apenas a descrição, sem títulos ou formatação extra.`;
    }

    if (promptLinkedin) {
        const titulo = tituloInput ? tituloInput.value : 'meu projeto';
        const tecnologias = tecnologiasInput ? tecnologiasInput.value : '';

        promptLinkedin.value = `Crie um post engajador para LinkedIn sobre o projeto "${titulo}"${tecnologias ? `, desenvolvido com ${tecnologias}` : ''}.
        
        O post deve:
        - Ter entre 200-300 palavras
        - Começar com um gancho que prenda atenção
        - Contar a história por trás do projeto
        - Incluir aprendizados ou insights
        -Terminar com uma pergunta ou call-to-action
        - Incluir 3-5 hashtags relevantes
        
        Tom: profissional mas autêntico e pessoal.`;
    }

// Atualizar prompts quando título mudar (apenas uma vez!)
    if (tituloInput && !copilotPromptsListenerAdded) {
        copilotPromptsListenerAdded = true;
        tituloInput.addEventListener('input', function () {
            initCopilotPrompts();
        });
    }
}

function buildPtProjectUrl(slug) {
    if (!slug) return '';
    const baseUrl = (window.siteBaseUrl || '').replace(/\/$/, '');
    if (!baseUrl) return '';
    return baseUrl + '/pt/projeto/' + encodeURIComponent(slug);
}

// Gerar descrição com IA
function gerarDescricao() {
    const prompt = document.getElementById('prompt_descricao').value;
    const btnText = document.getElementById('btnGerarDescText');
    const btnLoader = document.getElementById('btnGerarDescLoader');
    const resultDiv = document.getElementById('resultadoDescricao');
    const resultTextarea = document.getElementById('textoDescricaoGerado');

    if (!prompt.trim()) {
        alert('Por favor, insira um prompt para a IA.');
        return;
    }

    WVProgress.show('Gerando descrição...', 'A IA está analisando seu projeto para criar um texto profissional.');
    WVProgress.animateTo(90, 15000);

    const formData = new FormData();
    formData.append('action', 'generate_text');
    formData.append('prompt', prompt);
    formData.append('context', 'projeto');
    // Debug opcional: ajuda a verificar qual "agente/instrução" foi aplicado no backend
    formData.append('debug', '1');
    const csrf = document.querySelector('input[name="csrf_token"]');
    if (csrf) formData.append('csrf_token', csrf.value);

    fetch('../api/gemini.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            WVProgress.hide();

            if (data.success) {
                if (!data.text || !data.text.trim()) {
                    alert('A IA não retornou conteúdo. Tente ajustar o prompt ou o modelo.');
                    return;
                }
                resultDiv.style.display = 'block';
                resultTextarea.value = data.text;

                if (data.debug) {
                    console.log('[Gemini debug - projeto]', data.debug);
                    if (!data.debug.instructions_present) {
                        console.warn('Nenhuma instrução de agente encontrada para contexto "projeto". Verifique Configurações → Instruções para Descrição de Projetos.');
                    }
                    renderGeminiDebug(resultDiv, data.debug, 'projeto');
                }
            } else {
                alert('Erro ao gerar: ' + data.message);
            }
        })
        .catch(error => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            alert('Erro de conexão: ' + error.message);
        });
}

// Aplicar descrição gerada ao campo
function aplicarDescricao() {
    const resultadoTextarea = document.getElementById('textoDescricaoGerado');
    const textoGerado = resultadoTextarea ? resultadoTextarea.value : '';
    const descricaoField = document.getElementById('descricao') || document.querySelector('[name="descricao"]');

    if (!textoGerado.trim()) {
        alert('Nenhuma descrição gerada para aplicar.');
        return;
    }

    if (!descricaoField) {
        alert('Campo "Descrição" não encontrado na página.');
        return;
    }

    if (descricaoField.isContentEditable) {
        descricaoField.textContent = textoGerado.trim();
    } else {
        descricaoField.value = textoGerado.trim();
    }

    // Notificar listeners que dependem de input/change
    descricaoField.dispatchEvent(new Event('input', { bubbles: true }));
    descricaoField.dispatchEvent(new Event('change', { bubbles: true }));

    document.getElementById('resultadoDescricao').style.display = 'none';

    // Scroll para o campo de descrição
    descricaoField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    descricaoField.focus();
}

// Gerar post para LinkedIn
function gerarLinkedin() {
    const prompt = document.getElementById('prompt_linkedin').value;
    const btnText = document.getElementById('btnGerarLinkedText');
    const btnLoader = document.getElementById('btnGerarLinkedLoader');
    const resultDiv = document.getElementById('resultadoLinkedin');
    const resultTextarea = document.getElementById('textoLinkedinGerado');

    if (!prompt.trim()) {
        alert('Por favor, insira um prompt para a IA.');
        return;
    }

    WVProgress.show('Gerando post LinkedIn...', 'Criando um texto engajador com hashtags e ganchos profissionais.');
    WVProgress.animateTo(90, 12000);

    const formData = new FormData();
    formData.append('action', 'generate_text');
    formData.append('prompt', prompt);
    formData.append('context', 'linkedin');
    // Debug opcional: ajuda a verificar qual "agente/instrução" foi aplicado no backend
    formData.append('debug', '1');
    const csrf = document.querySelector('input[name="csrf_token"]');
    if (csrf) formData.append('csrf_token', csrf.value);

    fetch('../api/gemini.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';

            if (data.success) {
                if (!data.text || !data.text.trim()) {
                    alert('A IA não retornou conteúdo. Tente ajustar o prompt ou o modelo.');
                    return;
                }
                resultDiv.style.display = 'block';
                resultTextarea.value = data.text;

                const slug = window.projetoAtual && window.projetoAtual.slug ? window.projetoAtual.slug : '';
                const projectUrl = buildPtProjectUrl(slug);
                if (projectUrl && !resultTextarea.value.includes(projectUrl)) {
                    resultTextarea.value = resultTextarea.value.replace(/\s+$/, '');
                    resultTextarea.value += '\n\n🔗 ' + projectUrl;
                }

                if (data.debug) {
                    console.log('[Gemini debug - linkedin]', data.debug);
                    if (!data.debug.instructions_present) {
                        console.warn('Nenhuma instrução de agente encontrada para contexto "linkedin". Verifique Configurações → Instruções para Posts de LinkedIn.');
                    }
                    renderGeminiDebug(resultDiv, data.debug, 'linkedin');
                }
            } else {
                alert('Erro ao gerar: ' + data.message);
            }
        })
        .catch(error => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            alert('Erro de conexão: ' + error.message);
        });
}

function renderGeminiDebug(container, debug, contextLabel) {
    if (!container || !debug) return;

    const existing = container.querySelector('.gemini-debug');
    if (existing) existing.remove();

    const el = document.createElement('div');
    el.className = 'gemini-debug';
    el.style.cssText = 'margin-top:10px;padding:10px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;font-size:12px;color:#334155;';

    const preview = (debug.instructions_preview || '').trim();
    const previewHtml = preview
        ? `<div style="margin-top:6px;white-space:pre-wrap;max-height:120px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:8px;">${escapeHtml(preview)}</div>`
        : '<div style="margin-top:6px;color:#b91c1c;">Nenhuma instrução encontrada (campo vazio).</div>';

    el.innerHTML = `
        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div><strong>Debug IA (${escapeHtml(contextLabel)})</strong></div>
            <div style="opacity:.8">model=${escapeHtml(String(debug.model || ''))} · max_tokens=${escapeHtml(String(debug.max_tokens || ''))}</div>
        </div>
        <div style="margin-top:6px;">
            <div>instructions_key_used: <code>${escapeHtml(String(debug.instructions_key_used || ''))}</code></div>
            <div>instructions_length: <code>${escapeHtml(String(debug.instructions_length || 0))}</code></div>
        </div>
        <div style="margin-top:6px;"><strong>instructions_preview</strong>${previewHtml}</div>
    `;

    container.appendChild(el);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Copiar texto do LinkedIn
function copiarLinkedin() {
    const texto = document.getElementById('textoLinkedinGerado').value;

    navigator.clipboard.writeText(texto).then(function () {
        alert('Post copiado para a área de transferência!');
    }).catch(function (err) {
        // Fallback para navegadores mais antigos
        const textarea = document.getElementById('textoLinkedinGerado');
        textarea.select();
        document.execCommand('copy');
        alert('Post copiado!');
    });
}

// Salvar post do LinkedIn
function salvarPostLinkedin() {
    const projetoId = window.projetoAtual ? window.projetoAtual.id : null;
    const conteudo = document.getElementById('textoLinkedinGerado').value;
    const prompt = document.getElementById('prompt_linkedin').value;

    if (!projetoId) {
        alert('Salve o projeto primeiro antes de salvar posts.');
        return;
    }

    if (!conteudo.trim()) {
        alert('Gere um post primeiro.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'save_linkedin_post');
    formData.append('projeto_id', projetoId);
    formData.append('conteudo', conteudo);
    formData.append('prompt', prompt);

    fetch('../api/projetos.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Post salvo com sucesso!');
                location.reload();
            } else {
                alert('Erro ao salvar: ' + data.message);
            }
        })
        .catch(error => {
            alert('Erro de conexão: ' + error.message);
        });
}

// Reutilizar post antigo
function reutilizarPost(postId) {
    // Buscar conteúdo do post e colocar no textarea
    fetch('../api/projetos.php?action=get_linkedin_post&id=' + postId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('textoLinkedinGerado').value = data.post.conteudo;
                document.getElementById('resultadoLinkedin').style.display = 'block';

                // Scroll para o resultado
                document.getElementById('resultadoLinkedin').scrollIntoView({ behavior: 'smooth' });
            }
        });
}

// ===============================
// Funções para Social Variants (Instagram / Facebook)
// ===============================
function gerarSocialCaption() {
    const promptEl = document.getElementById('social_prompt');
    const rede = document.getElementById('social_rede_select').value;
    const prompt = (promptEl && promptEl.value) ? promptEl.value.trim() : '';
    const artigoId = document.querySelector('input[name="id"]').value;
    if (!artigoId) return alert('Salve o artigo primeiro.');

    // Se prompt vazio, use título + resumo
    if (!prompt) {
        const titulo = document.getElementById('titulo') ? document.getElementById('titulo').value : '';
        const resumo = document.getElementById('resumo') ? document.getElementById('resumo').value : '';
        promptFinal = `Resuma o artigo '${titulo}'${resumo ? `: ${resumo}` : ''} em uma legenda curta para ${rede}. Use tom ${rede === 'instagram' ? 'leve e direto, com emojis e hashtags' : 'mais informal e engajador'}. Inclua 2-3 hashtags relevantes.`;
    } else {
        promptFinal = prompt;
    }

    const formData = new FormData();
    formData.append('action', 'generate_text');
    formData.append('prompt', promptFinal);
    formData.append('context', rede);
    formData.append('debug', '1');
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    WVProgress.show('Gerando legenda social...', `Criando texto otimizado para ${rede.charAt(0).toUpperCase() + rede.slice(1)}.`);
    WVProgress.animateTo(90, 8000);

    fetch('../api/gemini.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            WVProgress.hide();
            if (!data.success) return alert('Erro ao gerar legenda: ' + (data.message || ''));
            document.getElementById('social_generation_result').style.display = 'block';
            document.getElementById('social_preview_caption').textContent = data.text || data.texto || '';
            if (data.debug) console.log('[Gemini debug - social-caption]', data.debug);
        })
        .catch(err => {
            WVProgress.hide();
            alert('Erro: ' + err.message);
        });
}

function gerarSocialImages() {
    const promptEl = document.getElementById('social_prompt');
    const prompt = (promptEl && promptEl.value) ? promptEl.value.trim() : '';
    if (!prompt) return alert('Por favor, insira um prompt para gerar imagens.');

    const formData = new FormData();
    formData.append('action', 'generate_images_multi');
    formData.append('prompt', prompt);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    WVProgress.show('Gerando imagens sociais...', 'Criando formatos 1:1 e 9:16 para suas redes.');
    WVProgress.animateTo(90, 20000);

    fetch('../api/gemini.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            WVProgress.hide();
            if (!data.success) return alert('Erro ao gerar imagens: ' + (data.message || ''));
            const imagesDiv = document.getElementById('social_preview_images');
            imagesDiv.innerHTML = '';
            if (data.imagem_1x1_url) {
                const img1 = document.createElement('img'); img1.src = data.imagem_1x1_url; img1.style.maxWidth = '180px'; img1.style.borderRadius = '8px'; imagesDiv.appendChild(img1);
                // store filename in dataset for saving
                imagesDiv.dataset.image1x1 = data.imagem_1x1 || '';
            }
            if (data.imagem_9x16_url) {
                const img2 = document.createElement('img'); img2.src = data.imagem_9x16_url; img2.style.maxWidth = '120px'; img2.style.borderRadius = '8px'; imagesDiv.appendChild(img2);
                imagesDiv.dataset.image9x16 = data.imagem_9x16 || '';
            }
            document.getElementById('social_generation_result').style.display = 'block';
        })
        .catch(err => {
            WVProgress.hide();
            alert('Erro: ' + err.message);
        });
}

function salvarSocialVariant() {
    const artigoId = document.querySelector('input[name="id"]').value;
    if (!artigoId) return alert('Salve o artigo primeiro.');

    const rede = document.getElementById('social_rede_select').value;
    const titulo = ''; // opcional
    const caption = document.getElementById('social_preview_caption').textContent || '';
    const image1 = document.getElementById('social_preview_images')?.dataset?.image1x1 || '';
    const image2 = document.getElementById('social_preview_images')?.dataset?.image9x16 || '';
    const status = document.getElementById('social_variant_status')?.value || 'rascunho';
    const csrf = document.querySelector('input[name="csrf_token"]').value;

    const formData = new FormData();
    formData.append('action', 'save_social_variant');
    formData.append('artigo_id', artigoId);
    formData.append('rede', rede);
    formData.append('titulo', titulo);
    formData.append('caption', caption);
    formData.append('image_1x1', image1);
    formData.append('image_9x16', image2);
    formData.append('status', status);
    formData.append('csrf_token', csrf);

    WVProgress.show('Salvando variante...', 'Aguarde a atualização do banco de dados.');

    fetch('../api/artigos.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            WVProgress.hide();
            if (!data.success) return alert('Erro ao salvar variante: ' + (data.message || ''));
            alert('✅ Variante salva com sucesso!');
            carregarSocialVariants();
        })
        .catch(err => {
            WVProgress.hide();
            alert('Erro: ' + err.message);
        });
}

function carregarSocialVariants() {
    const container = document.getElementById('social_variants_list');
    const idInput = document.querySelector('input[name="id"]');
    if (!container) return;
    if (!idInput || !idInput.value) {
        container.innerHTML = '(Salve o artigo para gerar variantes sociais)';
        return;
    }
    const artigoId = idInput.value;

    fetch('../api/artigos.php?action=list_social_variants&artigo_id=' + encodeURIComponent(artigoId))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                const message = data.message ? `: ${data.message}` : '';
                container.innerHTML = `(Erro ao carregar variantes${message})`;
                return;
            }
            if (!data.variants || data.variants.length === 0) { container.innerHTML = '(Nenhuma variante criada)'; return; }
            let html = '<ul style="list-style:none;padding:0;margin:0;">';
            data.variants.forEach(v => {
                const statusIcon = v.video_file ? '<i class="ph ph-check-circle" style="color:var(--success);"></i> gerado' : '';
                html += `<li class="variant-item animate-enter" style="padding:16px;background:var(--surface-card);border:1px solid var(--border);border-radius:var(--radius-lg);margin-bottom:12px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-sm);">
                        <div style="flex:1;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                <i class="ph ph-${v.rede === 'linkedin' ? 'linkedin-logo' : (v.rede === 'facebook' ? 'facebook-logo' : 'instagram-logo')}" style="color:var(--primary);font-size:18px;"></i>
                                <strong style="text-transform:capitalize;">${escapeHtml(v.rede || '')}</strong>
                            </div>
                            <div style="white-space:pre-wrap;font-size:13px;line-height:1.6;color:var(--text-secondary);">${escapeHtml(v.caption || '')}</div>
                            ${v.video_file ? `<div style="margin-top:8px;"><a class="btn btn-sm btn-secondary" href="${window.siteBaseUrl}/uploads/${v.video_file}" target="_blank"><i class="ph ph-play"></i> Reproduzir vídeo</a></div>` : ''}
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <button class="btn btn-sm btn-secondary" onclick="previewVariant(${v.id})"><i class="ph ph-eye"></i> Preview</button>
                            ${v.video_file ? (v.status === 'publicado' ? '<span class="badge badge-success">Publicado</span>' : `<button class="btn btn-sm btn-success" onclick="publishVariant(${v.id})"><i class="ph ph-check"></i> Marcar Pronto</button>`) : `<button class="btn btn-sm btn-secondary" onclick="enqueueVideo(${v.id}, this)"><i class="ph ph-film-strip"></i> Gerar Vídeo</button>`}
                            <button class="btn btn-sm btn-danger" onclick="confirmDeleteVariant(${v.id})"><i class="ph ph-trash"></i> Excluir</button>
                        </div>
                        <div style="margin-left:8px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;" id="video_status_${v.id}">${statusIcon}</div>
                    </li>`;
            });
            html += '</ul>';
            container.innerHTML = html;
        })
        .catch(err => console.error(err));
}

function limparSocialPreview() {
    const caption = document.getElementById('social_preview_caption');
    const imagesDiv = document.getElementById('social_preview_images');
    const wrapper = document.getElementById('social_generation_result');
    if (caption) {
        caption.textContent = '';
    }
    if (imagesDiv) {
        imagesDiv.innerHTML = '';
        delete imagesDiv.dataset.image1x1;
        delete imagesDiv.dataset.image9x16;
        delete imagesDiv.dataset.variantId;
    }
    if (wrapper) {
        wrapper.style.display = 'none';
    }
}

function confirmDeleteVariant(id) {
    if (!confirm('Confirma excluir esta variante?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_social_variant');
    formData.append('id', id);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fetch('../api/artigos.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return alert('Erro ao excluir: ' + (data.message || ''));
            alert('Variante excluída.');
            carregarSocialVariants();
        })
        .catch(err => alert('Erro: ' + err.message));
}

function publishVariant(id) {
    if (!confirm('Marcar esta variante como pronta para publicação manual?')) return;
    const formData = new FormData();
    formData.append('action', 'publish_social_variant');
    formData.append('id', id);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    fetch('../api/artigos.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Variante marcada como pronta para publicação manual.');
                carregarSocialVariants();
            } else {
                alert('Erro: ' + (data.message || ''));
            }
        })
        .catch(err => alert('Erro: ' + err.message));
}

function previewVariant(id) {
    fetch('../api/artigos.php?action=list_social_variants&artigo_id=' + document.querySelector('input[name="id"]').value)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return alert('Erro ao carregar variante');
            const v = data.variants.find(x => x.id == id);
            if (!v) return alert('Variante não encontrada');
            document.getElementById('social_generation_result').style.display = 'block';
            document.getElementById('social_preview_caption').textContent = v.caption || '';
            const imagesDiv = document.getElementById('social_preview_images');
            imagesDiv.innerHTML = '';
            imagesDiv.dataset.image1x1 = v.image_1x1 || '';
            imagesDiv.dataset.image9x16 = v.image_9x16 || '';
            imagesDiv.dataset.variantId = v.id || '';
            if (v.image_1x1) imagesDiv.innerHTML += `<img src="${window.siteBaseUrl}/uploads/${v.image_1x1}" style="max-width:180px;border-radius:8px;margin-right:8px;">`;
            if (v.image_9x16) imagesDiv.innerHTML += `<img src="${window.siteBaseUrl}/uploads/${v.image_9x16}" style="max-width:120px;border-radius:8px;">`;
        });
}

function gerarVideoFromVariant() {
    const variantId = document.getElementById('social_preview_images')?.dataset?.variantId;
    if (!variantId) return alert('Selecione uma variante primeiro (Preview).');
    if (!confirm('Gerar vídeo para essa variante? Isso pode levar alguns segundos.')) return;
    enqueueVideo(variantId, null);
}

function gerarVersaoSocial(rede, evt) {
    // Gera legenda (e pré-visualização) usando o agente específico configurado nas redes sociais
    const title = document.getElementById('titulo')?.value || '';
    const resumo = document.getElementById('resumo')?.value || '';
    const promptText = document.getElementById('social_prompt')?.value || `${title}${resumo ? ': ' + resumo : ''}`;
    limparSocialPreview();

    if (!confirm(`Gerar versão para ${rede} usando o agente social configurado?`)) return;

    WVProgress.show(`Gerando versão ${rede}...`, 'A IA está processando o conteúdo com o agente especializado.');
    WVProgress.animateTo(90, 8000);

    const formData = new FormData();
    formData.append('action', 'generate_social_agent');
    formData.append('rede', rede);
    formData.append('prompt', promptText);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');

    if (evt && evt.shiftKey) {
        formData.append('debug', '1');
    }

    fetch('../api/gemini.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            WVProgress.hide();
            if (!data.success) return alert('Erro ao gerar: ' + (data.message || ''));
            if (data.debug) console.log('[Gemini social debug]', data.debug);

            document.getElementById('social_generation_result').style.display = 'block';
            document.getElementById('social_preview_caption').textContent = data.text || '';
            document.getElementById('social_preview_images').innerHTML = '';

            alert('✅ Versão gerada. Revise e clique em "Salvar Variante" para criar a variante.');
        })
        .catch(err => {
            WVProgress.hide();
            alert('Erro: ' + err.message);
        });
}

function enqueueVideo(variantId, btn) {
    try {
        if (btn) {
            btn.disabled = true;
            btn.dataset.origText = btn.innerHTML;
            btn.innerHTML = 'Enfileirando...';
        }
        const csrf = document.querySelector('input[name="csrf_token"]')?.value || '';
        const formData = new FormData();
        formData.append('action', 'enqueue_from_variant');
        formData.append('id', variantId);
        formData.append('duration_per_image', 3);
        formData.append('csrf_token', csrf);

        fetch('../api/videos.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert('Erro ao enfileirar: ' + (data.message || ''));
                    if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.origText || 'Gerar Vídeo'; }
                    return;
                }
                const jobId = data.job_id;
                const providerLabel = data.provider ? (' · ' + String(data.provider).toUpperCase()) : '';
                const modelLabel = data.model ? (' · ' + data.model) : '';
                const statusEl = document.getElementById('video_status_' + variantId);
                if (statusEl) statusEl.innerHTML = `<span class="video-spinner"></span> Enfileirado (job #${jobId}${providerLabel}${modelLabel})`;
                pollVideoJob(jobId, variantId, btn);
            })
            .catch(err => {
                alert('Erro: ' + err.message);
                if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.origText || 'Gerar Vídeo'; }
            });
    } catch (e) {
        alert('Erro: ' + e.message);
    }
}

function renderVideoStatusHtml(status, message) {
    if (status === 'pending' || status === 'processing') {
        return `<span class="video-spinner"></span><span class="video-progress"><span class="bar"></span></span><span> ${status}</span>`;
    }
    if (status === 'failed') {
        return '❌ ' + (message || 'Erro');
    }
    if (status === 'success') {
        return '✅ Concluído';
    }
    return status || '';
}

function pollVideoJob(jobId, variantId, btn) {
    const statusEl = document.getElementById('video_status_' + variantId);
    let attempts = 0;
    const iv = setInterval(() => {
        attempts++;
        fetch('../api/videos.php?action=job_status&job_id=' + encodeURIComponent(jobId))
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    if (statusEl) statusEl.innerHTML = '⚠️ Erro ao obter status';
                    return;
                }
                const job = data.job;
                if (!job) return;
                if (statusEl) {
                    if (job.status === 'pending' || job.status === 'processing') {
                        statusEl.innerHTML = renderVideoStatusHtml(job.status);
                    } else if (job.status === 'failed') {
                        statusEl.innerHTML = renderVideoStatusHtml('failed', job.last_error);
                        clearInterval(iv);
                        if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.origText || 'Gerar Vídeo'; }
                    } else if (job.status === 'success') {
                        const url = job.output_url || (job.output_file ? window.siteBaseUrl + '/uploads/' + job.output_file : '');
                        statusEl.innerHTML = `✅ <a href="${url}" target="_blank">Ver vídeo</a>`;
                        // Atualizar preview se estiver aberto
                        const imagesDiv = document.getElementById('social_preview_images');
                        if (imagesDiv && imagesDiv.dataset.variantId == variantId && url) {
                            imagesDiv.innerHTML += `<div style="margin-left:8px"><video src="${url}" controls style="max-width:200px;border-radius:8px;margin-left:8px;"></video></div>`;
                        }
                        clearInterval(iv);
                        if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.origText || 'Gerar Vídeo'; }
                    }
                }
            })
            .catch(err => {
                if (statusEl) statusEl.innerHTML = '⚠️ Erro: ' + err.message;
            });

        if (attempts > 120) { // timeout ~6min
            clearInterval(iv);
            if (statusEl) statusEl.innerHTML = '⚠️ Timeout';
        }
    }, 3000);
}

// Auto carregar variantes ao abrir a página de edição
document.addEventListener('DOMContentLoaded', function () {
    var idInput = document.querySelector('input[name="id"]');
    if (idInput && idInput.value) {
        carregarSocialVariants();
    }

    // Keepalive: checar sessão periodicamente para evitar expiração durante edição
    setInterval(function () {
        fetch('../api/auth.php', { method: 'POST', body: new URLSearchParams({ action: 'check' }), credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.authenticated) {
                    // Aviso discreto para o usuário
                    if (!document.getElementById('sessionWarning')) {
                        var warn = document.createElement('div');
                        warn.id = 'sessionWarning';
                        warn.className = 'message message-warning';
                        warn.style.position = 'fixed';
                        warn.style.bottom = '12px';
                        warn.style.right = '12px';
                        warn.style.zIndex = 9999;
                        warn.textContent = '⚠️ Sessão expirada. Faça login novamente.';
                        document.body.appendChild(warn);
                    }
                } else {
                    var warnEl = document.getElementById('sessionWarning');
                    if (warnEl) warnEl.remove();
                }
            })
            .catch(() => { });
    }, 4 * 60 * 1000); // a cada 4 minutos
});
// Deletar projeto
function deletarProjeto(id) {
    if (!confirm('Tem certeza que deseja excluir este projeto? Esta ação não pode ser desfeita.')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    const csrf = (window.csrfToken || '') || (document.querySelector('input[name="csrf_token"]')?.value || '');
    if (!csrf) {
        alert('⚠️ CSRF inválido. Recarregue a página.');
        return;
    }
    formData.append('csrf_token', csrf);

    WVProgress.show('Excluindo projeto...', 'Processando a remoção definitiva do projeto.');
    fetch('../api/projetos.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            WVProgress.hide();
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + data.message);
            }
        })
        .catch(error => {
            WVProgress.hide();
            alert('Erro de conexão: ' + error.message);
        });
}

// Formulário de projeto
document.addEventListener('DOMContentLoaded', function () {
    const projetoForm = document.getElementById('projetoForm');

    if (projetoForm) {
        projetoForm.addEventListener('submit', function (e) {
            e.preventDefault();
            salvarProjeto();
        });
    }
});

// Salvar projeto
function salvarProjeto() {
    if (projetoSaveInProgress) {
        return;
    }
    projetoSaveInProgress = true;
    const form = document.getElementById('projetoForm');
    const formData = new FormData(form);
    formData.append('action', formData.get('id') ? 'update' : 'create');
    formData.append('csrf_token', document.querySelector('#projetoForm input[name="csrf_token"]').value);

    // Aviso para evitar 413 (Nginx client_max_body_size). Ajuste conforme o servidor.
    const approxLimitBytes = 2 * 1024 * 1024 * 1024;
    const uploadInfo = getUploadSizeInfo(form);
    if (uploadInfo.totalBytes > approxLimitBytes) {
        const totalMB = (uploadInfo.totalBytes / (1024 * 1024)).toFixed(2);
        const msg =
            `Os arquivos selecionados somam ~${totalMB}MB e podem exceder o limite do servidor (2GB), causando erro 413.\n\n` +
            `Sugestões:\n` +
            `- Reduza o número de imagens da galeria\n` +
            `- Comprima as imagens (JPG/WebP)\n` +
            `- Ou aumente o limite do Nginx (client_max_body_size)\n\n` +
            `Deseja tentar mesmo assim?`;
        if (!confirm(msg)) {
            return;
        }
    }

    const btnText = document.getElementById('btnSaveText');
    const btnLoader = document.getElementById('btnSaveLoader');
    const messageDiv = document.getElementById('messageDiv');
    const submitButtons = form.querySelectorAll('button[type="submit"]');

    submitButtons.forEach(btn => btn.disabled = true);
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';

    WVProgress.show('Salvando projeto...', 'Enviando dados e mídias para o servidor.');

    fetch('../api/projetos.php', {
        method: 'POST',
        body: formData
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type') || '';
            const rawText = await response.text();

            let data = null;
            if (contentType.includes('application/json')) {
                try { data = JSON.parse(rawText); } catch (_) { }
            } else {
                // alguns erros (ex.: 413) retornam HTML
                try { data = JSON.parse(rawText); } catch (_) { }
            }

            if (!response.ok) {
                const fallbackMessage =
                    response.status === 413
                        ? 'Upload muito grande (erro 413). Reduza as imagens ou aumente `client_max_body_size` no Nginx.'
                        : `Erro HTTP ${response.status}.`;

                throw new Error((data && data.message) ? data.message : fallbackMessage);
            }

            if (!data) {
                throw new Error('Resposta inválida do servidor (não JSON).');
            }

            return data;
        })
        .then(data => {
            WVProgress.hide();

            messageDiv.style.display = 'block';
            if (data.success) {
                messageDiv.className = 'message message-success';
                messageDiv.textContent = '✅ ' + data.message;

                // Se for novo projeto, redirecionar para edição
                if (data.projeto_id && !formData.get('id')) {
                    setTimeout(function () {
                        window.location.href = '?action=edit&id=' + data.projeto_id;
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
        })
        .finally(() => {
            projetoSaveInProgress = false;
            submitButtons.forEach(btn => btn.disabled = false);
        });
}

function getUploadSizeInfo(form) {
    const inputs = Array.from(form.querySelectorAll('input[type="file"]'));
    let totalBytes = 0;
    const details = [];

    for (const input of inputs) {
        const files = input.files ? Array.from(input.files) : [];
        if (!files.length) continue;

        let sum = 0;
        for (const file of files) sum += file.size || 0;
        totalBytes += sum;
        details.push({ name: input.name || input.id || 'file', count: files.length, bytes: sum });
    }

    return { totalBytes, details };
}

// Deletar mídia da galeria de um projeto
function deleteMedia(projetoId, filename) {
    if (!confirm('Tem certeza que deseja excluir esta mídia?')) return;
    const csrf = document.querySelector('#projetoForm input[name="csrf_token"]')?.value || '';
    if (!csrf) {
        alert('⚠️ CSRF inválido. Recarregue a página.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_media');
    formData.append('projeto_id', String(projetoId));
    formData.append('filename', filename);
    formData.append('csrf_token', csrf);

    fetch('../api/projetos.php', { method: 'POST', body: formData })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                // remover do DOM
                const el = document.querySelector('.gallery-item[data-filename="' + filename.replace(/"/g, '\\"') + '"]');
                if (el) el.remove();
                const msg = document.getElementById('messageDiv');
                if (msg) { msg.style.display = 'block'; msg.className = 'message message-success'; msg.textContent = '✅ ' + data.message; }
            } else {
                alert('Erro ao deletar mídia: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(err => {
            alert('Erro de conexão: ' + err.message);
        });
}

// ===============================
// FUNÇÕES CRUD PARA CATEGORIAS (Admin)
// ===============================
function abrirModalNovaCategoria() {
    document.getElementById('modalTitle').textContent = 'Nova Categoria';
    document.getElementById('categoria_id').value = '';
    document.getElementById('nome').value = '';
    document.getElementById('ativo').checked = true;
    document.getElementById('btnSaveText').style.display = 'inline';
    document.getElementById('btnSaveLoader').style.display = 'none';
    document.getElementById('categoriaModal').style.display = 'block';
}

function fecharModal() {
    document.getElementById('categoriaModal').style.display = 'none';
}

function editarCategoria(id) {
    const cat = (typeof categoriasData !== 'undefined') ? categoriasData.find(c => parseInt(c.id) === parseInt(id)) : null;
    if (!cat) {
        alert('Categoria não encontrada. Atualize a página e tente novamente.');
        return;
    }

    document.getElementById('modalTitle').textContent = 'Editar Categoria';
    document.getElementById('categoria_id').value = cat.id;
    document.getElementById('nome').value = cat.nome || '';
    document.getElementById('ativo').checked = (parseInt(cat.ativo) === 1 || cat.ativo === true);
    document.getElementById('btnSaveText').textContent = 'Atualizar';
    document.getElementById('btnSaveText').style.display = 'inline';
    document.getElementById('btnSaveLoader').style.display = 'none';
    document.getElementById('categoriaModal').style.display = 'block';
}

function salvarCategoria(e) {
    e.preventDefault();

    const id = document.getElementById('categoria_id').value;
    const nome = document.getElementById('nome').value.trim();
    const ativo = document.getElementById('ativo').checked ? 1 : 0;
    const csrf = document.getElementById('csrf_token').value;
    const btnText = document.getElementById('btnSaveText');
    const btnLoader = document.getElementById('btnSaveLoader');
    const messageDiv = document.getElementById('messageDiv');

    if (!nome) {
        alert('Nome é obrigatório.');
        return;
    }

    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';

    const formData = new FormData();
    formData.append('action', id ? 'atualizar' : 'criar');
    if (id) formData.append('id', id);
    formData.append('nome', nome);
    formData.append('ativo', ativo);
    formData.append('csrf_token', csrf);

    fetch('../api/categorias.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(data => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';

            if (data.success) {
                // fechar modal e recarregar para garantir sincronização
                fecharModal();
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-success';
                messageDiv.textContent = '✅ ' + data.message;
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-error';
                messageDiv.textContent = '❌ ' + (data.message || 'Erro ao salvar categoria.');
            }
        })
        .catch(err => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            messageDiv.style.display = 'block';
            messageDiv.className = 'message message-error';
            messageDiv.textContent = '❌ Erro de conexão: ' + err.message;
        });
}

function deletarCategoria(id) {
    if (!confirmDelete('Tem certeza que deseja deletar esta categoria?')) return;

    const csrf = document.getElementById('csrf_token').value;
    const messageDiv = document.getElementById('messageDiv');

    const formData = new FormData();
    formData.append('action', 'deletar');
    formData.append('id', id);
    formData.append('csrf_token', csrf);

    fetch('../api/categorias.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-success';
                messageDiv.textContent = '✅ ' + data.message;
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-error';
                messageDiv.textContent = '❌ ' + (data.message || 'Erro ao deletar categoria.');
            }
        })
        .catch(err => {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message message-error';
            messageDiv.textContent = '❌ Erro de conexão: ' + err.message;
        });
}

// ===============================
// FUNÇÕES CRUD PARA CATEGORIAS DE ARTIGOS (Admin)
// ===============================
function abrirModalNovaCategoriaArtigo() {
    document.getElementById('modalTitleArtigo').textContent = 'Nova Categoria de Artigos';
    document.getElementById('categoriaArtigo_id').value = '';
    document.getElementById('nomeArtigo').value = '';
    document.getElementById('ativoArtigo').checked = true;
    document.getElementById('btnSaveTextArtigo').style.display = 'inline';
    document.getElementById('btnSaveLoaderArtigo').style.display = 'none';
    document.getElementById('categoriaArtigoModal').style.display = 'block';
}

function fecharModalArtigo() {
    document.getElementById('categoriaArtigoModal').style.display = 'none';
}

function editarCategoriaArtigo(id) {
    const cat = (typeof categoriasArtigosData !== 'undefined') ? categoriasArtigosData.find(c => parseInt(c.id) === parseInt(id)) : null;
    if (!cat) {
        alert('Categoria não encontrada. Atualize a página e tente novamente.');
        return;
    }

    document.getElementById('modalTitleArtigo').textContent = 'Editar Categoria de Artigos';
    document.getElementById('categoriaArtigo_id').value = cat.id;
    document.getElementById('nomeArtigo').value = cat.nome || '';
    document.getElementById('ativoArtigo').checked = (parseInt(cat.ativo) === 1 || cat.ativo === true);
    document.getElementById('btnSaveTextArtigo').textContent = 'Atualizar';
    document.getElementById('btnSaveTextArtigo').style.display = 'inline';
    document.getElementById('btnSaveLoaderArtigo').style.display = 'none';
    document.getElementById('categoriaArtigoModal').style.display = 'block';
}

function salvarCategoriaArtigo(e) {
    e.preventDefault();

    const id = document.getElementById('categoriaArtigo_id').value;
    const nome = document.getElementById('nomeArtigo').value.trim();
    const ativo = document.getElementById('ativoArtigo').checked ? 1 : 0;
    const csrf = document.getElementById('csrf_token_artigo').value;
    const btnText = document.getElementById('btnSaveTextArtigo');
    const btnLoader = document.getElementById('btnSaveLoaderArtigo');
    const messageDiv = document.getElementById('messageDiv');

    if (!nome) {
        alert('Nome é obrigatório.');
        return;
    }

    btnText.style.display = 'none';
    btnLoader.style.display = 'inline';

    const formData = new FormData();
    formData.append('action', id ? 'atualizar' : 'criar');
    if (id) formData.append('id', id);
    formData.append('nome', nome);
    formData.append('ativo', ativo);
    formData.append('csrf_token', csrf);
    formData.append('target', 'artigos');

    fetch('../api/categorias.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(data => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';

            if (data.success) {
                // fechar modal e recarregar para garantir sincronização
                fecharModalArtigo();
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-success';
                messageDiv.textContent = '✅ ' + data.message;
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-error';
                messageDiv.textContent = '❌ ' + (data.message || 'Erro ao salvar categoria.');
            }
        })
        .catch(err => {
            btnText.style.display = 'inline';
            btnLoader.style.display = 'none';
            messageDiv.style.display = 'block';
            messageDiv.className = 'message message-error';
            messageDiv.textContent = '❌ Erro de conexão: ' + err.message;
        });
}

function deletarCategoriaArtigo(id) {
    if (!confirmDelete('Tem certeza que deseja deletar esta categoria de artigos?')) return;

    const csrf = document.getElementById('csrf_token_artigo').value;
    const messageDiv = document.getElementById('messageDiv');

    const formData = new FormData();
    formData.append('action', 'deletar');
    formData.append('id', id);
    formData.append('csrf_token', csrf);
    formData.append('target', 'artigos');

    fetch('../api/categorias.php', {
        method: 'POST',
        body: formData
    })
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-success';
                messageDiv.textContent = '✅ ' + data.message;
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-error';
                messageDiv.textContent = '❌ ' + (data.message || 'Erro ao deletar categoria.');
            }
        })
        .catch(err => {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message message-error';
            messageDiv.textContent = '❌ Erro de conexão: ' + err.message;
        });
}

/**
 * GLOBAL LOADING INDICATOR
 * Spinner discreto exibido automaticamente durante qualquer requisição assíncrona.
 * Uso manual: WVLoading.show() / WVLoading.hide()
 */
const WVLoading = (function () {
    let active = 0;
    let showTimer = null;
    let el = null;

    function ensureEl() {
        if (el) return el;
        if (!document.body) return null;
        el = document.createElement('div');
        el.id = 'wvGlobalLoading';
        el.className = 'wv-global-loading';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.innerHTML = '<span class="spinner"></span><span class="wv-global-loading__text">Processando...</span>';
        el.style.display = 'none';
        document.body.appendChild(el);
        return el;
    }

    function show() {
        if (!ensureEl()) return;
        if (showTimer) return;
        showTimer = setTimeout(function () {
            showTimer = null;
            if (active > 0 && el) el.style.display = 'inline-flex';
        }, 150);
    }

    function hide() {
        if (showTimer) { clearTimeout(showTimer); showTimer = null; }
        if (el) el.style.display = 'none';
    }

    function begin() { active++; show(); }
    function end() {
        active = Math.max(0, active - 1);
        if (active === 0) hide();
    }

    return {
        begin: begin,
        end: end,
        show: function () { active++; show(); },
        hide: function () { active = 0; hide(); },
        activeCount: function () { return active; }
    };
})();

(function patchGlobalFetch() {
    if (typeof window.fetch !== 'function' || window.fetch.__wvPatched) return;
    const originalFetch = window.fetch.bind(window);
    const wrappedFetch = function () {
        WVLoading.begin();
        let result;
        try {
            result = originalFetch.apply(this, arguments);
        } catch (err) {
            WVLoading.end();
            throw err;
        }
        return Promise.resolve(result).finally(function () {
            WVLoading.end();
        });
    };
    wrappedFetch.__wvPatched = true;
    window.fetch = wrappedFetch;
})();
