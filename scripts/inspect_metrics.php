<?php
require_once __DIR__ . '/../api/config.php';

try {
    $stmt = $pdo->prepare("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'metricas_publicacoes'
    ");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    echo "Columns in metricas_publicacoes:\n";
    print_r($cols);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
