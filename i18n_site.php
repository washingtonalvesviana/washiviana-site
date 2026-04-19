<?php
/**
 * WASHIVIANA PORTFOLIO - Site i18n generator (Gemini)
 * Gera traduções EN/ES para:
 * - UI strings (textos fixos do template)
 * - Configs (configuracoes_i18n: site_subtitulo, home_frase_impacto, mini_bio)
 */

require_once __DIR__ . '/config.php';

if (!isAuthenticated()) {
    jsonResponse(['success' => false, 'message' => 'Não autenticado.'], 401);
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
}

$langs = $_POST['langs'] ?? ['en', 'es'];
if (!is_array($langs)) $langs = ['en', 'es'];
$langs = array_values(array_unique(array_filter(array_map('normalizeLang', $langs), fn($l) => in_array($l, ['pt', 'en', 'es'], true))));
if (!$langs) $langs = ['pt', 'en', 'es'];

$apiKey = getConfig('gemini_api_key');
$model = getConfig('gemini_model') ?: 'gemini-2.0-flash';
// Para UI/config do site, prioriza velocidade/estabilidade (evita 504 do Nginx).
// Se quiser, crie a config `gemini_model_site` no banco e sobrescreva.
$modelSite = getConfig('gemini_model_site') ?: $model;
if ($modelSite === $model && (str_contains($model, 'pro') || str_contains($model, '2.5'))) {
    $modelSite = 'gemini-2.0-flash';
}
if (empty($apiKey)) {
    jsonResponse(['success' => false, 'message' => 'API Key do Gemini não configurada.'], 400);
}

