<?php
/**
 * WASHIVIANA PORTFOLIO - Detalhes do Artigo
 * Layout: Minimal Elegante (Estilo A)
 */
require_once __DIR__ . '/api/config.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: ' . routeConteudos());
    exit;
}

// Verificar se tabela existe (PostgreSQL)
$tabelaExiste = false;
try {
    $check = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'artigos')");
    $result = $check->fetch();
    $tabelaExiste = $result['exists'] ?? false;
} catch (Exception $e) {
    $tabelaExiste = false;
}

if (!$tabelaExiste) {
    header('Location: ' . routeConteudos());
    exit;
}

// Buscar artigo com i18n (fallback para PT)
$lang = CURRENT_LANG;
$artigo = null;

if ($lang !== 'pt') {
    try {
        $stmt = $pdo->prepare("
        SELECT a.*,
               t.titulo AS i18n_titulo,
               t.slug AS i18n_slug,
               t.resumo AS i18n_resumo,
               t.conteudo AS i18n_conteudo,
               t.meta_title,
               t.meta_description,
               t.og_title,
               t.og_description,
               t.og_image,
               t.keywords,
               t.schema_jsonld
        FROM artigos_i18n t
        JOIN artigos a ON a.id = t.artigo_id
        WHERE t.lang = ? AND t.slug = ? AND a.ativo = true
        LIMIT 1
    ");
        $stmt->execute([$lang, $slug]);
        $artigo = $stmt->fetch();
    } catch (Exception $e) {
        // artigos_i18n might not exist (older schema) or another DB error occurred. Fall through to try main artigos table below.
        $artigo = null;
    }
}

if (!$artigo) {
    try {
        $stmt = $pdo->prepare("
        SELECT a.*,
               t.meta_title,
               t.meta_description,
               t.og_title,
               t.og_description,
               t.og_image,
               t.keywords,
               t.schema_jsonld
        FROM artigos a
        LEFT JOIN artigos_i18n t ON t.artigo_id = a.id AND t.lang = 'pt'
        WHERE a.slug = ? AND a.ativo = true
        LIMIT 1
    ");
        $stmt->execute([$slug]);
        $artigo = $stmt->fetch();
    } catch (Exception $e) {
        // Fallback: artigos_i18n missing or query failed — try selecting only from artigos
        try {
            $stmt2 = $pdo->prepare("SELECT * FROM artigos WHERE slug = ? AND ativo = true LIMIT 1");
            $stmt2->execute([$slug]);
            $artigo = $stmt2->fetch();
        } catch (Exception $e2) {
            $artigo = null;
        }
    }
}

if (!$artigo) {
    header('Location: ' . routeConteudos());
    exit;
}

$artigoTitulo = $artigo['i18n_titulo'] ?? $artigo['titulo'];
$artigoResumo = $artigo['i18n_resumo'] ?? ($artigo['resumo'] ?? '');
$artigoConteudo = $artigo['i18n_conteudo'] ?? ($artigo['conteudo'] ?? '');
$slugAtual = $artigo['i18n_slug'] ?? $artigo['slug'];

// Usar campos localizados no restante do template
$artigo['titulo'] = $artigoTitulo;
$artigo['resumo'] = $artigoResumo;
$artigo['conteudo'] = $artigoConteudo;
$artigo['slug'] = $slugAtual;

// Slugs por idioma para hreflang/canonical
$slugs = ['pt' => $artigo['slug']];
try {
    $stmt = $pdo->prepare("SELECT lang, slug FROM artigos_i18n WHERE artigo_id = ? AND slug IS NOT NULL");
    $stmt->execute([$artigo['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $slugs[$row['lang']] = $row['slug'];
    }
} catch (Exception $e) {
    // ignore
}

$canonicalSlug = $slugs[$lang] ?? $slugs['pt'];
$canonicalUrl = BASE_URL . routeArtigo($canonicalSlug, $lang);
$seoTitle = !empty($artigo['meta_title']) ? $artigo['meta_title'] : $artigoTitulo;
$seoDescription = !empty($artigo['meta_description'])
    ? $artigo['meta_description']
    : limitText(strip_tags($artigoResumo ?: $artigoConteudo), 160);
$ogTitle = !empty($artigo['og_title']) ? $artigo['og_title'] : $seoTitle;
$ogDescription = !empty($artigo['og_description']) ? $artigo['og_description'] : $seoDescription;

$shareImageUrl = uploadFileUrl($artigo['imagem_capa'] ?? null)
    ?: uploadFileUrl($artigo['imagem_1x1'] ?? null)
    ?: uploadFileUrl($artigo['imagem_principal'] ?? null);
$shareImagePath = uploadFilePath($artigo['imagem_capa'] ?? null);
if (!$shareImagePath || !is_file($shareImagePath)) {
    $shareImagePath = uploadFilePath($artigo['imagem_1x1'] ?? null);
}
if (!$shareImagePath || !is_file($shareImagePath)) {
    $shareImagePath = uploadFilePath($artigo['imagem_principal'] ?? null);
}
$shareImageWidth = 1200;
$shareImageHeight = 630;
if ($shareImageUrl && $shareImagePath && is_file($shareImagePath)) {
    $imageInfo = getimagesize($shareImagePath);
    if ($imageInfo !== false) {
        $shareImageWidth = $imageInfo[0];
        $shareImageHeight = $imageInfo[1];
    }
}

// Incrementar visualizações (se a coluna existir)
try {
    $pdo->prepare("UPDATE artigos SET visualizacoes = visualizacoes + 1 WHERE id = ?")->execute([$artigo['id']]);
} catch (Exception $e) {
    // Coluna não existe, ignorar
}

// Buscar artigo anterior e próximo
$stmt = $pdo->prepare("SELECT slug, titulo FROM artigos WHERE id < ? AND ativo = true ORDER BY id DESC LIMIT 1");
$stmt->execute([$artigo['id']]);
$anterior = $stmt->fetch();

$stmt = $pdo->prepare("SELECT slug, titulo FROM artigos WHERE id > ? AND ativo = true ORDER BY id ASC LIMIT 1");
$stmt->execute([$artigo['id']]);
$proximo = $stmt->fetch();

// Configurações
$siteTitulo = getConfig('site_titulo') ?: 'Washington Viana';
$siteEmail = getConfig('site_email') ?: 'contato@washiviana.com';
$siteLinkedin = getConfig('site_linkedin') ?: 'https://linkedin.com/in/washingtonviana';
$siteInstagram = getConfig('site_instagram') ?: 'https://instagram.com/washiviana';
$siteGithub = getConfig('site_github') ?: 'https://github.com/washiviana';
$qsLang = '';
if (!empty($_GET)) {
    $params = $_GET;
    unset($params['slug']);
    if (!empty($params)) {
        $qsLang = '?' . http_build_query($params);
    }
} elseif (!empty($_SERVER['QUERY_STRING'])) {
    $qsLang = '?' . $_SERVER['QUERY_STRING'];
}

$schemaData = null;
if (!empty($artigo['schema_jsonld'])) {
    $decoded = json_decode($artigo['schema_jsonld'], true);
    if (is_array($decoded)) $schemaData = $decoded;
}
if (!$schemaData) {
    $schemaData = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $artigoTitulo,
        'description' => $seoDescription,
        'author' => [
            '@type' => 'Person',
            'name' => $artigo['autor'] ?? $siteTitulo,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $siteTitulo,
        ],
        'url' => $canonicalUrl,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl,
        ],
        'datePublished' => date('c', strtotime($artigo['created_at'] ?? $artigo['data_publicacao'] ?? 'now')),
    ];
    if (!empty($artigo['updated_at'])) {
        $schemaData['dateModified'] = date('c', strtotime($artigo['updated_at']));
    }
    if ($shareImageUrl) {
        $schemaData['image'] = [$shareImageUrl];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(htmlLang(CURRENT_LANG)); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seoTitle); ?> - <?php echo htmlspecialchars($siteTitulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . routeArtigo($slugs['pt'], 'pt')); ?>">
    <?php if (!empty($slugs['en'])): ?>
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . routeArtigo($slugs['en'], 'en')); ?>">
    <?php endif; ?>
    <?php if (!empty($slugs['es'])): ?>
    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . routeArtigo($slugs['es'], 'es')); ?>">
    <?php endif; ?>
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . routeArtigo($slugs['pt'], 'pt')); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    
    <!-- Open Graph / LinkedIn -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitulo); ?>">
    <?php if ($shareImageUrl): ?>
    <meta property="og:image" content="<?php echo $shareImageUrl; ?>">
    <meta property="og:image:width" content="<?php echo $shareImageWidth; ?>">
    <meta property="og:image:height" content="<?php echo $shareImageHeight; ?>">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($artigoTitulo); ?>">
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <?php if ($shareImageUrl): ?>
    <meta name="twitter:image" content="<?php echo $shareImageUrl; ?>">
    <?php endif; ?>

    
    
    <!-- Article Meta Tags -->
    <meta property="article:author" content="<?php echo htmlspecialchars($artigo['autor'] ?? 'Washington Viana'); ?>">
    <meta property="article:published_time" content="<?php echo date('c', strtotime($artigo['created_at'] ?? $artigo['data_publicacao'] ?? 'now')); ?>">
    <?php if (!empty($artigo['updated_at'])): ?>
    <meta property="article:modified_time" content="<?php echo date('c', strtotime($artigo['updated_at'])); ?>">
    <?php endif; ?>

    <?php if (!empty($schemaData)): ?>
    <script type="application/ld+json"><?php echo json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php endif; ?>

    <!-- Tailwind CSS (build local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . '/assets/css/tailwind.min.css?v=' . assetVersion('assets/css/tailwind.min.css')); ?>">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap" media="print" onload="this.media='all'">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap">
    </noscript>
    

    
    <style>
        /* Phosphor Icons are loaded via JS, no extra CSS needed for basic usage */
        
        /* Estilos para o conteúdo do artigo */
        .article-content h1, .article-content h2, .article-content h3, .article-content h4 {
            font-weight: 700;
            color: #1F2937;
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        .article-content h2 { font-size: 1.5rem; }
        .article-content h3 { font-size: 1.25rem; }
        .article-content p { margin-bottom: 1em; line-height: 1.8; }
        .article-content ul, .article-content ol { margin: 1em 0; padding-left: 1.5em; }
        .article-content li { margin-bottom: 0.5em; }
        .article-content a { color: #607AFB; text-decoration: underline; }
        .article-content blockquote {
            border-left: 4px solid #607AFB;
            padding-left: 1em;
            margin: 1.5em 0;
            font-style: italic;
            color: #4B5563;
        }
        .article-content code {
            background: #F3F4F6;
            padding: 0.2em 0.4em;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .article-content pre {
            background: #1F2937;
            color: #F3F4F6;
            padding: 1em;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1em 0;
        }
        .article-content pre code {
            background: none;
            padding: 0;
        }
    </style>
</head>
<body class="font-display">
    <div class="relative flex h-auto min-h-screen w-full flex-col bg-background-light overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            <div class="flex flex-1 justify-center py-5">
                <div class="layout-content-container flex flex-col w-full max-w-[960px] flex-1 px-4 sm:px-10">
                    
                    <!-- ========================================
                         HEADER
                    ========================================= -->
                    <header class="sticky top-5 z-50 flex items-center justify-between whitespace-nowrap border border-solid border-neutral-200 bg-white/80 px-4 sm:px-8 py-3 rounded-xl backdrop-blur-md shadow-sm">
	                        <a href="<?php echo routeHome(); ?>" class="flex items-center gap-3 text-neutral-800 hover:opacity-80 transition-opacity">
	                            <img src="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/logo_washiviana_60px_h.png'); ?>"
	                                 alt="<?php echo htmlspecialchars($siteTitulo); ?>"
	                                 width="125" height="60"
	                                 class="h-7 w-auto">
	                            <h2 class="text-neutral-800 text-lg font-bold leading-tight tracking-[-0.015em]"><?php echo htmlspecialchars($siteTitulo); ?></h2>
	                        </a>
                        
                        <nav class="hidden md:flex flex-1 justify-end gap-8">
                            <div class="flex items-center gap-8">
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeHome(); ?>"><?php echo htmlspecialchars(t('nav.home', 'Início')); ?></a>
                                <a class="text-primary text-sm font-bold leading-normal" href="<?php echo routeConteudos(); ?>"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                            </div>
	                            
	                            <!-- Idiomas -->
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    if (empty($slugs[$lc])) {
	                                        continue;
	                                    }
	                                    $langLinks[$lc] = ['label' => $label, 'href' => routeArtigo($slugs[$lc], $lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'right');
	                            ?>

                        </nav>
                        
                        <button id="mobile-menu-btn" class="md:hidden flex items-center justify-center h-10 w-10 text-neutral-700 hover:text-primary transition-colors">
                            <i class="ph ph-list text-2xl"></i>
                        </button>
                    </header>
                    
                    <!-- Menu Mobile -->
                    <nav id="mobile-menu" class="hidden md:hidden fixed inset-0 z-40 bg-white/95 backdrop-blur-lg flex flex-col items-center justify-center gap-6 text-lg">
                        <button id="mobile-menu-close" class="absolute top-8 right-8 text-neutral-700 hover:text-primary">
                            <i class="ph ph-x text-3xl"></i>
                        </button>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeHome(); ?>"><?php echo htmlspecialchars(t('nav.home', 'Início')); ?></a>
                        <a class="text-primary font-bold" href="<?php echo routeConteudos(); ?>"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                        <div class="pt-2">
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    if (empty($slugs[$lc])) {
	                                        continue;
	                                    }
	                                    $langLinks[$lc] = ['label' => $label, 'href' => routeArtigo($slugs[$lc], $lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'center');
	                            ?>
	                        </div>
	                    </nav>
                    
                    <!-- ========================================
                         MAIN CONTENT
                    ========================================= -->
                    <main class="flex flex-col gap-8 mt-8 md:mt-12">
                        
                        <!-- ========================================
                             IMAGEM DE CAPA
                        ========================================= -->
                        <?php 
                        $imagemCapaUrl = uploadFileUrl($artigo['imagem_capa'] ?? null)
                            ?: uploadFileUrl($artigo['imagem_1x1'] ?? null)
                            ?: uploadFileUrl($artigo['imagem_principal'] ?? null);
                        if ($imagemCapaUrl): ?>
                        <section class="w-full rounded-xl overflow-hidden shadow-lg">
                            <img src="<?php echo htmlspecialchars($imagemCapaUrl); ?>" 
                                 alt="<?php echo htmlspecialchars($artigo['titulo']); ?>"
                                 class="w-full h-auto max-h-[500px] object-cover">
                        </section>
                        <?php else: ?>
                        <section class="w-full rounded-xl overflow-hidden shadow-lg bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center min-h-[220px]">
                            <i class="ph ph-article text-6xl text-primary/40"></i>
                        </section>
                        <?php endif; ?>
                        
                        <!-- ========================================
                             ARTIGO
                        ========================================= -->
                        <article class="px-4">
                            <!-- Breadcrumb -->
                            <nav class="flex items-center gap-2 text-sm text-neutral-500 mb-6">
                                <a href="<?php echo routeConteudos(); ?>" class="hover:text-primary transition-colors"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
                                <i class="ph ph-caret-right text-base"></i>
                                <span class="text-neutral-700 font-medium"><?php echo limitText(htmlspecialchars($artigo['titulo']), 40); ?></span>
                            </nav>
                            
                            <!-- Cabeçalho do Artigo -->
                            <header class="mb-8">
                                <?php if (!empty($artigo['categorias_nomes'] ?? '')): ?>
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <?php foreach (explode(', ', $artigo['categorias_nomes']) as $cat): ?>
                                    <span class="px-3 py-1 bg-primary/10 text-primary text-xs font-bold uppercase rounded-full">
                                        <?php echo htmlspecialchars(trim($cat)); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <h1 class="text-neutral-800 text-3xl md:text-4xl font-black leading-tight tracking-[-0.02em] mb-4">
                                    <?php echo htmlspecialchars($artigo['titulo']); ?>
                                </h1>
                                
                                <!-- Meta -->
                                <div class="flex flex-wrap items-center gap-4 text-sm text-neutral-500">
                                    <span class="flex items-center gap-1">
                                        <i class="ph ph-calendar-blank text-base"></i>
                                        <?php echo formatDate($artigo['created_at'] ?? date('Y-m-d'), 'd \d\e F \d\e Y'); ?>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <i class="ph ph-user text-base"></i>
                                        <?php echo htmlspecialchars($artigo['autor'] ?? 'Washington Viana'); ?>
                                    </span>
                                    <?php if (isset($artigo['visualizacoes'])): ?>
                                    <span class="flex items-center gap-1">
                                        <i class="ph ph-eye text-base"></i>
                                        <?php echo number_format($artigo['visualizacoes']); ?> visualizações
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($artigo['fonte_ia'] ?? '')): ?>
                                    <span class="flex items-center gap-1 text-primary bg-primary/10 px-2 py-1 rounded-full">
                                        <i class="ph ph-robot text-sm"></i>
                                        Gerado por IA
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </header>
                            
                            <!-- Resumo -->
                            <?php if (!empty($artigo['resumo'] ?? '')): ?>
                            <div class="bg-neutral-50 border-l-4 border-primary p-5 rounded-r-lg mb-8">
                                <p class="text-neutral-700 text-lg leading-relaxed italic">
                                    <?php echo htmlspecialchars($artigo['resumo']); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Conteúdo -->
                            <div class="article-content prose prose-lg max-w-none text-neutral-700">
                                <?php 
                                $conteudo = $artigo['conteudo'] ?? '';
                                // Se não tiver tags HTML, converter texto puro em parágrafos
                                if (strip_tags($conteudo) === $conteudo) {
                                    // Converter quebras de linha duplas em parágrafos
                                    $paragrafos = preg_split('/\n\s*\n/', $conteudo);
                                    $conteudo = '';
                                    foreach ($paragrafos as $p) {
                                        $p = trim($p);
                                        if (!empty($p)) {
                                            // Converter quebras simples em <br>
                                            $p = nl2br(htmlspecialchars($p));
                                            $conteudo .= "<p class=\"mb-4 leading-relaxed\">$p</p>\n";
                                        }
                                    }
                                }
                                echo $conteudo;
                                ?>
                            </div>
                            
                            <!-- Tags -->
                            <?php if (!empty($artigo['tags'] ?? '')): ?>
                            <div class="flex flex-wrap gap-2 mt-8 pt-6 border-t border-neutral-200">
                                <span class="text-neutral-500 text-sm">Tags:</span>
                                <?php foreach (explode(',', $artigo['tags']) as $tag): ?>
                                <a href="conteudos.php?tag=<?php echo urlencode(trim($tag)); ?>" 
                                   class="px-3 py-1 bg-neutral-100 text-neutral-600 text-sm rounded-full hover:bg-primary hover:text-white transition-colors">
                                    #<?php echo htmlspecialchars(trim($tag)); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Navegação entre artigos -->
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-12 pt-8 border-t border-neutral-200">
                                <?php if ($anterior): ?>
                                <a href="<?php echo routeArtigo($anterior['slug']); ?>" 
                                   class="flex items-center gap-2 px-4 py-3 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors group w-full sm:w-auto">
                                    <i class="ph ph-arrow-left text-xl group-hover:-translate-x-1 transition-transform"></i>
                                    <span class="font-medium">Artigo Anterior</span>
                                </a>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>
                                
                                <a href="<?php echo routeConteudos(); ?>" 
                                   class="flex items-center gap-2 px-4 py-3 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-neutral-200 transition-colors">
                                    <i class="ph ph-article text-xl"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars(t('article.all_contents', 'Todos os Conteúdos')); ?></span>
                                </a>
                                
                                <?php if ($proximo): ?>
                                <a href="<?php echo routeArtigo($proximo['slug']); ?>" 
                                   class="flex items-center gap-2 px-4 py-3 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors group w-full sm:w-auto justify-end">
                                    <span class="font-medium">Próximo Artigo</span>
                                    <i class="ph ph-arrow-right text-xl group-hover:translate-x-1 transition-transform"></i>
                                </a>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>
                            </div>
                        </article>
                        
                    </main>
                    
                    <?php require __DIR__ . '/includes/site-footer.php'; ?>
                    
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
        }
        
        if (mobileMenuClose && mobileMenu) {
            mobileMenuClose.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
                document.body.style.overflow = '';
            });
            
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    document.body.style.overflow = '';
                });
            });
        }
    </script>
</body>
</html>
