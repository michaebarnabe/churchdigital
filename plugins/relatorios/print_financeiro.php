<?php
// plugins/relatorios/print_financeiro.php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Segurança: Apenas Admin/Tesoureiro
if (!is_logged_in() || (!has_role('admin') && !has_role('tesoureiro'))) {
    die("Acesso Negado.");
}

$mes = $_GET['mes'] ?? date('m');
$ano = $_GET['ano'] ?? date('Y');
$igreja_id = $_SESSION['igreja_id'];

// --- 1. Busca Dados da Igreja ---
$stmt = $pdo->prepare("SELECT * FROM igrejas WHERE id = ?");
$stmt->execute([$igreja_id]);
$igreja = $stmt->fetch();

// --- 2. Busca Movimentações do Mês Selecionado ---
$sqlKey = "$ano-" . str_pad($mes, 2, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare("
    SELECT f.*, m.nome as membro_nome 
    FROM financeiro_basico f
    LEFT JOIN membros m ON f.membro_id = m.id
    WHERE f.igreja_id = ? AND f.data_movimento LIKE ?
    ORDER BY f.data_movimento ASC
");
$stmt->execute([$igreja_id, "$sqlKey%"]);
$movimentos = $stmt->fetchAll();

// --- 3. Cálculos do Período ---
$entrada_periodo = 0;
$saida_periodo = 0;

foreach ($movimentos as $mov) {
    if ($mov['tipo'] == 'saida') {
        $saida_periodo += $mov['valor'];
    } else {
        $entrada_periodo += $mov['valor'];
    }
}

$saldo_periodo = $entrada_periodo - $saida_periodo;

// --- 4. Saldo Anterior (Tudo antes deste mês) ---
$stmtApp = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN tipo IN ('dizimo', 'oferta') THEN valor ELSE 0 END) - 
        SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saldo 
    FROM financeiro_basico 
    WHERE igreja_id = ? AND data_movimento < ?
");
$stmtApp->execute([$igreja_id, "$sqlKey-01"]);
$saldo_anterior = $stmtApp->fetchColumn() ?: 0.00;

$saldo_final = $saldo_anterior + $saldo_periodo;

$meses_nome = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro - <?php echo $meses_nome[(int)$mes] . '/' . $ano; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; background: #e5e7eb; }
        
        /* A4 Paper Styling */
        .page {
            background: white;
            width: 21cm;
            min-height: 29.7cm;
            display: block;
            margin: 0.5cm auto;
            padding: 2cm;
            box-shadow: 0 0 0.5cm rgba(0,0,0,0.1);
        }

        /* Print Styling */
        @media print {
            @page { margin: 0; size: auto; } /* Remove browser default headers/footers */
            body { background: white; margin: 0; }
            .page {
                width: 100%;
                margin: 0;
                box-shadow: none;
                padding: 1.5cm 2cm; /* Margin on the paper itself */
                min-height: auto;
            }
            .no-print { display: none !important; }
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: bold; text-transform: uppercase; color: #374151; }
        tr:nth-child(even) { background-color: #f9fafb; }
    </style>
</head>
<body>

    <!-- Controles de Tela (Somem na impressão) -->
    <div class="no-print fixed top-0 left-0 w-full bg-gray-800 text-white p-3 flex justify-between items-center z-50 shadow-md">
        <div class="font-bold">Pré-visualização de Impressão</div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded text-sm font-bold flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                Imprimir / Salvar PDF
            </button>
            <button onclick="window.close()" class="bg-gray-600 hover:bg-gray-500 px-4 py-2 rounded text-sm">Fechar</button>
        </div>
    </div>

    <div class="page mt-16 print:mt-0">
        
        <!-- Header -->
        <div class="flex justify-between items-start border-b-2 border-primary pb-4 mb-6">
            <div class="flex items-center gap-4">
                <?php if (!empty($igreja['logo_url'])): 
                    // Fix path logic: if http, keep it; if relative, prepend ../../
                    $logoSrc = (strpos($igreja['logo_url'], 'http') === 0) ? $igreja['logo_url'] : "../../" . $igreja['logo_url'];
                ?>
                    <img src="<?php echo $logoSrc; ?>" class="h-16 w-16 object-contain">
                <?php endif; ?>
                <div>
                    <h1 class="text-2xl font-bold uppercase text-gray-800"><?php echo $igreja['nome']; ?></h1>
                    <p class="text-sm text-gray-500">Relatório de Fechamento de Caixa</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-gray-700 uppercase">Referência</p>
                <p class="text-xl text-primary font-bold"><?php echo $meses_nome[(int)$mes] . ' / ' . $ano; ?></p>
                <p class="text-xs text-gray-400 mt-1">Gerado em <?php echo date('d/m/Y H:i'); ?></p>
            </div>
        </div>

        <!-- Resumo -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-50 border border-gray-200 p-3 rounded text-center">
                <p class="text-xs text-gray-500 uppercase font-bold">Saldo Anterior</p>
                <p class="text-lg font-bold text-gray-600">R$ <?php echo number_format($saldo_anterior, 2, ',', '.'); ?></p>
            </div>
            <div class="bg-green-50 border border-green-200 p-3 rounded text-center">
                <p class="text-xs text-green-700 uppercase font-bold">Entradas</p>
                <p class="text-lg font-bold text-green-700">+ R$ <?php echo number_format($entrada_periodo, 2, ',', '.'); ?></p>
            </div>
            <div class="bg-red-50 border border-red-200 p-3 rounded text-center">
                <p class="text-xs text-red-700 uppercase font-bold">Saídas</p>
                <p class="text-lg font-bold text-red-700">- R$ <?php echo number_format($saida_periodo, 2, ',', '.'); ?></p>
            </div>
            <div class="bg-gray-100 border border-gray-300 p-3 rounded text-center">
                <p class="text-xs text-gray-700 uppercase font-bold">Saldo Final</p>
                <p class="text-lg font-bold <?php echo $saldo_final >= 0 ? 'text-blue-700' : 'text-red-700'; ?>">R$ <?php echo number_format($saldo_final, 2, ',', '.'); ?></p>
            </div>
        </div>

        <!-- Tabela Detalhada -->
        <h3 class="font-bold text-gray-700 mb-2 uppercase text-xs tracking-wider border-b border-gray-300 pb-1">Detalhamento das Movimentações</h3>
        <table class="w-full mb-8">
            <thead>
                <tr>
                    <th width="15%">Data</th>
                    <th>Descrição / Membro</th>
                    <th width="15%">Tipo</th>
                    <th width="20%" class="text-right">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($movimentos) > 0): ?>
                    <?php foreach ($movimentos as $mov): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($mov['data_movimento'])); ?></td>
                            <td>
                                <?php 
                                echo $mov['descricao']; 
                                if (!empty($mov['membro_nome'])) {
                                    echo " <span class='text-gray-400 text-[10px]'>(" . $mov['membro_nome'] . ")</span>";
                                }
                                ?>
                            </td>
                            <td class="uppercase text-[10px] font-bold">
                                <?php if ($mov['tipo'] == 'dizimo' || $mov['tipo'] == 'oferta'): ?>
                                    <span class="text-green-600"><?php echo $mov['tipo']; ?></span>
                                <?php else: ?>
                                    <span class="text-red-600">Saída</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right font-mono">
                                <?php 
                                if ($mov['tipo'] == 'saida') {
                                    echo "<span class='text-red-600'>- " . number_format($mov['valor'], 2, ',', '.') . "</span>";
                                } else {
                                    echo "<span class='text-green-600'> " . number_format($mov['valor'], 2, ',', '.') . "</span>";
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-6 text-gray-400 italic">Nenhuma movimentação neste período.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Área de Assinaturas -->
        <div class="mt-20 grid grid-cols-2 gap-16">
            <div class="text-center">
                <div class="border-t border-black w-2/3 mx-auto mb-2"></div>
                <p class="font-bold text-sm text-gray-800">Tesouraria</p>
                <p class="text-xs text-gray-500">Responsável Financeiro</p>
            </div>
            <div class="text-center">
                <div class="border-t border-black w-2/3 mx-auto mb-2"></div>
                <p class="font-bold text-sm text-gray-800">Pastor / Presidência</p>
                <p class="text-xs text-gray-500">Visto da Liderança</p>
            </div>
        </div>

    </div>

    <!-- Auto Print Script -->
    <script>
        // Opcional: Descomentar para imprimir direto ao carregar
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