// UI keys used in templates (PT source is the fallback text)
$uiPt = [
    'nav.home' => 'Início',
    'nav.contents' => 'Conteúdos',
    'nav.projects' => 'Projetos',
    'nav.about' => 'Sobre',
    'nav.contact' => 'Contato',
    'hero.cta_contents' => 'Explorar Conteúdos',
    'hero.cta_projects' => 'Ver Projetos',
    'home.section_find' => 'O que você vai encontrar aqui',
    'home.latest' => 'Últimos conteúdos',
    'home.soon' => 'Conteúdos em breve...',
    'home.view_all_contents' => 'Ver todos os conteúdos →',
    'home.about_title' => 'Um pouco sobre mim',
    'home.about_cta' => 'Conheça minha trajetória',
    'home.card_1.title' => 'Automação & IA',
    'home.card_1.subtext' => 'Aplicações reais usando tecnologia para otimizar processos.',
    'home.card_2.title' => 'Tech Insights',
    'home.card_2.subtext' => 'Notícias comentadas e análises sobre tecnologia.',
    'home.card_3.title' => 'Projetos',
    'home.card_3.subtext' => 'Projetos que realizei e participei ao longo da carreira.',
    'home.card_4.title' => 'Design & Experiências Digitais',
    'home.card_4.subtext' => 'VR, 3D e design aplicado em soluções inovadoras.',
    'contents.kicker' => 'News & Artigos',
    'contents.subtitle' => 'Insights sobre tecnologia, IA e automação',
    'contents.page_title' => 'Conteúdos',
    'search.title' => 'Busca: {term}',
    'filters.all' => 'Todos',
    'footer.menu' => 'Menu',
    'footer.rights' => 'Todos os direitos reservados.',
    'footer.admin' => 'Área Administrativa',

    // Detail pages / actions
    'article.all_contents' => 'Todos os Conteúdos',
    'projects.all' => 'Todos os Projetos',
    'projects.page_title_all' => 'Todos os Projetos',
    'projects.count.one' => '{count} projeto encontrado',
    'projects.count.other' => '{count} projetos encontrados',
    'projects.empty_category' => 'Nenhum projeto encontrado nesta categoria.',
    'projects.view_all' => 'Ver Todos',
    'project.visit' => 'Visitar Projeto',
    'project.prev' => 'Projeto Anterior',
    'project.next' => 'Próximo Projeto',
    'actions.read' => 'Ler',
    'actions.view' => 'Ver',

    // Gallery
    'gallery.title' => 'Galeria',
    'gallery.image_alt' => 'Galeria',
    'gallery.backdrop_close' => 'Fechar galeria',
    'gallery.open' => 'Abrir',
    'gallery.close' => 'Fechar',
    'gallery.close_aria' => 'Fechar (Esc)',
    'gallery.prev' => 'Imagem anterior (←)',
    'gallery.next' => 'Próxima imagem (→)',
    'gallery.image_of' => 'Imagem {current} de {total}',

    // About page
    'page.about.title' => 'Sobre',
    'page.about.meta_desc' => 'Conheça mais sobre {name} - {subtitle}',
    'about.photo_alt' => 'Foto de {name}',
    'about.kicker' => 'Sobre Mim',
    'about.p1' => 'Profissional criativo baseado em <strong class="text-neutral-800">Indaiatuba, SP - Brasil</strong>. Minha jornada tem sido moldada por experiências práticas do mundo real, indo além dos limites da teoria acadêmica. Ao longo de mais de uma década de carreira, mergulhei em diversos aspectos de várias disciplinas, forjando um caminho ancorado no conhecimento prático.',
    'about.p2' => 'Com fervor por <strong class="text-neutral-800">criatividade e tecnologia</strong>, explorei os domínios do design, desenvolvimento e inteligência artificial, guiado pela crença de que a verdadeira expertise é aprimorada através da prática e inovação. Meu background único me permitiu dominar um arsenal de habilidades que se entrelaçam perfeitamente, oferecendo valor inigualável para qualquer organização.',
    'about.expertise.title' => 'Áreas de Expertise',
    'about.expertise.ai.title' => 'Inteligência Artificial',
    'about.expertise.ai.desc' => 'Integração de IA em projetos digitais. Aproveitando o poder da inteligência artificial para criar soluções inovadoras e automatizadas.',
    'about.expertise.automation.title' => 'Automação',
    'about.expertise.automation.desc' => 'Automação de processos empresariais com foco em produtividade. Criação de workflows inteligentes que economizam tempo e recursos.',
    'about.expertise.dev.title' => 'Desenvolvimento',
    'about.expertise.dev.desc' => 'Desenvolvimento completo de websites e aplicativos. Do frontend ao backend, criando soluções digitais robustas e escaláveis.',
    'about.expertise.design3d.title' => 'Design & 3D',
    'about.expertise.design3d.desc' => 'Design gráfico, motion design e modelagem 3D. Criação de experiências visuais impactantes que elevam a comunicação.',
    'about.expertise.uxui.title' => 'UX/UI Design',
    'about.expertise.uxui.desc' => 'Design de experiência e interface focado no usuário. Criação de interfaces intuitivas que proporcionam jornadas memoráveis.',
    'about.expertise.cloud.title' => 'Cloud & DevOps',
    'about.expertise.cloud.desc' => 'Arquitetura cloud e práticas DevOps. Infraestrutura escalável e deploy contínuo para aplicações modernas.',
    'about.philosophy.title' => 'Minha Filosofia',
    'about.philosophy.p1' => '<strong class="text-neutral-800">Minha educação tem sido o próprio mercado,</strong> onde adaptabilidade, resolução de problemas e busca incansável pela excelência têm sido meus princípios orientadores. Como resultado, trago para a mesa um conjunto dinâmico de habilidades que engloba IA aplicada, automação, desenvolvimento, design e uma visão estratégica de tecnologia.',
    'about.philosophy.p2' => '<strong class="text-neutral-800">O que realmente me diferencia</strong> é minha capacidade de aplicar esse conhecimento diverso de maneira prática e eficaz. Prospero em derrubar barreiras entre disciplinas, promovendo colaboração e impulsionando inovação a partir de um lugar de compreensão do mundo real.',
    'about.philosophy.quote' => 'Seja revitalizando sua presença digital, implementando soluções de IA de ponta ou revolucionando seus processos com automação, estou aqui para trazer excelência prática para cada empreendimento. Vamos explorar as possibilidades ilimitadas juntos.',
    'about.cta.title' => 'Vamos Trabalhar Juntos?',
    'about.cta.desc' => 'Estou sempre aberto a novos projetos e oportunidades de colaboração. Entre em contato e vamos criar algo incrível.',
    'about.cta.email' => 'Enviar Email',
    'about.cta.whatsapp' => 'WhatsApp',

    // Landings
    'landing.setup_required_title' => 'Configuração Necessária',
    'landing.setup_required_desc' => 'A tabela de artigos ainda não foi criada/configurada.',
    'landing.soon_title' => 'Conteúdos em breve',
    'landing.view_all_contents' => 'Ver Todos os Conteúdos',
    'landing.view_all_projects' => 'Ver Todos os Projetos',
    'landing.automation.title' => 'Automação & IA',
    'landing.automation.desc' => 'Aplicações reais de IA e automação para produtividade, processos e experiências digitais.',
    'landing.automation.kicker' => 'Automação & IA',
    'landing.automation.soon_desc' => 'Novos conteúdos de Automação & IA serão publicados em breve.',
    'landing.tech.title' => 'Tech Insights',
    'landing.tech.desc' => 'Notícias comentadas, análises e tendências de tecnologia — com opinião e contexto.',
    'landing.tech.kicker' => 'Tech Insights',
    'landing.tech.soon_desc' => 'Novos Tech Insights serão publicados em breve.',
    'landing.design.title' => 'Design & Experiências Digitais',
    'landing.design.desc' => 'VR, 3D, UI/UX e experiências interativas — projetos com foco em estética, usabilidade e impacto.',
    'landing.design.kicker' => 'Design',
    'landing.design.empty_title' => 'Nenhum projeto encontrado',
    'landing.design.empty_desc' => 'Em breve mais projetos de design e experiências digitais por aqui.',
];

