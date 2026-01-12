<?php
// plugins/aniversariantes/view.php

$mes_atual = date('m');
$ano_atual = date('Y');

// Lógica de Permissão
$can_filter = has_role('admin') || has_role('secretario');
$selected_mes = $mes_atual;

// Se for staff, pode filtrar. Se for membro, trava no mês atual.
if ($can_filter && isset($_GET['mes'])) {
    $selected_mes = $_GET['mes'];
}

$meses_nome = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// Query
// Busca membros que fazem aniversário no mês selecionado, ordenados pelo dia
$stmt = $pdo->prepare("
    SELECT id, nome, data_nascimento, foto, cargo 
    FROM membros 
    WHERE igreja_id = ? AND MONTH(data_nascimento) = ?
    ORDER BY DAY(data_nascimento) ASC
");
$stmt->execute([$_SESSION['igreja_id'], $selected_mes]);
$aniversariantes = $stmt->fetchAll();
?>

<div class="fade-in max-w-4xl mx-auto mb-20">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fas fa-birthday-cake text-primary"></i> Aniversariantes
        </h2>

        <!-- Filtro (Apenas Staff) -->
        <?php if ($can_filter): ?>
            <form action="index.php" method="GET" class="flex items-center gap-2">
                <input type="hidden" name="page" value="aniversariantes">
                <select name="mes" onchange="this.form.submit()" class="p-2 border rounded-lg focus:outline-none focus:border-primary bg-white text-gray-700 font-medium">
                    <?php foreach ($meses_nome as $num => $nome): ?>
                        <option value="<?php echo $num; ?>" <?php echo ($num == $selected_mes) ? 'selected' : ''; ?>>
                            <?php echo $nome; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php else: ?>
            <div class="bg-primary/10 text-primary px-4 py-2 rounded-full font-bold text-sm">
                <?php echo $meses_nome[(int)$selected_mes]; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($aniversariantes) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($aniversariantes as $membro): 
                $dia = date('d', strtotime($membro['data_nascimento']));
                $mes_nasc = date('m', strtotime($membro['data_nascimento']));
                $ano_nasc = date('Y', strtotime($membro['data_nascimento']));
                
                // Cálculo de idade
                // Se o mês selecionado for < mês atual, já fez. Se for > vai fazer.
                // Mas a idade exibida geralmente é a que completa no ano.
                $idade_imaginaria = $ano_atual - $ano_nasc;
            ?>
                <div class="bg-white rounded-xl shadow-sm p-4 flex items-center gap-4 hover:shadow-md transition border-l-4 border-primary/20">
                    
                    <!-- Foto -->
                    <div class="w-16 h-16 rounded-full bg-gray-100 flex-shrink-0 overflow-hidden border-2 border-white shadow-sm flex items-center justify-center text-gray-400 font-bold text-xl">
                        <?php if (!empty($membro['foto'])): ?>
                            <img src="<?php echo e($membro['foto']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?php echo strtoupper(substr($membro['nome'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>

                    <!-- Info -->
                    <div class="flex-grow">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-gray-800 leading-tight"><?php echo e($membro['nome']); ?></h3>
                            <span class="bg-primary text-white text-xs font-bold px-2 py-1 rounded-lg">
                                Dia <?php echo $dia; ?>
                            </span>
                        </div>
                        
                        <p class="text-xs text-primary font-medium mt-0.5">
                            <?php echo $membro['cargo'] ?? 'Membro'; ?>
                        </p>

                        <p class="text-xs text-gray-400 mt-1">
                            Completa <strong><?php echo $idade_imaginaria; ?> anos</strong>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-12 bg-white rounded-xl shadow-sm">
            <div class="text-gray-300 text-6xl mb-4">
                <i class="far fa-calendar-times"></i>
            </div>
            <p class="text-gray-500 font-medium">Nenhum aniversariante encontrado em <span class="text-primary font-bold"><?php echo $meses_nome[(int)$selected_mes]; ?></span>.</p>
        </div>
    <?php endif; ?>

</div>
