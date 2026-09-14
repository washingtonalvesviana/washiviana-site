<?php
/**
 * WASHIVIANA PORTFOLIO - Landing: Automação & IA
 * Layout: Minimal Elegante (Estilo A)
 */
require_once __DIR__ . '/api/config.php';

// Buscar configurações
$siteTitulo = getConfig('site_titulo') ?: 'Washington Viana';
$siteEmail = getConfig('site_email') ?: 'contato@washiviana.com';
$siteLinkedin = getConfig('site_linkedin') ?: 'https://linkedin.com/in/washingtonviana';
$siteInstagram = getConfig('site_instagram') ?: 'https://instagram.com/washiviana';
$siteTelefone = getConfig('site_telefone') ?: '+55 19 9 99422907';
$siteWhatsappDigits = preg_replace('/\D+/', '', (string)$siteTelefone);
$siteWhatsappLink = $siteWhatsappDigits ? ('https://wa.me/' . $siteWhatsappDigits) : '#';
$qsLang = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';

$paginaTitulo = t('landing.automation.title', 'Automação & IA');
$paginaDescricao = t('landing.automation.desc', 'Aplicações reais de IA e automação para produtividade, processos e experiências digitais.');

// Verificar se tabela de artigos existe (PostgreSQL)
$tabelaExiste = false;
try {
    $check = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'artigos')");
    $result = $check->fetch();
    $tabelaExiste = $result['exists'] ?? false;
} catch (Exception $e) {
    $tabelaExiste = false;
}

