<?php
/**
 * WASHIVIANA PORTFOLIO - Homepage
 * Layout: Minimal Elegante (Estilo A)
 */
require_once __DIR__ . '/api/i18n.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$pathForRouting = stripBasePath($path);

// Não roteia admin/api/assets/uploads (arquivos reais devem ser servidos direto pelo Nginx)
if (!preg_match('~^/(admin|api|assets|uploads)(/|$)~', $pathForRouting)) {
    $pathNoTrailing = rtrim($pathForRouting, '/');
    if ($pathNoTrailing === '') $pathNoTrailing = '/';

    $langFromPath = detectLangFromPath($pathNoTrailing);

    // Root "/" -> redireciona para melhor idioma (Accept-Language/cookie)
    if ($pathNoTrailing === '/' && !$langFromPath) {
        $lang = currentLang();
        setLangCookie($lang);
        header('Location: ' . routeHome($lang), true, 302);
        exit;
    }

    // Rotas "limpas" sem prefixo de idioma: redireciona para /{lang}/...
    if (!$langFromPath && $pathNoTrailing !== '/') {
        $restNoLang = ltrim($pathNoTrailing, '/');
        $restNoLang = preg_replace('~\\.php$~i', '', $restNoLang);
        $restNoLang = trim((string)$restNoLang);
        if (preg_match('~^(artigo|article|articulo)/([^/]+)$~', $restNoLang, $m)) {
            $lang = currentLang();
            setLangCookie($lang);
            header('Location: ' . routeArtigo(rawurldecode($m[2]), $lang), true, 302);
            exit;
        }
        if (preg_match('~^(projeto|project|proyecto)/([^/]+)$~', $restNoLang, $m)) {
            $lang = currentLang();
            setLangCookie($lang);
            header('Location: ' . routeProjeto(rawurldecode($m[2]), $lang), true, 302);
            exit;
        }
        $known = ['conteudos', 'projetos', 'sobre', 'automacao-ia', 'tech-insights', 'design-experiencias'];
        if (in_array($restNoLang, $known, true)) {
            $lang = currentLang();
            setLangCookie($lang);
            header('Location: ' . urlPath('/' . $restNoLang, $lang), true, 302);
            exit;
        }
    }

    // Rotas com prefixo de idioma: /pt/... /en/... /es/...
    if ($langFromPath) {
        setLangCookie($langFromPath);
        $rest = ltrim(stripLangPrefix($pathNoTrailing), '/');

        if ($rest === '') {
            // homepage (continua o fluxo normal do index.php)
        } elseif ($rest === 'conteudos') {
            require __DIR__ . '/conteudos.php';
            exit;
        } elseif ($rest === 'projetos') {
            require __DIR__ . '/projetos.php';
            exit;
        } elseif ($rest === 'sobre') {
            require __DIR__ . '/sobre.php';
            exit;
        } elseif ($rest === 'automacao-ia' || $rest === 'automacao-ia.php') {
            require __DIR__ . '/automacao-ia.php';
            exit;
        } elseif ($rest === 'tech-insights' || $rest === 'tech-insights.php') {
            require __DIR__ . '/tech-insights.php';
            exit;
        } elseif ($rest === 'design-experiencias' || $rest === 'design-experiencias.php') {
            require __DIR__ . '/design-experiencias.php';
            exit;
        } elseif (preg_match('~^(artigo|article|articulo)/([^/]+)$~', $rest, $m)) {
            $_GET['slug'] = rawurldecode($m[2]);
            require __DIR__ . '/artigo.php';
            exit;
        } elseif (preg_match('~^(projeto|project|proyecto)/([^/]+)$~', $rest, $m)) {
            $_GET['slug'] = rawurldecode($m[2]);
            require __DIR__ . '/projeto.php';
            exit;
        } else {
            http_response_code(404);
            echo "404";
            exit;
        }
    }
}

require_once __DIR__ . '/api/config.php';

