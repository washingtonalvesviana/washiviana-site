<?php
// scripts/generate_images_for_variant.php
// Uso: php scripts/generate_images_for_variant.php <variant_id>
require_once __DIR__ . '/../api/config.php';
if (php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from CLI only.\n";
    exit(1);
}
// Iniciar sessão CLI e forçar user_id para permitir chamadas internas que exigem auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    // Usar user_id=1 (admin) para operações internas via CLI
    $_SESSION['user_id'] = 1;
}
set_time_limit(300);

$id = intval($argv[1] ?? 0);
if (!$id) {
    echo "Usage: php generate_images_for_variant.php <variant_id>\n";
    exit(2);
}
try {
    // Buscar variante
    $stmt = $pdo->prepare('SELECT * FROM artigos_social_variants WHERE id = ?');
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if (!$v) {
        echo "Variant not found: {$id}\n";
        exit(3);
    }

    // Se variante já tem imagens, nada a fazer
    if (!empty($v['image_9x16']) || !empty($v['image_1x1'])) {
        echo "Variant {$id} already has images.\n";
        exit(0);
    }

    // Preparar prompt a partir da legenda/título
    $prompt = trim($v['caption'] ?? '') ?: trim($v['titulo'] ?? 'Gerar imagem para post social');
    if ($prompt === '') $prompt = 'Imagem promocional para rede social';

    // Chamar endpoint interno (CLI) do gemini: usar POST via exec para isolar execução
    $cliPhp = (PHP_BINARY) ?: 'php';
    $script = __DIR__ . '/../api/gemini.php';
    // Usar --define para passar vars via env não trivial; vamos invocar com php -f e definir $_POST via env var
    $cmd = escapeshellcmd($cliPhp) . ' ' . escapeshellarg($script);

    // Construir comando que usa PHP CLI to perform a request by setting $_POST before including
    // Criar um temporário que fará a chamada com POST params e echo output
    $tmp = sys_get_temp_dir() . '/genimg_' . uniqid() . '.php';
    $code = "<?php\n";
    $code .= "session_start();\n";
    $code .= '$_SESSION[\'user_id\'] = 1;' . "\n";
    $code .= '$_POST[\'action\'] = \'generate_images_multi\';' . "\n";
    $code .= '$_POST[\'prompt\'] = ' . var_export($prompt, true) . ";\n";
    $code .= "include '" . addslashes(__DIR__ . '/../api/gemini.php') . "';\n";
    file_put_contents($tmp, $code);
    $execCmd = escapeshellcmd($cliPhp) . ' ' . escapeshellarg($tmp) . ' 2>&1';
    // Executar (bloqueante) e capturar saída
    exec($execCmd, $out, $rc);
    // Remover temp script
    @unlink($tmp);

    $outStr = implode("\n", $out);

    // Normalizar saída: remover warnings/headers antes do JSON (procura primeiro '{')
    $jsonStr = null;
    $pos = strpos($outStr, '{');
    if ($pos !== false) {
        $jsonStr = substr($outStr, $pos);
    } else {
        $jsonStr = $outStr;
    }

    $json = json_decode($jsonStr, true);
    if (!$json || empty($json['success'])) {
        echo "Gemini did not return images: " . substr($outStr, 0, 2000) . "\n";
        exit(11);
    }

    $img1 = $json['imagem_1x1'] ?? null;
    $img2 = $json['imagem_9x16'] ?? null;
    if (!$img1 && !$img2) {
        echo "Gemini response missing images: " . substr($outStr, 0, 2000) . "\n";
        exit(12);
    }

    // Persistir nomes no banco
    $stmt = $pdo->prepare('UPDATE artigos_social_variants SET image_1x1 = ?, image_9x16 = ?, updated_at = now() WHERE id = ?');
    $stmt->execute([$img1 ?: '', $img2 ?: '', $id]);

    echo "Images generated for variant {$id}: image_1x1={$img1}, image_9x16={$img2}\n";
    exit(0);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    exit(20);
}
