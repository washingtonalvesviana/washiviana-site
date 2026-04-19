<?php
require_once __DIR__ . '/api/radar_lib.php';
$r = radarGenerateIdeasForTopic(1, 10, 3);
file_put_contents('/tmp/ideas_result.json', json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Done\n";