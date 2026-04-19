<?php
require_once __DIR__ . '/../api/config.php';

$person = 'urn:li:person:qyyIt_9a-B';
$stmt = $pdo->prepare("UPDATE redes_sociais_config SET person_urn = ? WHERE rede = 'linkedin'");
$stmt->execute([$person]);
echo "Person URN atualizado para: $person\n";