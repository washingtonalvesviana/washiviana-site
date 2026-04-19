<?php
require_once __DIR__ . '/../api/config.php';

echo "Rodando teste de notificação de job...\n";
$job = [ 'id' => 9999, 'variant_id' => 1, 'status' => 'failed', 'attempts' => 3 ];
try {
    notifyJobFailure($job, 'Mensagem de teste de falha');
    echo "notifyJobFailure executado (sem exceção).\n";
    exit(0);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
