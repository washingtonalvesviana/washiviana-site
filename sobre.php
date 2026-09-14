<?php
/**
 * WASHIVIANA PORTFOLIO - Página Sobre
 * Layout: Minimal Elegante (Estilo A)
 */
require_once __DIR__ . '/api/config.php';

// Buscar configurações
$siteTitulo = getConfig('site_titulo') ?: 'Washington Viana';
$siteSubtitulo = getConfigI18n('site_subtitulo') ?: 'Tech & IA com linguagem humana';
$siteEmail = getConfig('site_email') ?: 'contato@washiviana.com';
$siteTelefone = getConfig('site_telefone') ?: '+55 19 9 9942 2907';
$siteLinkedin = getConfig('site_linkedin') ?: 'https://linkedin.com/in/washingtonviana';
$siteInstagram = getConfig('site_instagram') ?: 'https://instagram.com/washiviana';
$siteWhatsappDigits = preg_replace('/\D+/', '', (string)$siteTelefone);
$siteWhatsappLink = $siteWhatsappDigits ? ('https://wa.me/' . $siteWhatsappDigits) : '#';
$qsLang = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';

// Áreas de expertise
$expertises = [
    [
        'icon' => 'ph-robot',
        'titulo' => t('about.expertise.ai.title', 'Inteligência Artificial'),
        'descricao' => t('about.expertise.ai.desc', 'Integração de IA em projetos digitais. Aproveitando o poder da inteligência artificial para criar soluções inovadoras e automatizadas.')
    ],
    [
        'icon' => 'ph-gears',
        'titulo' => t('about.expertise.automation.title', 'Automação'),
        'descricao' => t('about.expertise.automation.desc', 'Automação de processos empresariais com foco em produtividade. Criação de workflows inteligentes que economizam tempo e recursos.')
    ],
    [
        'icon' => 'ph-code',
        'titulo' => t('about.expertise.dev.title', 'Desenvolvimento'),
        'descricao' => t('about.expertise.dev.desc', 'Desenvolvimento completo de websites e aplicativos. Do frontend ao backend, criando soluções digitais robustas e escaláveis.')
    ],
    [
        'icon' => 'ph-cube',
        'titulo' => t('about.expertise.design3d.title', 'Design & 3D'),
        'descricao' => t('about.expertise.design3d.desc', 'Design gráfico, motion design e modelagem 3D. Criação de experiências visuais impactantes que elevam a comunicação.')
    ],
    [
        'icon' => 'ph-hand-pointing',
        'titulo' => t('about.expertise.uxui.title', 'UX/UI Design'),
        'descricao' => t('about.expertise.uxui.desc', 'Design de experiência e interface focado no usuário. Criação de interfaces intuitivas que proporcionam jornadas memoráveis.')
    ],
    [
        'icon' => 'ph-cloud',
        'titulo' => t('about.expertise.cloud.title', 'Cloud & DevOps'),
        'descricao' => t('about.expertise.cloud.desc', 'Arquitetura cloud e práticas DevOps. Infraestrutura escalável e deploy contínuo para aplicações modernas.')
    ]
];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(htmlLang(CURRENT_LANG)); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('page.about.title', 'Sobre')); ?> - <?php echo htmlspecialchars($siteTitulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(t_params('page.about.meta_desc', 'Conheça mais sobre {name} - {subtitle}', ['name' => $siteTitulo, 'subtitle' => $siteSubtitulo])); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars(BASE_URL . routeSobre()); ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . routeSobre('pt')); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . routeSobre('en')); ?>">
    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . routeSobre('es')); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . routeSobre('pt')); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitulo); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars(t('page.about.title', 'Sobre') . ' - ' . $siteTitulo); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(t_params('page.about.meta_desc', 'Conheça mais sobre {name} - {subtitle}', ['name' => $siteTitulo, 'subtitle' => $siteSubtitulo])); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars(BASE_URL . routeSobre()); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi1.png'); ?>">
    <meta property="og:image:width" content="1344">
    <meta property="og:image:height" content="756">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars(t('page.about.title', 'Sobre') . ' - ' . $siteTitulo); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars(t_params('page.about.meta_desc', 'Conheça mais sobre {name} - {subtitle}', ['name' => $siteTitulo, 'subtitle' => $siteSubtitulo])); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi1.png'); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    
    <!-- Tailwind CSS (build local) -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . '/assets/css/tailwind.min.css?v=' . assetVersion('assets/css/tailwind.min.css')); ?>">
    <link rel="preload" as="image"
          href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-280.webp'); ?>"
          imagesrcset="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-280.webp'); ?> 280w, <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-360.webp'); ?> 360w, <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-420.webp'); ?> 420w"
          imagesizes="(min-width: 480px) 256px, 192px"
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
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
                                <a class="text-primary text-sm font-bold leading-normal" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                            </div>
	                            
	                            <!-- Idiomas -->
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    $langLinks[$lc] = ['label' => $label, 'href' => routeSobre($lc) . $qsLang];
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
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="<?php echo routeProjetos(); ?>"><?php echo htmlspecialchars(t('nav.projects', 'Projetos')); ?></a>
                        <a class="text-primary font-bold" href="<?php echo routeSobre(); ?>"><?php echo htmlspecialchars(t('nav.about', 'Sobre')); ?></a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>"><?php echo htmlspecialchars(t('nav.contact', 'Contato')); ?></a>
	                        <div class="pt-2">
	                            <?php
	                                $langLinks = [];
	                                foreach (['pt' => 'PT', 'en' => 'EN', 'es' => 'ES'] as $lc => $label) {
	                                    $langLinks[$lc] = ['label' => $label, 'href' => routeSobre($lc) . $qsLang];
	                                }
	                                renderLangSelector($langLinks, CURRENT_LANG, 'center');
	                            ?>
	                        </div>
	                    </nav>
                    
                    <!-- ========================================
                         MAIN CONTENT
                    ========================================= -->
                    <main class="flex flex-col gap-16 md:gap-20 mt-10 md:mt-16">
                        
                        <!-- ========================================
                             HERO - Sobre Mim
                        ========================================= -->
                        <section class="@container">
                            <div class="flex flex-col gap-8 px-4 py-8 @[864px]:flex-row @[864px]:items-start @[864px]:gap-12">
                                <!-- Foto -->
	                                <div class="flex-shrink-0 mx-auto @[864px]:mx-0 @[864px]:sticky @[864px]:top-32">
	                                    <picture>
	                                        <source
	                                            type="image/webp"
	                                            srcset="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-280.webp'); ?> 280w,
	                                                    <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-360.webp'); ?> 360w,
	                                                    <?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg-420.webp'); ?> 420w"
	                                            sizes="(min-width: 480px) 256px, 192px"
	                                        >
	                                        <img
	                                            src="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi_4_no_bg.png?v=' . assetVersion('assets/imgs/washi_4_no_bg.png')); ?>"
	                                            alt="<?php echo htmlspecialchars(t_params('about.photo_alt', 'Foto de {name}', ['name' => $siteTitulo])); ?>"
	                                            width="412" height="538"
	                                            class="block w-48 @[480px]:w-64 max-w-full h-auto"
	                                            loading="eager"
	                                            fetchpriority="high"
	                                            decoding="async"
	                                        >
	                                    </picture>
	                                </div>
                                
                                <!-- Conteúdo -->
                                <div class="flex flex-col gap-5 text-center @[864px]:text-left flex-1">
                                    <div>
                                        <p class="text-primary text-sm font-bold uppercase tracking-wider mb-2"><?php echo htmlspecialchars(t('about.kicker', 'Sobre Mim')); ?></p>
                                        <h1 class="text-neutral-800 text-3xl @[480px]:text-4xl font-black leading-tight tracking-[-0.02em]">
                                            <?php echo htmlspecialchars($siteTitulo); ?>
                                        </h1>
                                        <p class="text-primary text-lg font-semibold mt-1">
                                            <?php echo htmlspecialchars($siteSubtitulo); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="flex flex-col gap-4 text-neutral-600 text-base leading-relaxed">
                                        <p><?php echo t('about.p1', 'Sou Creative Technologist e desenvolvedor de produtos com IA, com mais de 29 anos de experiência prática em design gráfico, motion design, 3D, UX/UI, produção audiovisual e desenvolvimento de software.'); ?></p>
                                        <p><?php echo t('about.p2', 'Minha carreira começou na comunicação visual e avançou para produtos digitais, desenvolvimento web, aplicações interativas, Unity, realidade virtual e automação. Essa trajetória me permite analisar um projeto por mais de um ângulo: o que ele precisa comunicar, como será usado e como pode ser construído e mantido.'); ?></p>
                                        <p><?php echo t('about.p3', 'Hoje, a inteligência artificial faz parte do meu fluxo de trabalho diário. Uso IA em pesquisa, planejamento, prototipação, programação, documentação, testes e tarefas repetitivas. Também crio minhas próprias ferramentas e fluxos quando uma solução pronta não atende ao problema.'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </section>
                        
                        <!-- ========================================
                             ÁREAS DE EXPERTISE
                        ========================================= -->
                        <section>
                            <h2 class="text-neutral-800 text-xl font-bold leading-tight tracking-[-0.015em] px-4 pb-6 text-center">
                                <?php echo htmlspecialchars(t('about.expertise.title', 'Áreas de Expertise')); ?>
                            </h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                                <?php foreach ($expertises as $exp): ?>
                                <div class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-6 hover:border-primary hover:shadow-md transition-all group">
                                    <div class="text-primary group-hover:scale-110 transition-transform">
                                        <i class="ph <?php echo $exp['icon']; ?> text-3xl"></i>
                                    </div>
                                    <h3 class="text-neutral-800 text-lg font-bold leading-tight"><?php echo $exp['titulo']; ?></h3>
                                    <p class="text-neutral-600 text-sm leading-relaxed"><?php echo $exp['descricao']; ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        
                        <!-- ========================================
                             O QUE EU OFEREÇO
                        ========================================= -->
                        <section>
                            <h2 class="text-neutral-800 text-xl font-bold leading-tight tracking-[-0.015em] mb-6 text-center">
                                <?php echo htmlspecialchars(t('about.bring.title', 'O que eu ofereço')); ?>
                            </h2>
                            <div class="max-w-3xl mx-auto grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php
                                $aboutBring = [
                                    t('about.bring.1', 'Visão de ponta a ponta, da ideia à entrega funcional'),
                                    t('about.bring.2', 'Experiência conectando design, comunicação e software'),
                                    t('about.bring.3', 'Fluxos assistidos por IA com revisão humana e controles práticos'),
                                    t('about.bring.4', 'Criação de ferramentas reutilizáveis para reduzir tarefas repetitivas'),
                                    t('about.bring.5', 'Comunicação clara com equipes técnicas e não técnicas'),
                                    t('about.bring.6', 'Abordagem pragmática para qualidade, privacidade, segurança e entrega'),
                                ];
                                foreach ($aboutBring as $item): ?>
                                <div class="flex items-start gap-3 rounded-lg border border-neutral-200 bg-white p-4">
                                    <i class="ph ph-check-circle text-primary text-xl mt-0.5"></i>
                                    <span class="text-neutral-600 text-sm leading-relaxed"><?php echo htmlspecialchars($item); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        
                        <!-- ========================================
                             FILOSOFIA
                        ========================================= -->
                        <section class="bg-neutral-50 rounded-xl p-8 md:p-10 border border-neutral-200">
                            <h2 class="text-neutral-800 text-xl font-bold leading-tight tracking-[-0.015em] mb-6 text-center">
                                <?php echo htmlspecialchars(t('about.philosophy.title', 'Como eu trabalho')); ?>
                            </h2>
                            <div class="max-w-2xl mx-auto flex flex-col gap-5 text-neutral-600 text-base leading-relaxed">
                                <p><?php echo t('about.philosophy.p1', 'Uso IA como multiplicador de desempenho, não como substituta do julgamento profissional. Eu reviso, testo e adapto os resultados ao contexto real de cada projeto. Quando privacidade e controle de dados são importantes, prefiro modelos executados localmente e evito enviar informações sensíveis para serviços em nuvem.'); ?></p>
                                <p><?php echo t('about.philosophy.p2', 'Meu trabalho inclui produtos SaaS com IA, sistemas de automação, aplicações web, UX/UI, motion graphics, 3D, conteúdo 360° e experiências de realidade virtual. Sou mais eficaz em projetos que precisam de alguém capaz de conectar direção criativa com execução técnica.'); ?></p>
                                
                                <blockquote class="bg-white border-l-4 border-primary px-6 py-5 rounded-r-lg text-neutral-700 italic mt-4">
                                    "<?php echo t('about.philosophy.quote', 'Seja revitalizando sua presença digital, implementando soluções de IA de ponta ou revolucionando seus processos com automação, estou aqui para trazer excelência prática para cada empreendimento. Vamos explorar as possibilidades ilimitadas juntos.'); ?>"
                                </blockquote>
                            </div>
                        </section>
                        
                        <!-- ========================================
                             CTA - Vamos Trabalhar Juntos
                        ========================================= -->
                        <section class="bg-gradient-to-br from-primary to-primary-dark rounded-xl p-8 md:p-12 text-center text-white">
                            <div class="max-w-xl mx-auto flex flex-col items-center gap-6">
                                <h2 class="text-2xl md:text-3xl font-bold leading-tight">
                                    <?php echo htmlspecialchars(t('about.cta.title', 'Tem uma ideia complexa que precisa de pensamento criativo e técnico ao mesmo tempo?')); ?>
                                </h2>
                                <p class="text-white/90 text-base leading-relaxed">
                                    <?php echo htmlspecialchars(t('about.cta.desc', 'Fale comigo e vamos transformá-la em uma solução clara e funcional.')); ?>
                                </p>
                                <div class="flex flex-wrap gap-3 justify-center mt-2">
                                    <a href="mailto:<?php echo htmlspecialchars($siteEmail); ?>" class="flex items-center gap-2 min-w-[84px] cursor-pointer justify-center overflow-hidden rounded-lg h-12 px-6 bg-white text-primary text-base font-bold leading-normal hover:bg-neutral-100 transition-colors shadow-md">
                                        <i class="ph ph-envelope text-xl"></i>
                                        <span><?php echo htmlspecialchars(t('about.cta.email', 'Enviar Email')); ?></span>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($siteLinkedin); ?>" target="_blank" class="flex items-center gap-2 min-w-[84px] cursor-pointer justify-center overflow-hidden rounded-lg h-12 px-6 bg-white/20 text-white text-base font-bold leading-normal hover:bg-white/30 transition-colors">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                            <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                                        </svg>
                                        <span>LinkedIn</span>
                                    </a>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $siteTelefone); ?>" target="_blank" class="flex items-center gap-2 min-w-[84px] cursor-pointer justify-center overflow-hidden rounded-lg h-12 px-6 bg-white/20 text-white text-base font-bold leading-normal hover:bg-white/30 transition-colors">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars(t('about.cta.whatsapp', 'WhatsApp')); ?></span>
                                    </a>
                                </div>
                            </div>
                        </section>
                        
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
