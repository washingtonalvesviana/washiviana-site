<?php
$footerSiteTitulo = function_exists('getConfig') ? (getConfig('site_titulo') ?: 'Washington Viana') : 'Washington Viana';
$footerSiteEmail = function_exists('getConfig') ? (getConfig('site_email') ?: 'contato@washiviana.com') : 'contato@washiviana.com';
$footerLinkedinTarget = 'https://www.linkedin.com/in/washington-alves-viana-38583269/';
$footerInstagramTarget = 'https://www.instagram.com/washingtonalvesviana/';
$footerSiteLinkedin = function_exists('getConfig') ? (getConfig('site_linkedin') ?: $footerLinkedinTarget) : $footerLinkedinTarget;
$footerSiteInstagram = function_exists('getConfig') ? (getConfig('site_instagram') ?: $footerInstagramTarget) : $footerInstagramTarget;

if (is_string($footerSiteLinkedin) && preg_match('/linkedin\.com\/in\/washingtonviana/i', $footerSiteLinkedin)) {
    $footerSiteLinkedin = $footerLinkedinTarget;
}
if (is_string($footerSiteInstagram) && preg_match('/instagram\.com\/washiviana/i', $footerSiteInstagram)) {
    $footerSiteInstagram = $footerInstagramTarget;
}
$footerSiteTelefone = function_exists('getConfig') ? (getConfig('site_telefone') ?: '+55 19 9 99422907') : '+55 19 9 99422907';
$footerWhatsappDigits = preg_replace('/\D+/', '', (string)$footerSiteTelefone);
$footerSiteWhatsappLink = $footerWhatsappDigits ? ('https://wa.me/' . $footerWhatsappDigits) : '#';
?>
<footer class="mt-16 md:mt-20 border-t border-solid border-neutral-200 pt-10 pb-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center md:text-left px-4">
        <div class="flex flex-col items-center md:items-start gap-4">
            <a href="<?php echo function_exists('routeHome') ? routeHome() : '/'; ?>" class="flex items-center gap-3 text-neutral-800">
                <img src="<?php echo htmlspecialchars((defined('BASE_URL') ? BASE_URL : '') . '/assets/imgs/logo_washiviana_60px_h.png'); ?>"
                     alt="<?php echo htmlspecialchars($footerSiteTitulo); ?>"
                     width="125" height="60"
                     class="h-6 w-auto">
                <h2 class="text-neutral-800 text-lg font-bold leading-tight tracking-[-0.015em]"><?php echo htmlspecialchars($footerSiteTitulo); ?></h2>
            </a>
            <p class="text-neutral-500 text-sm">
                © <?php echo date('Y'); ?> <?php echo htmlspecialchars($footerSiteTitulo); ?>. <?php echo htmlspecialchars(function_exists('t') ? t('footer.rights', 'Todos os direitos reservados.') : 'Todos os direitos reservados.'); ?>
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="font-bold text-neutral-800 uppercase text-sm tracking-wider"><?php echo htmlspecialchars(function_exists('t') ? t('footer.menu', 'Menu') : 'Menu'); ?></h4>
            <a class="text-neutral-600 text-sm hover:text-primary transition-colors" href="<?php echo function_exists('routeHome') ? routeHome() : '/'; ?>"><?php echo htmlspecialchars(function_exists('t') ? t('nav.home', 'Início') : 'Início'); ?></a>
            <a class="text-neutral-600 text-sm hover:text-primary transition-colors" href="<?php echo function_exists('routeConteudos') ? routeConteudos() : '/conteudos'; ?>"><?php echo htmlspecialchars(function_exists('t') ? t('nav.contents', 'Conteúdos') : 'Conteúdos'); ?></a>
            <a class="text-neutral-600 text-sm hover:text-primary transition-colors" href="<?php echo function_exists('routeProjetos') ? routeProjetos() : '/projetos'; ?>"><?php echo htmlspecialchars(function_exists('t') ? t('nav.projects', 'Projetos') : 'Projetos'); ?></a>
            <a class="text-neutral-600 text-sm hover:text-primary transition-colors" href="<?php echo function_exists('routeSobre') ? routeSobre() : '/sobre'; ?>"><?php echo htmlspecialchars(function_exists('t') ? t('nav.about', 'Sobre') : 'Sobre'); ?></a>
        </div>

        <div class="flex flex-col items-center md:items-start gap-4">
            <h4 class="font-bold text-neutral-800 uppercase text-sm tracking-wider"><?php echo htmlspecialchars(function_exists('t') ? t('nav.contact', 'Contato') : 'Contato'); ?></h4>
            <a class="text-neutral-600 text-sm hover:text-primary transition-colors" href="mailto:<?php echo htmlspecialchars($footerSiteEmail); ?>">
                <?php echo htmlspecialchars($footerSiteEmail); ?>
            </a>
            <div class="flex gap-4">
                <a href="<?php echo htmlspecialchars($footerSiteLinkedin); ?>" target="_blank" rel="noopener" class="text-neutral-500 hover:text-primary transition-colors" title="LinkedIn">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/>
                    </svg>
                </a>
                <a href="<?php echo htmlspecialchars($footerSiteInstagram); ?>" target="_blank" rel="noopener" class="text-neutral-500 hover:text-primary transition-colors" title="Instagram">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8 1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5 5 5 0 0 1-5 5 5 5 0 0 1-5-5 5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/>
                    </svg>
                </a>
                <a href="<?php echo htmlspecialchars($footerSiteWhatsappLink); ?>" target="_blank" rel="noopener" class="text-neutral-500 hover:text-primary transition-colors" title="WhatsApp">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</footer>
