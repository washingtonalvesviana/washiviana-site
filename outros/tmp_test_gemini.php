<?php
require_once __DIR__ . '/api/radar_lib.php';
$r = radarGeminiGenerate("Teste simples para verificar API", 512, 0.2);
file_put_contents('/tmp/gemini_result.json', json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Done\n";