function renderMiniBio(string $text): string {
    $text = trim($text);
    if ($text === '') return '';

    $hasHtml = preg_match('~<\\s*(p|br|strong|em|ul|ol|li)\\b~i', $text);
    if ($hasHtml) {
        return strip_tags($text, '<p><br><strong><em><ul><ol><li>');
    }

    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $parts = preg_split("/\\n\\s*\\n/", $text);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out[] = '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    return $out ? implode("\n", $out) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function ensureUploadWebpVariant(?string $relativePath): ?string {
    if (empty($relativePath)) return null;
    $relativePath = ltrim($relativePath, '/');

    $srcPath = UPLOAD_DIR . $relativePath;
    if (!is_file($srcPath)) return null;

    $ext = strtolower((string)pathinfo($srcPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) return null;
    if (!function_exists('imagewebp')) return null;

    $webpRelative = preg_replace('~\\.(jpe?g|png)$~i', '.webp', $relativePath);
    if (!$webpRelative) return null;
    $webpPath = UPLOAD_DIR . $webpRelative;

    if (is_file($webpPath)) return $webpRelative;

    $img = null;
    if ($ext === 'png') {
        $img = @imagecreatefrompng($srcPath);
        if ($img) {
            imagepalettetotruecolor($img);
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
    } else {
        $img = @imagecreatefromjpeg($srcPath);
    }
    if (!$img) return null;

    $tmp = $webpPath . '.tmp';
    @imagewebp($img, $tmp, 82);
    imagedestroy($img);

    if (is_file($tmp)) {
        @rename($tmp, $webpPath);
    }

    return is_file($webpPath) ? $webpRelative : null;
}

// Buscar configurações do banco
$siteTitulo = getConfig('site_titulo') ?: 'Washington Viana';
$siteSubtitulo = getConfigI18n('site_subtitulo') ?: 'Tech & IA com linguagem humana';
$fraseImpacto = getConfigI18n('home_frase_impacto') ?: 'Crio soluções que unem tecnologia, criatividade e automação para transformar negócios e pessoas.';
$siteEmail = getConfig('site_email') ?: 'contato@washiviana.com';
$siteLinkedin = getConfig('site_linkedin') ?: 'https://linkedin.com/in/washingtonviana';
$siteInstagram = getConfig('site_instagram') ?: 'https://instagram.com/washiviana';
$siteTelefone = getConfig('site_telefone') ?: '+55 19 9 99422907';
$siteWhatsappDigits = preg_replace('/\D+/', '', (string)$siteTelefone);
$siteWhatsappLink = $siteWhatsappDigits ? ('https://wa.me/' . $siteWhatsappDigits) : '#';

$qsLang = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';

// Mini bio para seção Sobre
$miniBio = getConfigI18n('mini_bio') ?: '+10 anos criando soluções com tecnologia para empresas. Atuo com IA aplicada, automação, design tecnológico e desenvolvimento com foco em produtividade e governança.';

// Buscar últimos artigos (conteúdos/news)
$ultimosConteudos = [];
$tabelaArtigosExiste = false;

try {
    $check = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'artigos')");
    $result = $check->fetch();
    $tabelaArtigosExiste = $result['exists'] ?? false;
} catch (Exception $e) {
    $tabelaArtigosExiste = false;
}

if ($tabelaArtigosExiste) {
    try {
        // Busca os últimos 10 para saber se há mais de 9
        $stmt = $pdo->query("SELECT a.*, ca.nome as categoria_nome
                            FROM artigos a 
                            LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id
                            WHERE a.status_publicacao = 'publicado'
                            ORDER BY a.created_at DESC 
                            LIMIT 10");
        $ultimosConteudos = $stmt->fetchAll();
    } catch (Exception $e) {
        $ultimosConteudos = [];
        error_log("Erro ao buscar últimos conteúdos: " . $e->getMessage());
    }
}

// Categorias para os cards (4 pilares do site)
$defaultCards = [
    1 => ['icon' => 'smart_toy', 'titulo' => t('home.card_1.title', 'Automação & IA'), 'subtexto' => t('home.card_1.subtext', 'Aplicações reais usando tecnologia para otimizar processos.'), 'link' => 'automacao-ia.php'],
    2 => ['icon' => 'insights', 'titulo' => t('home.card_2.title', 'Tech Insights'), 'subtexto' => t('home.card_2.subtext', 'Notícias comentadas e análises sobre tecnologia.'), 'link' => 'tech-insights.php'],
    3 => ['icon' => 'work', 'titulo' => t('home.card_3.title', 'Projetos'), 'subtexto' => t('home.card_3.subtext', 'Projetos que realizei e participei ao longo da carreira.'), 'link' => 'projetos.php'],
    4 => ['icon' => 'palette', 'titulo' => t('home.card_4.title', 'Design & Experiências Digitais'), 'subtexto' => t('home.card_4.subtext', 'VR, 3D e design aplicado em soluções inovadoras.'), 'link' => 'design-experiencias.php'],
];

$categorias = [];
for ($i = 1; $i <= 4; $i++) {
    $categorias[] = [
        'icon' => getConfig("home_card_{$i}_icon") ?: $defaultCards[$i]['icon'],
        'titulo' => getConfigI18n("home_card_{$i}_titulo") ?: $defaultCards[$i]['titulo'],
        'subtexto' => getConfigI18n("home_card_{$i}_subtexto") ?: $defaultCards[$i]['subtexto'],
        'link' => getConfig("home_card_{$i}_link") ?: $defaultCards[$i]['link'],
    ];
}

function routeFromConfigLink(string $link): string {
    $link = trim($link);
    if ($link === '') return routeHome();
    if (preg_match('~^https?://~i', $link)) return $link;

    $link = ltrim($link, '/');
    $link = preg_replace('~\\.php$~i', '', $link);
    $link = trim((string)$link);

    switch ($link) {
        case '':
        case 'index':
            return routeHome();
        case 'conteudos':
            return routeConteudos();
        case 'projetos':
            return routeProjetos();
        case 'sobre':
            return routeSobre();
        case 'automacao-ia':
            return urlPath('/automacao-ia');
        case 'tech-insights':
            return urlPath('/tech-insights');
        case 'design-experiencias':
            return urlPath('/design-experiencias');
        default:
            return urlPath('/' . $link);
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(htmlLang(CURRENT_LANG)); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	    <title><?php echo htmlspecialchars($siteTitulo); ?> - <?php echo htmlspecialchars($siteSubtitulo); ?></title>
	    <meta name="description" content="<?php echo htmlspecialchars($fraseImpacto); ?>">
	    <?php
	        $canonicalUrl = BASE_URL . routeHome() . $qsLang;
	    ?>
	    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
	    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . routeHome('pt') . $qsLang); ?>">
	    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . routeHome('en') . $qsLang); ?>">
	    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . routeHome('es') . $qsLang); ?>">
	    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . routeHome('pt') . $qsLang); ?>">
        <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
        <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    
    <!-- Tailwind CSS (build local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . '/assets/css/tailwind.min.css?v=1'); ?>">
    <link rel="preload" as="image"
          href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-360.webp'); ?>"
          imagesrcset="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-280.webp'); ?> 280w, <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-360.webp'); ?> 360w, <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-420.webp'); ?> 420w"
          imagesizes="(min-width: 864px) 420px, (min-width: 480px) 360px, 280px"
          fetchpriority="high">
    
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
    </style>