// Config keys (PT source from configuracoes)
$configKeys = [
    'site_subtitulo',
    'home_frase_impacto',
    'mini_bio',
    // Home cards
    'home_card_1_titulo', 'home_card_1_subtexto',
    'home_card_2_titulo', 'home_card_2_subtexto',
    'home_card_3_titulo', 'home_card_3_subtexto',
    'home_card_4_titulo', 'home_card_4_subtexto',
];
$configPt = [];
foreach ($configKeys as $k) {
    $configPt[$k] = (string)(getConfig($k) ?? '');
}

try {
    // Categorias (PT source)
    $categoriasProjetos = [];
    $categoriasArtigos = [];
    try {
        $stmt = $pdo->query("SELECT id, nome, slug FROM categorias WHERE ativo = true ORDER BY ordem ASC");
        $categoriasProjetos = $stmt->fetchAll();
    } catch (Exception $e) {
        $categoriasProjetos = [];
    }
    try {
        $stmt = $pdo->query("SELECT id, nome, slug, descricao FROM categorias_artigos WHERE ativo = true ORDER BY ordem ASC");
        $categoriasArtigos = $stmt->fetchAll();
    } catch (Exception $e) {
        $categoriasArtigos = [];
    }

    $savedUi = [];
    $savedConfig = [];
    $savedCats = [];

    // Para evitar timeouts (504) quando pedir EN+ES, gera 1 idioma por vez.
    foreach ($langs as $lang) {
        $t0 = microtime(true);

        // 1) UI em chunks para evitar truncamento (MAX_TOKENS / markdown parcial)
        $uiChunks = array_chunk($uiPt, 24, true);
        $uiMerged = [];
        foreach ($uiChunks as $idx => $chunk) {
            $promptUi = "Você é especialista em localização (i18n) e UX writing.\n" .
                "Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" .
                "Não inclua pensamentos/raciocínio.\n\n" .
                "Traduza do PT-BR para {$lang} mantendo o mesmo sentido.\n" .
                "- Para UI: mantenha curto (botões/menus), preserve setas e pontuação.\n" .
                "- Preserve placeholders exatamente como estão: {name}, {subtitle}, {count}, {term}, {current}, {total}.\n" .
                "- Preserve tags HTML já existentes (ex.: <strong class=\"...\">) sem remover/alterar atributos.\n\n" .
                "Retorne neste formato:\n" .
                "{ \"ui\": {\"key\":\"text\"...} }\n\n" .
                "UI (PT-BR):\n" . json_encode($chunk, JSON_UNESCAPED_UNICODE);

            $outUi = callGeminiJsonForSite($apiKey, $modelSite, $promptUi);
            $ui = $outUi['ui'][$lang] ?? ($outUi['ui'] ?? []);
            if (is_array($ui)) {
                foreach ($ui as $k => $v) {
                    if (!is_string($k)) continue;
                    if (!is_string($v) || trim($v) === '') continue;
                    $uiMerged[$k] = trim($v);
                }
            }

            @file_put_contents(
                sys_get_temp_dir() . '/washiviana_i18n_site.log',
                '[' . date('c') . "] lang={$lang} model={$modelSite} ui_chunk=" . ($idx + 1) . '/' . count($uiChunks) . " merged=" . count($uiMerged) . PHP_EOL,
                FILE_APPEND
            );
        }

        // 2) Configs em 1 chamada
        $promptCfg = "Você é especialista em localização (i18n) e UX writing.\n" .
            "Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" .
            "Não inclua pensamentos/raciocínio.\n\n" .
            "Traduza do PT-BR para {$lang} mantendo o mesmo sentido.\n" .
            "- Mantenha tom profissional.\n" .
            "- Preserve placeholders exatamente como estão: {name}, {subtitle}, {count}, {term}, {current}, {total}.\n\n" .
            "Retorne neste formato:\n" .
            "{ \"config\": {\"key\":\"text\"...} }\n\n" .
            "CONFIG (PT-BR):\n" . json_encode($configPt, JSON_UNESCAPED_UNICODE);
        $outCfg = callGeminiJsonForSite($apiKey, $modelSite, $promptCfg);
        $cfg = $outCfg['config'][$lang] ?? ($outCfg['config'] ?? []);

        // Salvar UI
        foreach ($uiPt as $key => $_fallback) {
            $text = $uiMerged[$key] ?? null;
            if (!is_string($text) || trim($text) === '') continue;
            $stmt = $pdo->prepare("
                INSERT INTO ui_strings (chave, lang, texto)
                VALUES (?, ?, ?)
                ON CONFLICT (chave, lang) DO UPDATE SET
                  texto = EXCLUDED.texto,
                  updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $lang, trim($text)]);
            $savedUi[$lang][] = $key;
        }

        // Salvar config
        if (is_array($cfg)) {
            foreach ($configKeys as $key) {
                $val = $cfg[$key] ?? null;
                if (!is_string($val) || trim($val) === '') continue;
                $stmt = $pdo->prepare("
                    INSERT INTO configuracoes_i18n (chave, lang, valor)
                    VALUES (?, ?, ?)
                    ON CONFLICT (chave, lang) DO UPDATE SET
                      valor = EXCLUDED.valor,
                      updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$key, $lang, trim($val)]);
                $savedConfig[$lang][] = $key;
            }
        }

        $elapsedMs = (int)round((microtime(true) - $t0) * 1000);
        @file_put_contents(
            sys_get_temp_dir() . '/washiviana_i18n_site.log',
            '[' . date('c') . "] lang={$lang} model={$modelSite} ms={$elapsedMs} saved_ui=" . count($savedUi[$lang] ?? []) . " saved_cfg=" . count($savedConfig[$lang] ?? []) . PHP_EOL,
            FILE_APPEND
        );

        // 3) Categorias (nome/descricao) para EN/ES
        try {
            if (!empty($categoriasProjetos) || !empty($categoriasArtigos)) {
                $payload = [
                    'categorias_projetos' => array_map(function ($r) {
                        return [
                            'id' => (int)$r['id'],
                            'nome' => (string)($r['nome'] ?? ''),
                            'slug' => (string)($r['slug'] ?? ''),
                        ];
                    }, $categoriasProjetos),
                    'categorias_artigos' => array_map(function ($r) {
                        return [
                            'id' => (int)$r['id'],
                            'nome' => (string)($r['nome'] ?? ''),
                            'descricao' => (string)($r['descricao'] ?? ''),
                            'slug' => (string)($r['slug'] ?? ''),
                        ];
                    }, $categoriasArtigos),
                ];

                $promptCats = "Você é especialista em localização (i18n).\n" .
                    "Responda APENAS com JSON válido (sem markdown, sem texto extra).\n" .
                    "Não inclua pensamentos/raciocínio.\n\n" .
                    "Traduza do PT-BR para {$lang} mantendo o mesmo sentido.\n" .
                    "- NÃO traduza nem altere o campo slug.\n" .
                    "- Retorne neste formato:\n" .
                    "{ \"categorias_projetos\": {\"id\": {\"nome\":\"...\"}}, \"categorias_artigos\": {\"id\": {\"nome\":\"...\",\"descricao\":\"...\"}} }\n\n" .
                    "DADOS (PT-BR):\n" . json_encode($payload, JSON_UNESCAPED_UNICODE);

                $outCats = callGeminiJsonForSite($apiKey, $modelSite, $promptCats);

                $projOut = $outCats['categorias_projetos'] ?? [];
                $artOut = $outCats['categorias_artigos'] ?? [];

                foreach ($categoriasProjetos as $c) {
                    $id = (int)$c['id'];
                    $nome = $projOut[(string)$id]['nome'] ?? null;
                    if (!is_string($nome) || trim($nome) === '') continue;
                    $stmt = $pdo->prepare("
                        INSERT INTO categorias_i18n (categoria_id, lang, nome, slug)
                        VALUES (?, ?, ?, ?)
                        ON CONFLICT (categoria_id, lang) DO UPDATE SET
                          nome = EXCLUDED.nome,
                          slug = EXCLUDED.slug,
                          updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([$id, $lang, trim($nome), (string)$c['slug']]);
                    $savedCats[$lang]['categorias_projetos'][] = $id;
                }

                foreach ($categoriasArtigos as $c) {
                    $id = (int)$c['id'];
                    $nome = $artOut[(string)$id]['nome'] ?? null;
                    $desc = $artOut[(string)$id]['descricao'] ?? null;
                    if (!is_string($nome) || trim($nome) === '') continue;
                    $stmt = $pdo->prepare("
                        INSERT INTO categorias_artigos_i18n (categoria_id, lang, nome, slug, descricao)
                        VALUES (?, ?, ?, ?, ?)
                        ON CONFLICT (categoria_id, lang) DO UPDATE SET
                          nome = EXCLUDED.nome,
                          slug = EXCLUDED.slug,
                          descricao = EXCLUDED.descricao,
                          updated_at = CURRENT_TIMESTAMP
                    ");
                    $stmt->execute([
                        $id,
                        $lang,
                        trim($nome),
                        (string)$c['slug'],
                        is_string($desc) ? trim($desc) : null,
                    ]);
                    $savedCats[$lang]['categorias_artigos'][] = $id;
                }

                @file_put_contents(
                    sys_get_temp_dir() . '/washiviana_i18n_site.log',
                    '[' . date('c') . "] lang={$lang} saved_cats_proj=" . count($savedCats[$lang]['categorias_projetos'] ?? []) . " saved_cats_art=" . count($savedCats[$lang]['categorias_artigos'] ?? []) . PHP_EOL,
                    FILE_APPEND
                );
            }
        } catch (Exception $e) {
            @file_put_contents(
                sys_get_temp_dir() . '/washiviana_i18n_site.log',
                '[' . date('c') . '] categorias error: ' . $e->getMessage() . PHP_EOL,
                FILE_APPEND
            );
        }
    }

    clearUiStringsCache();
    clearConfigI18nCache();

    jsonResponse([
        'success' => true,
        'message' => 'Traduções do site geradas.',
        'saved_ui' => $savedUi,
        'saved_config' => $savedConfig,
        'saved_categories' => $savedCats,
    ]);
} catch (Exception $e) {
    $msg = $e->getMessage();
    @file_put_contents(
        sys_get_temp_dir() . '/washiviana_i18n_site.log',
        '[' . date('c') . '] ' . $msg . PHP_EOL,
        FILE_APPEND
    );
    error_log("i18n_site error: " . $msg);
    jsonResponse(['success' => false, 'message' => 'Erro ao gerar i18n do site.', 'details' => $msg], 500);
}

