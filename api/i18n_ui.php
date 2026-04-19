<?php
/**
 * UI translations: t($key, $fallback)
 */

function interpolateParams(string $text, array $params): string {
    if ($text === '' || empty($params)) return $text;
    $replace = [];
    foreach ($params as $k => $v) {
        $key = trim((string)$k);
        if ($key === '') continue;
        $replace['{' . $key . '}'] = (string)$v;
    }
    return $replace ? strtr($text, $replace) : $text;
}

function loadUiStrings(string $lang): array {
    global $pdo;
    $lang = normalizeLang($lang);
    if ($lang === 'pt') return [];

    $cacheKey = "__ui_strings_{$lang}";
    if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        return $_SESSION[$cacheKey];
    }

    try {
        $stmt = $pdo->prepare("SELECT chave, texto FROM ui_strings WHERE lang = ?");
        $stmt->execute([$lang]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $k = (string)($row['chave'] ?? '');
            $v = (string)($row['texto'] ?? '');
            if ($k !== '') $map[$k] = $v;
        }
        $_SESSION[$cacheKey] = $map;
        return $map;
    } catch (Exception $e) {
        return [];
    }
}

function t(string $key, string $fallback = ''): string {
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : currentLang();
    $lang = normalizeLang($lang);
    if ($lang === 'pt') return $fallback;

    $map = loadUiStrings($lang);
    $val = $map[$key] ?? null;
    if (is_string($val) && $val !== '') return $val;
    return $fallback;
}

function t_params(string $key, string $fallback = '', array $params = []): string {
    $text = t($key, $fallback);
    return interpolateParams($text, $params);
}

function tn(
    string $keySingular,
    string $keyPlural,
    int $count,
    string $fallbackSingular,
    string $fallbackPlural,
    array $params = []
): string {
    $key = ($count === 1) ? $keySingular : $keyPlural;
    $fallback = ($count === 1) ? $fallbackSingular : $fallbackPlural;
    $params = ['count' => $count] + $params;
    return t_params($key, $fallback, $params);
}

function clearUiStringsCache(): void {
    foreach (['en', 'es'] as $lang) {
        $k = "__ui_strings_{$lang}";
        if (isset($_SESSION[$k])) unset($_SESSION[$k]);
    }
}
