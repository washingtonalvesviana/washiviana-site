#!/usr/bin/env php
<?php
/**
 * Worker para publicar variantes sociais agendadas
 * Uso: php scripts/worker_publish_scheduled.php
 * Recomendado: rodar via cron a cada 1 minuto ou systemd timer
 */
require_once __DIR__ . '/../api/config.php';

$pdo = $pdo ?? null;
if (!$pdo) die("Não foi possível conectar ao banco.\n");

echo "Iniciando worker de publicação agendada...\n";

try {
    // Selecionar até 10 variantes agendadas prontas para publicar
    $pdo->beginTransaction();
    // Selecionar apenas variantes com status 'pronto' (mais seguro)
    $stmt = $pdo->prepare("SELECT id FROM artigos_social_variants WHERE scheduled_at IS NOT NULL AND scheduled_at <= NOW() AND status = 'pronto' ORDER BY scheduled_at ASC LIMIT 10 FOR UPDATE SKIP LOCKED");
    $stmt->execute();
    $ids = array_column($stmt->fetchAll(), 'id');
    $pdo->commit();

    if (empty($ids)) {
        echo "Nenhuma variante agendada no momento.\n";
        exit(0);
    }

    foreach ($ids as $id) {
        echo "Processando variante agendada $id: marcando como pronta para publicação manual...\n";
        // Carregar variante
        $stmt = $pdo->prepare("SELECT * FROM artigos_social_variants WHERE id = ?");
        $stmt->execute([$id]);
        $v = $stmt->fetch();
        if (!$v) continue;

        $artigoId = $v['artigo_id'];
        $rede = $v['rede'];

        try {
            // Em vez de publicar automaticamente, apenas marcar como pronta para publicação manual
            $stmt = $pdo->prepare("UPDATE artigos_social_variants SET status = 'pronto_para_publicacao', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);

            // Registrar entrada em publicacoes_redes com status 'gerado' (se tabela existir)
            try {
                $stmt = $pdo->prepare("INSERT INTO publicacoes_redes (rede, artigo_id, post_id, url_post, conteudo, status, publicado_em, created_at) VALUES (?, ?, ?, ?, ?, 'gerado', NULL, CURRENT_TIMESTAMP)");
                $stmt->execute([$rede, $artigoId, null, null, $v['caption'] ?? '']);
            } catch (Exception $e) {
                error_log('worker_publish_scheduled: não foi possível inserir publicacoes_redes: ' . $e->getMessage());
            }

            echo "Variante $id marcada como pronta para publicação manual.\n";
        } catch (Exception $e) {
            echo "Exceção ao processar variante $id: " . $e->getMessage() . "\n";
            // marcar erro
            $stmt = $pdo->prepare("UPDATE artigos_social_variants SET status = 'erro', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

} catch (Exception $e) {
    echo "Erro no worker: " . $e->getMessage() . "\n";
    exit(1);
}
