<?php
require_once __DIR__ . '/../api/config.php';

echo "Iniciando migração de banco de dados...\n";

try {
    // 1. Atualizar redes_sociais_config
    // Garantir que todas as redes existam
    $redes = ['linkedin', 'instagram', 'facebook', 'tiktok', 'youtube'];
    
    // Schema esperado:
    // rede (PK), ativo (bool), client_id, client_secret, access_token, refresh_token, expires_at, page_id, person_urn, organization_urn, publish_target, dados_extras (jsonb), updated_at

    foreach ($redes as $rede) {
        // Verificar se existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM redes_sociais_config WHERE rede = ?");
        $stmt->execute([$rede]);
        if ($stmt->fetchColumn() == 0) {
            echo "Inserindo configuração base para: $rede\n";
            $stmt = $pdo->prepare("INSERT INTO redes_sociais_config (rede, ativo, dados_extras) VALUES (?, false, '{}')");
            $stmt->execute([$rede]);
        }
    }

    // Adicionar colunas se não existirem (compatibilidade MySQL/Postgres)
    $columnsToAdd = [
        'page_id' => 'VARCHAR(255)',
        'refresh_token' => 'TEXT',
        'expires_at' => 'TIMESTAMP NULL'
    ];

    foreach ($columnsToAdd as $col => $type) {
        try {
            if (DB_DRIVER === 'pgsql') {
                 // Postgres: check column
                 $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='redes_sociais_config' AND column_name=?");
                 $stmt->execute([$col]);
                 if (!$stmt->fetch()) {
                     echo "Adicionando coluna $col em redes_sociais_config...\n";
                     $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN $col $type");
                 }
            } else {
                 // MySQL: try add, ignore if exists (or check legacy way)
                 // Simplesmente tentar adicionar e ignorar erro 'Duplicate column' é uma estratégia, mas melhor verificar
                 $stmt = $pdo->prepare("SHOW COLUMNS FROM redes_sociais_config LIKE ?");
                 $stmt->execute([$col]);
                 if (!$stmt->fetch()) {
                     echo "Adicionando coluna $col em redes_sociais_config...\n";
                     $pdo->exec("ALTER TABLE redes_sociais_config ADD COLUMN $col $type");
                 }
            }
        } catch (Exception $e) {
            echo "Aviso ao adicionar coluna $col: " . $e->getMessage() . "\n";
        }
    }

    // 2. Atualizar publicacoes_redes
    // Adicionar colunas de métricas se não houver
    $metricsCols = [
        'likes' => 'INT DEFAULT 0',
        'comments' => 'INT DEFAULT 0',
        'shares' => 'INT DEFAULT 0',
        'reach' => 'INT DEFAULT 0',
        'metrics_updated_at' => 'TIMESTAMP NULL'
    ];

    foreach ($metricsCols as $col => $type) {
         try {
            if (DB_DRIVER === 'pgsql') {
                 $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='publicacoes_redes' AND column_name=?");
                 $stmt->execute([$col]);
                 if (!$stmt->fetch()) {
                     echo "Adicionando coluna $col em publicacoes_redes...\n";
                     $pdo->exec("ALTER TABLE publicacoes_redes ADD COLUMN $col $type");
                 }
            } else {
                 $stmt = $pdo->prepare("SHOW COLUMNS FROM publicacoes_redes LIKE ?");
                 $stmt->execute([$col]);
                 if (!$stmt->fetch()) {
                     echo "Adicionando coluna $col em publicacoes_redes...\n";
                     $pdo->exec("ALTER TABLE publicacoes_redes ADD COLUMN $col $type");
                 }
            }
        } catch (Exception $e) {
            echo "Aviso ao adicionar coluna $col: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Radar Hype tables
    // Check radar_items for velocity/hype columns
    $radarCols = [
        'velocity' => 'FLOAT DEFAULT 0', // VPH ou similar
        'is_trending' => 'BOOLEAN DEFAULT FALSE',
        'hype_score' => 'FLOAT DEFAULT 0'
    ];
    
     foreach ($radarCols as $col => $type) {
         try {
            if (DB_DRIVER === 'pgsql') {
                 $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='radar_items' AND column_name=?");
                 $stmt->execute([$col]);
                 if (!$stmt->fetch()) {
                     echo "Adicionando coluna $col em radar_items...\n";
                     $pdo->exec("ALTER TABLE radar_items ADD COLUMN $col $type");
                 }
            } else {
                 $stmt = $pdo->prepare("SHOW COLUMNS FROM radar_items LIKE ?");
                 $stmt->execute([$col]);
                 if (!$stmt->fetch()) {
                     echo "Adicionando coluna $col em radar_items...\n";
                     $pdo->exec("ALTER TABLE radar_items ADD COLUMN $col $type");
                 }
            }
        } catch (Exception $e) {
            echo "Aviso ao adicionar coluna $col: " . $e->getMessage() . "\n";
        }
    }

    echo "Migração concluída com sucesso.\n";

} catch (Exception $e) {
    echo "ERRO CRÍTICO NA MIGRAÇÃO: " . $e->getMessage() . "\n";
    exit(1);
}
