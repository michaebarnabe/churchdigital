<?php
// plugins/relatorios/view.php
// Apenas Admin e Tesoureiro
if (!has_role('admin') && !has_role('tesoureiro')) {
    echo "Acesso Negado.";
    exit;
}

$mes_atual = date('m');
$ano_atual = date('Y');
?>

<div class="fade-in max-w-2xl mx-auto">
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i> Relatórios Financeiros
        </h2>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-8 border border-gray-100">
        
        <div class="text-center mb-8">
            <div class="bg-blue-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-primary text-2xl">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800">Selecione o Período</h3>
            <p class="text-gray-500 text-sm">Escolha o mês de referência para gerar o fechamento de caixa.</p>
        </div>

        <form action="plugins/relatorios/print_financeiro.php" method="GET" target="_blank" class="space-y-6">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-sm uppercase tracking-wide">Mês</label>
                    <select name="mes" class="w-full p-4 border rounded-xl focus:ring-4 focus:ring-primary/20 focus:border-primary focus:outline-none transition bg-gray-50 hover:bg-white text-lg font-medium cursor-pointer appearance-none">
                        <?php
                        $meses = [
                            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                        ];
                        foreach ($meses as $num => $nome):
                            $selected = ($num == $mes_atual) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $num; ?>" <?php echo $selected; ?>><?php echo $nome; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2 text-sm uppercase tracking-wide">Ano</label>
                    <select name="ano" class="w-full p-4 border rounded-xl focus:ring-4 focus:ring-primary/20 focus:border-primary focus:outline-none transition bg-gray-50 hover:bg-white text-lg font-medium cursor-pointer appearance-none">
                        <?php for($i = date('Y'); $i >= 2024; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($i == $ano_atual) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary text-white font-bold py-4 rounded-xl shadow-xl shadow-primary/30 hover:shadow-2xl hover:scale-[1.02] transform transition-all duration-300 flex items-center justify-center gap-2 group">
                <span>Gerar Relatório PDF</span>
                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </button>
            
            <p class="text-xs text-center text-gray-400 mt-4">
                <i class="fas fa-info-circle mr-1"></i> O relatório será aberto em uma nova aba para impressão.
            </p>

        </form>
    </div>
</div>
