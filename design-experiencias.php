<?php
/**
 * WASHIVIANA PORTFOLIO - Landing: Design & Experiências Digitais
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

$paginaTitulo = t('landing.design.title', 'Design & Experiências Digitais');
$paginaDescricao = t('landing.design.desc', 'VR, 3D, UI/UX e experiências interativas — projetos com foco em estética, usabilidade e impacto.');

// Forçar filtro por tag "design" (busca em título/descrição/tecnologias)
$tagSlug = 'design';
$tagTermo = '%' . $tagSlug . '%';

// Buscar projetos (mesma lógica do projetos.php, com filtro fixo + i18n)
$lang = CURRENT_LANG;
if ($lang !== 'pt') {
    $sql = "SELECT p.*, COALESCE(ci.nome, c.nome) as categoria_nome, c.slug as categoria_slug,
                   COALESCE(t.titulo, p.titulo) AS titulo_exib,
                   COALESCE(t.descricao, p.descricao) AS descricao_exib,
                   COALESCE(t.slug, p.slug) AS slug_exib
            FROM projetos p
            LEFT JOIN projetos_i18n t ON t.projeto_id = p.id AND t.lang = " . $pdo->quote($lang) . "
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN categorias_i18n ci ON ci.categoria_id = c.id AND ci.lang = " . $pdo->quote($lang) . "
            WHERE p.ativo = true
              AND (COALESCE(t.titulo, p.titulo) ILIKE " . $pdo->quote($tagTermo) . "
                   OR COALESCE(t.descricao, p.descricao) ILIKE " . $pdo->quote($tagTermo) . "
                   OR p.tecnologias ILIKE " . $pdo->quote($tagTermo) . ")
	            ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC";
} else {
    $sql = "SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug,
                   p.titulo AS titulo_exib,
                   p.descricao AS descricao_exib,
                   p.slug AS slug_exib
            FROM projetos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.ativo = true
              AND (p.titulo ILIKE " . $pdo->quote($tagTermo) . "
                   OR p.descricao ILIKE " . $pdo->quote($tagTermo) . "
                   OR p.tecnologias ILIKE " . $pdo->quote($tagTermo) . ")
	            ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC";
}

$projetos = [];
try {
    $stmt = $pdo->query($sql);
    $projetos = $stmt->fetchAll();

    foreach ($projetos as &$projRow) {
        if (isset($projRow['titulo_exib'])) $projRow['titulo'] = $projRow['titulo_exib'];
        if (isset($projRow['descricao_exib'])) $projRow['descricao'] = $projRow['descricao_exib'];
        if (isset($projRow['slug_exib'])) $projRow['slug'] = $projRow['slug_exib'];
    }
    unset($projRow);
} catch (Exception $e) {
    $projetos = [];
    error_log("Erro ao buscar projetos Design: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(htmlLang(CURRENT_LANG)); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paginaTitulo); ?> - <?php echo htmlspecialchars($siteTitulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($paginaDescricao); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/design-experiencias')); ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/design-experiencias', 'pt')); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/design-experiencias', 'en')); ?>">
    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/design-experiencias', 'es')); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . urlPath('/design-experiencias', 'pt')); ?>">
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
	                                    $langLinks[$lc] = ['label' => $label, 'href' => urlPath('/design-experiencias', $lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'right');
	                            ?>
	                        </div>
	                    </header>

                    <main class="flex flex-col gap-10 mt-8 md:mt-12">
                        <!-- Título -->
                        <section class="text-center py-6 px-4">
                            <div class="flex items-center justify-center gap-2 text-primary mb-3">
                                <i class="ph ph-palette text-2xl"></i>
                                <span class="text-sm font-bold uppercase tracking-wider"><?php echo htmlspecialchars(t('landing.design.kicker', 'Design')); ?></span>
                            </div>
                            <h1 class="text-neutral-800 text-3xl md:text-4xl font-black leading-tight tracking-[-0.02em]">
                                <?php echo htmlspecialchars($paginaTitulo); ?>
                            </h1>
                            <p class="text-neutral-600 text-base mt-2">
                                <?php echo htmlspecialchars($paginaDescricao); ?>
                            </p>
                        </section>

                        <!-- Grid -->
                        <section class="px-4">
                            <?php if (empty($projetos)): ?>
                                <div class="text-center py-16">
                                    <i class="ph ph-folder-open text-6xl text-neutral-300 mb-4"></i>
                                    <h3 class="text-neutral-800 text-xl font-bold mb-2"><?php echo htmlspecialchars(t('landing.design.empty_title', 'Nenhum projeto encontrado')); ?></h3>
                                    <p class="text-neutral-600 text-base mb-4"><?php echo htmlspecialchars(t('landing.design.empty_desc', 'Em breve mais projetos de design e experiências digitais por aqui.')); ?></p>
                                    <a href="<?php echo routeProjetos(); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark transition-colors">
                                        <i class="ph ph-arrow-left text-lg"></i>
                                        <?php echo htmlspecialchars(t('landing.view_all_projects', 'Ver Todos os Projetos')); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($projetos as $projeto): ?>
                                        <a href="<?php echo routeProjeto($projeto['slug']); ?>"
                                           class="flex flex-col rounded-xl overflow-hidden bg-white border border-neutral-200 group hover:shadow-lg hover:border-primary/30 transition-all">

	                                            <?php if ($projeto['imagem_principal']): ?>
	                                                <div class="w-full aspect-video bg-cover bg-center bg-neutral-100 overflow-hidden">
	                                                    <?php if (isVideoFilename($projeto['imagem_principal'])): ?>
	                                                        <video
	                                                            src="<?php echo UPLOAD_URL . $projeto['imagem_principal']; ?>"
	                                                            class="w-full h-full object-cover"
	                                                            preload="metadata"
	                                                            muted
	                                                            playsinline></video>
	                                                    <?php else: ?>
	                                                        <img src="<?php echo UPLOAD_URL . $projeto['imagem_principal']; ?>"
	                                                             alt="<?php echo htmlspecialchars($projeto['titulo']); ?>"
	                                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
	                                                             loading="lazy">
	                                                    <?php endif; ?>
	                                                </div>
	                                            <?php else: ?>
                                                <div class="w-full aspect-video bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
                                                    <i class="ph ph-folder text-5xl text-primary/40"></i>
                                                </div>
                                            <?php endif; ?>

                                            <div class="p-5 flex flex-col gap-3 flex-grow">
                                                <div class="flex flex-col gap-2 flex-grow">
                                                    <?php if ($projeto['categoria_nome']): ?>
                                                        <span class="text-primary text-xs font-bold uppercase tracking-wide">
                                                            <?php echo htmlspecialchars($projeto['categoria_nome']); ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <h3 class="text-neutral-800 text-lg font-bold leading-tight group-hover:text-primary transition-colors">
                                                        <?php echo htmlspecialchars($projeto['titulo']); ?>
                                                    </h3>

                                                    <?php if ($projeto['descricao']): ?>
                                                        <p class="text-neutral-600 text-sm leading-relaxed line-clamp-2">
                                                            <?php echo limitText(strip_tags($projeto['descricao']), 100); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="flex items-center justify-between text-xs text-neutral-500">
                                                    <span class="flex items-center gap-1">
                                                        <i class="ph ph-calendar-blank text-base"></i>
                                                        <?php echo formatDate($projeto['created_at'] ?? date('Y-m-d')); ?>
                                                    </span>
                                                    <span class="inline-flex items-center gap-1 text-primary">
                                                        <?php echo htmlspecialchars(t('actions.view', 'Ver')); ?>
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