// Buscar artigos PUBLICADOS nas categorias automacao e inteligencia-artificial
$artigos = [];
if ($tabelaExiste) {
    try {
        $lang = CURRENT_LANG;
        if ($lang !== 'pt') {
            $sql = "SELECT a.*, COALESCE(ci.nome, ca.nome) as categoria_nome, ca.slug as categoria_slug,
                           COALESCE(t.titulo, a.titulo) AS titulo_exib,
                           COALESCE(t.resumo, a.resumo) AS resumo_exib,
                           COALESCE(t.slug, a.slug) AS slug_exib
                    FROM artigos a
                    LEFT JOIN artigos_i18n t ON t.artigo_id = a.id AND t.lang = " . $pdo->quote($lang) . "
                    LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id
                    LEFT JOIN categorias_artigos_i18n ci ON ci.categoria_id = ca.id AND ci.lang = " . $pdo->quote($lang) . "
                    WHERE a.status_publicacao = 'publicado'
                      AND (ca.slug = 'automacao' OR ca.slug = 'inteligencia-artificial')
                    ORDER BY a.created_at DESC";
        } else {
            $sql = "SELECT a.*, ca.nome as categoria_nome, ca.slug as categoria_slug,
                           a.titulo AS titulo_exib,
                           a.resumo AS resumo_exib,
                           a.slug AS slug_exib
                    FROM artigos a
                    LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id
                    WHERE a.status_publicacao = 'publicado'
                      AND (ca.slug = 'automacao' OR ca.slug = 'inteligencia-artificial')
                    ORDER BY a.created_at DESC";
        }
        $stmt = $pdo->query($sql);
        $artigos = $stmt->fetchAll();

        foreach ($artigos as &$artRow) {
            if (isset($artRow['titulo_exib'])) $artRow['titulo'] = $artRow['titulo_exib'];
            if (isset($artRow['resumo_exib'])) $artRow['resumo'] = $artRow['resumo_exib'];
            if (isset($artRow['slug_exib'])) $artRow['slug'] = $artRow['slug_exib'];
        }
        unset($artRow);
    } catch (Exception $e) {
        $artigos = [];
        error_log("Erro ao buscar artigos Automação & IA: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(htmlLang(CURRENT_LANG)); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paginaTitulo); ?> - <?php echo htmlspecialchars($siteTitulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($paginaDescricao); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/automacao-ia')); ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/automacao-ia', 'pt')); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/automacao-ia', 'en')); ?>">
    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/automacao-ia', 'es')); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/automacao-ia', 'pt')); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitulo); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($paginaTitulo . ' - ' . $siteTitulo); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($paginaDescricao); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars(BASE_URL . urlPath('/automacao-ia')); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi1.png'); ?>">
    <meta property="og:image:width" content="1344">
    <meta property="og:image:height" content="756">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($paginaTitulo . ' - ' . $siteTitulo); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($paginaDescricao); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi1.png'); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">

    <!-- Tailwind CSS (build local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . '/assets/css/tailwind.min.css?v=1'); ?>">

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

                    <!-- Header -->
	                    <header class="sticky top-5 z-50 flex items-center justify-between whitespace-nowrap border border-solid border-neutral-200 bg-white/80 px-4 sm:px-8 py-3 rounded-xl backdrop-blur-md shadow-sm">
		                        <a href="<?php echo routeHome(); ?>" class="flex items-center gap-3 text-neutral-800 hover:opacity-80 transition-opacity">
		                            <img src="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/logo_washiviana_60px_h.png'); ?>"
		                                 alt="<?php echo htmlspecialchars($siteTitulo); ?>"
		                                 width="125" height="60"
		                                 class="h-7 w-auto">
		                            <h2 class="text-neutral-800 text-lg font-bold leading-tight tracking-[-0.015em]"><?php echo htmlspecialchars($siteTitulo); ?></h2>
		                        </a>
	                        <div class="flex items-center gap-4">
	                            <nav class="hidden md:flex items-center gap-6">
	                                <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeHome(); ?>"><?php echo htmlspecialchars(t('nav.home', 'Início')); ?></a>
	                                <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeConteudos(); ?>"><?php echo htmlspecialchars(t('nav.contents', 'Conteúdos')); ?></a>
	                                <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
	                                <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
	                                <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                            </nav>
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    $langLinks[$lc] = ['label' => $label, 'href' => urlPath('/automacao-ia', $lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'right');
	                            ?>
	                        </div>
	                    </header>

                    <main class="flex flex-col gap-10 mt-8 md:mt-12">
                        <!-- Título -->
                        <section class="text-center py-6 px-4">
                            <div class="flex items-center justify-center gap-2 text-primary mb-3">
                                <i class="ph ph-robot text-2xl"></i>
                                <span class="text-sm font-bold uppercase tracking-wider"><?php echo htmlspecialchars(t('landing.automation.kicker', 'Automação & IA')); ?></span>
                            </div>
                            <h1 class="text-neutral-800 text-3xl md:text-4xl font-black leading-tight tracking-[-0.02em]">
                                <?php echo htmlspecialchars($paginaTitulo); ?>
                            </h1>
                            <p class="text-neutral-600 text-base mt-2">
                                <?php echo htmlspecialchars($paginaDescricao); ?>
                            </p>
                        </section>

                        <!-- Conteúdo -->
                        <section class="px-4">
                            <?php if (!$tabelaExiste): ?>
                                <div class="text-center py-16 bg-white rounded-xl border border-neutral-200">
                                    <i class="ph ph-database text-6xl text-primary/40 mb-4"></i>
                                    <h3 class="text-neutral-800 text-xl font-bold mb-2"><?php echo htmlspecialchars(t('landing.setup_required_title', 'Configuração Necessária')); ?></h3>
                                    <p class="text-neutral-600 text-base mb-6 max-w-md mx-auto">
                                        <?php echo htmlspecialchars(t('landing.setup_required_desc', 'A tabela de artigos ainda não foi criada/configurada.')); ?>
                                    </p>
                                </div>
                            <?php elseif (empty($artigos)): ?>
                                <div class="text-center py-16">
                                    <i class="ph ph-pencil-simple text-6xl text-neutral-300 mb-4"></i>
                                    <h3 class="text-neutral-800 text-xl font-bold mb-2"><?php echo htmlspecialchars(t('landing.soon_title', 'Conteúdos em breve')); ?></h3>
                                    <p class="text-neutral-600 text-base mb-4"><?php echo htmlspecialchars(t('landing.automation.soon_desc', 'Novos conteúdos de Automação & IA serão publicados em breve.')); ?></p>
                                    <a href="<?php echo routeConteudos(); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark transition-colors">
                                        <i class="ph ph-arrow-left text-lg"></i>
                                        <?php echo htmlspecialchars(t('landing.view_all_contents', 'Ver Todos os Conteúdos')); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($artigos as $art): ?>
                                        <a href="<?php echo routeArtigo($art['slug']); ?>"
                                           class="flex flex-col rounded-xl overflow-hidden bg-white border border-neutral-200 group hover:shadow-lg hover:border-primary/30 transition-all">
                                            <?php
                                                $imagem = $art['imagem_capa'] ?? $art['imagem_1x1'] ?? $art['imagem_principal'] ?? null;
                                            ?>
                                            <?php if ($imagem): ?>
                                                <div class="w-full aspect-video bg-cover bg-center bg-neutral-100 overflow-hidden">
                                                    <img src="<?php echo UPLOAD_URL . $imagem; ?>"
                                                         alt="<?php echo htmlspecialchars($art['titulo']); ?>"
                                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                                         loading="lazy">
                                                </div>
                                            <?php else: ?>
                                                <div class="w-full aspect-video bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
                                                    <i class="ph ph-article text-5xl text-primary/40"></i>
                                                </div>
                                            <?php endif; ?>

                                            <div class="p-5 flex flex-col gap-3 flex-grow">
                                                <div class="flex flex-col gap-2 flex-grow">
                                                    <?php if (!empty($art['categoria_nome'])): ?>
                                                        <span class="text-primary text-xs font-bold uppercase tracking-wide">
                                                            <?php echo htmlspecialchars($art['categoria_nome']); ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <h3 class="text-neutral-800 text-lg font-bold leading-tight group-hover:text-primary transition-colors">
                                                        <?php echo htmlspecialchars($art['titulo']); ?>
                                                    </h3>

                                                    <?php if (!empty($art['resumo'])): ?>
                                                        <p class="text-neutral-600 text-sm leading-relaxed line-clamp-2">
                                                            <?php echo limitText(strip_tags($art['resumo']), 120); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="flex items-center justify-between text-xs text-neutral-500">
                                                    <span class="flex items-center gap-1">
                                                        <i class="ph ph-calendar-blank text-base"></i>
                                                        <?php echo formatDate($art['created_at'] ?? date('Y-m-d')); ?>
                                                    </span>
                                                    <span class="inline-flex items-center gap-1 text-primary">
                                                        <?php echo htmlspecialchars(t('actions.read', 'Ler')); ?>
                                                        <i class="ph ph-arrow-right text-base"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </main>

                    <?php require __DIR__ . '/includes/site-footer.php'; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
