<?php
/**
 * WASHIVIANA PORTFOLIO - Homepage
 * Layout: Minimal Elegante (Estilo A)
 */
require_once __DIR__ . '/api/config.php';

// Buscar configurações do banco
$siteTitulo = getConfig('site_titulo') ?: 'Washington Viana';
$siteSubtitulo = getConfig('site_subtitulo') ?: 'Tech & IA com linguagem humana';
$fraseImpacto = getConfig('home_frase_impacto') ?: 'Crio soluções que unem tecnologia, criatividade e automação para transformar negócios e pessoas.';
$siteEmail = getConfig('site_email') ?: 'contato@washiviana.com';
$siteLinkedin = getConfig('site_linkedin') ?: 'https://linkedin.com/in/washingtonviana';
$siteInstagram = getConfig('site_instagram') ?: 'https://instagram.com/washiviana';
$siteTelefone = getConfig('site_telefone') ?: '+55 19 9 99422907';
$siteWhatsappDigits = preg_replace('/\D+/', '', (string)$siteTelefone);
$siteWhatsappLink = $siteWhatsappDigits ? ('https://wa.me/' . $siteWhatsappDigits) : '#';

// Mini bio para seção Sobre
$miniBio = getConfig('mini_bio') ?: '+10 anos criando soluções com tecnologia para empresas. Atuo com IA aplicada, automação, design tecnológico e desenvolvimento com foco em produtividade e governança.';

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
        $stmt = $pdo->query("SELECT a.*, ca.nome as categoria_nome
                            FROM artigos a 
                            LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id
                            WHERE a.status_publicacao = 'publicado'
                            ORDER BY a.created_at DESC 
                            LIMIT 3");
        $ultimosConteudos = $stmt->fetchAll();
    } catch (Exception $e) {
        $ultimosConteudos = [];
        error_log("Erro ao buscar últimos conteúdos: " . $e->getMessage());
    }
}

