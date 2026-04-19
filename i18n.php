<?php
/**
 * i18n helpers (pt/en/es) + URL helpers.
 */

function normalizeLang(?string $lang): string {
    $lang = strtolower(trim((string)$lang));
    if ($lang === 'pt-br' || $lang === 'pt_br') return 'pt';
    if ($lang === 'en-us' || $lang === 'en_us') return 'en';
    if ($lang === 'es-es' || $lang === 'es_es') return 'es';
    if (in_array($lang, ['pt', 'en', 'es'], true)) return $lang;
    return 'pt';
}

function htmlLang(string $lang): string {
    switch ($lang) {
        case 'en':
            return 'en';
        case 'es':
            return 'es';
        default:
            return 'pt-BR';
    }
}

function detectLangFromPath(string $path): ?string {
    if (preg_match('~^/(pt|en|es)(?:/|$)~', $path, $m)) {
        return $m[1];
    }
    return null;
}

function detectLangFromAcceptLanguage(?string $header): ?string {
    if (!$header) return null;
    $header = strtolower($header);
    // Basic parsing: take first lang tag.
    $first = trim(explode(',', $header)[0] ?? '');
    if ($first === '') return null;
    if (str_starts_with($first, 'pt')) return 'pt';
    if (str_starts_with($first, 'en')) return 'en';
    if (str_starts_with($first, 'es')) return 'es';
    return null;
}

function currentLang(): string {
    if (defined('CURRENT_LANG')) return CURRENT_LANG;
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $fromPath = detectLangFromPath($path);
    if ($fromPath) return normalizeLang($fromPath);
    $cookie = $_COOKIE['lang'] ?? null;
    if ($cookie) return normalizeLang($cookie);
    $accept = detectLangFromAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
    if ($accept) return normalizeLang($accept);
    return 'pt';
}

