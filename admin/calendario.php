<?php
/**
 * WASHIVIANA PORTFOLIO - Calendário de Agendamento
 * Visualização de posts agendados em formato de calendário
 */
require_once __DIR__ . '/../api/config.php';
requireAuth();

// Parâmetros do calendário
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// Calcular mês anterior e próximo
$mesAnterior = $mes - 1;
$anoAnterior = $ano;
if ($mesAnterior < 1) {
    $mesAnterior = 12;
    $anoAnterior--;
}

$mesProximo = $mes + 1;
$anoProximo = $ano;
if ($mesProximo > 12) {
    $mesProximo = 1;
    $anoProximo++;
}

// Buscar posts do mês
$primeiroDia = "$ano-$mes-01";
$ultimoDia = date('Y-m-t', strtotime($primeiroDia));

$stmt = $pdo->prepare("
    SELECT a.*, ca.nome as categoria_nome 
    FROM artigos a 
    LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id 
    WHERE a.data_agendamento BETWEEN ? AND ?
    ORDER BY a.data_agendamento ASC
");
$stmt->execute([$primeiroDia . ' 00:00:00', $ultimoDia . ' 23:59:59']);
$postsAgendados = $stmt->fetchAll();

// Organizar posts por dia
$postsPorDia = [];
foreach ($postsAgendados as $post) {
    $dia = date('j', strtotime($post['data_agendamento']));
    if (!isset($postsPorDia[$dia])) {
        $postsPorDia[$dia] = [];
    }
    $postsPorDia[$dia][] = $post;
}

// Dias da semana
$diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$mesesNomes = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
               'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

// Calcular estrutura do calendário
$primeiroDiaSemana = date('w', strtotime($primeiroDia));
$totalDias = date('t', strtotime($primeiroDia));
$hoje = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - Admin Washiviana</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .calendar-nav a {
            padding: 8px 15px;
            background: var(--light);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
        }
        .calendar-nav a:hover {
            background: var(--primary);
            color: white;
        }
        .calendar-title {
            font-size: 24px;
            font-weight: 700;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .calendar-day-header {
            background: var(--primary);
            color: white;
            padding: 12px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
        }
        .calendar-day {
            background: white;
            min-height: 120px;
            padding: 8px;
            position: relative;
        }
        .calendar-day.empty {
            background: #f9f9f9;
        }
        .calendar-day.today {
            background: rgba(0, 188, 212, 0.05);
        }
        .calendar-day.today .day-number {
            background: var(--primary);
            color: white;
        }
        .day-number {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .day-posts {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .day-post {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .day-post.status-rascunho {
            background: #6c757d;
        }
        .day-post.status-agendado {
            background: #17a2b8;
        }
        .day-post.status-publicado {
            background: #28a745;
        }
        .day-post.status-falha {
            background: #dc3545;
        }
        .day-post:hover {
            filter: brightness(1.1);
        }
        .day-post .post-time {
            font-weight: 600;
        }
        
        .more-posts {
            font-size: 10px;
            color: var(--text-muted);
            text-align: center;
            padding: 2px;
        }
        
        /* Legenda */
        .calendar-legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background: var(--light);
            border-radius: 8px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        /* Lista lateral */
        .calendar-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
        }
        @media (max-width: 1200px) {
            .calendar-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .upcoming-posts {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .upcoming-posts h3 {
            margin: 0 0 15px;
            font-size: 16px;
        }
        .upcoming-post {
            display: flex;
            gap: 12px;
            padding: 12px;
            background: var(--light);
            border-radius: 8px;
            margin-bottom: 10px;
            text-decoration: none;
            color: var(--text);
        }
        .upcoming-post:hover {
            background: rgba(0, 188, 212, 0.1);
        }
        .upcoming-thumb {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            background: #ddd;
        }
        .upcoming-info {
            flex: 1;
        }
        .upcoming-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .upcoming-meta {
            font-size: 11px;
            color: var(--text-muted);
        }
        .upcoming-date {
            font-weight: 600;
            color: var(--primary);
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'includes/header.php'; ?>
    
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1><i class="ph ph-calendar"></i> Calendário de Agendamento</h1>
                <a href="artigos.php?action=new" class="btn btn-primary"><i class="ph ph-plus-circle"></i> Novo Conteúdo</a>
            </div>
            
            <div class="calendar-layout">
                <div>
                    <!-- Navegação do Calendário -->
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <a href="?mes=<?php echo $mesAnterior; ?>&ano=<?php echo $anoAnterior; ?>"><i class="ph ph-arrow-left"></i> Anterior</a>
                            <a href="?mes=<?php echo date('m'); ?>&ano=<?php echo date('Y'); ?>">Hoje</a>
                            <a href="?mes=<?php echo $mesProximo; ?>&ano=<?php echo $anoProximo; ?>">Próximo <i class="ph ph-arrow-right"></i></a>
                        </div>
                        <div class="calendar-title">
                            <?php echo $mesesNomes[$mes] . ' ' . $ano; ?>
                        </div>
                    </div>
                    
                    <!-- Grid do Calendário -->
                    <div class="calendar-grid">
                        <!-- Cabeçalho dos dias -->
                        <?php foreach ($diasSemana as $dia): ?>
                            <div class="calendar-day-header"><?php echo $dia; ?></div>
                        <?php endforeach; ?>
                        
                        <!-- Dias vazios antes do primeiro dia -->
                        <?php for ($i = 0; $i < $primeiroDiaSemana; $i++): ?>
                            <div class="calendar-day empty"></div>
                        <?php endfor; ?>
                        
                        <!-- Dias do mês -->
                        <?php for ($dia = 1; $dia <= $totalDias; $dia++): 
                            $dataAtual = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                            $isToday = $dataAtual === $hoje;
                            $postsHoje = $postsPorDia[$dia] ?? [];
                        ?>
                            <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>">
                                <div class="day-number"><?php echo $dia; ?></div>
                                <div class="day-posts">
                                    <?php 
                                    $maxPosts = 3;
                                    $count = 0;
                                    foreach ($postsHoje as $post): 
                                        if ($count >= $maxPosts) break;
                                        $status = $post['status_publicacao'] ?? 'rascunho';
                                        $hora = date('H:i', strtotime($post['data_agendamento']));
                                    ?>
                                        <a href="artigos.php?action=edit&id=<?php echo $post['id']; ?>" 
                                           class="day-post status-<?php echo $status; ?>"
                                           title="<?php echo htmlspecialchars($post['titulo']); ?>">
                                            <span class="post-time"><?php echo $hora; ?></span>
                                            <?php echo htmlspecialchars(substr($post['titulo'], 0, 20)); ?>
                                        </a>
                                    <?php 
                                        $count++;
                                    endforeach; 
                                    
                                    if (count($postsHoje) > $maxPosts):
                                    ?>
                                        <div class="more-posts">+<?php echo count($postsHoje) - $maxPosts; ?> mais</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                        
                        <!-- Dias vazios após o último dia -->
                        <?php 
                        $totalCelulas = $primeiroDiaSemana + $totalDias;
                        $celulasRestantes = 7 - ($totalCelulas % 7);
                        if ($celulasRestantes < 7):
                            for ($i = 0; $i < $celulasRestantes; $i++): ?>
                                <div class="calendar-day empty"></div>
                            <?php endfor;
                        endif; ?>
                    </div>
                    
                    <!-- Legenda -->
                    <div class="calendar-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #6c757d;"></div>
                            <span>Rascunho</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #17a2b8;"></div>
                            <span>Agendado</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #28a745;"></div>
                            <span>Publicado</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #dc3545;"></div>
                            <span>Falha</span>
                        </div>
                    </div>
                </div>
                
                <!-- Próximos Posts -->
                <div class="upcoming-posts">
                    <h3><i class="ph ph-clipboard-text"></i> Próximos Agendamentos</h3>
                    
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT a.*, ca.nome as categoria_nome 
                        FROM artigos a 
                        LEFT JOIN categorias_artigos ca ON a.categoria_id = ca.id 
                        WHERE a.data_agendamento >= NOW()
                        AND a.status_publicacao = 'agendado'
                        ORDER BY a.data_agendamento ASC
                        LIMIT 10
                    ");
                    $stmt->execute();
                    $proximosPosts = $stmt->fetchAll();
                    
                    if (empty($proximosPosts)):
                    ?>
                        <p class="text-muted" style="text-align: center; padding: 20px;">
                            Nenhum post agendado.<br>
                            <a href="artigos.php?action=new" style="color: var(--primary);">Criar novo conteúdo</a>
                        </p>
                    <?php else:
                        foreach ($proximosPosts as $post):
                            $imagem = $post['imagem_1x1'] ?: ($post['imagem_principal'] ?: null);
                            $redes = json_decode($post['redes_destino'] ?? '[]', true);
                    ?>
                        <a href="artigos.php?action=edit&id=<?php echo $post['id']; ?>" class="upcoming-post">
                            <?php if ($imagem): ?>
                                <img src="<?php echo UPLOAD_URL . $imagem; ?>" alt="" class="upcoming-thumb">
                            <?php else: ?>
                                <div class="upcoming-thumb" style="display: flex; align-items: center; justify-content: center; font-size: 20px;"><i class="ph ph-pencil-simple"></i></div>
                            <?php endif; ?>
                            <div class="upcoming-info">
                                <div class="upcoming-title"><?php echo htmlspecialchars($post['titulo']); ?></div>
                                <div class="upcoming-meta">
                                    <span class="upcoming-date"><?php echo date('d/m H:i', strtotime($post['data_agendamento'])); ?></span>
                                    <?php 
                                        foreach (array_slice($redes, 0, 3) as $rede): 
                                            // Fallback icon mapping
                                            $icon = '<i class="ph ph-share-network"></i>';
                                            $rId = strtolower($rede['rede'] ?? '');
                                            if (strpos($rId, 'linkedin') !== false) $icon = '<i class="ph ph-linkedin-logo"></i>';
                                            elseif (strpos($rId, 'instagram') !== false) $icon = '<i class="ph ph-instagram-logo"></i>';
                                            elseif (strpos($rId, 'facebook') !== false) $icon = '<i class="ph ph-facebook-logo"></i>';
                                    ?>
                                        <span title="<?php echo $rede['nome'] ?? ''; ?>"><?php echo $icon; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </a>
                    <?php 
                        endforeach;
                    endif; 
                    ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js?v=<?php echo time(); ?>"></script>
</body>
</html>

