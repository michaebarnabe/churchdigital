<?php
// plugins/financeiro/view.php

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// --- LOGIC: DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM financeiro_basico WHERE id = ? AND igreja_id = ?");
    if ($stmt->execute([$_GET['id'], TenantScope::getId()])) {
        echo "<script>window.location.href='index.php?page=financeiro&msg=deleted';</script>";
        exit;
    }
}

// --- LOGIC: SAVE / UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'update')) {
    $id = $_POST['id'] ?? null;
    $tipo = $_POST['tipo'] ?? 'oferta';
    $valor = str_replace(',', '.', $_POST['valor'] ?? '0');
    $descricao = $_POST['descricao'] ?? '';
    $data_movimento = $_POST['data_movimento'] ?? date('Y-m-d');
    $membro_id = !empty($_POST['membro_id']) ? $_POST['membro_id'] : null;
    $igreja_id = TenantScope::getId();

    if ($valor) {
        if ($id && $action === 'update') {
            // UPDATE
            $sql = "UPDATE financeiro_basico SET tipo=?, valor=?, descricao=?, data_movimento=?, membro_id=? WHERE id=? AND igreja_id=?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$tipo, $valor, $descricao, $data_movimento, $membro_id, $id, $igreja_id])) {
                echo "<script>window.location.href='index.php?page=financeiro&msg=updated';</script>";
                exit;
            }
        } else {
            // INSERT
            $sql = "INSERT INTO financeiro_basico (igreja_id, tipo, valor, descricao, data_movimento, membro_id) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$igreja_id, $tipo, $valor, $descricao, $data_movimento, $membro_id])) {
                echo "<script>window.location.href='index.php?page=financeiro&msg=success';</script>";
                exit;
            }
        }
    }
}

// --- VIEW: NEW / EDIT TRANSACTION ---
if ($action === 'new' || $action === 'edit') {
    $transacao = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM financeiro_basico WHERE id = ? AND igreja_id = ?");
        $stmt->execute([$_GET['id'], TenantScope::getId()]);
        $transacao = $stmt->fetch();
        
        if (!$transacao) { echo "Lançamento não encontrado."; exit; }
    }

    // Valores Iniciais
    $tipo = $transacao['tipo'] ?? ($_GET['type'] ?? 'dizimo');
    $valor = $transacao['valor'] ?? '';
    $data_movimento = $transacao['data_movimento'] ?? date('Y-m-d');
    $descricao = $transacao['descricao'] ?? '';
    $sel_membro = $transacao['membro_id'] ?? '';
    $id = $transacao['id'] ?? '';

    // Buscar membros para o select
    $stmt = $pdo->prepare("SELECT id, nome FROM membros WHERE igreja_id = ? ORDER BY nome ASC");
    $stmt->execute([TenantScope::getId()]);
    $membros = $stmt->fetchAll();

    $formAction = $action === 'edit' ? 'update' : 'save';
    $titulo = $action === 'edit' ? 'Editar Lançamento' : 'Novo Lançamento';