</head>
<body class="font-display">
    <div class="relative flex h-auto min-h-screen w-full flex-col bg-background-light overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col">
            <div class="flex flex-1 justify-center py-5">
                <div class="layout-content-container flex flex-col w-full max-w-[960px] flex-1 px-4 sm:px-10">
                    
                    <!-- ========================================
                         HEADER - Sticky com blur
                    ========================================= -->
                    <header class="sticky top-5 z-50 flex items-center justify-between whitespace-nowrap border border-solid border-neutral-200 bg-white/80 px-4 sm:px-8 py-3 rounded-xl backdrop-blur-md shadow-sm">
                        <!-- Logo / Nome -->
	                        <a href="<?php echo routeHome(); ?>" class="flex items-center gap-3 text-neutral-800 hover:opacity-80 transition-opacity">
	                            <img src="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/logo_washiviana_60px_h.png'); ?>"
	                                 alt="<?php echo htmlspecialchars($siteTitulo); ?>"
	                                 width="125" height="60"
	                                 class="h-7 w-auto">
	                            <h2 class="text-neutral-800 text-lg font-bold leading-tight tracking-[-0.015em]"><?php echo htmlspecialchars($siteTitulo); ?></h2>
	                        </a>
                        
	                        <!-- Menu Desktop -->
	                        <nav class="hidden md:flex flex-1 justify-end gap-8">
	                            <div class="flex items-center gap-8">
	                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeHome(); ?>"><?php echo htmlspecialchars(t('nav.home', 'Início')); ?></a>
	                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeConteudos(); ?>"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
	                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
	                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
	                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                </div>
	                
	                            <!-- Idiomas -->
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    $langLinks[$lc] = ['label' => $label, 'href' => urlPath('/', $lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'right');
	                            ?>

                        </nav>
                        
                        <!-- Menu Mobile Toggle -->
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
	                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
	                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
	                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                        <div class="pt-2">
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    $langLinks[$lc] = ['label' => $label, 'href' => urlPath('/', $lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'center');
	                            ?>
	                        </div>
	                    </nav>
                    
                    <!-- ========================================
                         MAIN CONTENT
                    ========================================= -->
                    <main class="flex flex-col gap-16 md:gap-24 mt-10 md:mt-16">
                        
                        <!-- ========================================
                             HERO SECTION
                        ========================================= -->
                        <section class="@container">
                            <div class="flex flex-col-reverse gap-10 px-4 py-10 @[864px]:flex-row @[864px]:items-center">
                                <!-- Texto -->
                                <div class="flex flex-col gap-6 text-center @[864px]:text-left @[864px]:flex-1">
                                    <div class="flex flex-col gap-3">
                                        <h1 class="text-neutral-800 text-4xl font-black leading-tight tracking-[-0.033em] @[480px]:text-5xl">
                                            <?php echo htmlspecialchars($siteSubtitulo); ?>
                                        </h1>
                                        <p class="text-neutral-600 text-base font-normal leading-relaxed @[480px]:text-lg">
                                            <?php echo htmlspecialchars($fraseImpacto); ?>
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-3 justify-center @[864px]:justify-start">
                                        <a href="<?php echo routeConteudos(); ?>" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-11 px-5 @[480px]:h-12 @[480px]:px-6 bg-primary text-white text-sm font-bold leading-normal tracking-[0.015em] @[480px]:text-base hover:bg-primary-dark transition-colors shadow-md hover:shadow-lg">
	                                            <span><?php echo htmlspecialchars(t('hero.cta_contents', 'Explorar Conteúdos')); ?></span>
	                                        </a>
                                        <a href="<?php echo routeProjetos(); ?>" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-11 px-5 @[480px]:h-12 @[480px]:px-6 bg-neutral-100 text-neutral-800 text-sm font-bold leading-normal tracking-[0.015em] @[480px]:text-base hover:bg-neutral-200 transition-colors border border-primary">
	                                            <span><?php echo htmlspecialchars(t('hero.cta_projects', 'Ver Projetos')); ?></span>
	                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Imagem -->
	                                <div class="flex-shrink-0 mx-auto @[864px]:mx-0">
	                                    <picture>
	                                        <source
	                                            type="image/webp"
	                                            srcset="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-280.webp'); ?> 280w,
	                                                    <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-360.webp'); ?> 360w,
	                                                    <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-420.webp'); ?> 420w"
	                                            sizes="(min-width: 864px) 420px, (min-width: 480px) 360px, 280px"
	                                        >
	                                        <img
	                                            src="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg.png?v=1'); ?>"
	                                            alt="Foto de <?php echo htmlspecialchars($siteTitulo); ?>"
	                                            width="412" height="538"
	                                            class="block w-[280px] @[480px]:w-[360px] @[864px]:w-[420px] max-w-full h-auto"
	                                            loading="eager"
	                                            fetchpriority="high"
	                                            decoding="async"
	                                        >
	                                    </picture>
	                                </div>
        </div>
    </section>

                        <!-- ========================================
                             CATEGORIAS - "O que você vai encontrar aqui"
                        ========================================= -->
                        <section>
	                            <h2 class="text-neutral-800 text-xl font-bold leading-tight tracking-[-0.015em] px-4 pb-4">
	                                <?php echo htmlspecialchars(t('home.section_find', 'O que você vai encontrar aqui')); ?>
	                            </h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4">
                                <?php foreach ($categorias as $cat): ?>
                                <a href="<?php echo htmlspecialchars(routeFromConfigLink((string)$cat['link'])); ?>" class="flex flex-1 gap-3 rounded-xl border border-neutral-200 bg-white p-5 flex-col hover:border-primary hover:shadow-md transition-all group">
                                    <div class="text-primary group-hover:scale-110 transition-transform">
                                        <i class="ph <?php echo $cat['icon']; ?> text-3xl"></i>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <h3 class="text-neutral-800 text-base font-bold leading-tight"><?php echo $cat['titulo']; ?></h3>
                                        <p class="text-neutral-600 text-sm font-normal leading-normal"><?php echo $cat['subtexto']; ?></p>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        
                        <!-- ========================================
                             ÚLTIMAS ATUALIZAÇÕES
                        ========================================= -->
                        <section>
	                            <h2 class="text-neutral-800 text-xl font-bold leading-tight tracking-[-0.015em] px-4 pb-4">
	                                <?php echo htmlspecialchars(t('home.latest', 'Últimos conteúdos')); ?>
	                            </h2>
                            
                            <?php if (!empty($ultimosConteudos)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-4">
                                <?php foreach (array_slice($ultimosConteudos, 0, 9) as $conteudo): ?>
                                <a href="<?php echo routeArtigo($conteudo['slug']); ?>" class="flex flex-col rounded-xl overflow-hidden bg-white border border-neutral-200 group hover:shadow-lg transition-all">
                                    <!-- Thumbnail -->
                                    <?php 
                                    $imagem = $conteudo['imagem_1x1'] ?? $conteudo['imagem_principal'] ?? null;
                                    $webpImagem = ensureUploadWebpVariant($imagem);
                                    if ($imagem): ?>
                                    <div class="w-full aspect-video bg-cover bg-center bg-neutral-100" 
                                         style="<?php
                                             $jpgUrl = UPLOAD_URL . $imagem;
                                             if ($webpImagem) {
                                                 $webpUrl = UPLOAD_URL . $webpImagem;
                                                 echo htmlspecialchars("background-image: url('{$jpgUrl}'); background-image: image-set(url('{$webpUrl}') type('image/webp') 1x, url('{$jpgUrl}') 1x);");
                                             } else {
                                                 echo htmlspecialchars("background-image: url('{$jpgUrl}');");
                                             }
                                         ?>">
                                    </div>
                                    <?php else: ?>
                                    <div class="w-full aspect-video bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
                                        <i class="ph ph-article text-5xl text-primary/40"></i>
                                    </div>
                                    <?php endif; ?>
                                    <!-- Info -->
                                    <div class="p-5 flex flex-col gap-3 flex-grow">
                                        <div class="flex flex-col gap-2 flex-grow">
                                            <?php if (!empty($conteudo['categoria_nome'])): ?>
                                            <span class="text-primary text-xs font-bold uppercase tracking-wide">
                                                <?php echo htmlspecialchars($conteudo['categoria_nome']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <h3 class="text-neutral-800 text-lg font-bold leading-tight group-hover:text-primary transition-colors">
                                                <?php echo htmlspecialchars($conteudo['titulo']); ?>
                                            </h3>
                                            <p class="text-neutral-500 text-sm">
                                                <?php echo formatDate($conteudo['created_at'], 'd \d\e F, Y'); ?>
                                            </p>
                                        </div>
                                        <span class="text-primary text-sm font-bold flex items-center gap-2 group-hover:gap-3 transition-all">
                                            Ler artigo 
                                            <i class="ph ph-arrow-right text-base"></i>
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($ultimosConteudos) > 9): ?>
                                <div class="flex justify-center mt-4">
                                    <a href="<?php echo routeConteudos(); ?>" class="text-primary text-base font-bold px-6 py-3 rounded-lg border border-primary bg-white hover:bg-primary hover:text-white transition-colors shadow-sm">
                                        <?php echo htmlspecialchars(t('home.view_more', 'Ver mais')); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <div class="text-center py-12 px-4">
                                <i class="ph ph-article text-5xl text-neutral-300 mb-4"></i>
	                                <p class="text-neutral-500"><?php echo htmlspecialchars(t('home.soon', 'Conteúdos em breve...')); ?></p>
	                                <a href="<?php echo routeConteudos(); ?>" class="text-primary text-sm font-medium mt-2 inline-block hover:underline"><?php echo htmlspecialchars(t('home.view_all_contents', 'Ver todos os conteúdos →')); ?></a>
                            </div>
                            <?php endif; ?>
                        </section>
                        
                        <!-- ========================================
                             SEÇÃO SOBRE - Mini Bio
                        ========================================= -->
                        <section class="bg-neutral-50 rounded-xl p-8 md:p-12 text-center border border-neutral-200">
                            <div class="max-w-2xl mx-auto flex flex-col items-center gap-6">
	                                <h2 class="text-neutral-800 text-2xl md:text-3xl font-bold leading-tight">
	                                    <?php echo htmlspecialchars(t('home.about_title', 'Um pouco sobre mim')); ?>
	                                </h2>
                                <div class="text-neutral-600 text-base leading-relaxed space-y-4">
	                                    <?php echo renderMiniBio(getConfigI18n('mini_bio') ?: $miniBio); ?>
	                                </div>
	                                <a href="<?php echo routeSobre(); ?>" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 px-6 bg-primary text-white text-base font-bold leading-normal tracking-[0.015em] hover:bg-primary-dark transition-colors mt-2 shadow-md hover:shadow-lg">
	                                    <span><?php echo htmlspecialchars(t('home.about_cta', 'Conheça minha trajetória')); ?></span>
	                                </a>
                            </div>
                        </section>
                        
	                        <!-- ========================================
	                             SOCIAL PROOF - "Já criei com/para"
	                        ========================================= -->
	                        <section>
	                            <div class="py-8 text-center">
	                                <div class="flex flex-wrap justify-center items-center gap-8 md:gap-12 opacity-50 grayscale hover:grayscale-0 hover:opacity-100 transition-all duration-500">
	                                    <!-- Android -->
	                                    <div class="text-neutral-600" aria-label="Android">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Android</title>
	                                            <path d="M18.4395 5.5586c-.675 1.1664-1.352 2.3318-2.0274 3.498-.0366-.0155-.0742-.0286-.1113-.043-1.8249-.6957-3.484-.8-4.42-.787-1.8551.0185-3.3544.4643-4.2597.8203-.084-.1494-1.7526-3.021-2.0215-3.4864a1.1451 1.1451 0 0 0-.1406-.1914c-.3312-.364-.9054-.4859-1.379-.203-.475.282-.7136.9361-.3886 1.5019 1.9466 3.3696-.0966-.2158 1.9473 3.3593.0172.031-.4946.2642-1.3926 1.0177C2.8987 12.176.452 14.772 0 18.9902h24c-.119-1.1108-.3686-2.099-.7461-3.0683-.7438-1.9118-1.8435-3.2928-2.7402-4.1836a12.1048 12.1048 0 0 0-2.1309-1.6875c.6594-1.122 1.312-2.2559 1.9649-3.3848.2077-.3615.1886-.7956-.0079-1.1191a1.1001 1.1001 0 0 0-.8515-.5332c-.5225-.0536-.9392.3128-1.0488.5449zm-.0391 8.461c.3944.5926.324 1.3306-.1563 1.6503-.4799.3197-1.188.0985-1.582-.4941-.3944-.5927-.324-1.3307.1563-1.6504.4727-.315 1.1812-.1086 1.582.4941zM7.207 13.5273c.4803.3197.5506 1.0577.1563 1.6504-.394.5926-1.1038.8138-1.584.4941-.48-.3197-.5503-1.0577-.1563-1.6504.4008-.6021 1.1087-.8106 1.584-.4941z"/>
	                                        </svg>
	                                    </div>

	                                    <!-- Apple -->
	                                    <div class="text-neutral-600" aria-label="Apple">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Apple</title>
	                                            <path d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/>
	                                        </svg>
	                                    </div>

	                                    <!-- Blender -->
	                                    <div class="text-neutral-600" aria-label="Blender">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Blender</title>
	                                            <path d="M12.51 13.214c.046-.8.438-1.506 1.03-2.006a3.424 3.424 0 0 1 2.212-.79c.85 0 1.631.3 2.211.79.592.5.983 1.206 1.028 2.005.045.823-.285 1.586-.865 2.153a3.389 3.389 0 0 1-2.374.938 3.393 3.393 0 0 1-2.376-.938c-.58-.567-.91-1.33-.865-2.152M7.35 14.831c.006.314.106.922.256 1.398a7.372 7.372 0 0 0 1.593 2.757 8.227 8.227 0 0 0 2.787 2.001 8.947 8.947 0 0 0 3.66.76 8.964 8.964 0 0 0 3.657-.772 8.285 8.285 0 0 0 2.785-2.01 7.428 7.428 0 0 0 1.592-2.762 6.964 6.964 0 0 0 .25-3.074 7.123 7.123 0 0 0-1.016-2.779 7.764 7.764 0 0 0-1.852-2.043h.002L13.566 2.55l-.02-.015c-.492-.378-1.319-.376-1.86.002-.547.382-.609 1.015-.123 1.415l-.001.001 3.126 2.543-9.53.01h-.013c-.788.001-1.545.518-1.695 1.172-.154.665.38 1.217 1.2 1.22V8.9l4.83-.01-8.62 6.617-.034.025c-.813.622-1.075 1.658-.563 2.313.52.667 1.625.668 2.447.004L7.414 14s-.069.52-.063.831zm12.09 1.741c-.97.988-2.326 1.548-3.795 1.55-1.47.004-2.827-.552-3.797-1.538a4.51 4.51 0 0 1-1.036-1.622 4.282 4.282 0 0 1 .282-3.519 4.702 4.702 0 0 1 1.153-1.371c.942-.768 2.141-1.183 3.396-1.185 1.256-.002 2.455.41 3.398 1.175.48.391.87.854 1.152 1.367a4.28 4.28 0 0 1 .522 1.706 4.236 4.236 0 0 1-.239 1.811 4.54 4.54 0 0 1-1.035 1.626"/>
	                                        </svg>
	                                    </div>

	                                    <!-- Unity -->
	                                    <div class="text-neutral-600" aria-label="Unity">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Unity</title>
	                                            <path d="m12.9288 4.2939 3.7997 2.1929c.1366.077.1415.2905 0 .3675l-4.515 2.6076a.4192.4192 0 0 1-.4246 0L7.274 6.8543c-.139-.0745-.1415-.293 0-.3675l3.7972-2.193V0L1.3758 5.5977V16.793l3.7177-2.1456v-4.3858c-.0025-.1565.1813-.2682.318-.1838l4.5148 2.6076a.4252.4252 0 0 1 .2136.3676v5.2127c.0025.1565-.1813.2682-.3179.1838l-3.7996-2.1929-3.7178 2.1457L12 24l9.6954-5.5977-3.7178-2.1457-3.7996 2.1929c-.1341.082-.3229-.0248-.3179-.1838V13.053c0-.1565.087-.2956.2136-.3676l4.5149-2.6076c.134-.082.3228.0224.3179.1838v4.3858l3.7177 2.1456V5.5977L12.9288 0Z"/>
	                                        </svg>
	                                    </div>

	                                    <!-- IBM -->
	                                    <div class="text-neutral-600" aria-label="IBM">
	                                        <svg role="img" viewBox="0 0 512 205" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>IBM</title>
	                                            <path d="M99.55552,190.060579 L99.55552,204.282819 L0,204.282819 L0,190.060579 L99.55552,190.060579 Z M255.1384,190.059939 C245.151671,199.241068 232.070596,204.31949 218.50496,204.282019 L218.50496,204.282019 L113.77792,204.141379 L113.77792,190.059939 Z M403.1664,190.059779 L398.2,204.282179 L393.2784,190.059779 L403.1664,190.059779 Z M355.55584,190.060579 L355.55584,204.282819 L284.44464,204.282819 L284.44464,190.060579 L355.55584,190.060579 Z M512,190.060579 L512,204.282819 L440.8888,204.282819 L440.8888,190.060579 L512,190.060579 Z M271.24672,162.908899 C270.026362,167.89787 268.099708,172.686973 265.52512,177.131139 L265.52512,177.131139 L113.77792,177.131139 L113.77792,162.908899 Z M412.6976,162.909379 L407.7056,177.131779 L388.7392,177.131779 L383.7472,162.909379 L412.6976,162.909379 Z M355.55584,162.908899 L355.55584,177.131139 L284.44464,177.131139 L284.44464,162.908899 L355.55584,162.908899 Z M512,162.908899 L512,177.131139 L440.8888,177.131139 L440.8888,162.908899 L512,162.908899 Z M99.55552,162.908899 L99.55552,177.131139 L0,177.131139 L0,162.908899 L99.55552,162.908899 Z M71.11104,135.757379 L71.11104,149.979779 L28.44432,149.979779 L28.44432,135.757379 L71.11104,135.757379 Z M184.88896,135.757379 L184.88896,149.979779 L142.22224,149.979779 L142.22224,135.757379 L184.88896,135.757379 Z M270.90576,135.757379 C272.166041,140.393192 272.805755,145.175711 272.80816,149.979779 L272.80816,149.979779 L224.96976,149.979779 L224.96976,135.757379 Z M422.2304,135.757379 L417.2368,149.979779 L379.208,149.979779 L374.2144,135.757379 L422.2304,135.757379 Z M355.55568,135.757379 L355.55568,149.979779 L312.88896,149.979779 L312.88896,135.757379 L355.55568,135.757379 Z M483.55552,135.757379 L483.55552,149.979779 L440.8888,149.979779 L440.8888,135.757379 L483.55552,135.757379 Z M71.11104,108.606019 L71.11104,122.828259 L28.44432,122.828259 L28.44432,108.606019 L71.11104,108.606019 Z M355.55568,108.606019 L355.55568,122.828259 L312.88896,122.828259 L312.88896,108.606019 L355.55568,108.606019 Z M483.55552,108.606019 L483.55552,122.828259 L440.8888,122.828259 L440.8888,108.606019 L483.55552,108.606019 Z M253.64576,108.605379 C258.382421,112.634795 262.394807,117.444874 265.50928,122.827459 L265.50928,122.827459 L142.22176,122.827459 L142.22176,108.605379 Z M431.7616,108.605379 L426.7696,122.827779 L369.6752,122.827779 L364.6832,108.605379 L431.7616,108.605379 Z M394.224,81.4549786 L398.2224,92.9509786 L402.2192,81.4549786 L483.5552,81.4549786 L483.5552,95.6773786 L440.8896,95.6773786 L440.8896,82.6085786 L436.3008,95.6773786 L360.144,95.6773786 L355.5552,82.6069786 L355.5552,95.6773786 L312.8896,95.6773786 L312.8896,81.4549786 L394.224,81.4549786 Z M142.22224,81.4543386 L265.51024,81.4551386 C262.395586,86.8377816 258.383042,91.6479099 253.64624,95.6773786 L253.64624,95.6773786 L142.22224,95.6773786 L142.22224,81.4543386 Z M71.11104,81.4543386 L71.11104,95.6765786 L28.44432,95.6765786 L28.44432,81.4543386 L71.11104,81.4543386 Z M71.11104,54.3029786 L71.11104,68.5252186 L28.44432,68.5252186 L28.44432,54.3029786 L71.11104,54.3029786 Z M184.88896,54.3029786 L184.88896,68.5252186 L142.22224,68.5252186 L142.22224,54.3029786 L184.88896,54.3029786 Z M272.80816,54.3031386 C272.805733,59.1071522 272.166019,63.8896155 270.90576,68.5253786 L270.90576,68.5253786 L224.96976,68.5253786 L224.96976,54.3031386 Z M384.7824,54.3029786 L389.728,68.5253786 L312.8896,68.5253786 L312.8896,54.3029786 L384.7824,54.3029786 Z M483.5552,54.3029786 L483.5552,68.5253786 L406.7168,68.5253786 L411.6624,54.3029786 L483.5552,54.3029786 Z M99.55552,27.1514586 L99.55552,41.3736986 L0,41.3736986 L0,27.1514586 L99.55552,27.1514586 Z M265.52512,27.1514586 C268.099627,31.5955505 270.026276,36.3845354 271.24672,41.3733786 L271.24672,41.3733786 L113.77792,41.3733786 L113.77792,27.1514586 Z M512,27.1509786 L512,41.3733786 L416.1584,41.3733786 L421.104,27.1509786 L512,27.1509786 Z M375.3408,27.1509786 L380.2864,41.3733786 L284.4448,41.3733786 L284.4448,27.1509786 L375.3408,27.1509786 Z M99.55552,9.85716419e-05 L99.55552,14.2223386 L0,14.2223386 L0,9.85716419e-05 L99.55552,9.85716419e-05 Z M218.50496,4.91529226e-05 C232.066886,-0.0182214039 245.141087,5.05759937 255.13792,14.2221786 L255.13792,14.2221786 L113.77792,14.2221786 L113.77792,4.91529226e-05 Z M512,0.000578571642 L512,14.2229786 L425.6,14.2229786 L430.5456,0.000578571642 L512,0.000578571642 Z M365.8992,0.000578571642 L370.8448,14.2229786 L284.4448,14.2229786 L284.4448,0.000578571642 L365.8992,0.000578571642 Z" />
	                                        </svg>
	                                    </div>

	                                    <!-- Adobe -->
	                                    <div class="text-neutral-600" aria-label="Adobe">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Adobe</title>
	                                            <path d="M13.966 22.624l-1.69-4.281H8.122l3.892-9.144 5.662 13.425zM8.884 1.376H0v21.248z"/>
	                                        </svg>
	                                    </div>

	                                    <!-- Node.js -->
	                                    <div class="text-neutral-600" aria-label="Node.js">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Node.js</title>
	                                            <path d="M11.998,24c-0.321,0-0.641-0.084-0.922-0.247l-2.936-1.737c-0.438-0.245-0.224-0.332-0.08-0.383 c0.585-0.203,0.703-0.25,1.328-0.604c0.065-0.037,0.151-0.023,0.218,0.017l2.256,1.339c0.082,0.045,0.197,0.045,0.272,0l8.795-5.076 c0.082-0.047,0.134-0.141,0.134-0.238V6.921c0-0.099-0.053-0.192-0.137-0.242l-8.791-5.072c-0.081-0.047-0.189-0.047-0.271,0 L3.075,6.68C2.99,6.729,2.936,6.825,2.936,6.921v10.15c0,0.097,0.054,0.189,0.139,0.235l2.409,1.392 c1.307,0.654,2.108-0.116,2.108-0.89V7.787c0-0.142,0.114-0.253,0.256-0.253h1.115c0.139,0,0.255,0.112,0.255,0.253v10.021 c0,1.745-0.95,2.745-2.604,2.745c-0.508,0-0.909,0-2.026-0.551L2.28,18.675c-0.57-0.329-0.922-0.945-0.922-1.604V6.921 c0-0.659,0.353-1.275,0.922-1.603l8.795-5.082c0.557-0.315,1.296-0.315,1.848,0l8.794,5.082c0.57,0.329,0.924,0.944,0.924,1.603 v10.15c0,0.659-0.354,1.273-0.924,1.604l-8.794,5.078C12.643,23.916,12.324,24,11.998,24z M19.099,13.993 c0-1.9-1.284-2.406-3.987-2.763c-2.731-0.361-3.009-0.548-3.009-1.187c0-0.528,0.235-1.233,2.258-1.233 c1.807,0,2.473,0.389,2.747,1.607c0.024,0.115,0.129,0.199,0.247,0.199h1.141c0.071,0,0.138-0.031,0.186-0.081 c0.048-0.054,0.074-0.123,0.067-0.196c-0.177-2.098-1.571-3.076-4.388-3.076c-2.508,0-4.004,1.058-4.004,2.833 c0,1.925,1.488,2.457,3.895,2.695c2.88,0.282,3.103,0.703,3.103,1.269c0,0.983-0.789,1.402-2.642,1.402 c-2.327,0-2.839-0.584-3.011-1.742c-0.02-0.124-0.126-0.215-0.253-0.215h-1.137c-0.141,0-0.254,0.112-0.254,0.253 c0,1.482,0.806,3.248,4.655,3.248C17.501,17.007,19.099,15.91,19.099,13.993z"/>
	                                        </svg>
	                                    </div>

	                                    <!-- Python -->
	                                    <div class="text-neutral-600" aria-label="Python">
	                                        <svg role="img" viewBox="0 0 24 24" class="h-8 w-auto" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
	                                            <title>Python</title>
	                                            <path d="M14.25.18l.9.2.73.26.59.3.45.32.34.34.25.34.16.33.1.3.04.26.02.2-.01.13V8.5l-.05.63-.13.55-.21.46-.26.38-.3.31-.33.25-.35.19-.35.14-.33.1-.3.07-.26.04-.21.02H8.77l-.69.05-.59.14-.5.22-.41.27-.33.32-.27.35-.2.36-.15.37-.1.35-.07.32-.04.27-.02.21v3.06H3.17l-.21-.03-.28-.07-.32-.12-.35-.18-.36-.26-.36-.36-.35-.46-.32-.59-.28-.73-.21-.88-.14-1.05-.05-1.23.06-1.22.16-1.04.24-.87.32-.71.36-.57.4-.44.42-.33.42-.24.4-.16.36-.1.32-.05.24-.01h.16l.06.01h8.16v-.83H6.18l-.01-2.75-.02-.37.05-.34.11-.31.17-.28.25-.26.31-.23.38-.2.44-.18.51-.15.58-.12.64-.1.71-.06.77-.04.84-.02 1.27.05zm-6.3 1.98l-.23.33-.08.41.08.41.23.34.33.22.41.09.41-.09.33-.22.23-.34.08-.41-.08-.41-.23-.33-.33-.22-.41-.09-.41.09zm13.09 3.95l.28.06.32.12.35.18.36.27.36.35.35.47.32.59.28.73.21.88.14 1.04.05 1.23-.06 1.23-.16 1.04-.24.86-.32.71-.36.57-.4.45-.42.33-.42.24-.4.16-.36.09-.32.05-.24.02-.16-.01h-8.22v.82h5.84l.01 2.76.02.36-.05.34-.11.31-.17.29-.25.25-.31.24-.38.2-.44.17-.51.15-.58.13-.64.09-.71.07-.77.04-.84.01-1.27-.04-1.07-.14-.9-.2-.73-.25-.59-.3-.45-.33-.34-.34-.25-.34-.16-.33-.1-.3-.04-.25-.02-.2.01-.13v-5.34l.05-.64.13-.54.21-.46.26-.38.3-.32.33-.24.35-.2.35-.14.33-.1.3-.06.26-.04.21-.02.13-.01h5.84l.69-.05.59-.14.5-.21.41-.28.33-.32.27-.35.2-.36.15-.36.1-.35.07-.32.04-.28.02-.21V6.07h2.09l.14.01zm-6.47 14.25l-.23.33-.08.41.08.41.23.33.33.23.41.08.41-.08.33-.23.23-.33.08-.41-.08-.41-.23-.33-.33-.23-.41-.08-.41.08z"/>
	                                        </svg>
	                                    </div>
	                                </div>
	                            </div>
	                        </section>
                        
                    </main>
                    
                    <?php require __DIR__ . '/includes/site-footer.php'; ?>

                </div>
            </div>
        </div>
    </div>
    
    <!-- ========================================
         SCRIPTS
    ========================================= -->
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
            
            // Fechar ao clicar em um link
            mobileMenu.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    document.body.style.overflow = '';
                });
            });
        }
    </script>

    <script>
        // Registrar visita (sendBeacon para confiabilidade) — envia apenas path e UA
        (function(){
            try {
                const data = { path: location.pathname + location.search, ua: navigator.userAgent };
                const url = '<?php echo htmlspecialchars(BASE_URL . '/api/metrics.php'); ?>';
                if (navigator.sendBeacon) {
                    const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
                    navigator.sendBeacon(url, blob);
                } else {
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data),
                        keepalive: true
                    }).catch(function(){});
                }
            } catch (e) {}
        })();
    </script>
</body>
</html>
