<?php
require_once __DIR__ . '/api/config.php';

header('Content-Type: application/xml; charset=utf-8');

function xmlEscape(string $value): string {
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function isoDate(?string $ts): ?string {
    if (!$ts) return null;
    $t = strtotime($ts);
    if ($t === false) return null;
    return gmdate('Y-m-d\\TH:i:s\\Z', $t);
}

function urlLoc(string $path): string {
    return rtrim(BASE_URL, '/') . $path;
}

$langs = ['pt', 'en', 'es'];

// Static pages (always)
$static = [
    ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['path' => '/conteudos', 'priority' => '0.8', 'changefreq' => 'daily'],
    ['path' => '/projetos', 'priority' => '0.8', 'changefreq' => 'weekly'],
    ['path' => '/automacao-ia', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['path' => '/tech-insights', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['path' => '/design-experiencias', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ['path' => '/sobre', 'priority' => '0.6', 'changefreq' => 'monthly'],
];

// Fetch content
$artigos = [];
$projetos = [];
try {
    $stmt = $pdo->query("SELECT id, slug, updated_at, created_at FROM artigos WHERE status_publicacao = 'publicado' AND ativo = true ORDER BY created_at DESC");
    $artigos = $stmt->fetchAll();
} catch (Exception $e) {
    $artigos = [];
}

try {
    $stmt = $pdo->query("SELECT id, slug, updated_at, created_at FROM projetos WHERE ativo = true ORDER BY created_at DESC");
    $projetos = $stmt->fetchAll();
} catch (Exception $e) {
    $projetos = [];
}

// Load i18n slugs in bulk
$artigosSlugs = [];
$projetosSlugs = [];
try {
    $stmt = $pdo->query("SELECT artigo_id, lang, slug FROM artigos_i18n WHERE slug IS NOT NULL");
    foreach ($stmt->fetchAll() as $row) {
        $artigosSlugs[(int)$row['artigo_id']][$row['lang']] = $row['slug'];
    }
} catch (Exception $e) {
    $artigosSlugs = [];
}

try {
    $stmt = $pdo->query("SELECT projeto_id, lang, slug FROM projetos_i18n WHERE slug IS NOT NULL");
    foreach ($stmt->fetchAll() as $row) {
        $projetosSlugs[(int)$row['projeto_id']][$row['lang']] = $row['slug'];
    }
} catch (Exception $e) {
    $projetosSlugs = [];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";

// Static URLs per language
foreach ($static as $item) {
    foreach ($langs as $lang) {
        $loc = urlLoc(urlPath($item['path'], $lang));
        echo "  <url>\n";
        echo "    <loc>" . xmlEscape($loc) . "</loc>\n";
        foreach ($langs as $altLang) {
            $alt = urlLoc(urlPath($item['path'], $altLang));
            $hreflang = $altLang === 'pt' ? 'pt-BR' : $altLang;
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"" . xmlEscape($hreflang) . "\" href=\"" . xmlEscape($alt) . "\" />\n";
        }
        echo "    <changefreq>" . xmlEscape($item['changefreq']) . "</changefreq>\n";
        echo "    <priority>" . xmlEscape($item['priority']) . "</priority>\n";
        echo "  </url>\n";
    }
}

// Artigos
foreach ($artigos as $a) {
    $id = (int)$a['id'];
    $lastmod = isoDate($a['updated_at'] ?? $a['created_at'] ?? null);
    $slugs = $artigosSlugs[$id] ?? [];
    $slugs['pt'] = $a['slug'];

    foreach ($langs as $lang) {
        if ($lang !== 'pt' && empty($slugs[$lang])) continue;
        $loc = urlLoc(routeArtigo($slugs[$lang] ?? $slugs['pt'], $lang));
        echo "  <url>\n";
        echo "    <loc>" . xmlEscape($loc) . "</loc>\n";
        foreach ($langs as $altLang) {
            if ($altLang !== 'pt' && empty($slugs[$altLang])) continue;
            $alt = urlLoc(routeArtigo($slugs[$altLang] ?? $slugs['pt'], $altLang));
            $hreflang = $altLang === 'pt' ? 'pt-BR' : $altLang;
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"" . xmlEscape($hreflang) . "\" href=\"" . xmlEscape($alt) . "\" />\n";
        }
        if ($lastmod) echo "    <lastmod>" . xmlEscape($lastmod) . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        echo "  </url>\n";
    }
}

// Projetos
foreach ($projetos as $p) {
    $id = (int)$p['id'];
    $lastmod = isoDate($p['updated_at'] ?? $p['created_at'] ?? null);
    $slugs = $projetosSlugs[$id] ?? [];
    $slugs['pt'] = $p['slug'];

    foreach ($langs as $lang) {
        if ($lang !== 'pt' && empty($slugs[$lang])) continue;
        $loc = urlLoc(routeProjeto($slugs[$lang] ?? $slugs['pt'], $lang));
        echo "  <url>\n";
        echo "    <loc>" . xmlEscape($loc) . "</loc>\n";
        foreach ($langs as $altLang) {
            if ($altLang !== 'pt' && empty($slugs[$altLang])) continue;
            $alt = urlLoc(routeProjeto($slugs[$altLang] ?? $slugs['pt'], $altLang));
            $hreflang = $altLang === 'pt' ? 'pt-BR' : $altLang;
            echo "    <xhtml:link rel=\"alternate\" hreflang=\"" . xmlEscape($hreflang) . "\" href=\"" . xmlEscape($alt) . "\" />\n";
        }
        if ($lastmod) echo "    <lastmod>" . xmlEscape($lastmod) . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "  </url>\n";
    }
}

echo "</urlset>\n";
