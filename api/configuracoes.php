<?php
/**
 * WASHIVIANA PORTFOLIO - API de Configurações
 * Salvar e atualizar configurações do site
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autorizado'], 401);
}

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido'], 405);
}

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
}

try {
    // Lista de configurações permitidas
    $allowedConfigs = [
        'site_titulo',
        'site_subtitulo',
        'home_frase_impacto',
        // Home cards (4 pilares)
        'home_card_1_icon',
        'home_card_1_titulo',
        'home_card_1_subtexto',
        'home_card_1_link',
        'home_card_2_icon',
        'home_card_2_titulo',
        'home_card_2_subtexto',
        'home_card_2_link',
        'home_card_3_icon',
        'home_card_3_titulo',
        'home_card_3_subtexto',
        'home_card_3_link',
        'home_card_4_icon',
        'home_card_4_titulo',
        'home_card_4_subtexto',
        'home_card_4_link',
        'site_email',
        'site_telefone',
        'site_linkedin',
        'site_instagram',
        'site_github',
        'mini_bio',
        'llm_text_provider',
        'llm_image_provider',
        'llm_video_provider',
        'llm_text_model',
        'llm_image_model',
        'llm_video_model',
        'llm_auto_validate_on_load',
        'gemini_api_key',
        'gemini_model',
        'gemini_image_model',
        'gemini_max_tokens',
        'deepseek_api_key',
        'openrouter_api_key',
        'anthropic_api_key',
        'ollama_base_url',
        'ollama_api_key',
        'ia_instrucoes',
        'ia_instrucoes_projeto',
        'ia_instrucoes_linkedin',
        // Notificações (email, Sentry)
        'notify_email',
        'sentry_dsn',
        // Manter compatibilidade com OpenAI (caso volte a usar)
        'openai_api_key',
        'openai_model',
        'openai_max_tokens'
    ];
    
    $updated = 0;
    
    foreach ($allowedConfigs as $key) {
        if (isset($_POST[$key])) {
            $value = trim($_POST[$key]);
            setConfig($key, $value);
            $updated++;
        }
    }

    // Compatibilidade retroativa: quando Gemini estiver selecionado, refletir modelos legados
    $textProvider = trim($_POST['llm_text_provider'] ?? getConfig('llm_text_provider') ?? 'gemini');
    $imageProvider = trim($_POST['llm_image_provider'] ?? getConfig('llm_image_provider') ?? 'gemini');
    $llmTextModel = trim($_POST['llm_text_model'] ?? '');
    $llmImageModel = trim($_POST['llm_image_model'] ?? '');

    if ($textProvider === 'gemini' && $llmTextModel !== '') {
        setConfig('gemini_model', $llmTextModel);
    }
    if ($imageProvider === 'gemini' && $llmImageModel !== '') {
        setConfig('gemini_image_model', $llmImageModel);
    }
    
    if ($updated > 0) {
        // Limpar cache de sessão
        if (isset($_SESSION['config'])) {
            unset($_SESSION['config']);
        }
        
        jsonResponse([
            'success' => true, 
            'message' => "Configurações salvas com sucesso! ({$updated} campos atualizados)"
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Nenhuma configuração para salvar']);
    }
    
} catch (Exception $e) {
    error_log("Config API Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Erro ao salvar configurações'], 500);
}

