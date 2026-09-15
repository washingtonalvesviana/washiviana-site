<?php
/**
 * WASHIVIANA PORTFOLIO - Lista de Projetos/Conteúdos
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

// Buscar categorias ativas (com i18n)
$lang = CURRENT_LANG;
if ($lang !== 'pt') {
    $stmt = $pdo->query(
        "SELECT c.*, COALESCE(t.nome, c.nome) AS nome_exib
         FROM categorias c
         LEFT JOIN categorias_i18n t ON t.categoria_id = c.id AND t.lang = " . $pdo->quote($lang) . "
         WHERE c.ativo = true
         ORDER BY c.ordem ASC"
    );
} else {
    $stmt = $pdo->query("SELECT * FROM categorias WHERE ativo = true ORDER BY ordem ASC");
}
$categorias = $stmt->fetchAll();
foreach ($categorias as &$catRow) {
    if (isset($catRow['nome_exib'])) $catRow['nome'] = $catRow['nome_exib'];
}
unset($catRow);

// Filtro por categoria ou tag
$categoriaSlug = $_GET['categoria'] ?? null;
$tagSlug = $_GET['tag'] ?? null;
$categoriaAtual = null;

// Buscar projetos
if ($lang !== 'pt') {
    $sql = "SELECT p.*, COALESCE(ci.nome, c.nome) as categoria_nome, c.slug as categoria_slug,
                    COALESCE(t.titulo, p.titulo) AS titulo_exib,
                    COALESCE(t.descricao, p.descricao) AS descricao_exib,
                    COALESCE(t.slug, p.slug) AS slug_exib
            FROM projetos p
            LEFT JOIN projetos_i18n t ON t.projeto_id = p.id AND t.lang = " . $pdo->quote($lang) . "
            LEFT JOIN categorias c ON p.categoria_id = c.id
            LEFT JOIN categorias_i18n ci ON ci.categoria_id = c.id AND ci.lang = " . $pdo->quote($lang) . "
            WHERE p.ativo = true";
} else {
    $sql = "SELECT p.*, c.nome as categoria_nome, c.slug as categoria_slug,
                   p.titulo AS titulo_exib,
                   p.descricao AS descricao_exib,
                   p.slug AS slug_exib
            FROM projetos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.ativo = true";
}

if ($categoriaSlug) {
    if ($lang !== 'pt') {
        $stmt = $pdo->prepare(
            "SELECT c.id, COALESCE(t.nome, c.nome) AS nome
             FROM categorias c
             LEFT JOIN categorias_i18n t ON t.categoria_id = c.id AND t.lang = ?
             WHERE c.slug = ?"
        );
        $stmt->execute([$lang, $categoriaSlug]);
        $categoriaAtual = $stmt->fetch();
    } else {
        $stmt = $pdo->prepare("SELECT id, nome FROM categorias WHERE slug = ?");
        $stmt->execute([$categoriaSlug]);
        $categoriaAtual = $stmt->fetch();
    }
    
    if ($categoriaAtual) {
        $sql .= " AND p.categoria_id = " . intval($categoriaAtual['id']);
    }
}

// Filtro por tag (busca no título, descrição ou tecnologias)
if ($tagSlug) {
    $tagTermo = '%' . $tagSlug . '%';
    $sql .= " AND (titulo_exib ILIKE " . $pdo->quote($tagTermo) . " OR descricao_exib ILIKE " . $pdo->quote($tagTermo) . " OR p.tecnologias ILIKE " . $pdo->quote($tagTermo) . ")";
}

$sql .= " ORDER BY (NULLIF(p.ordem, 0) IS NULL) ASC, NULLIF(p.ordem, 0) ASC NULLS LAST, p.created_at DESC";
$stmt = $pdo->query($sql);
$projetos = $stmt->fetchAll();

foreach ($projetos as &$projetoRow) {
    if (isset($projetoRow['titulo_exib'])) $projetoRow['titulo'] = $projetoRow['titulo_exib'];
    if (isset($projetoRow['descricao_exib'])) $projetoRow['descricao'] = $projetoRow['descricao_exib'];
    if (isset($projetoRow['slug_exib'])) $projetoRow['slug'] = $projetoRow['slug_exib'];
}
unset($projetoRow);

// Título da página
$paginaTitulo = t('projects.page_title_all', 'Projetos Selecionados');
if ($categoriaAtual) {
    $paginaTitulo = $categoriaAtual['nome'];
} elseif ($tagSlug) {
    $paginaTitulo = t_params('search.title', 'Busca: {term}', ['term' => ucfirst($tagSlug)]);
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(htmlLang(CURRENT_LANG)); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($paginaTitulo); ?> - <?php echo htmlspecialchars($siteTitulo); ?></title>
    <meta name="description" content="Projetos e conteúdos de <?php echo htmlspecialchars($siteTitulo); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars(BASE_URL . routeProjetos() . $qsLang); ?>">
    <link rel="alternate" hreflang="pt-BR" href="<?php echo htmlspecialchars(BASE_URL . routeProjetos('pt') . $qsLang); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars(BASE_URL . routeProjetos('en') . $qsLang); ?>">
    <link rel="alternate" hreflang="es" href="<?php echo htmlspecialchars(BASE_URL . routeProjetos('es') . $qsLang); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars(BASE_URL . routeProjetos('pt') . $qsLang); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitulo); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($paginaTitulo . ' - ' . $siteTitulo); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars('Projetos e conteúdos de ' . $siteTitulo); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars(BASE_URL . routeProjetos() . $qsLang); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi1.png'); ?>">
    <meta property="og:image:width" content="1344">
    <meta property="og:image:height" content="756">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($paginaTitulo . ' - ' . $siteTitulo); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars('Projetos e conteúdos de ' . $siteTitulo); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/washi1.png'); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(BASE_URL . '/assets/imgs/favicon_washiviana.png'); ?>">
    
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
	                                    $langLinks[$lc] = ['label' => $label, 'href' => routeProjetos($lc) . $qsLang];
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
	                                            $langLinks[$lc] = ['label' => $label, 'href' => routeProjetos($lc) . $qsLang];
	                                        }
	                                        renderLangSelector($langLinks, CURRENT_LANG, 'center');
	                                    ?>
	                                </div>
	                            </nav>
                    
                    <!-- ========================================
                         MAIN CONTENT
                    ========================================= -->
                    <main class="flex flex-col gap-8 mt-10 md:mt-16">
                        
                        <!-- ========================================
                             TÍTULO DA PÁGINA
                        ========================================= -->
                        <section class="text-center py-8 px-4">
                            <h1 class="text-neutral-800 text-3xl md:text-4xl font-black leading-tight tracking-[-0.02em]">
                                <?php echo htmlspecialchars($paginaTitulo); ?>
                            </h1>
                            <p class="text-neutral-600 text-base mt-2">
                                <?php
                                    $count = (int)count($projetos);
                                    echo htmlspecialchars(
                                        tn(
                                            'projects.count.one',
                                            'projects.count.other',
                                            $count,
                                            '{count} projeto encontrado',
                                            '{count} projetos encontrados'
                                        )
                                    );
                                ?>
                            </p>
                            <?php if (!$categoriaSlug && !$tagSlug): ?>
                                <p class="text-neutral-600 text-base mt-3 max-w-2xl mx-auto leading-relaxed"><?php echo htmlspecialchars(t('projects.intro', 'Estes projetos mostram como transito entre direção criativa, design e execução técnica. Alguns começaram como desafios de comunicação; outros surgiram como problemas de produto ou de engenharia. Em cada caso, trabalhei para tornar a solução clara, utilizável e pronta para o contexto real em que seria usada.')); ?></p>
                                <div class="flex flex-wrap gap-2 justify-center mt-5">
                                    <?php foreach ([
                                        t('projects.label.1', 'IA e automação'),
                                        t('projects.label.2', 'Web e SaaS'),
                                        t('projects.label.3', 'UX/UI'),
                                        t('projects.label.4', '3D e motion'),
                                        t('projects.label.5', 'VR e experiências 360°'),
                                        t('projects.label.6', 'Produção audiovisual'),
                                    ] as $label): ?>
                                    <span class="px-3 py-1 rounded-full bg-neutral-100 text-neutral-700 text-xs font-medium border border-neutral-200"><?php echo htmlspecialchars($label); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                        
                        <!-- ========================================
                             FILTROS POR CATEGORIA
                        ========================================= -->
                        <?php if (!empty($categorias)): ?>
                        <section class="sticky top-24 z-30 bg-background-light/80 backdrop-blur-sm py-4 -mx-4 px-4 sm:-mx-10 sm:px-10">
                            <div class="flex flex-wrap gap-2 justify-center">
                                <a href="<?php echo routeProjetos(); ?>" class="px-4 py-2 rounded-full text-sm font-medium transition-all <?php echo !$categoriaSlug && !$tagSlug ? 'bg-primary text-white' : 'bg-white text-neutral-700 border border-neutral-200 hover:border-primary hover:text-primary'; ?>">
	                                    <?php echo htmlspecialchars(t('filters.all', 'Todos')); ?>
	                                </a>
                                <?php foreach ($categorias as $cat): ?>
	                                <a href="<?php echo routeProjetos(); ?>?categoria=<?php echo urlencode($cat['slug']); ?>" 
                                   class="px-4 py-2 rounded-full text-sm font-medium transition-all <?php echo $categoriaSlug == $cat['slug'] ? 'bg-primary text-white' : 'bg-white text-neutral-700 border border-neutral-200 hover:border-primary hover:text-primary'; ?>">
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endif; ?>
                        
                        <!-- ========================================
                             GRID DE PROJETOS
                        ========================================= -->
                        <section class="px-4">
                            <?php if (empty($projetos)): ?>
                            <!-- Estado vazio -->
                            <div class="text-center py-16">
                                <i class="ph ph-folder-open text-6xl text-neutral-300 mb-4"></i>
                                <p class="text-neutral-600 text-lg mb-4"><?php echo htmlspecialchars(t('projects.empty_category', 'Nenhum projeto encontrado nesta categoria.')); ?></p>
                                <a href="<?php echo routeProjetos(); ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary text-white font-medium hover:bg-primary-dark transition-colors">
                                    <i class="ph ph-arrow-left text-lg"></i>
                                    <?php echo htmlspecialchars(t('projects.view_all', 'Ver Todos')); ?>
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($projetos as $projeto): ?>
                                <a href="<?php echo routeProjeto($projeto['slug']); ?>" 
                                   class="flex flex-col rounded-xl overflow-hidden bg-white border border-neutral-200 group hover:shadow-lg hover:border-primary/30 transition-all">
                                    
                                    <!-- Imagem -->
                                        <?php
                                            $projetoCardMediaUrl = uploadFileUrl($projeto['imagem_principal'] ?? null);
                                        ?>
                                        <?php if (!empty($projeto['imagem_principal']) && $projetoCardMediaUrl): ?>
	                                    <div class="w-full aspect-video bg-cover bg-center bg-neutral-100 overflow-hidden">
	                                        <?php if (isVideoFilename($projeto['imagem_principal'])): ?>
	                                            <video
                                                    src="<?php echo htmlspecialchars($projetoCardMediaUrl); ?>"
	                                                class="w-full h-full object-cover"
	                                                preload="metadata"
	                                                muted
	                                                playsinline></video>
	                                        <?php else: ?>
                                                <img src="<?php echo htmlspecialchars($projetoCardMediaUrl); ?>" 
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
                                    
                                    <!-- Info -->
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
                                        
                                        <!-- Tecnologias -->
                                        <?php if ($projeto['tecnologias']): ?>
                                        <div class="flex flex-wrap gap-2 mt-auto pt-2">
                                            <?php foreach (array_slice(explode(',', $projeto['tecnologias']), 0, 3) as $tech): ?>
                                            <span class="px-2 py-1 bg-primary/10 text-primary text-xs font-medium rounded">
                                                <?php echo htmlspecialchars(trim($tech)); ?>
                                            </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <span class="text-primary text-sm font-bold flex items-center gap-2 group-hover:gap-3 transition-all mt-2">
                                            Ver projeto 
                                            <i class="ph ph-arrow-right text-base"></i>
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </section>

                        <?php if (!$categoriaSlug && !$tagSlug): ?>
                        <!-- Projetos em destaque -->
                        <section class="px-4">
                            <h2 class="text-neutral-800 text-2xl font-bold mb-6 text-center"><?php echo htmlspecialchars(t('projects.feat.title', 'Projetos em destaque')); ?></h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php
                                $featured = [
                                    ['t' => t('projects.feat.1.title', 'DataWise'), 'd' => t('projects.feat.1.desc', 'Plataforma SaaS multi-tenant habilitada por IA, projetada para unificar dados, automatizar processos e permitir que usuários interajam com sistemas por linguagem natural. Atuei na definição de produto, UX/UI, arquitetura full-stack, módulos de IA, controles de governança e implantação. Uma entrega solo alcançou uma plataforma funcional em aproximadamente quatro meses, frente a uma estimativa de seis a nove meses para uma abordagem convencional em equipe.')],
                                    ['t' => t('projects.feat.2.title', 'SEMAD | Goiás Parks VR'), 'd' => t('projects.feat.2.desc', 'Experiência imersiva em VR 360° cobrindo sete parques estaduais de Goiás. O projeto combinou captação em campo, áudio espacial, mascotes 3D, aplicação local multi-headset e efeitos sensoriais sincronizados, como aroma, vento, água e calor. A exposição de 30 dias recebeu mais de 10.000 visitantes.')],
                                    ['t' => t('projects.feat.3.title', 'ABAL / COP30 — Projeção Anamórfica 3D'), 'd' => t('projects.feat.3.desc', 'Projeção anamórfica que traduziu processos de produção de alumínio em uma narrativa visual para um ambiente de evento em grande escala. Criei o pipeline de 3D e pós-produção com Blender, After Effects e DaVinci Resolve.')],
                                    ['t' => t('projects.feat.4.title', 'Glowtech USA'), 'd' => t('projects.feat.4.desc', 'Experiência de marca e digital integrada para painéis solares desenvolvidos para carrinhos de golfe. O trabalho incluiu identidade de marca, mascote 3D, motion graphics, comerciais, site com foco em SEO e um aplicativo móvel interativo com visualização 3D e simulação de energia.')],
                                    ['t' => t('projects.feat.5.title', 'Torcetex — Marca e Mídia'), 'd' => t('projects.feat.5.desc', 'Parceria estratégica de 12 anos cobrindo posicionamento de marca, plataformas digitais, produção audiovisual e comunicação para uma empresa têxtil que trabalha com fibras naturais e sustentabilidade. Este projeto representa colaboração de longo prazo, e não uma entrega isolada.')],
                                    ['t' => t('projects.feat.6.title', 'Lex-Privacy AI — Plataforma de Privacidade LGPD'), 'd' => t('projects.feat.6.desc', 'Plataforma SaaS de governança de dados pessoais e conformidade LGPD: conecta um gateway read-only aos bancos do cliente e revela, por linhagem e mapa de risco, onde estão os dados pessoais, de onde vieram e para onde vão. Monólito modular (FastAPI + React 19), multi-tenant, com agente autônomo (LLM + RAG). Cerca de 90% do desenvolvimento foi assistido por IA.')],
                                    ['t' => t('projects.feat.7.title', 'Infraestrutura de IA Local de Alta Performance'), 'd' => t('projects.feat.7.desc', 'Plataforma de IA privada on-premise em 2 servidores dedicados, em parceria com a IBM AI Factory (Tier 3): LLM com 256K de contexto, 512 sequências e até ~1.300 inferências/s, embeddings, RAG/OCR, geração de mídia e render 3D na mesma GPU. Os dados nunca saem da infraestrutura própria e o custo marginal de inferência é ≈ 0. 120 dias no ar sem ocorrências, com espelho redundante. Configurada com base em pesquisa, testes de laboratório com vários modelos e documentação oficial (Hugging Face, OpenAI, Anthropic, Qwen).')],
                                    ['t' => t('projects.feat.8.title', 'AngloGold Ashanti — Segurança de Barragens em VR 360'), 'd' => t('projects.feat.8.desc', 'Produção imersiva completa para o programa de segurança de barragens: experiência VR 360 interativa em Unity para Meta Quest 3 e versão para sala de projeção mapeada. Roteiro, captação 360 em campo, edição, pós-produção, motion e locução em português e inglês com apoio de IA. O conteúdo cobre CMG, ZAS, mapa de inundação, sistema de sirenes, rotas de fuga e simulação de emergência. Conteúdo audiovisual completo não divulgado por restrições contratuais.')],
                                    ['t' => t('projects.feat.9.title', 'Sun Brazil — Plataforma de Turismo com IA'), 'd' => t('projects.feat.9.desc', 'Plataforma de turismo full-stack para uma agência americana de 30+ anos: site de vendas, portal do cliente, portal de parceiros e CMS proprietário (sem WordPress/Drupal). Arquitetura de três camadas (Next.js 16 / NestJS 11 / FastAPI) com PostgreSQL + pgvector, Redis/BullMQ, 4 idiomas e 2 mercados (BR/US). IA nos dois lados: modo automatizado (RPA) para a agência administrar conteúdo e autoatendimento humanizado com LLM (respostas naturais, ancoradas no catálogo real) para o viajante montar e pesquisar de forma autônoma e rápida, além do orquestrador de pacotes multi-agente. 49 testes na API e suíte E2E com 23 cenários; desenvolvimento orientado a especificação com apoio de IA.')],
                                ];
                                foreach ($featured as $f): ?>
                                <div class="rounded-xl border border-neutral-200 bg-white p-6">
                                    <h3 class="text-neutral-800 text-lg font-bold mb-2"><?php echo htmlspecialchars($f['t']); ?></h3>
                                    <p class="text-neutral-600 text-sm leading-relaxed"><?php echo htmlspecialchars($f['d']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endif; ?>
                        
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