function setLangCookie(string $lang): void {
    $lang = normalizeLang($lang);
    // 6 months
    setcookie('lang', $lang, [
        'expires' => time() + 60 * 60 * 24 * 180,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function stripLangPrefix(string $path): string {
    $result = preg_replace('~^/(pt|en|es)(?=/|$)~', '', $path);
    return $result === null ? $path : $result;
}

function langPrefix(?string $lang = null): string {
    $lang = normalizeLang($lang ?: currentLang());
    return '/' . $lang;
}

function urlPath(string $path, ?string $lang = null): string {
    $path = '/' . ltrim($path, '/');
    $lang = normalizeLang($lang ?: currentLang());
    return '/' . $lang . ($path === '/' ? '/' : $path);
}

function routeHome(?string $lang = null): string {
    return urlPath('/', $lang);
}

function routeConteudos(?string $lang = null): string {
    return urlPath('/conteudos', $lang);
}

function routeProjetos(?string $lang = null): string {
    return urlPath('/projetos', $lang);
}

function routeSobre(?string $lang = null): string {
    return urlPath('/sobre', $lang);
}

function routeArtigo(string $slug, ?string $lang = null): string {
    $lang = normalizeLang($lang ?: currentLang());
    $prefix = 'artigo';
    switch ($lang) {
        case 'en':
            $prefix = 'article';
            break;
        case 'es':
            $prefix = 'articulo';
            break;
        default:
            $prefix = 'artigo';
            break;
    }
    return urlPath('/' . $prefix . '/' . rawurlencode($slug), $lang);
}

function routeProjeto(string $slug, ?string $lang = null): string {
    $lang = normalizeLang($lang ?: currentLang());
    $prefix = 'projeto';
    switch ($lang) {
        case 'en':
            $prefix = 'project';
            break;
        case 'es':
            $prefix = 'proyecto';
            break;
        default:
            $prefix = 'projeto';
            break;
    }
    return urlPath('/' . $prefix . '/' . rawurlencode($slug), $lang);
}

/**
 * Renderiza seletor de idioma (dropdown) com links por idioma.
 *
 * $langLinks formato:
 * [
 *   'pt' => ['label' => 'PT', 'href' => '/pt/...'],
 *   'en' => ['label' => 'EN', 'href' => '/en/...'],
 * ]
 */
function renderLangSelector(array $langLinks, ?string $currentLang = null, string $dropdownAlign = 'right'): void {
    if (empty($langLinks)) {
        return;
    }

    $currentLang = normalizeLang($currentLang ?: currentLang());
    $currentLabel = $langLinks[$currentLang]['label'] ?? strtoupper($currentLang);

    $alignClass = 'right-0';
    switch ($dropdownAlign) {
        case 'left':
            $alignClass = 'left-0';
            break;
        case 'center':
            $alignClass = 'left-1/2 -translate-x-1/2';
            break;
        default:
            $alignClass = 'right-0';
            break;
    }
    ?>
    <details class="relative">
        <summary
            class="list-none [&::-webkit-details-marker]:hidden inline-flex items-center gap-2 px-2.5 py-2 rounded-lg border border-neutral-200 bg-white text-neutral-700 hover:border-primary hover:text-primary transition-colors cursor-pointer"
            aria-label="Idioma"
            title="Idioma"
        >
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm7.93 9h-3.17a15.66 15.66 0 0 0-1.15-5 8.01 8.01 0 0 1 4.32 5ZM12 4c.87 1.2 1.75 3.42 2.06 7H9.94C10.25 7.42 11.13 5.2 12 4ZM4.07 13h3.17a15.66 15.66 0 0 0 1.15 5 8.01 8.01 0 0 1-4.32-5ZM7.24 11H4.07a8.01 8.01 0 0 1 4.32-5 15.66 15.66 0 0 0-1.15 5ZM12 20c-.87-1.2-1.75-3.42-2.06-7h4.12C13.75 16.58 12.87 18.8 12 20Zm2.85-2a15.66 15.66 0 0 0 1.15-5h3.17a8.01 8.01 0 0 1-4.32 5ZM9.09 13c.31 3.58 1.19 5.8 2.91 7a8.01 8.01 0 0 1-4.32-5Zm5.82-2H9.09c.1-1.25.3-2.41.57-3.45A10.92 10.92 0 0 1 12 6a10.92 10.92 0 0 1 2.34 1.55c.27 1.04.47 2.2.57 3.45Zm-2.91-7c1.72 1.2 2.6 3.42 2.91 7h-5.82C9.4 7.42 10.28 5.2 12 4ZM9.66 16.45c-.27-1.04-.47-2.2-.57-3.45h5.82c-.1 1.25-.3 2.41-.57 3.45A10.92 10.92 0 0 1 12 18a10.92 10.92 0 0 1-2.34-1.55Z"/>
            </svg>
            <span class="text-xs font-semibold"><?php echo htmlspecialchars($currentLabel); ?></span>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" class="opacity-70">
                <path d="M7 10l5 5 5-5z"/>
            </svg>
        </summary>
        <div class="absolute <?php echo $alignClass; ?> mt-2 w-28 rounded-xl border border-neutral-200 bg-white shadow-lg overflow-hidden z-50">
            <?php foreach ($langLinks as $lc => $data): ?>
                <?php
                    $lc = normalizeLang((string)$lc);
                    $href = (string)($data['href'] ?? '#');
                    $label = (string)($data['label'] ?? strtoupper($lc));
                    $isActive = ($lc === $currentLang);
                ?>
                <a href="<?php echo htmlspecialchars($href); ?>"
                   class="flex items-center justify-between px-3 py-2 text-sm font-medium transition-colors <?php echo $isActive ? 'bg-primary/10 text-primary' : 'text-neutral-700 hover:bg-neutral-50'; ?>">
                    <span><?php echo htmlspecialchars($label); ?></span>
                    <?php if ($isActive): ?>
                        <span aria-hidden="true">✓</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </details>
    <?php
}
