<?php
require_once __DIR__ . '/api/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "I18N DEBUG\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "PATH: " . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') . "\n";
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

echo "detectLangFromPath: " . var_export(detectLangFromPath($path), true) . "\n";
echo "COOKIE[lang]: " . ($_COOKIE['lang'] ?? '(none)') . "\n";
echo "HTTP_ACCEPT_LANGUAGE: " . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '(none)') . "\n";

echo "currentLang(): " . currentLang() . "\n";

echo "\n-- DB TABLE CHECKS --\n";
$tables = ['ui_strings','configuracoes_i18n','categorias_i18n','categorias_artigos_i18n','artigos_i18n'];
foreach ($tables as $t) {
    try {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?) as exists");
        $stmt->execute([$t]);
        $r = $stmt->fetch();
        $exists = $r['exists'] ?? false;
    } catch (Exception $e) {
        $exists = false;
    }
    echo "$t: " . ($exists ? 'exists' : 'MISSING') . "\n";
    if ($exists) {
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) as c FROM " . $t);
            $cnt = $countStmt->fetch();
            echo "  rows: " . ($cnt['c'] ?? 0) . "\n";
            // For ui_strings show en/es counts
            if ($t === 'ui_strings') {
                $c2 = $pdo->query("SELECT lang, COUNT(*) as c FROM ui_strings WHERE lang IN ('en','es') GROUP BY lang")->fetchAll();
                foreach ($c2 as $r2) echo "  " . $r2['lang'] . ": " . $r2['c'] . "\n";
            }
        } catch (Exception $e) {
            echo "  error reading rows: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n-- SAMPLE UI STRINGS (en) --\n";
try {
    $stmt = $pdo->prepare("SELECT chave, texto FROM ui_strings WHERE lang = 'en' ORDER BY chave LIMIT 20");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        echo $row['chave'] . " => " . substr(trim($row['texto']),0,80) . "\n";
    }
} catch (Exception $e) {
    echo "(could not read ui_strings en): " . $e->getMessage() . "\n";
}

echo "\n-- SUGGESTED NEXT STEPS --\n";
echo "1) Visit this endpoint at /_debug_i18n.php while reproducing the issue (e.g., /en/ or after clicking EN).\n";
echo "2) If ui_strings are missing, run /i18n_site.php (admin) to generate translations or restore DB migration 007.\n";

?>