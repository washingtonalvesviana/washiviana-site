<?php
/**
 * WASHIVIANA PORTFOLIO - Admin Configurações
 */
// Força invalidação de cache OPcache
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}
require_once __DIR__ . '/../api/config.php';
requireAuth();

// Buscar configurações atuais
$configs = [];
$stmt = $pdo->query("SELECT * FROM configuracoes");
while ($row = $stmt->fetch()) {
    $configs[$row['chave']] = $row['valor'];
}

// Status de traduções do site (EN/ES)
$uiKeys = [
    'nav.home','nav.contents','nav.projects','nav.about','nav.contact',
    'hero.cta_contents','hero.cta_projects',
    'home.section_find','home.latest','home.soon','home.view_all_contents','home.about_title','home.about_cta',
    'contents.kicker','contents.subtitle','filters.all','footer.menu',
];
$configKeysI18n = [
    'site_subtitulo','home_frase_impacto','mini_bio',
    'home_card_1_titulo','home_card_1_subtexto',
    'home_card_2_titulo','home_card_2_subtexto',
    'home_card_3_titulo','home_card_3_subtexto',
    'home_card_4_titulo','home_card_4_subtexto',
];
$i18nStatus = [
    'ui' => ['en' => 0, 'es' => 0, 'total' => count($uiKeys)],
    'config' => ['en' => 0, 'es' => 0, 'total' => count($configKeysI18n)],
];
try {
    $inUi = implode(',', array_fill(0, count($uiKeys), '?'));
    $stmt = $pdo->prepare("SELECT lang, COUNT(*) AS total FROM ui_strings WHERE lang IN ('en','es') AND chave IN ($inUi) GROUP BY lang");
    $stmt->execute($uiKeys);
    foreach ($stmt->fetchAll() as $row) {
        $lang = $row['lang'];
        $i18nStatus['ui'][$lang] = (int)$row['total'];
    }
} catch (Exception $e) { /* ignore */ }
try {
    $inCfg = implode(',', array_fill(0, count($configKeysI18n), '?'));
    $stmt = $pdo->prepare("SELECT lang, COUNT(*) AS total FROM configuracoes_i18n WHERE lang IN ('en','es') AND chave IN ($inCfg) GROUP BY lang");
    $stmt->execute($configKeysI18n);
    foreach ($stmt->fetchAll() as $row) {
        $lang = $row['lang'];
        $i18nStatus['config'][$lang] = (int)$row['total'];
    }
} catch (Exception $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo assetVersion('assets/css/admin.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .config-card-collapsible .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .config-card-toggle {
            border: 1px solid var(--border);
            background: var(--bg-card, #fff);
            color: inherit;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .15s ease;
        }
        .config-card-toggle:hover {
            opacity: .9;
        }
        .config-card-collapsed > .card-body {
            display: none;
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>Configurações</h1>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <button type="button" id="btnCollapseAllCards" class="btn btn-secondary btn-sm">
                        <i class="ph ph-arrows-in-simple"></i> Recolher todos
                    </button>
                    <button type="button" id="btnExpandAllCards" class="btn btn-secondary btn-sm">
                        <i class="ph ph-arrows-out-simple"></i> Expandir todos
                    </button>
                </div>
            </div>
            
            <div id="messageDiv" class="message" style="display: none;"></div>
            
            <form id="configForm" class="config-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-row">
                    <div class="form-col-8">
                        <div class="card">
                            <div class="card-header"><h3>Informações do Site</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="site_titulo">Título do Site</label>
                                    <input type="text" id="site_titulo" name="site_titulo" 
                                           value="<?php echo htmlspecialchars($configs['site_titulo'] ?? 'Washington Viana'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_subtitulo">Subtítulo</label>
                                    <input type="text" id="site_subtitulo" name="site_subtitulo" 
                                           value="<?php echo htmlspecialchars($configs['site_subtitulo'] ?? 'Interdisciplinary Creative & Developer'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="home_frase_impacto">Frase de Impacto (Homepage)</label>
                                    <textarea id="home_frase_impacto" name="home_frase_impacto" rows="3"><?php echo htmlspecialchars($configs['home_frase_impacto'] ?? ''); ?></textarea>
                                    <small class="form-text">Esta frase aparece na página inicial ao lado da sua foto</small>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3>Homepage — Cards (4 pilares)</h3></div>
                            <div class="card-body">
                                <p class="text-muted" style="margin-bottom: 12px;">
                                    Configure os 4 cards da seção “O que você vai encontrar aqui” (ícone, título, subtexto e link).
                                </p>

                                <?php
                                    $homeCardsDefaults = [
                                        1 => ['icon' => 'ph-robot', 'titulo' => 'Automação & IA', 'subtexto' => 'Aplicações reais usando tecnologia para otimizar processos.', 'link' => 'automacao-ia.php'],
                                        2 => ['icon' => 'ph-lightbulb', 'titulo' => 'Tech Insights', 'subtexto' => 'Notícias comentadas e análises sobre tecnologia.', 'link' => 'tech-insights.php'],
                                        3 => ['icon' => 'ph-briefcase', 'titulo' => 'Projetos', 'subtexto' => 'Projetos que realizei e participei ao longo da carreira.', 'link' => 'projetos.php'],
                                        4 => ['icon' => 'ph-palette', 'titulo' => 'Design & Experiências Digitais', 'subtexto' => 'VR, 3D e design aplicado em soluções inovadoras.', 'link' => 'design-experiencias.php'],
                                    ];
                                ?>

                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <div style="border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 14px;">
                                        <div style="display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
                                            <strong>Card <?php echo $i; ?></strong>
                                            <small class="text-muted">Ícones: Phosphor Icons (ex.: <code>ph-robot</code>, <code>ph-lightbulb</code>)</small>
                                        </div>
                                        <div class="form-row" style="margin-top: 12px;">
                                            <div class="form-col-3">
                                                <div class="form-group">
                                                    <label for="home_card_<?php echo $i; ?>_icon">Ícone</label>
                                                    <input type="text"
                                                           id="home_card_<?php echo $i; ?>_icon"
                                                           name="home_card_<?php echo $i; ?>_icon"
                                                           value="<?php echo htmlspecialchars($configs["home_card_{$i}_icon"] ?? $homeCardsDefaults[$i]['icon']); ?>">
                                                </div>
                                            </div>
                                            <div class="form-col-9">
                                                <div class="form-group">
                                                    <label for="home_card_<?php echo $i; ?>_titulo">Título</label>
                                                    <input type="text"
                                                           id="home_card_<?php echo $i; ?>_titulo"
                                                           name="home_card_<?php echo $i; ?>_titulo"
                                                           value="<?php echo htmlspecialchars($configs["home_card_{$i}_titulo"] ?? $homeCardsDefaults[$i]['titulo']); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-col-8">
                                                <div class="form-group">
                                                    <label for="home_card_<?php echo $i; ?>_subtexto">Subtexto</label>
                                                    <input type="text"
                                                           id="home_card_<?php echo $i; ?>_subtexto"
                                                           name="home_card_<?php echo $i; ?>_subtexto"
                                                           value="<?php echo htmlspecialchars($configs["home_card_{$i}_subtexto"] ?? $homeCardsDefaults[$i]['subtexto']); ?>">
                                                </div>
                                            </div>
                                            <div class="form-col-4">
                                                <div class="form-group">
                                                    <label for="home_card_<?php echo $i; ?>_link">Link</label>
                                                    <input type="text"
                                                           id="home_card_<?php echo $i; ?>_link"
                                                           name="home_card_<?php echo $i; ?>_link"
                                                           placeholder="Ex: automacao-ia.php"
                                                           value="<?php echo htmlspecialchars($configs["home_card_{$i}_link"] ?? $homeCardsDefaults[$i]['link']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header"><h3>Contato</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="site_email">Email</label>
                                    <input type="email" id="site_email" name="site_email" 
                                           value="<?php echo htmlspecialchars($configs['site_email'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_telefone">Telefone/WhatsApp</label>
                                    <input type="text" id="site_telefone" name="site_telefone" 
                                           value="<?php echo htmlspecialchars($configs['site_telefone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_linkedin">LinkedIn URL</label>
                                    <input type="url" id="site_linkedin" name="site_linkedin" 
                                           placeholder="https://linkedin.com/in/..."
                                           value="<?php echo htmlspecialchars($configs['site_linkedin'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_instagram">Instagram URL</label>
                                    <input type="url" id="site_instagram" name="site_instagram" 
                                           placeholder="https://instagram.com/..."
                                           value="<?php echo htmlspecialchars($configs['site_instagram'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_github">GitHub URL</label>
                                    <input type="url" id="site_github" name="site_github" 
                                           placeholder="https://github.com/..."
                                           value="<?php echo htmlspecialchars($configs['site_github'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header"><h3>Sobre Mim</h3></div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="mini_bio">Mini Bio</label>
                                    <textarea id="mini_bio" name="mini_bio" rows="4"><?php echo htmlspecialchars($configs['mini_bio'] ?? ''); ?></textarea>
                                    <small class="form-text">Texto curto sobre você que aparece na homepage e página Sobre</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card card-copilot">
                            <div class="card-header">
                                <h3>Configurações de IA</h3>
                                <p class="card-subtitle">Configuração multi-provedor (texto, imagem e vídeo)</p>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="llm_text_provider">Provedor para Texto</label>
                                    <select id="llm_text_provider" name="llm_text_provider">
                                        <?php $savedTextProvider = $configs['llm_text_provider'] ?? 'gemini'; ?>
                                        <option value="gemini" <?php echo $savedTextProvider === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                                        <option value="openai" <?php echo $savedTextProvider === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                        <option value="deepseek" <?php echo $savedTextProvider === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                                        <option value="openrouter" <?php echo $savedTextProvider === 'openrouter' ? 'selected' : ''; ?>>OpenRouter</option>
                                        <option value="anthropic" <?php echo $savedTextProvider === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                                        <option value="ollama" <?php echo $savedTextProvider === 'ollama' ? 'selected' : ''; ?>>Ollama (Local)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="llm_image_provider">Provedor para Imagens</label>
                                    <select id="llm_image_provider" name="llm_image_provider">
                                        <?php $savedImageProvider = $configs['llm_image_provider'] ?? 'gemini'; ?>
                                        <option value="gemini" <?php echo $savedImageProvider === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                                        <option value="openai" <?php echo $savedImageProvider === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                        <option value="deepseek" <?php echo $savedImageProvider === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                                        <option value="openrouter" <?php echo $savedImageProvider === 'openrouter' ? 'selected' : ''; ?>>OpenRouter</option>
                                        <option value="anthropic" <?php echo $savedImageProvider === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                                        <option value="ollama" <?php echo $savedImageProvider === 'ollama' ? 'selected' : ''; ?>>Ollama (Local)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="llm_video_provider">Provedor para Vídeo</label>
                                    <select id="llm_video_provider" name="llm_video_provider">
                                        <?php $savedVideoProvider = $configs['llm_video_provider'] ?? 'gemini'; ?>
                                        <option value="gemini" <?php echo $savedVideoProvider === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                                        <option value="openai" <?php echo $savedVideoProvider === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                        <option value="deepseek" <?php echo $savedVideoProvider === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                                        <option value="openrouter" <?php echo $savedVideoProvider === 'openrouter' ? 'selected' : ''; ?>>OpenRouter</option>
                                        <option value="anthropic" <?php echo $savedVideoProvider === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                                        <option value="ollama" <?php echo $savedVideoProvider === 'ollama' ? 'selected' : ''; ?>>Ollama (Local)</option>
                                    </select>
                                </div>

                                <div class="form-group llm-provider-field" data-provider="gemini">
                                    <label for="gemini_api_key">Google Gemini API Key</label>
                                    <div class="input-group">
                                        <input type="password" id="gemini_api_key" name="gemini_api_key" 
                                               placeholder="AIza......"
                                               value="<?php echo htmlspecialchars($configs['gemini_api_key'] ?? ''); ?>">
                                        <button type="button" onclick="carregarModelosLLM()" class="btn btn-secondary btn-sm" title="Carregar modelos disponíveis">
                                            <span id="btnLoadModelsText"><i class="ph ph-arrows-clockwise"></i> Carregar Modelos</span>
                                            <span id="btnLoadModelsLoader" style="display:none;"><i class="ph ph-spinner"></i></span>
                                        </button>
                                    </div>
                                    <small class="form-text">Chave para o provedor Gemini.</small>
                                </div>

                                <div class="form-group llm-provider-field" data-provider="openai">
                                    <label for="openai_api_key">OpenAI API Key</label>
                                    <input type="password" id="openai_api_key" name="openai_api_key"
                                           placeholder="sk-..."
                                           value="<?php echo htmlspecialchars($configs['openai_api_key'] ?? ''); ?>">
                                </div>

                                <div class="form-group llm-provider-field" data-provider="deepseek">
                                    <label for="deepseek_api_key">DeepSeek API Key</label>
                                    <input type="password" id="deepseek_api_key" name="deepseek_api_key"
                                           placeholder="sk-..."
                                           value="<?php echo htmlspecialchars($configs['deepseek_api_key'] ?? ''); ?>">
                                </div>

                                <div class="form-group llm-provider-field" data-provider="openrouter">
                                    <label for="openrouter_api_key">OpenRouter API Key</label>
                                    <input type="password" id="openrouter_api_key" name="openrouter_api_key"
                                           placeholder="sk-or-v1-..."
                                           value="<?php echo htmlspecialchars($configs['openrouter_api_key'] ?? ''); ?>">
                                </div>

                                <div class="form-group llm-provider-field" data-provider="anthropic">
                                    <label for="anthropic_api_key">Anthropic API Key</label>
                                    <input type="password" id="anthropic_api_key" name="anthropic_api_key"
                                           placeholder="sk-ant-..."
                                           value="<?php echo htmlspecialchars($configs['anthropic_api_key'] ?? ''); ?>">
                                </div>

                                <div class="form-group llm-provider-field" data-provider="ollama">
                                    <label for="ollama_base_url">Ollama Base URL</label>
                                    <input type="text" id="ollama_base_url" name="ollama_base_url"
                                           placeholder="http://localhost:11434"
                                           value="<?php echo htmlspecialchars($configs['ollama_base_url'] ?? 'http://localhost:11434'); ?>">
                                    <small class="form-text">Endpoint local/remoto da API do Ollama.</small>
                                </div>

                                <div class="form-group llm-provider-field" data-provider="ollama">
                                    <label for="ollama_api_key">Ollama API Key (opcional)</label>
                                    <input type="password" id="ollama_api_key" name="ollama_api_key"
                                           placeholder="Bearer token opcional"
                                           value="<?php echo htmlspecialchars($configs['ollama_api_key'] ?? ''); ?>">
                                </div>

                                <div class="form-group" style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="button" onclick="validarProvedoresEModelos()" class="btn btn-secondary btn-sm" title="Validar somente provedores selecionados e carregar modelos compatíveis">
                                        <i class="ph ph-shield-check"></i> Validar Selecionados + Modelos
                                    </button>
                                    <button type="button" onclick="carregarModelosLLM()" class="btn btn-secondary btn-sm" title="Carregar apenas modelos dos provedores selecionados nas modalidades">
                                        <i class="ph ph-arrows-clockwise"></i> Carregar Modelos (Seleção Atual)
                                    </button>
                                </div>

                                <div class="form-group">
                                    <?php $autoValidateOnLoad = ($configs['llm_auto_validate_on_load'] ?? '1') === '1'; ?>
                                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                        <input type="hidden" name="llm_auto_validate_on_load" value="0">
                                        <input type="checkbox" id="llm_auto_validate_on_load" name="llm_auto_validate_on_load" value="1" <?php echo $autoValidateOnLoad ? 'checked' : ''; ?>>
                                        Validar provedores automaticamente ao abrir esta página
                                    </label>
                                    <small class="form-text">Quando ativado, o admin valida credenciais/modelos no carregamento da tela de configurações.</small>
                                </div>

                                <div id="providerValidationResults" class="alert alert-info" style="display:none;"></div>

                                <div class="form-group">
                                    <label for="notify_email">Email para notificações (erros de jobs/video)</label>
                                    <input type="email" id="notify_email" name="notify_email" placeholder="ops@seudominio.com" value="<?php echo htmlspecialchars($configs['notify_email'] ?? ''); ?>">
                                    <small class="form-text">Emails serão notificados automaticamente quando um job de vídeo falhar.</small>
                                </div>

                                <div class="form-group">
                                    <label for="sentry_dsn">Sentry DSN (opcional)</label>
                                    <input type="text" id="sentry_dsn" name="sentry_dsn" placeholder="https://...@o...ingest.sentry.io/..." value="<?php echo htmlspecialchars($configs['sentry_dsn'] ?? ''); ?>">
                                    <small class="form-text">Se você usa Sentry e o SDK estiver instalado, o worker tentará enviar eventos de erro.</small>
                                </div>
                                
                                <div id="modelosStatus" class="alert alert-info" style="display:none;"></div>
                                
                                 <?php 
                                $savedTextModel = $configs['llm_text_model'] ?? ($configs['gemini_model'] ?? 'gemini-2.0-flash');
                                $defaultTextModels = [
                                    'gemini-2.0-flash' => 'Gemini 2.0 Flash (Recomendado)',
                                    'gemini-3-flash-preview' => 'Gemini 3 Flash (Preview)',
                                    'gemini-1.5-flash' => 'Gemini 1.5 Flash (Rápido)',
                                    'gemini-1.5-pro' => 'Gemini 1.5 Pro (Avançado)'
                                ];
                                ?>
                                <div class="form-group">
                                    <label for="llm_text_model">Modelo de Texto</label>
                                    <select id="llm_text_model" name="llm_text_model">
                                        <?php if (!isset($defaultTextModels[$savedTextModel])): ?>
                                            <option value="<?php echo htmlspecialchars($savedTextModel); ?>" selected><?php echo htmlspecialchars($savedTextModel); ?> (Atual)</option>
                                        <?php endif; ?>
                                        <?php foreach ($defaultTextModels as $id => $name): ?>
                                            <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $savedTextModel == $id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                 <?php 
                                $savedImageModel = $configs['llm_image_model'] ?? ($configs['gemini_image_model'] ?? 'imagen-3.0-generate-002');
                                $defaultImageModels = [
                                    'imagen-3.0-generate-002' => 'Imagen 3 (Recomendado)',
                                    'gemini-2.5-flash-image' => 'Nano Banana (Gemini 2.5 Flash)',
                                    'imagegeneration@006' => 'Imagen 2'
                                ];
                                ?>
                                <div class="form-group">
                                    <label for="llm_image_model">Modelo de Imagens</label>
                                    <select id="llm_image_model" name="llm_image_model">
                                        <?php if (!isset($defaultImageModels[$savedImageModel])): ?>
                                            <option value="<?php echo htmlspecialchars($savedImageModel); ?>" selected><?php echo htmlspecialchars($savedImageModel); ?> (Atual)</option>
                                        <?php endif; ?>
                                        <?php foreach ($defaultImageModels as $id => $name): ?>
                                            <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $savedImageModel == $id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text">Modelo para geração de imagens de artigos</small>
                                </div>

                                <?php 
                                $savedVideoModel = $configs['llm_video_model'] ?? '';
                                ?>
                                <div class="form-group">
                                    <label for="llm_video_model">Modelo de Vídeo</label>
                                    <select id="llm_video_model" name="llm_video_model">
                                        <?php if ($savedVideoModel): ?>
                                            <option value="<?php echo htmlspecialchars($savedVideoModel); ?>" selected><?php echo htmlspecialchars($savedVideoModel); ?> (Atual)</option>
                                        <?php else: ?>
                                            <option value="" selected>Nenhum modelo selecionado</option>
                                        <?php endif; ?>
                                    </select>
                                    <small class="form-text">Usado para provedores que oferecem geração de vídeo.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="gemini_max_tokens">Máximo de Tokens</label>
                                    <input type="number" id="gemini_max_tokens" name="gemini_max_tokens" 
                                           min="100" max="8192" step="100"
                                           value="<?php echo $configs['gemini_max_tokens'] ?? 2048; ?>">
                                    <small class="form-text">Controla o tamanho das respostas geradas (100-8192)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="ia_instrucoes">Instruções para o Agente de IA</label>
                                    <textarea id="ia_instrucoes" name="ia_instrucoes" rows="8" 
                                              placeholder="Ex: Você é Washington Viana, um criador de conteúdo apaixonado por tecnologia e inovação. Escreva de forma envolvente, com tom profissional mas acessível. Use exemplos práticos e mantenha o foco em soluções criativas..."><?php echo htmlspecialchars($configs['ia_instrucoes'] ?? ''); ?></textarea>
                                    <small class="form-text">
                                        <strong>Configure a personalidade geral.</strong> Base usada para qualquer conteúdo quando não houver contexto específico.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="ia_instrucoes_projeto"><i class="ph ph-wrench"></i> Instruções para Descrição de Projetos</label>
                                    <textarea id="ia_instrucoes_projeto" name="ia_instrucoes_projeto" rows="6" 
                                              placeholder="Ex: Descreva projetos do portfólio focando em problema, solução, impacto, stack e métricas de resultado."><?php echo htmlspecialchars($configs['ia_instrucoes_projeto'] ?? ''); ?></textarea>
                                    <small class="form-text">Usado quando gerar texto com contexto "projeto" (descrição para cards/landing de projetos).</small>
                                </div>

                                <div class="form-group">
                                    <label for="ia_instrucoes_linkedin"><i class="ph ph-briefcase"></i> Instruções para Posts de LinkedIn</label>
                                    <textarea id="ia_instrucoes_linkedin" name="ia_instrucoes_linkedin" rows="6" 
                                              placeholder="Ex: Tom profissional e pessoal, gancho inicial, narrativa, aprendizado, CTA e 3-5 hashtags no final."><?php echo htmlspecialchars($configs['ia_instrucoes_linkedin'] ?? ''); ?></textarea>
                                    <small class="form-text">Usado quando gerar texto com contexto "linkedin" (post social no fluxo de projetos).</small>
                                </div>
                                
                                <button type="button" onclick="testarLLM()" class="btn btn-secondary">
                                    <span id="btnTestText"><i class="ph ph-plug"></i> Testar Conexão</span>
                                    <span id="btnTestLoader" style="display:none;">Testando...</span>
                                </button>
                                <div id="testeResult" class="copilot-result" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-col-4">
                        <div class="card">
                            <div class="card-header"><h3>Salvar Alterações</h3></div>
                            <div class="card-body">
                                <p>As alterações serão aplicadas imediatamente em todo o site.</p>
                                <button type="button" onclick="salvarConfiguracoesManual()" class="btn btn-primary btn-block">
                                    <span id="btnSaveText"><i class="ph ph-floppy-disk"></i> Salvar Configurações</span>
                                    <span id="btnSaveLoader" style="display:none;">Salvando...</span>
                                </button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3><i class="ph ph-globe"></i> Idiomas do Site (EN/ES)</h3></div>
                            <div class="card-body">
                                <p class="text-muted" style="margin-bottom: 10px;">Gera traduções do layout (menus/botões) e configs (subtítulo, bio, cards).</p>
                                <div style="display:flex; flex-direction:column; gap:8px; margin-bottom: 12px;">
                                    <div><strong>UI:</strong> EN <?php echo (int)$i18nStatus['ui']['en']; ?>/<?php echo (int)$i18nStatus['ui']['total']; ?> · ES <?php echo (int)$i18nStatus['ui']['es']; ?>/<?php echo (int)$i18nStatus['ui']['total']; ?></div>
                                    <div><strong>Configs:</strong> EN <?php echo (int)$i18nStatus['config']['en']; ?>/<?php echo (int)$i18nStatus['config']['total']; ?> · ES <?php echo (int)$i18nStatus['config']['es']; ?>/<?php echo (int)$i18nStatus['config']['total']; ?></div>
                                </div>
                                <button type="button" onclick="gerarI18nSite(['en','es'])" class="btn btn-secondary btn-block" id="btnI18nSite">
                                    <span id="btnI18nSiteText"><i class="ph ph-sparkle"></i> Gerar EN + ES (IA)</span>
                                    <span id="btnI18nSiteLoader" style="display:none;">Gerando...</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header"><h3>Informações</h3></div>
                            <div class="card-body">
                                <ul class="info-list">
                                    <li><strong>Versão:</strong> 1.0.0</li>
                                    <li><strong>PHP:</strong> <?php echo phpversion(); ?></li>
                                    <li><strong>PostgreSQL:</strong> <?php echo $pdo->query('SELECT VERSION()')->fetch()['version']; ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo assetVersion('assets/js/admin.js'); ?>"></script>
    <script>
        function fetchWithTimeout(url, options, timeout) {
            timeout = typeof timeout === 'number' ? timeout : 30000;
            const controller = new AbortController();
            const id = setTimeout(() => controller.abort(), timeout);
            const opts = Object.assign({}, options, { signal: controller.signal });
            return fetch(url, opts)
                .finally(() => clearTimeout(id));
        }

        // Função de salvamento
        function salvarConfiguracoesManual() {
            var form = document.getElementById('configForm');
            var formData = new FormData(form);
            var messageDiv = document.getElementById('messageDiv');
            var btnSaveText = document.getElementById('btnSaveText');
            var btnSaveLoader = document.getElementById('btnSaveLoader');
            var saveButton = btnSaveText.closest('button');

            if (saveButton) {
                saveButton.disabled = true;
            }
            btnSaveText.style.display = 'none';
            btnSaveLoader.style.display = 'inline';
            messageDiv.style.display = 'none';

            fetchWithTimeout('../api/configuracoes.php', {
                method: 'POST',
                body: formData
            }, 30000)
            .then(function(response) {
                if (!response.ok) {
                    return response.text().then(function(text) {
                        throw new Error('HTTP ' + response.status + ': ' + (text || response.statusText));
                    });
                }
                return response.json();
            })
            .then(function(data) {
                btnSaveText.style.display = 'inline';
                btnSaveLoader.style.display = 'none';
                if (saveButton) {
                    saveButton.disabled = false;
                }

                messageDiv.style.display = 'block';
                if (data.success) {
                    messageDiv.className = 'message message-success';
                    messageDiv.textContent = '✅ ' + data.message;
                } else {
                    messageDiv.className = 'message message-error';
                    messageDiv.textContent = '❌ ' + data.message;
                }

                window.scrollTo({ top: 0, behavior: 'smooth' });

                // Ocultar mensagem após 5 segundos
                setTimeout(function() {
                    messageDiv.style.display = 'none';
                }, 5000);
            })
            .catch(function(error) {
                btnSaveText.style.display = 'inline';
                btnSaveLoader.style.display = 'none';
                if (saveButton) {
                    saveButton.disabled = false;
                }

                messageDiv.style.display = 'block';
                messageDiv.className = 'message message-error';
                if (error.name === 'AbortError') {
                    messageDiv.textContent = '❌ Tempo de espera esgotado. O servidor demorou muito para responder.';
                } else {
                    messageDiv.textContent = '❌ Erro de conexão: ' + error.message;
                }
            });
        }

        function gerarI18nSite(langs) {
            var form = document.getElementById('configForm');
            var csrf = form?.querySelector('input[name="csrf_token"]')?.value || '';
            if (!csrf) {
                alert('⚠️ CSRF inválido. Recarregue a página.');
                return;
            }

            var btn = document.getElementById('btnI18nSite');
            var btnText = document.getElementById('btnI18nSiteText');
            var btnLoader = document.getElementById('btnI18nSiteLoader');
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline';
            document.body.style.cursor = 'progress';

            function parseJsonResponseSafe(resp) {
                return resp.text().then(function(text){
                    var data = null;
                    try { data = JSON.parse(text); } catch(e) {}
                    if (!resp.ok) {
                        var msg = (data && (data.message || data.details)) ? (data.message || data.details) : text;
                        throw new Error('HTTP ' + resp.status + ' - ' + (msg || 'Erro'));
                    }
                    if (!data) throw new Error('Resposta inválida do servidor.');
                    return data;
                });
            }

            function postLang(lang) {
                var formData = new FormData();
                formData.append('csrf_token', csrf);
                formData.append('langs[]', lang);
                return fetchWithTimeout('../api/i18n_site.php', { method: 'POST', body: formData }, 120000).then(parseJsonResponseSafe);
            }

            // Para evitar 504 (gateway timeout) quando pedir EN+ES juntos,
            // executa de forma sequencial (en -> es) e mantém o loader ativo.
            var requested = (langs || []).slice(0);
            if (!requested.length) requested = ['en', 'es'];

            var sequence = Promise.resolve();
            var done = [];
            requested.forEach(function(lang){
                sequence = sequence.then(function(){
                    btnText.textContent = 'Processando ' + lang.toUpperCase() + '...';
                    return postLang(lang).then(function(data){
                        if (!data.success) {
                            var details = data.details ? ('\n\nDetalhes:\n' + data.details) : '';
                            throw new Error((data.message || 'Erro ao gerar traduções.') + details);
                        }
                        done.push(lang);
                    });
                });
            });

            sequence
                .then(function(){
                    btn.disabled = false;
                    btnText.textContent = '✨ Gerar EN + ES (IA)';
                    btnText.style.display = 'inline';
                    btnLoader.style.display = 'none';
                    document.body.style.cursor = '';
                    alert('✅ Traduções geradas: ' + done.map(function(l){return l.toUpperCase();}).join(', '));
                    window.location.reload();
                })
                .catch(function(err){
                    btn.disabled = false;
                    btnText.textContent = '✨ Gerar EN + ES (IA)';
                    btnText.style.display = 'inline';
                    btnLoader.style.display = 'none';
                    document.body.style.cursor = '';
                    alert('❌ ' + err.message);
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            if (window.__wvProvidersValidatedOnce) return;
            if (typeof validarProvedoresEModelos !== 'function') return;
            var autoValidateInput = document.getElementById('llm_auto_validate_on_load');
            if (autoValidateInput && !autoValidateInput.checked) return;
            window.__wvProvidersValidatedOnce = true;
            setTimeout(function () {
                validarProvedoresEModelos();
            }, 400);
        });

        document.addEventListener('DOMContentLoaded', function () {
            var cards = document.querySelectorAll('main.admin-content .card');
            if (!cards.length) return;
            var cardControllers = [];

            function cardStorageKey(card, index) {
                var titleEl = card.querySelector('.card-header h3');
                var title = titleEl ? (titleEl.textContent || '').trim().toLowerCase() : ('card-' + index);
                var normalized = title
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                return 'config_card_collapsed_' + (normalized || ('card-' + index));
            }

            cards.forEach(function (card, index) {
                var header = card.querySelector('.card-header');
                var body = card.querySelector('.card-body');
                if (!header || !body) return;
                if (header.querySelector('.config-card-toggle')) return;

                card.classList.add('config-card-collapsible');

                var toggleBtn = document.createElement('button');
                toggleBtn.type = 'button';
                toggleBtn.className = 'config-card-toggle';
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.title = 'Recolher card';
                toggleBtn.innerHTML = '<i class="ph ph-minus"></i>';

                var key = cardStorageKey(card, index);
                var collapsed = localStorage.getItem(key) === '1';

                function applyState(isCollapsed) {
                    if (isCollapsed) {
                        card.classList.add('config-card-collapsed');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                        toggleBtn.title = 'Expandir card';
                        toggleBtn.innerHTML = '<i class="ph ph-plus"></i>';
                        localStorage.setItem(key, '1');
                    } else {
                        card.classList.remove('config-card-collapsed');
                        toggleBtn.setAttribute('aria-expanded', 'true');
                        toggleBtn.title = 'Recolher card';
                        toggleBtn.innerHTML = '<i class="ph ph-minus"></i>';
                        localStorage.setItem(key, '0');
                    }
                }

                applyState(collapsed);

                toggleBtn.addEventListener('click', function () {
                    applyState(!card.classList.contains('config-card-collapsed'));
                });

                header.appendChild(toggleBtn);
                cardControllers.push({ card: card, applyState: applyState });
            });

            var btnCollapseAll = document.getElementById('btnCollapseAllCards');
            var btnExpandAll = document.getElementById('btnExpandAllCards');

            if (btnCollapseAll) {
                btnCollapseAll.addEventListener('click', function () {
                    cardControllers.forEach(function (ctrl) { ctrl.applyState(true); });
                });
            }

            if (btnExpandAll) {
                btnExpandAll.addEventListener('click', function () {
                    cardControllers.forEach(function (ctrl) { ctrl.applyState(false); });
                });
            }
        });
    </script>
</body>
</html>
