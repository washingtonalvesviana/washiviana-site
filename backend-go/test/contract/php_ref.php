<?php
/**
 * Referência de contrato — executa um endpoint PHP autenticado via CLI e imprime o JSON.
 *
 * Uso:  php php_ref.php <arquivo-api> '<query-string>'
 * Ex.:  php php_ref.php artigos.php 'action=list'
 *       php php_ref.php get_i18n_seo.php 'entity=artigo&id=1&lang=en'
 *
 * ATENÇÃO: cria uma sessão autenticada fake (user_id=1) SOMENTE para testes de
 * leitura/paridade. Não usar em produção nem para operações de escrita.
 */

if ($argc < 3) {
    fwrite(STDERR, "uso: php_ref.php <api-file> <query-string>\n");
    exit(2);
}

$repoRoot = dirname(__DIR__, 3); // backend-go/test/contract -> repo root
$apiFile  = $repoRoot . '/api/' . basename($argv[1]);
if (!is_file($apiFile)) {
    fwrite(STDERR, "arquivo não encontrado: {$apiFile}\n");
    exit(2);
}

// Simula uma requisição GET
parse_str($argv[2] ?? '', $q);
$_GET     = $q;
$_REQUEST = $q;
$_POST    = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR']    = '127.0.0.1';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['SCRIPT_NAME']    = '/api/' . basename($argv[1]);

// Sessão fake autenticada (somente leitura)
$sp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/wv_contract_sessions';
@mkdir($sp, 0700, true);
if (is_dir($sp) && is_writable($sp)) {
    session_save_path($sp);
}
session_name('washiviana_sess');
session_start();
$_SESSION['user_id']       = 1;
$_SESSION['csrf_token']    = 'contract-test';
$_SESSION['last_activity']  = time();

require $apiFile;
