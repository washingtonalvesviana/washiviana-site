<?php
require_once __DIR__ . '/api/config.php';

$stmt = $pdo->query("SELECT conteudo FROM artigos WHERE id = 2");
$artigo = $stmt->fetch();

echo "<h2>Conteúdo RAW do banco:</h2>";
echo "<pre>" . htmlspecialchars(substr($artigo['conteudo'], 0, 2000)) . "</pre>";

echo "<hr>";
echo "<h2>Conteúdo renderizado:</h2>";
echo $artigo['conteudo'];
