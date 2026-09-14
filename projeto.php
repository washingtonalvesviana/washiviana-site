<?php
/**
 * WASHIVIANA PORTFOLIO - Detalhes do Projeto
 * Layout: Minimal Elegante (Estilo A)
 */
require_once __DIR__ . '/api/config.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: ' . routeProjetos());
    exit;
}

// Buscar projeto com i18n (fallback para PT)
$lang = CURRENT_LANG;
$projeto = null;

if ($lang !== 'pt') {
    try {
        $stmt = $pdo->prepare("
        SELECT p.*,
               COALESCE(ci.nome, c.nome) as categoria_nome,
               c.slug as categoria_slug,
               t.titulo AS i18n_titulo,
               t.slug AS i18n_slug,
               t.descricao AS i18n_descricao,
               t.meta_title,
               t.meta_description,
               t.og_title,
               t.og_description,
               t.og_image,
               t.keywords,
               t.schema_jsonld
        FROM projetos_i18n t
        JOIN projetos p ON p.id = t.projeto_id
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN categorias_i18n ci ON ci.categoria_id = c.id AND ci.lang = ?
        WHERE t.lang = ? AND t.slug = ? AND p.ativo = true
        LIMIT 1
    ");
        $stmt->execute([$lang, $lang, $slug]);
        $projeto = $stmt->fetch();
    } catch (Exception $e) {
        // projetos_i18n or categorias_i18n may not exist — fallback to simple select below
        $projeto = null;
    }
}

if (!$projeto) {
    try {
        $stmt = $pdo->prepare("
        SELECT p.*,
               COALESCE(ci.nome, c.nome) as categoria_nome,
               c.slug as categoria_slug,
               t.meta_title,
               t.meta_description,
               t.og_title,
               t.og_description,
               t.og_image,
               t.keywords,
               t.schema_jsonld
        FROM projetos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        LEFT JOIN categorias_i18n ci ON ci.categoria_id = c.id AND ci.lang = ?
        LEFT JOIN projetos_i18n t ON t.projeto_id = p.id AND t.lang = 'pt'
        WHERE p.slug = ? AND p.ativo = true
        LIMIT 1
    ");
        $stmt->execute([$lang, $slug]);
        $projeto = $stmt->fetch();
    } catch (Exception $e) {
        // Fallback: categorias_i18n or projetos_i18n might not exist — try simpler query joining only categorias
        try {
            $stmt2 = $pdo->prepare("
            SELECT p.*,
                   c.nome as categoria_nome,
                   c.slug as categoria_slug
            FROM projetos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.slug = ? AND p.ativo = true
            LIMIT 1
        ");
            $stmt2->execute([$slug]);
            $projeto = $stmt2->fetch();
        } catch (Exception $e2) {
            // Final fallback: select only from projetos
            try {
                $stmt3 = $pdo->prepare("SELECT * FROM projetos WHERE slug = ? AND ativo = true LIMIT 1");
                $stmt3->execute([$slug]);
                $projeto = $stmt3->fetch();
            } catch (Exception $e3) {
                $projeto = null;
            }
        }
    }
}

if (!$projeto) {
    header('Location: ' . routeProjetos());
    exit;
}

$projetoTitulo = $projeto['i18n_titulo'] ?? $projeto['titulo'];
$projetoDescricao = $projeto['i18n_descricao'] ?? ($projeto['descricao'] ?? '');
$slugAtual = $projeto['i18n_slug'] ?? $projeto['slug'];

// Usar campos localizados no restante do template
$projeto['titulo'] = $projetoTitulo;
$projeto['descricao'] = $projetoDescricao;
$projeto['slug'] = $slugAtual;

// Slugs por idioma para hreflang/canonical
$slugs = ['pt' => $projeto['slug']];
try {
    $stmt = $pdo->prepare("SELECT lang, slug FROM projetos_i18n WHERE projeto_id = ? AND slug IS NOT NULL");
    $stmt->execute([$projeto['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $slugs[$row['lang']] = $row['slug'];
    }
} catch (Exception $e) {
    // ignore
}

$canonicalSlug = $slugs[$lang] ?? $slugs['pt'];
$canonicalUrl = BASE_URL . routeProjeto($canonicalSlug, $lang);
$seoTitle = !empty($projeto['meta_title']) ? $projeto['meta_title'] : $projetoTitulo;
$seoDescription = !empty($projeto['meta_description'])
    ? $projeto['meta_description']
    : limitText(strip_tags($projetoDescricao), 160);
$ogTitle = !empty($projeto['og_title']) ? $projeto['og_title'] : $seoTitle;
$ogDescription = !empty($projeto['og_description']) ? $projeto['og_description'] : $seoDescription;

// Processar midia com validacao de existencia em uploads
$imagemPrincipalFile = $projeto['imagem_principal'] ?? null;
$imagemPrincipalUrl = uploadFileUrl($imagemPrincipalFile);

$galeriaRaw = json_decode($projeto['imagens_galeria'], true) ?: [];
$galeriaImagens = [];
$galeriaVideos = [];
foreach ($galeriaRaw as $item) {
    $mediaUrl = uploadFileUrl($item);
    if (!$mediaUrl) {
        continue;
    }

    if (isVideoFilename($item)) {
        $galeriaVideos[] = [
            'file' => $item,
            'url' => $mediaUrl,
        ];
    } else {
        $galeriaImagens[] = [
            'file' => $item,
            'url' => $mediaUrl,
        ];
    }
}
$galeriaMediaTotal = count($galeriaImagens) + count($galeriaVideos);

$shareImageFile = null;
$shareImageUrl = null;
if (!empty($imagemPrincipalFile) && !isVideoFilename($imagemPrincipalFile) && $imagemPrincipalUrl) {
    $shareImageFile = normalizeUploadFilename($imagemPrincipalFile);
    $shareImageUrl = $imagemPrincipalUrl;
} elseif (!empty($galeriaImagens)) {
    $shareImageFile = normalizeUploadFilename($galeriaImagens[0]['file']);
    $shareImageUrl = $galeriaImagens[0]['url'];
}
$shareImagePath = $shareImageFile ? uploadFilePath($shareImageFile) : null;
$shareImageWidth = 1200;
$shareImageHeight = 630;
if ($shareImagePath && is_file($shareImagePath)) {
    $imageInfo = getimagesize($shareImagePath);
    if ($imageInfo !== false) {
        $shareImageWidth = $imageInfo[0];
        $shareImageHeight = $imageInfo[1];
    }
}

// Buscar projeto anterior e próximo
$stmt = $pdo->prepare("SELECT slug, titulo FROM projetos WHERE id < ? AND ativo = true ORDER BY id DESC LIMIT 1");
$stmt->execute([$projeto['id']]);
$anterior = $stmt->fetch();

$stmt = $pdo->prepare("SELECT slug, titulo FROM projetos WHERE id > ? AND ativo = true ORDER BY id ASC LIMIT 1");
$stmt->execute([$projeto['id']]);
$proximo = $stmt->fetch();

// Configurações
$siteTitulo = getConfig('site_titulo') ?: 'Washington Viana';
$siteEmail = getConfig('site_email') ?: 'contato@washiviana.com';
$siteLinkedin = getConfig('site_linkedin') ?: 'https://linkedin.com/in/washingtonviana';
$siteInstagram = getConfig('site_instagram') ?: 'https://instagram.com/washiviana';
$siteTelefone = getConfig('site_telefone') ?: '+55 19 9 99422907';
$siteWhatsappDigits = preg_replace('/\D+/', '', (string)$siteTelefone);
$siteWhatsappLink = $siteWhatsappDigits ? ('https://wa.me/' . $siteWhatsappDigits) : '#';
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
if (!empty($projeto['schema_jsonld'])) {
    $decoded = json_decode($projeto['schema_jsonld'], true);
    if (is_array($decoded)) $schemaData = $decoded;
}
if (!$schemaData) {
    $schemaData = [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        'name' => $projetoTitulo,
        'description' => $seoDescription,
        'author' => [
            '@type' => 'Person',
            'name' => $siteTitulo,
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
    ];
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
    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . routeProjeto($slugs['pt'], 'pt')); ?>">
    <?php if (!empty($slugs['en'])): ?>
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . routeProjeto($slugs['en'], 'en')); ?>">
    <?php endif; ?>
    <?php if (!empty($slugs['es'])): ?>
    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . routeProjeto($slugs['es'], 'es')); ?>">
    <?php endif; ?>
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . routeProjeto($slugs['pt'], 'pt')); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">

    <!-- Open Graph / Twitter -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitulo); ?>">
    <?php if ($shareImageUrl): ?>
    <meta property="og:image" content="<?php echo $shareImageUrl; ?>">
    <meta property="og:image:width" content="<?php echo $shareImageWidth; ?>">
    <meta property="og:image:height" content="<?php echo $shareImageHeight; ?>">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($projetoTitulo); ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
    <?php if ($shareImageUrl): ?>
    <meta name="twitter:image" content="<?php echo $shareImageUrl; ?>">
    <?php endif; ?>
    
    <!-- Tailwind CSS (build local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . '/assets/css/tailwind.min.css?v=' . assetVersion('assets/css/tailwind.min.css')); ?>">
    
	    <!-- Fonts -->
	    <link rel="preconnect" href="https://fonts.googleapis.com">
	    <link rel="dns-prefetch" href="//fonts.googleapis.com">
	    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	    <link rel="dns-prefetch" href="//fonts.gstatic.com">
	    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap" media="print" onload="this.media='all'">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap">
    </noscript>
    

    
    <style>
        /* Phosphor Icons are loaded via JS, no extra CSS needed for basic usage */
    </style>
    <?php if (!empty($schemaData)): ?>
    <script type="application/ld+json"><?php echo json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php endif; ?>
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
	                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeConteudos(); ?>"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
	                                <a class="text-primary text-sm font-bold leading-normal" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
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
		                                    $langLinks[$lc] = ['label' => $label, 'href' => routeProjeto($slugs[$lc], $lc) . $qsLang];
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
	                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeConteudos(); ?>"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
	                        <a class="text-primary font-bold" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
	                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
	                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
		                        <div class="pt-2">
		                            <?php
		                                $langLinks = [];
		                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
		                                    if (empty($slugs[$lc])) {
		                                        continue;
		                                    }
		                                    $langLinks[$lc] = ['label' => $label, 'href' => routeProjeto($slugs[$lc], $lc) . $qsLang];
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
                             IMAGEM PRINCIPAL
                        ========================================= -->
                            <?php if (!empty($imagemPrincipalFile) && $imagemPrincipalUrl): ?>
	                        <section class="w-full rounded-xl overflow-hidden shadow-lg">
                                <?php if (isVideoFilename($imagemPrincipalFile)): ?>
	                                <video
                                        src="<?php echo htmlspecialchars($imagemPrincipalUrl); ?>"
	                                    controls
	                                    playsinline
	                                    preload="metadata"
	                                    class="w-full h-auto max-h-[500px] object-cover"></video>
	                            <?php else: ?>
                                    <img src="<?php echo htmlspecialchars($imagemPrincipalUrl); ?>" 
	                                     alt="<?php echo htmlspecialchars($projeto['titulo']); ?>"
	                                     class="w-full h-auto max-h-[500px] object-cover">
	                            <?php endif; ?>
	                        </section>
	                        <?php endif; ?>
                        
                        <!-- ========================================
                             DETALHES DO PROJETO
                        ========================================= -->
                        <section class="px-4">
                            <!-- Breadcrumb -->
                            <nav class="flex items-center gap-2 text-sm text-neutral-500 mb-6">
                                <a href="<?php echo routeProjetos(); ?>" class="hover:text-primary transition-colors"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
                                <i class="ph ph-caret-right text-base"></i>
                                <?php if ($projeto['categoria_nome']): ?>
                                <a href="<?php echo routeProjetos(); ?>?categoria=<?php echo urlencode($projeto['categoria_slug']); ?>" class="hover:text-primary transition-colors">
                                    <?php echo htmlspecialchars($projeto['categoria_nome']); ?>
                                </a>
                                <i class="ph ph-caret-right text-base"></i>
                                <?php endif; ?>
                                <span class="text-neutral-700 font-medium"><?php echo htmlspecialchars($projeto['titulo']); ?></span>
                            </nav>
                            
                            <!-- Título e Categoria -->
                            <div class="mb-6">
                                <?php if ($projeto['categoria_nome']): ?>
                                <span class="text-primary text-sm font-bold uppercase tracking-wide">
                                    <?php echo htmlspecialchars($projeto['categoria_nome']); ?>
                                </span>
                                <?php endif; ?>
                                <h1 class="text-neutral-800 text-3xl md:text-4xl font-black leading-tight tracking-[-0.02em] mt-2">
                                    <?php echo htmlspecialchars($projeto['titulo']); ?>
                                </h1>
                            </div>
                            
                            <!-- Meta Info -->
                            <div class="flex flex-wrap items-center gap-4 py-4 border-y border-neutral-200 mb-8">
                                <?php if ($projeto['tecnologias']): ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach (explode(',', $projeto['tecnologias']) as $tech): ?>
                                    <span class="px-3 py-1 bg-primary/10 text-primary text-sm font-medium rounded-full">
                                        <?php echo htmlspecialchars(trim($tech)); ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($projeto['url_projeto']): ?>
                                <a href="<?php echo htmlspecialchars($projeto['url_projeto']); ?>" 
                                   target="_blank"
                                   class="ml-auto flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-bold rounded-lg hover:bg-primary-dark transition-colors shadow-md">
                                    <i class="ph ph-arrow-square-out text-xl"></i>
                                    <span><?php echo htmlspecialchars(t('project.visit', 'Visitar Projeto')); ?></span>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Descrição -->
                            <?php if ($projeto['descricao']): ?>
                            <div class="prose prose-lg max-w-none text-neutral-700 leading-relaxed mb-10">
                                <?php 
                                $descricao = $projeto['descricao'];
                                // Se o texto não contiver tags HTML, aplica escape e nl2br
                                if (strip_tags($descricao) === $descricao) {
                                    echo nl2br(htmlspecialchars($descricao));
                                } else {
                                    // Se tiver HTML, exibe diretamente (presume-se que o admin/IA gerou HTML seguro)
                                    echo $descricao;
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Galeria -->
                            <?php if (!empty($galeriaImagens) || !empty($galeriaVideos)): ?>
                            <?php $mediaIndex = 0; ?>
                            <div class="mt-10">
                                <?php if (!empty($galeriaImagens)): ?>
                                <h2 class="text-neutral-800 text-xl font-bold mb-6 flex items-center gap-2">
                                    <i class="ph ph-images text-primary text-xl"></i>
                                    <?php echo htmlspecialchars(t('gallery.title', 'Galeria')); ?>
                                </h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
                                        <?php foreach ($galeriaImagens as $img): ?>
                                    <button type="button"
                                            data-gallery-item="1"
                                            data-gallery-index="<?php echo (int)$mediaIndex; ?>"
                                            data-gallery-src="<?php echo htmlspecialchars($img['url']); ?>"
                                            data-gallery-type="image"
                                            class="block aspect-video rounded-lg overflow-hidden bg-neutral-100 hover:shadow-lg transition-shadow group focus:outline-none focus:ring-2 focus:ring-primary/40">
                    	                                        <img src="<?php echo htmlspecialchars($img['url']); ?>" 
                                             alt="<?php echo htmlspecialchars(t('gallery.image_alt', 'Galeria')); ?>" 
                                             loading="lazy"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    </button>
                                    <?php $mediaIndex++; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($galeriaVideos)): ?>
                                <h2 class="text-neutral-800 text-xl font-bold mb-6 flex items-center gap-2">
                                    <i class="ph ph-film-strip text-primary text-xl"></i>
                                    <?php echo htmlspecialchars(t('gallery.videos', 'Vídeos')); ?>
                                </h2>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <?php foreach ($galeriaVideos as $vid): ?>
                                    <button type="button"
                                            data-gallery-item="1"
                                            data-gallery-index="<?php echo (int)$mediaIndex; ?>"
                                            data-gallery-src="<?php echo htmlspecialchars($vid['url']); ?>"
                                            data-gallery-type="video"
                                            class="block aspect-video rounded-lg overflow-hidden bg-neutral-100 hover:shadow-lg transition-shadow group focus:outline-none focus:ring-2 focus:ring-primary/40">
                                        <video
                    	                                            src="<?php echo htmlspecialchars($vid['url']); ?>"
                                            class="w-full h-full object-cover"
                                            preload="metadata"
                                            muted
                                            playsinline></video>
                                    </button>
                                    <?php $mediaIndex++; ?>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Navegação entre projetos -->
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-12 pt-8 border-t border-neutral-200">
                                <?php if ($anterior): ?>
                                <a href="<?php echo routeProjeto($anterior['slug']); ?>" 
                                   class="flex items-center gap-2 px-4 py-3 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors group w-full sm:w-auto">
                                    <i class="ph ph-arrow-left text-xl group-hover:-translate-x-1 transition-transform"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars(t('project.prev', 'Projeto Anterior')); ?></span>
                                </a>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>
                                
                                <a href="<?php echo routeProjetos(); ?>" 
                                   class="flex items-center gap-2 px-4 py-3 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-neutral-200 transition-colors">
                                    <i class="ph ph-squares-four text-xl"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars(t('projects.all', 'Todos os Projetos')); ?></span>
                                </a>
                                
                                <?php if ($proximo): ?>
                                <a href="<?php echo routeProjeto($proximo['slug']); ?>" 
                                   class="flex items-center gap-2 px-4 py-3 rounded-lg bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors group w-full sm:w-auto justify-end">
                                    <span class="font-medium"><?php echo htmlspecialchars(t('project.next', 'Próximo Projeto')); ?></span>
                                    <i class="ph ph-arrow-right text-xl group-hover:translate-x-1 transition-transform"></i>
                                </a>
                                <?php else: ?>
                                <span></span>
                                <?php endif; ?>
                            </div>
                        </section>
                        
                    </main>
                    
                    <?php require __DIR__ . '/includes/site-footer.php'; ?>
                    
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($galeriaMediaTotal > 0): ?>
    <!-- Modal da Galeria (carrossel) -->
    <div id="galleryModal"
         class="fixed inset-0 z-[100] hidden"
         aria-hidden="true">
        <!-- Backdrop -->
        <button type="button"
                id="galleryBackdrop"
                class="absolute inset-0 bg-black/80"
                aria-label="<?php echo htmlspecialchars(t('gallery.backdrop_close', 'Fechar galeria')); ?>"></button>

        <!-- Dialog -->
        <div class="relative z-[101] mx-auto flex h-full w-full max-w-6xl items-center justify-center p-4">
            <div class="relative w-full overflow-hidden rounded-xl bg-neutral-900 shadow-2xl border border-white/10">
                <!-- Top bar -->
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/10">
                    <div class="text-white/90 text-sm font-medium">
                        <span id="galleryCounter">1 / <?php echo (int)$galeriaMediaTotal; ?></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <a id="galleryOpenNewTab"
                           href="#"
                           target="_blank"
                           rel="noopener"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/15 transition-colors">
                            <i class="ph ph-arrow-square-out text-base"></i>
                            <?php echo htmlspecialchars(t('gallery.open', 'Abrir')); ?>
                        </a>
                        <button type="button"
                                id="galleryClose"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-3 py-2 text-xs font-semibold text-white hover:bg-white/15 transition-colors"
                                aria-label="<?php echo htmlspecialchars(t('gallery.close_aria', 'Fechar (Esc)')); ?>">
                            <i class="ph ph-x text-base"></i>
                            <?php echo htmlspecialchars(t('gallery.close', 'Fechar')); ?>
                        </button>
                    </div>
                </div>

	                <!-- Media stage -->
	                <div class="relative flex items-center justify-center bg-black">
	                    <img id="galleryImage"
	                         src=""
	                         alt=""
	                         class="max-h-[80vh] w-auto max-w-full object-contain select-none">
	                    <video id="galleryVideo"
	                           class="max-h-[80vh] w-auto max-w-full object-contain select-none hidden"
	                           controls
	                           playsinline
	                           preload="metadata"></video>

                    <!-- Prev/Next -->
                    <button type="button"
                            id="galleryPrev"
                            class="absolute left-3 top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40"
                            aria-label="<?php echo htmlspecialchars(t('gallery.prev', 'Imagem anterior (←)')); ?>">
                        <i class="ph ph-caret-left"></i>
                    </button>
                    <button type="button"
                            id="galleryNext"
                            class="absolute right-3 top-1/2 -translate-y-1/2 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40"
                            aria-label="<?php echo htmlspecialchars(t('gallery.next', 'Próxima imagem (→)')); ?>">
                        <i class="ph ph-caret-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

        <?php if ($galeriaMediaTotal > 0): ?>
        // Galeria: modal + carrossel
        (function () {
            const modal = document.getElementById('galleryModal');
            const backdrop = document.getElementById('galleryBackdrop');
            const closeBtn = document.getElementById('galleryClose');
            const prevBtn = document.getElementById('galleryPrev');
            const nextBtn = document.getElementById('galleryNext');
	            const imgEl = document.getElementById('galleryImage');
	            const videoEl = document.getElementById('galleryVideo');
	            const counterEl = document.getElementById('galleryCounter');
	            const openNewTabEl = document.getElementById('galleryOpenNewTab');
	            const items = Array.from(document.querySelectorAll('[data-gallery-item="1"]'));
	            const media = items
	                .map((el) => ({
	                    src: el.getAttribute('data-gallery-src') || '',
	                    type: el.getAttribute('data-gallery-type') || 'image',
	                }))
	                .filter((m) => Boolean(m.src));

	            if (!modal || !items.length || !media.length) return;

            let currentIndex = 0;
            let lastActiveElement = null;

	            function setIndex(nextIndex) {
	                currentIndex = (nextIndex + media.length) % media.length;
	                const item = media[currentIndex];
	                const src = item.src;

	                const alt = <?php echo json_encode(t('gallery.image_of', 'Imagem {current} de {total}')); ?>
	                    .replace('{current}', String(currentIndex + 1))
	                    .replace('{total}', String(media.length));

	                // reset
	                if (videoEl) {
	                    try { videoEl.pause(); } catch (_) {}
	                    videoEl.removeAttribute('src');
	                    videoEl.load();
	                    videoEl.classList.add('hidden');
	                }
	                imgEl.classList.add('hidden');

	                if (item.type === 'video') {
	                    if (videoEl) {
	                        videoEl.src = src;
	                        videoEl.classList.remove('hidden');
	                        videoEl.load();
	                    }
	                } else {
	                    imgEl.src = src;
	                    imgEl.alt = alt;
	                    imgEl.classList.remove('hidden');
	                }

	                counterEl.textContent = `${currentIndex + 1} / ${media.length}`;
	                openNewTabEl.href = src;
	            }

            function openModal(index) {
                lastActiveElement = document.activeElement;
                setIndex(index);
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                closeBtn.focus();
            }

            function closeModal() {
                if (videoEl) {
                    try { videoEl.pause(); } catch (_) {}
                    videoEl.removeAttribute('src');
                    videoEl.load();
                }
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
                    lastActiveElement.focus();
                }
            }

            function onKeyDown(e) {
                if (modal.classList.contains('hidden')) return;
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeModal();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    setIndex(currentIndex - 1);
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    setIndex(currentIndex + 1);
                }
            }

            items.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.getAttribute('data-gallery-index') || '0', 10) || 0;
                    openModal(idx);
                });
            });

            backdrop.addEventListener('click', closeModal);
            closeBtn.addEventListener('click', closeModal);
            prevBtn.addEventListener('click', () => setIndex(currentIndex - 1));
            nextBtn.addEventListener('click', () => setIndex(currentIndex + 1));
            document.addEventListener('keydown', onKeyDown);

	            // Preload vizinhos para navegação mais suave
	            imgEl.addEventListener('load', () => {
	                const next = new Image();
	                const nextItem = media[(currentIndex + 1) % media.length];
	                next.src = (nextItem && nextItem.type !== 'video') ? (nextItem.src || '') : '';
	                const prev = new Image();
	                const prevItem = media[(currentIndex - 1 + media.length) % media.length];
	                prev.src = (prevItem && prevItem.type !== 'video') ? (prevItem.src || '') : '';
	            });
        })();
        <?php endif; ?>
    </script>
</body>
</html>
