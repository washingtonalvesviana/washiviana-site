<?php
/**
 * API para listar modelos disponíveis do Google Gemini
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Pegar API key do request ou do banco
$apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? getConfig('gemini_api_key');

if (empty($apiKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'API Key não configurada'
    ]);
    exit;
}

try {
    // Buscar lista de modelos do Gemini (tentar /v1 e /v1beta e mesclar resultados)
    $models = [];
    $endpoints = [
        "https://generativelanguage.googleapis.com/v1/models?key=" . urlencode($apiKey),
        "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($apiKey)
    ];

    foreach ($endpoints as $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            // ignora um endpoint se houver erro de conexão
            continue;
        }

        if ($httpCode !== 200) {
            continue;
        }

        $data = json_decode($response, true);
        if (!isset($data['models'])) continue;

        foreach ($data['models'] as $modelObj) {
            $modelId = str_replace('models/', '', $modelObj['name'] ?? '');
            // dedupe por modelId
            if (!isset($models[$modelId])) {
                $models[$modelId] = $modelObj;
            }
        }
    }

    if (empty($models)) {
        throw new Exception("Resposta inválida da API ou chave sem modelos disponíveis");
    }

    // Separar modelos por tipo
    $textModels = [];
    $imageModels = [];
    
    foreach ($models as $name => $model) {
        $nameFull = $model['name'] ?? '';
        $displayName = $model['displayName'] ?? $nameFull;
        $description = $model['description'] ?? '';
        $modelId = str_replace('models/', '', $nameFull);

        // Filtrar modelos de texto (gemini)
        if (strpos($modelId, 'gemini') !== false && strpos($modelId, 'embedding') === false) {
            $supportedMethods = $model['supportedGenerationMethods'] ?? [];
            if (in_array('generateContent', $supportedMethods)) {
                $textModels[] = [
                    'id' => $modelId,
                    'name' => $displayName,
                    'description' => $description
                ];
            }
        }

        // Filtrar modelos de imagem: detectar imagens e também modelos Gemini com 'image' na id/displayName
        $lowerId = strtolower($modelId);
        $lowerDisp = strtolower($displayName);
        if (strpos($lowerId, 'imagen') !== false || strpos($lowerId, 'image') !== false || strpos($lowerDisp, 'image') !== false) {
            $imageModels[] = [
                'id' => $modelId,
                'name' => $displayName,
                'description' => $description
            ];
        }
    }
    
    // Ordenar por nome
    usort($textModels, fn($a, $b) => strcmp($b['id'], $a['id'])); // Decrescente para mais recentes primeiro
    usort($imageModels, fn($a, $b) => strcmp($b['id'], $a['id']));

    $imageModelsAvailable = !empty($imageModels);

    echo json_encode([
        'success' => true,
        'textModels' => $textModels,
        'imageModels' => $imageModels,
        'imageModelsAvailable' => $imageModelsAvailable,
        'totalModels' => array_sum(array_map(fn($m)=>1, $models))
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

