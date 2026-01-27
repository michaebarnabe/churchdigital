<?php
// plugins/patrimonio/view.php

$action = $_GET['action'] ?? 'list';
$tab = $_GET['tab'] ?? 'individuais'; // individuais | lotes
$msg = $_GET['msg'] ?? '';

// Tenant Scope
$igreja_id = TenantScope::getId();

// Permissões
if (!has_role('admin') && !has_role('tesoureiro') && !has_role('secretario')) {
    echo "<div class='p-4 bg-red-100 text-red-700'>Acesso Negado.</div>";
    return;
}

// --- LOGICA: SALVAR (INSERT / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'update')) {
    $id = $_POST['id'] ?? null;
    $tipo = $_POST['tipo']; // individual | lote
    $nome = $_POST['nome'];
    $categoria = $_POST['categoria'];
    $local = $_POST['local'];
    $observacoes = $_POST['observacoes'];
    
    // Check Limits (apenas se for novo INSERT e NÃO for soft-deleted sendo restaurado, mas aqui é novo insert normal)
    if (!$id && !PlanEnforcer::canAdd($pdo, 'patrimonio')) {
        die("Limite do plano atingido.");
    }

    try {
        $pdo->beginTransaction();

        $codigo_patrimonio = $_POST['codigo_patrimonio'] ?? null;
        if (!$codigo_patrimonio && !$id) {
             // Gerar Código Auto: PAT-{ID_IGREJA}-{TIMESTAMP} (simplificado) ou SEQUENCIAL
             // Vamos usar algo simples: PAT- + Random
             $codigo_patrimonio = 'PAT-' . strtoupper(substr(uniqid(), -6));
        }

        // Foto Upload (usando a mesma lógica Blob/File ou link direto se mudarmos estratégia, mas aqui vamos assumir path simples ou manter sem foto por enquanto para agilizar MVP, 
        // ou reutilizar lógica de upload se o form enviar $_FILES). O user pediu foto no prompt anterior, vamos incluir suporte básico.
        $foto_path = $_POST['foto_atual'] ?? null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
             $uploadDir = 'uploads/';
             if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
             $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
             $fileName = uniqid('pat_') . '.' . $ext;
             move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fileName);
             $foto_path = $uploadDir . $fileName;
        }

        if ($tipo === 'individual') {
            $data_aquisicao = $_POST['data_aquisicao'] ?: null;
            $valor_estimado = $_POST['valor_estimado'] ? str_replace(['R$','.',' ','-'], ['','','',''], $_POST['valor_estimado']) : null;
            $valor_estimado = $valor_estimado ? str_replace(',', '.', $valor_estimado) : null;
            $status = $_POST['status'];

            if ($id) {
                $stmt = $pdo->prepare("UPDATE patrimonio_itens SET nome=?, categoria=?, local=?, data_aquisicao=?, valor_estimado=?, status=?, observacoes=?, foto=? WHERE id=? AND igreja_id=?");
                $stmt->execute([$nome, $categoria, $local, $data_aquisicao, $valor_estimado, $status, $observacoes, $foto_path, $id, $igreja_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO patrimonio_itens (igreja_id, tipo, nome, categoria, local, data_aquisicao, valor_estimado, status, observacoes, codigo_patrimonio, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$igreja_id, $tipo, $nome, $categoria, $local, $data_aquisicao, $valor_estimado, $status, $observacoes, $codigo_patrimonio, $foto_path]);
            }

        } else {
            // LOTE
            $qtd_total = intval($_POST['quantidade_total']);
            $qtd_uso = intval($_POST['quantidade_uso']);
            $qtd_manutencao = intval($_POST['quantidade_manutencao']);
            
            // Validação de Lote
            $disponivel = $qtd_total - ($qtd_uso + $qtd_manutencao);
            if ($disponivel < 0) {
                throw new Exception("A quantidade em uso/manutenção não pode exceder o total.");
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE patrimonio_itens SET nome=?, categoria=?, local=?, quantidade_total=?, quantidade_uso=?, quantidade_manutencao=?, observacoes=?, foto=? WHERE id=? AND igreja_id=?");
                $stmt->execute([$nome, $categoria, $local, $qtd_total, $qtd_uso, $qtd_manutencao, $observacoes, $foto_path, $id, $igreja_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO patrimonio_itens (igreja_id, tipo, nome, categoria, local, quantidade_total, quantidade_uso, quantidade_manutencao, observacoes, codigo_patrimonio, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$igreja_id, $tipo, $nome, $categoria, $local, $qtd_total, $qtd_uso, $qtd_manutencao, $observacoes, $codigo_patrimonio, $foto_path]);
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='index.php?page=patrimonio&tab={$tipo}s&msg=saved';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='bg-red-100 p-4 text-red-700'>Erro: " . $e->getMessage() . "</div>";
    }
}

// --- LOGICA: DELETE (SOFT) ---
if ($action === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE patrimonio_itens SET ativo = 0, deleted_at = NOW() WHERE id = ? AND igreja_id = ?");
    $stmt->execute([$id, $igreja_id]);
    echo "<script>window.location.href='index.php?page=patrimonio&msg=deleted';</script>";
    exit;
}

// --- LOGICA: HISTORICO (AJAX/POST) ---
if ($action === 'add_history' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'];
    $evento = $_POST['tipo_evento'];
    $obs = $_POST['observacao'];
    $resp = $_POST['responsavel'];
    
    $stmt = $pdo->prepare("INSERT INTO patrimonio_historico (item_id, tipo_evento, responsavel, observacao, data_evento) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$item_id, $evento, $resp, $obs]);
    
    // Opcional: Atualizar status do item pai automaticamente? 
    // O user pediu histórico vinculado. Vamos apenas registrar por enquanto para não conflitar com a edição manual.
    
    echo "<script>window.location.href='index.php?page=patrimonio&action=edit&id={$item_id}&tab_interna=historico';</script>";
    exit;
}

// --- VIEW: FORM (NEW/EDIT) ---
if ($action === 'new' || $action === 'edit') {
    $item = null;
    if ($action === 'edit') {
        $stmt = $pdo->prepare("SELECT * FROM patrimonio_itens WHERE id = ? AND igreja_id = ?");
        $stmt->execute([$_GET['id'], $igreja_id]);
        $item = $stmt->fetch();
        if (!$item) die("Item não encontrado.");
    }
    
    $tipo = $item['tipo'] ?? 'individual';
    // Check Limits for NEW
    $canAdd = PlanEnforcer::canAdd($pdo, 'patrimonio');
    if ($action === 'new' && !$canAdd) {
        PlanEnforcer::renderUpgradeModal("Limite de patrimônio atingido.");
    }

?>
    <div class="bg-white rounded-xl shadow p-6 fade-in mb-20">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800"><?php echo $action === 'edit' ? 'Editar Item' : 'Novo Item Patrimonial'; ?></h2>
            <a href="index.php?page=patrimonio" class="text-gray-500 hover:text-gray-700"><i class="fas fa-arrow-left"></i> Voltar</a>
        </div>

        <form method="POST" enctype="multipart/form-data" id="formPatrimonio">
            <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'update' : 'save'; ?>">
            <?php if($item): ?><input type="hidden" name="id" value="<?php echo $item['id']; ?>"><?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Tipo Selector -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Tipo de Item</label>
                    <select name="tipo" id="tipoSelect" class="w-full border rounded p-2" <?php echo $item ? 'readonly disabled' : ''; ?>>
                        <option value="individual" <?php echo $tipo=='individual'?'selected':''; ?>>Item Individual</option>
                        <option value="lote" <?php echo $tipo=='lote'?'selected':''; ?>>Lote / Quantidade</option>
                    </select>
                    <?php if($item): ?><input type="hidden" name="tipo" value="<?php echo $tipo; ?>"><?php endif; ?>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Nome do Bem</label>
                    <input type="text" name="nome" value="<?php echo e($item['nome']??''); ?>" class="w-full border rounded p-2" required placeholder="Ex: Guitarra Fender, Cadeiras Plásticas...">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Categoria</label>
                    <input type="text" name="categoria" value="<?php echo e($item['categoria']??''); ?>" class="w-full border rounded p-2" list="catList" placeholder="Ex: Instrumentos">
                    <datalist id="catList">
                        <option value="Instrumentos">
                        <option value="Mobiliário">
                        <option value="Eletrônicos">
                        <option value="Veículos">
                        <option value="Cozinha">
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Localização</label>
                    <input type="text" name="local" value="<?php echo e($item['local']??''); ?>" class="w-full border rounded p-2" placeholder="Ex: Templo Principal">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Foto (Opcional)</label>
                    <input type="file" name="foto" class="w-full text-sm">
                    <?php if(!empty($item['foto'])): ?>
                        <div class="mt-2 text-xs text-gray-500">Foto atual cadastrada</div>
                        <input type="hidden" name="foto_atual" value="<?php echo $item['foto']; ?>">
                    <?php endif; ?>
                </div>
            </div>

            <!-- FIELDS: INDIVIDUAL -->
            <div id="fieldsIndividual" class="<?php echo $tipo==='lote'?'hidden':''; ?>">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 mb-6">
                    <h3 class="text-sm font-bold text-gray-600 mb-3 uppercase">Detalhes Individuais</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Data Aquisição</label>
                            <input type="date" name="data_aquisicao" value="<?php echo e($item['data_aquisicao']??''); ?>" class="w-full border rounded p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Valor Estimado</label>
                            <input type="text" name="valor_estimado" value="<?php echo e($item['valor_estimado']??''); ?>" class="w-full border rounded p-2" placeholder="R$ 0,00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Status Atual</label>
                            <select name="status" class="w-full border rounded p-2">
                                <option value="ativo" <?php echo ($item['status']??'')=='ativo'?'selected':''; ?>>Ativo (Disponível)</option>
                                <option value="em_uso" <?php echo ($item['status']??'')=='em_uso'?'selected':''; ?>>Em Uso</option>
                                <option value="manutencao" <?php echo ($item['status']??'')=='manutencao'?'selected':''; ?>>Em Manutenção</option>
                                <option value="baixado" <?php echo ($item['status']??'')=='baixado'?'selected':''; ?>>Baixado / Inativo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FIELDS: LOTE -->
            <div id="fieldsLote" class="<?php echo $tipo==='individual'?'hidden':''; ?>">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 mb-6">
                    <h3 class="text-sm font-bold text-blue-600 mb-3 uppercase">Controle de Quantidade</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-blue-800 mb-1">Quantidade TOTAL</label>
                            <input type="number" name="quantidade_total" id="qtdTotal" value="<?php echo e($item['quantidade_total']??'1'); ?>" min="1" class="w-full border rounded p-2 font-bold text-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Em Uso</label>
                            <input type="number" name="quantidade_uso" id="qtdUso" value="<?php echo e($item['quantidade_uso']??'0'); ?>" min="0" class="w-full border rounded p-2">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-orange-700 mb-1">Em Manutenção</label>
                            <input type="number" name="quantidade_manutencao" id="qtdManu" value="<?php echo e($item['quantidade_manutencao']??'0'); ?>" min="0" class="w-full border rounded p-2">
                        </div>
                    </div>
                    <div class="mt-4 text-right text-sm">
                        <span class="font-bold text-gray-600">Disponível: <span id="labelDisponivel" class="text-xl text-green-600 font-bold ml-1">0</span></span>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-1">Observações</label>
                <textarea name="observacoes" class="w-full border rounded p-2 h-24"><?php echo e($item['observacoes']??''); ?></textarea>
            </div>

            <div class="flex justify-end gap-3">
                <button type="submit" class="bg-green-600 text-white font-bold py-3 px-8 rounded-lg hover:bg-green-700 shadow-lg transition">
                    <i class="fas fa-save mr-2"></i> Salvar Item
                </button>
            </div>
        </form>
    </div>

    <!-- HISTÓRICO (SOMENTE EDIÇÃO) -->
    <?php if($action === 'edit' && $item): ?>
    <div class="bg-white rounded-xl shadow p-6 fade-in mb-20">
        <h3 class="text-lg font-bold text-gray-800 mb-4 text-primary border-b pb-2">Histórico de Movimentações</h3>
        
        <!-- Form Histórico -->
        <form method="POST" action="index.php?page=patrimonio&action=add_history" class="bg-gray-50 p-4 rounded mb-6 flex flex-col md:flex-row gap-4 items-end">
             <input type="hidden" name="action" value="add_history">
             <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
             
             <div class="flex-1 w-full">
                 <label class="block text-xs font-bold text-gray-600 mb-1">Evento</label>
                 <select name="tipo_evento" class="w-full border rounded p-2 bg-white">
                     <option value="uso">Uso / Empréstimo</option>
                     <option value="manutencao">Manutenção</option>
                     <option value="retorno">Retorno / Devolução</option>
                     <option value="baixa">Baixa</option>
                 </select>
             </div>
             <div class="flex-1 w-full">
                 <label class="block text-xs font-bold text-gray-600 mb-1">Responsável</label>
                 <input type="text" name="responsavel" class="w-full border rounded p-2" required placeholder="Nome">
             </div>
             <div class="flex-[2] w-full">
                 <label class="block text-xs font-bold text-gray-600 mb-1">Observação</label>
                 <input type="text" name="observacao" class="w-full border rounded p-2" placeholder="Detalhes...">
             </div>
             <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 h-[42px] w-full md:w-auto">
                 Registrar
             </button>
        </form>

        <!-- Lista -->
        <?php 
        $hist = $pdo->query("SELECT * FROM patrimonio_historico WHERE item_id={$item['id']} ORDER BY data_evento DESC")->fetchAll();
        if(count($hist) > 0): 
        ?>
        <div class="space-y-3">
            <?php foreach($hist as $h): ?>
                <div class="flex justify-between items-center border-b pb-2 last:border-0 text-sm">
                    <div>
                        <span class="font-bold uppercase text-xs <?php echo $h['tipo_evento']=='manutencao'?'text-orange-500':($h['tipo_evento']=='uso'?'text-blue-500':'text-green-500'); ?>">
                            <?php echo $h['tipo_evento']; ?>
                        </span>
                        <p class="text-gray-700"><?php echo e($h['observacao']); ?></p>
                        <p class="text-xs text-gray-400">Resp: <?php echo e($h['responsavel']); ?></p>
                    </div>
                    <div class="text-right text-xs text-gray-400">
                        <?php echo date('d/m/Y H:i', strtotime($h['data_evento'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <p class="text-center text-gray-400 text-sm py-4">Nenhum registro de histórico.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
        // Toggle Logic
        const typeSelect = document.getElementById('tipoSelect');
        const fieldsInd = document.getElementById('fieldsIndividual');
        const fieldsLot = document.getElementById('fieldsLote');
        
        if (typeSelect) {
            typeSelect.addEventListener('change', function() {
                if (this.value === 'individual') {
                    fieldsInd.classList.remove('hidden');
                    fieldsLot.classList.add('hidden');
                } else {
                    fieldsInd.classList.add('hidden');
                    fieldsLot.classList.remove('hidden');
                }
            });
        }

        // Math Logic for Lotes
        const inputs = [document.getElementById('qtdTotal'), document.getElementById('qtdUso'), document.getElementById('qtdManu')];
        const labelDisp = document.getElementById('labelDisponivel');

        function updateDisp() {
            if(!inputs[0]) return;
            const t = parseInt(inputs[0].value) || 0;
            const u = parseInt(inputs[1].value) || 0;
            const m = parseInt(inputs[2].value) || 0;
            const disp = t - (u + m);
            
            labelDisp.textContent = disp;
            if (disp < 0) labelDisp.classList.replace('text-green-600', 'text-red-600');
            else labelDisp.classList.replace('text-red-600', 'text-green-600');
        }

        inputs.forEach(i => i && i.addEventListener('input', updateDisp));
        updateDisp();
    </script>

<?php
}
// --- VIEW: LIST ---
else {
    // Current Usage
    $currentUsage = $pdo->prepare("SELECT COUNT(*) FROM patrimonio_itens WHERE igreja_id = ? AND ativo = 1");
    $currentUsage->execute([$igreja_id]); // Simplified per tenant for List View context, PlanEnforcer handles Matrix correct count
    // TO DO: Show user friendly limit bar (Need PlanEnforcer limit info here too)
    $limit = PlanEnforcer::getLimit($pdo, 'patrimonio');
    // Re-count correctly for limit display using PlanEnforcer Logic (Raw SQL for simplicity here)
    // Actually let's trust PlanEnforcer::canAdd logic implicitly for buttons, but for display let's do a simple count relative to *this* tenant for now or replicate PlanEnforcer count if Matrix.
    
    // For List Logic, let's just list items of THIS tenant.
    $tipoFilter = substr($tab, 0, -1); // individuais -> individual, lotes -> lote
    if ($tipoFilter == 'individua') $tipoFilter = 'individual'; // fix substring if needed. 
    // tab 'individuais' -> 'individual'
    // tab 'lotes' -> 'lote'
    
    $lista = $pdo->prepare("SELECT * FROM patrimonio_itens WHERE igreja_id = ? AND tipo = ? AND ativo = 1 ORDER BY nome ASC");
    $lista->execute([$igreja_id, ($tab === 'lotes' ? 'lote' : 'individual')]);
    $itens = $lista->fetchAll();
    
    $totalValue = 0;
    foreach($itens as $i) $totalValue += ($i['valor_estimado'] ?? 0);
?>
    <div class="fade-in pb-20">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-boxes text-primary mr-2"></i> Patrimônio</h2>
                <span class="text-sm text-gray-500">Gerencie seus bens e equipamentos</span>
            </div>
            <?php if(PlanEnforcer::canAdd($pdo, 'patrimonio')): ?>
            <a href="index.php?page=patrimonio&action=new" class="bg-black text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-800 transition shadow-lg">
                <i class="fas fa-plus mr-2"></i> Novo Item
            </a>
            <?php else: ?>
                <button onclick="alert('Limite do seu plano atingido.')" class="bg-gray-300 text-gray-500 px-4 py-2 rounded-lg font-bold cursor-not-allowed">
                    <i class="fas fa-lock mr-2"></i> Limite Atingido
                </button>
            <?php endif; ?>
        </div>
        
        <?php if($msg === 'saved'): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-4">Salvo com sucesso!</div><?php endif; ?>
        <?php if($msg === 'deleted'): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-4">Item removido.</div><?php endif; ?>

        <!-- TABS -->
        <div class="flex gap-4 border-b border-gray-200 mb-6">
            <a href="index.php?page=patrimonio&tab=individuais" class="pb-2 border-b-2 <?php echo $tab==='individuais'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
                Itens Individuais
            </a>
            <a href="index.php?page=patrimonio&tab=lotes" class="pb-2 border-b-2 <?php echo $tab==='lotes'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
                Lotes / Quantidades
            </a>
        </div>

        <?php if (count($itens) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Sumário Valor -->
                <?php if($tab === 'individuais'): ?>
                <div class="col-span-full bg-blue-50 p-4 rounded-lg flex items-center justify-between border border-blue-100 mb-2">
                    <span class="text-blue-800 font-bold">Valor Total Estimado</span>
                    <span class="text-2xl font-bold text-blue-600">R$ <?php echo number_format($totalValue, 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>

                <?php foreach ($itens as $item): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex gap-4 hover:shadow-md transition group overflow-hidden relative">
                        <!-- Imagem -->
                        <div class="w-20 h-20 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400 flex-shrink-0 overflow-hidden">
                            <?php if($item['foto']): ?>
                                <img src="<?php echo e($item['foto']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-cube text-2xl"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-gray-800 truncate"><?php echo e($item['nome']); ?></h3>
                                    <p class="text-xs text-gray-500"><?php echo e($item['categoria']); ?> • <?php echo e($item['local']); ?></p>
                                </div>
                                <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded text-gray-500"><?php echo e($item['codigo_patrimonio']); ?></span>
                            </div>
                            
                            <div class="mt-3 flex items-center justify-between">
                                <?php if($tab === 'individuais'): ?>
                                    <span class="text-xs px-2 py-1 rounded font-bold uppercase <?php echo ($item['status']=='ativo'||$item['status']=='em_uso')?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>">
                                        <?php echo str_replace('_', ' ', $item['status']); ?>
                                    </span>
                                    <?php if($item['valor_estimado']): ?>
                                        <span class="font-bold text-sm text-gray-700">R$ <?php echo number_format($item['valor_estimado'], 2, ',', '.'); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Lote Stats -->
                                    <div class="text-xs w-full">
                                        <div class="flex justify-between mb-1">
                                            <span>Total: <strong><?php echo $item['quantidade_total']; ?></strong></span>
                                            <span class="text-green-600">Disp: <strong><?php echo $item['quantidade_total'] - ($item['quantidade_uso']+$item['quantidade_manutencao']); ?></strong></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden flex">
                                            <?php 
                                                $pctUso = ($item['quantidade_uso'] / $item['quantidade_total']) * 100;
                                                $pctManu = ($item['quantidade_manutencao'] / $item['quantidade_total']) * 100;
                                            ?>
                                            <div class="bg-blue-500 h-full" style="width: <?php echo $pctUso; ?>%"></div>
                                            <div class="bg-orange-500 h-full" style="width: <?php echo $pctManu; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Link Overlay -->
                        <a href="index.php?page=patrimonio&action=edit&id=<?php echo $item['id']; ?>" class="absolute inset-0 z-10 block"></a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-xl shadow-sm border border-dashed text-gray-400">
                <i class="fas fa-box-open text-5xl mb-4 opacity-30"></i>
                <p>Nenhum item cadastrado nesta categoria.</p>
                <?php if(PlanEnforcer::canAdd($pdo, 'patrimonio')): ?>
                <a href="index.php?page=patrimonio&action=new" class="mt-4 inline-block text-primary font-bold hover:underline">Cadastrar Primeiro Item</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php
}
?>