// Categorias para os cards (4 pilares do site)
$categorias = [
    [
        'icon' => 'ph-robot',
        'titulo' => 'Automação & IA',
        'subtexto' => 'Aplicações reais usando tecnologia para otimizar processos.',
        'link' => 'conteudos.php?tag=ia'
    ],
    [
        'icon' => 'ph-trend-up',
        'titulo' => 'Tech Insights',
        'subtexto' => 'Notícias comentadas e análises sobre tecnologia.',
        'link' => 'conteudos.php'
    ],
    [
        'icon' => 'ph-briefcase',
        'titulo' => 'Projetos',
        'subtexto' => 'Projetos que realizei e participei ao longo da carreira.',
        'link' => 'projetos.php'
    ],
    [
        'icon' => 'ph-palette',
        'titulo' => 'Design & Experiências Digitais',
        'subtexto' => 'VR, 3D e design aplicado em soluções inovadoras.',
        'link' => 'projetos.php?tag=design'
    ]
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitulo); ?> - <?php echo htmlspecialchars($siteSubtitulo); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($fraseImpacto); ?>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Literata:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#607AFB",
                        "primary-dark": "#4B5EE0",
                        "background-light": "#f5f6f8",
                        "background-dark": "#0f1323",
                        "neutral-800": "#1F2937",
                        "neutral-700": "#374151",
                        "neutral-600": "#4B5563",
                        "neutral-200": "#E5E7EB",
                        "neutral-100": "#F3F4F6",
                        "neutral-50": "#F9FAFB"
                    },
                    fontFamily: {
                        display: ["Literata", "Georgia", "serif"]
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg: "0.5rem",
                        xl: "0.75rem",
                        full: "9999px"
                    }
                }
            }
        };
    </script>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
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
                        <a href="index.php" class="flex items-center gap-3 text-neutral-800 hover:opacity-80 transition-opacity">
                            <div class="size-5 text-primary">
                                <svg fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor"></path>
                                </svg>
            </div>
                            <h2 class="text-neutral-800 text-lg font-bold leading-tight tracking-[-0.015em]"><?php echo htmlspecialchars($siteTitulo); ?></h2>
                        </a>
                        
                        <!-- Menu Desktop -->
                        <nav class="hidden md:flex flex-1 justify-end gap-8">
                            <div class="flex items-center gap-8">
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="index.php">Início</a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="conteudos.php">Conteúdos</a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="projetos.php">Projetos</a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="sobre.php">Sobre</a>
                                <a class="text-neutral-700 text-sm font-medium leading-normal hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>">Contato</a>
                </div>
                
                            <!-- Ícones Sociais -->
                            <div class="flex gap-2">
                                <a href="<?php echo htmlspecialchars($siteLinkedin); ?>" target="_blank" rel="noopener" title="LinkedIn" class="flex cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 w-10 bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                            <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                        </svg>
                    </a>
                                <a href="<?php echo htmlspecialchars($siteInstagram); ?>" target="_blank" rel="noopener" title="Instagram" class="flex cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 w-10 bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                        <path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/>
                        </svg>
                    </a>
                                <a href="<?php echo htmlspecialchars($siteWhatsappLink); ?>" target="_blank" rel="noopener" title="WhatsApp" class="flex cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 w-10 bg-neutral-100 text-neutral-700 hover:bg-primary hover:text-white transition-colors">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                                        <path d="M16.6 14.4c-.2-.1-1.3-.7-1.5-.8-.2-.1-.4-.1-.6.1-.2.2-.7.8-.8.9-.2.1-.3.2-.6.1-.2-.1-.9-.3-1.7-1-.7-.6-1.1-1.3-1.3-1.6-.1-.2 0-.4.1-.5.1-.1.2-.3.3-.4.1-.1.1-.2.2-.4.1-.1 0-.3 0-.4 0-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.5.3-.2.2-.7.7-.7 1.7s.7 2  .8 2.1c.1.1 1.4 2.2 3.4 3.1.5.2.8.4 1.1.5.5.2 1 .2 1.3.1.4-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1-.1-.1-.3-.2-.5-.3z"></path>
                                        <path d="M20.5 3.5A11.8 11.8 0 0 0 3.2 19.9L2 24l4.2-1.1A11.8 11.8 0 1 0 20.5 3.5zm-8.7 18.3c-1.8 0-3.6-.5-5.1-1.5l-.4-.2-2.5.7.7-2.4-.3-.4a9.8 9.8 0 1 1 7.6 3.8z"></path>
                                    </svg>
                    </a>
                            </div>
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
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="index.php">Início</a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="conteudos.php">Conteúdos</a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="projetos.php">Projetos</a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="sobre.php">Sobre</a>
                        <a class="text-neutral-800 font-medium hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($siteEmail); ?>">Contato</a>
                        <div class="flex gap-4 mt-4">
                            <a href="<?php echo htmlspecialchars($siteLinkedin); ?>" target="_blank" class="text-neutral-600 hover:text-primary"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg></a>
                            <a href="<?php echo htmlspecialchars($siteInstagram); ?>" target="_blank" class="text-neutral-600 hover:text-primary"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></svg></a>
                            <a href="<?php echo htmlspecialchars($siteWhatsappLink); ?>" target="_blank" class="text-neutral-600 hover:text-primary" title="WhatsApp"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M16.6 14.4c-.2-.1-1.3-.7-1.5-.8-.2-.1-.4-.1-.6.1-.2.2-.7.8-.8.9-.2.1-.3.2-.6.1-.2-.1-.9-.3-1.7-1-.7-.6-1.1-1.3-1.3-1.6-.1-.2 0-.4.1-.5.1-.1.2-.3.3-.4.1-.1.1-.2.2-.4.1-.1 0-.3 0-.4 0-.1-.6-1.4-.8-1.9-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.5.3-.2.2-.7.7-.7 1.7s.7 2 .8 2.1c.1.1 1.4 2.2 3.4 3.1.5.2.8.4 1.1.5.5.2 1 .2 1.3.1.4-.1 1.3-.5 1.5-1 .2-.5.2-.9.1-1-.1-.1-.3-.2-.5-.3z"></path><path d="M20.5 3.5A11.8 11.8 0 0 0 3.2 19.9L2 24l4.2-1.1A11.8 11.8 0 1 0 20.5 3.5zm-8.7 18.3c-1.8 0-3.6-.5-5.1-1.5l-.4-.2-2.5.7.7-2.4-.3-.4a9.8 9.8 0 1 1 7.6 3.8z"></path></svg></a>
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
                                        <a href="projetos.php" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-11 px-5 @[480px]:h-12 @[480px]:px-6 bg-primary text-white text-sm font-bold leading-normal tracking-[0.015em] @[480px]:text-base hover:bg-primary-dark transition-colors shadow-md hover:shadow-lg">
                                            <span>Explorar Conteúdos</span>
                                        </a>
                                        <a href="projetos.php" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-11 px-5 @[480px]:h-12 @[480px]:px-6 bg-neutral-100 text-neutral-800 text-sm font-bold leading-normal tracking-[0.015em] @[480px]:text-base hover:bg-neutral-200 transition-colors">
                                            <span>Ver Projetos</span>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Avatar/Imagem -->
                                <div class="flex-shrink-0 mx-auto @[864px]:mx-0">
                                    <div class="w-48 h-48 @[480px]:w-56 @[480px]:h-56 bg-center bg-no-repeat bg-cover rounded-full border-4 border-primary/30 shadow-xl" 
                                         style="background-image: url('assets/imgs/washi2.png');"
                                         title="<?php echo htmlspecialchars($siteTitulo); ?>">
                </div>
            </div>
        </div>
    </section>

                        <!-- ========================================
                             CATEGORIAS - "O que você vai encontrar aqui"
                        ========================================= -->
                        <section>
                            <h2 class="text-neutral-800 text-xl font-bold leading-tight tracking-[-0.015em] px-4 pb-4">
                                O que você vai encontrar aqui
                            </h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4">
                                <?php foreach ($categorias as $cat): ?>
                                <a href="<?php echo $cat['link']; ?>" class="flex flex-1 gap-3 rounded-xl border border-neutral-200 bg-white p-5 flex-col hover:border-primary hover:shadow-md transition-all group">
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
                                Últimos conteúdos
                            </h2>
                            
                            <?php if (!empty($ultimosConteudos)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-4">
                                <?php foreach ($ultimosConteudos as $conteudo): ?>
                                <a href="artigo.php?slug=<?php echo urlencode($conteudo['slug']); ?>" class="flex flex-col rounded-xl overflow-hidden bg-white border border-neutral-200 group hover:shadow-lg transition-all">
                                    <!-- Thumbnail -->
                                    <?php 
                                    $imagem = $conteudo['imagem_1x1'] ?? $conteudo['imagem_principal'] ?? null;
                                    if ($imagem): ?>
                                    <div class="w-full aspect-video bg-cover bg-center bg-neutral-100" 
                                         style="background-image: url('<?php echo UPLOAD_URL . $imagem; ?>');">
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
                            <?php else: ?>
                            <div class="text-center py-12 px-4">
                                <i class="ph ph-article text-5xl text-neutral-300 mb-4"></i>
                                <p class="text-neutral-500">Conteúdos em breve...</p>
                                <a href="conteudos.php" class="text-primary text-sm font-medium mt-2 inline-block hover:underline">Ver todos os conteúdos →</a>
                            </div>
                            <?php endif; ?>
                        </section>
                        
                        <!-- ========================================
                             SEÇÃO SOBRE - Mini Bio
                        ========================================= -->
                        <section class="bg-neutral-50 rounded-xl p-8 md:p-12 text-center border border-neutral-200">
                            <div class="max-w-2xl mx-auto flex flex-col items-center gap-6">
                                <h2 class="text-neutral-800 text-2xl md:text-3xl font-bold leading-tight">
                                    Um pouco sobre mim
                                </h2>
                                <p class="text-neutral-600 text-base leading-relaxed">
                                    <?php echo htmlspecialchars($miniBio); ?>
                                </p>
                                <a href="sobre.php" class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 px-6 bg-primary text-white text-base font-bold leading-normal tracking-[0.015em] hover:bg-primary-dark transition-colors mt-2 shadow-md hover:shadow-lg">
                                    <span>Conheça minha trajetória</span>
                                </a>
                            </div>
                        </section>
                        
                        <!-- ========================================
                             SOCIAL PROOF - "Já criei com/para"
                        ========================================= -->
                        <section>
                            <div class="py-8 text-center">
                                <h3 class="text-neutral-500 text-sm font-semibold tracking-wider uppercase mb-8">
                                    Já criei com/para
                                </h3>
                                <div class="flex flex-wrap justify-center items-center gap-8 md:gap-12 opacity-50 grayscale hover:grayscale-0 hover:opacity-100 transition-all duration-500">
                                    <!-- AWS -->
                                    <div class="flex items-center gap-2 text-neutral-600">
                                        <svg viewBox="0 0 80 48" class="h-8" fill="currentColor">
                                            <path d="M23.2 26.1c0 .9.1 1.6.3 2.2.2.5.5 1.1.9 1.7.1.2.2.4.2.5 0 .2-.1.4-.4.7l-1.2.8c-.2.1-.3.2-.5.2-.2 0-.4-.1-.6-.3-.3-.3-.5-.6-.7-.9-.2-.3-.4-.7-.6-1.1-1.6 1.9-3.6 2.8-6 2.8-1.7 0-3.1-.5-4.1-1.5-1-.9-1.5-2.2-1.5-3.8 0-1.7.6-3 1.8-4 1.2-1 2.8-1.5 4.8-1.5.7 0 1.4.1 2.1.2.8.1 1.5.3 2.3.5v-1.4c0-1.5-.3-2.6-.9-3.2-.6-.6-1.7-.9-3.2-.9-.7 0-1.4.1-2.1.3-.7.2-1.5.4-2.2.8-.3.2-.6.2-.7.3-.1 0-.2.1-.3.1-.3 0-.4-.2-.4-.6v-.9c0-.3 0-.6.1-.7.1-.1.3-.3.6-.4.7-.4 1.6-.7 2.5-.9.9-.2 1.9-.3 3-.3 2.3 0 4 .5 5.1 1.6 1.1 1.1 1.6 2.7 1.6 4.9v6.4zm-8.3 3.1c.7 0 1.4-.1 2.1-.4.7-.3 1.4-.7 1.9-1.3.3-.4.6-.8.7-1.3.1-.5.2-1.1.2-1.8v-.9c-.6-.2-1.2-.3-1.9-.4-.7-.1-1.3-.2-2-.2-1.3 0-2.3.3-2.9.8-.6.5-.9 1.3-.9 2.3 0 .9.2 1.6.7 2.1.5.4 1.2.7 2.1.7zm16.4 2.2c-.4 0-.6-.1-.8-.2-.2-.2-.4-.5-.5-.9l-5.8-19.1c-.1-.5-.2-.8-.2-.9 0-.4.2-.6.6-.6h1.9c.4 0 .7.1.8.2.2.2.3.5.4.9l4.1 16.3 3.9-16.3c.1-.5.2-.8.4-.9.2-.2.5-.2.9-.2h1.6c.4 0 .7.1.9.2.2.2.3.5.4.9l3.9 16.5 4.2-16.5c.1-.5.2-.8.4-.9.2-.2.5-.2.8-.2h1.8c.4 0 .6.2.6.6 0 .1 0 .3-.1.4 0 .2-.1.4-.2.6l-5.9 19.1c-.1.5-.3.8-.5.9-.2.2-.5.2-.8.2h-1.7c-.4 0-.7-.1-.9-.2-.2-.2-.3-.5-.4-.9l-3.8-15.9-3.8 15.8c-.1.5-.2.8-.4.9-.2.2-.5.2-.9.2h-1.7zm26.3.6c-1 0-2.1-.1-3-.4-.9-.3-1.7-.6-2.2-1-.3-.2-.5-.5-.6-.7-.1-.2-.1-.5-.1-.7v-1c0-.4.2-.6.5-.6.1 0 .3 0 .4.1.1.1.3.1.5.2.7.3 1.4.6 2.2.8.8.2 1.5.3 2.3.3 1.2 0 2.2-.2 2.8-.6.6-.4 1-.9 1-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.9-.6-1.8-.9l-2.5-.8c-1.3-.4-2.3-1-2.9-1.8-.6-.8-.9-1.6-.9-2.6 0-.8.2-1.4.5-2 .3-.6.8-1.1 1.3-1.5.5-.4 1.2-.7 1.9-.9.7-.2 1.5-.3 2.3-.3.4 0 .8 0 1.3.1.4.1.8.2 1.2.3.4.1.7.2 1.1.4.3.1.6.3.8.4.2.1.4.3.5.5.1.2.1.4.1.7v.9c0 .4-.2.6-.5.6-.2 0-.5-.1-.9-.3-1.3-.6-2.7-.9-4.3-.9-1.1 0-2 .2-2.6.5-.6.4-.8.9-.8 1.6 0 .5.2.9.5 1.2.4.3 1 .6 1.9.9l2.4.8c1.3.4 2.2 1 2.8 1.7.6.7.9 1.6.9 2.5 0 .8-.2 1.5-.5 2.1-.3.6-.8 1.1-1.4 1.5-.6.4-1.2.7-2 .9-.9.3-1.7.4-2.7.4z"/>
                                        </svg>
                                        <span class="font-semibold">AWS</span>
                                    </div>
                                    
                                    <!-- Google -->
                                    <div class="flex items-center gap-2 text-neutral-600">
                                        <svg viewBox="0 0 24 24" class="h-6" fill="currentColor">
                                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                        </svg>
                                        <span class="font-semibold">Google</span>
                                    </div>
                                    
                                    <!-- Slack -->
                                    <div class="flex items-center gap-2 text-neutral-600">
                                        <svg viewBox="0 0 24 24" class="h-6" fill="currentColor">
                                            <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/>
                                        </svg>
                                        <span class="font-semibold">Slack</span>
                                    </div>
                                    
                                    <!-- Microsoft -->
                                    <div class="flex items-center gap-2 text-neutral-600">
                                        <svg viewBox="0 0 24 24" class="h-5" fill="currentColor">
                                            <path d="M0 0h11.377v11.372H0zm12.623 0H24v11.372H12.623zM0 12.623h11.377V24H0zm12.623 0H24V24H12.623"/>
                                        </svg>
                                        <span class="font-semibold">Microsoft</span>
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
</body>
</html>