function extractJsonObjectSite(string $text): ?array {
    $text = trim($text);
    // Remove markdown fences comuns (```json ... ```)
    if (str_starts_with($text, '```')) {
        $text = preg_replace('~^```[a-zA-Z0-9_-]*\\s*~', '', $text) ?? $text;
        $text = preg_replace('~\\s*```$~', '', $text) ?? $text;
        $text = trim($text);
    }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) return $decoded;
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) return null;
    $maybe = substr($text, $start, $end - $start + 1);
    $decoded = json_decode($maybe, true);
    return is_array($decoded) ? $decoded : null;
}

function concatGeminiTextPartsSite(array $candidate): string {
    $parts = $candidate['content']['parts'] ?? [];
    if (!is_array($parts)) return '';
    $out = '';
    foreach ($parts as $p) {
        if (is_array($p) && isset($p['text']) && is_string($p['text'])) $out .= $p['text'];
    }
    return $out;
}

function callGeminiJsonForSite(string $apiKey, string $model, string $prompt): array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);

    // UI do site tem muitos keys: aumenta para reduzir chance de truncamento.
    $maxTokens = 4096;
    $thinkingConfig = null;
    if (str_contains($model, 'gemini-2.5')) {
        $maxTokens = 6144;
        $thinkingConfig = ['thinkingBudget' => 512];
    }

    $base = [
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature' => 0.2,
        ],
    ];
    if (is_array($thinkingConfig)) $base['generationConfig']['thinkingConfig'] = $thinkingConfig;

    $attempts = [
        $base + ['generationConfig' => $base['generationConfig'] + ['responseMimeType' => 'application/json']],
        $base,
    ];

    $lastError = null;
    foreach ($attempts as $data) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($data),
            // Mantém abaixo de timeouts comuns do Nginx/PHP-FPM
            CURLOPT_TIMEOUT => 50,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            $lastError = "cURL error: " . $err;
            continue;
        }
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($raw, true);
            $errMsg = is_array($decoded) ? ($decoded['error']['message'] ?? $raw) : $raw;
            $lastError = "Gemini HTTP {$code}: " . $errMsg;
            continue;
        }

        $json = json_decode($raw, true);
        $candidate = $json['candidates'][0] ?? null;
        $text = is_array($candidate) ? concatGeminiTextPartsSite($candidate) : '';
        if ($text === '' && is_array($candidate)) {
            $finishReason = $candidate['finishReason'] ?? '';
            $blockReason = $json['promptFeedback']['blockReason'] ?? '';
            $lastError = "Gemini retornou resposta sem texto. finishReason={$finishReason} blockReason={$blockReason}";
            continue;
        }

        $parsed = extractJsonObjectSite($text);
        if (is_array($parsed)) return $parsed;
        $lastError = "Resposta não-JSON do Gemini. Preview: " . mb_substr(trim($text), 0, 600, 'UTF-8');
    }

    throw new Exception($lastError ?: 'Erro desconhecido ao chamar Gemini.');
}