?>
    <div class="bg-white rounded-xl shadow p-6 mb-20 fade-in">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800"><?php echo $titulo; ?></h2>
            <a href="index.php?page=financeiro" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <form action="index.php?page=financeiro&action=<?php echo $formAction; ?>" method="POST" class="space-y-4">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php endif; ?>

            <!-- Tipo -->
            <div>
                <label class="block text-gray-700 font-bold mb-2">Tipo de Movimento</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="dizimo" class="peer sr-only" <?php echo $tipo === 'dizimo' ? 'checked' : ''; ?> onchange="toggleMembro(true)">
                        <div class="p-3 text-center border rounded-lg peer-checked:bg-blue-600 peer-checked:text-white hover:bg-gray-50 transition">
                            <i class="fas fa-envelope-open-text mb-1"></i><br>Dízimo
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="oferta" class="peer sr-only" <?php echo $tipo === 'oferta' ? 'checked' : ''; ?> onchange="toggleMembro(true)">
                        <div class="p-3 text-center border rounded-lg peer-checked:bg-green-600 peer-checked:text-white hover:bg-gray-50 transition">
                            <i class="fas fa-hand-holding-usd mb-1"></i><br>Oferta
                        </div>
                    </label>
                     <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="saida" class="peer sr-only" <?php echo $tipo === 'saida' ? 'checked' : ''; ?> onchange="toggleMembro(false)">
                        <div class="p-3 text-center border rounded-lg peer-checked:bg-red-600 peer-checked:text-white hover:bg-gray-50 transition">
                            <i class="fas fa-file-invoice-dollar mb-1"></i><br>Despesa
                        </div>
                    </label>
                </div>
            </div>

            <!-- Valor e Data -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                     <label class="block text-gray-700 font-bold mb-2">Valor (R$)</label>
                     <input type="number" step="0.01" name="valor" value="<?php echo $valor; ?>" placeholder="0.00" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none text-lg">
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Data</label>
                    <input type="date" name="data_movimento" value="<?php echo $data_movimento; ?>" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
            </div>

            <!-- Membro (Condicional) -->
            <div id="membro-field" class="<?php echo $tipo === 'saida' ? 'hidden' : ''; ?>">
                <label class="block text-gray-700 font-bold mb-2">Membro (Opcional)</label>
                <select name="membro_id" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white">
                    <option value="">-- Selecione ou Anônimo --</option>
                    <?php foreach ($membros as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $sel_membro == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo e($m['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Descrição -->
            <div>
                <label class="block text-gray-700 font-bold mb-2">Descrição / Observação</label>
                <input type="text" name="descricao" value="<?php echo e($descricao); ?>" placeholder="Ex: Oferta de Missões / Conta de Luz" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
            </div>

            <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:brightness-90 transition mt-6 shadow-md">
                <?php echo $action === 'edit' ? 'Salvar Alterações' : 'Salvar Lançamento'; ?>
            </button>
        </form>
    </div>

    <script>
        function toggleMembro(show) {
            const field = document.getElementById('membro-field');
            if (show) {
                field.classList.remove('hidden');
            } else {
                field.classList.add('hidden');
                field.querySelector('select').value = '';
            }
        }
    </script>
<?php
} 
// --- VIEW: LIST TRANSACTIONS ---
else {
    // Buscar Extrato
    $sql = "
        SELECT f.*, m.nome as membro_nome 
        FROM financeiro_basico f
        LEFT JOIN membros m ON f.membro_id = m.id
        WHERE f.igreja_id = ? 
        ORDER BY f.data_movimento DESC, f.id DESC 
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([TenantScope::getId()]);
    $lancamentos = $stmt->fetchAll();
?>
    <div class="space-y-4 fade-in">
        
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">Financeiro</h2>
            <a href="index.php?page=financeiro&action=new" class="bg-gray-800 text-white p-3 rounded-full w-12 h-12 flex items-center justify-center shadow-lg hover:scale-105 transition transform">
                <i class="fas fa-plus"></i>
            </a>
        </div>

        <?php if ($msg == 'success'): ?>
             <div class="bg-green-100 text-green-700 p-3 rounded-lg text-sm border-l-4 border-green-500">Lançamento salvo com sucesso!</div>
        <?php elseif ($msg == 'updated'): ?>
             <div class="bg-blue-100 text-blue-700 p-3 rounded-lg text-sm border-l-4 border-blue-500">Lançamento atualizado!</div>
        <?php elseif ($msg == 'deleted'): ?>
             <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm border-l-4 border-red-500">Lançamento removido.</div>
        <?php endif; ?>

        <!-- Lista Transações -->
        <div class="pb-20 space-y-3">
            <?php if (count($lancamentos) > 0): ?>
                <?php foreach ($lancamentos as $l): ?>
                    <?php 
                        $isEntrada = in_array($l['tipo'], ['dizimo', 'oferta']);
                        $colorClass = $isEntrada ? 'text-green-600' : 'text-red-600';
                        $iconClass = $l['tipo'] == 'saida' ? 'fa-arrow-up rotate-45' : 'fa-arrow-down rotate-45';
                        $bgIcon = $isEntrada ? 'bg-green-100' : 'bg-red-100';
                    ?>
                    <div class="bg-white rounded-lg shadow-sm p-4 flex items-center justify-between group">
                        
                        <!-- Coluna Info -->
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 <?php echo $bgIcon; ?> rounded-full flex items-center justify-center <?php echo $colorClass; ?>">
                                <i class="fas <?php echo $l['tipo'] == 'dizimo' ? 'fa-envelope' : ($l['tipo'] == 'oferta' ? 'fa-hand-holding' : 'fa-file-invoice'); ?>"></i>
                            </div>
                            <div>
                                <p class="font-bold text-gray-800 capitalize"><?php echo $l['tipo']; ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('d/m', strtotime($l['data_movimento'])); ?> • 
                                    <?php echo $l['membro_nome'] ? $l['membro_nome'] : ($l['descricao'] ?: 'Sem descrição'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Coluna Ações e Valor -->
                        <div class="text-right">
                            <div class="font-bold <?php echo $colorClass; ?> mb-1">
                                <?php echo $isEntrada ? '+' : '-'; ?> R$ <?php echo number_format($l['valor'], 2, ',', '.'); ?>
                            </div>
                            <!-- Actions -->
                            <div class="text-xs text-gray-400 space-x-3">
                                <a href="index.php?page=financeiro&action=edit&id=<?php echo $l['id']; ?>" class="hover:text-blue-500">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="index.php?page=financeiro&action=delete&id=<?php echo $l['id']; ?>" class="hover:text-red-500" onclick="return confirm('Tem certeza que deseja apagar este lançamento?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-10 text-gray-500">
                    <i class="fas fa-search-dollar text-4xl mb-3 opacity-30"></i>
                    <p>Nenhum lançamento no período.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
}
?>
