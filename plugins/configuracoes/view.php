<?php
// plugins/configuracoes/view.php

$action = $_GET['action'] ?? 'view';
$igreja_id = TenantScope::getId();
$currentTenant = $pdo->query("SELECT * FROM igrejas WHERE id = $igreja_id")->fetch();
$parent_id = $currentTenant['parent_id'];
$is_branch = !empty($parent_id);

// 1. Identificar se é Matriz (pode ter filiais)
// Verifica limites do plano
$planoLimits = $pdo->prepare("
    SELECT p.limite_filiais 
    FROM assinaturas a 
    JOIN planos p ON a.plano_id = p.id 
    WHERE a.igreja_id = ? AND a.status = 'ativa'
");
$planoLimits->execute([$igreja_id]);
$limiteFiliais = $planoLimits->fetchColumn() ?: 0;

$is_matrix = (!$is_branch && $limiteFiliais > 0);

// Helpers
$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// --- ACTIONS ---

// SAVE SETTINGS
if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campos Básicos
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    $endereco = $_POST['endereco'];
    $bairro = $_POST['bairro'];
    $cidade = $_POST['cidade'];
    $estado = $_POST['estado'];
    $cep = $_POST['cep'];
    
    // Campos de Tema (Só salva se NÃO for filial)
    if (!$is_branch) {
        $logo_url = $_POST['logo_url'];
        $cor_primaria = $_POST['cor_primaria'];
        $cor_secundaria = $_POST['cor_secundaria'];
        
        $sql = "UPDATE igrejas SET nome=?, telefone=?, endereco=?, bairro=?, cidade=?, estado=?, cep=?, logo_url=?, cor_primaria=?, cor_secundaria=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $telefone, $endereco, $bairro, $cidade, $estado, $cep, $logo_url, $cor_primaria, $cor_secundaria, $igreja_id]);
    } else {
        // Filial só atualiza dados cadastrais
        $sql = "UPDATE igrejas SET nome=?, telefone=?, endereco=?, bairro=?, cidade=?, estado=?, cep=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $telefone, $endereco, $bairro, $cidade, $estado, $cep, $igreja_id]);
    }

    echo "<script>window.location.href='index.php?page=configuracoes&msg=saved';</script>";
    exit;
}

// CREATE BRANCH
if ($action === 'create_branch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_matrix) die("Apenas matriz pode criar filiais.");
    
    // Check Limits
    if (!PlanEnforcer::canAdd($pdo, 'filiais')) {
        PlanEnforcer::renderUpgradeModal('Limite de filiais do seu plano atingido!');
    }

    $nome = $_POST['branch_nome'];
    $email = $_POST['branch_email']; // Admin inicial da filial
    $senha = password_hash($_POST['branch_senha'], PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        // 1. Criar Igreja Filial (parent_id = $igreja_id)
        $stmt = $pdo->prepare("INSERT INTO igrejas (nome, parent_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$nome, $igreja_id]);
        $newBranchId = $pdo->lastInsertId();
        
        // 2. Criar Admin da Filial
        $stmtUser = $pdo->prepare("INSERT INTO usuarios (igreja_id, nome, email, senha, nivel) VALUES (?, ?, ?, ?, 'admin')");
        $stmtUser->execute([$newBranchId, 'Admin Filial', $email, $senha]);
        $newUserId = $pdo->lastInsertId();

        // 3. Assign Role (RBAC) - Admin
        $roleId = $pdo->query("SELECT id FROM papeis WHERE nome='Administrador' AND is_system=1")->fetchColumn();
        if ($roleId) {
             $pdo->prepare("INSERT INTO papel_usuario (usuario_id, papel_id) VALUES (?, ?)")->execute([$newUserId, $roleId]);
        }
        
        $pdo->commit();
        echo "<script>window.location.href='index.php?page=configuracoes&tab=filiais&msg=branch_created';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Erro: " . $e->getMessage();
    }
}

// --- VIEW DATA ---
// Reload tenant data ensure freshness
$tenantData = $pdo->query("SELECT * FROM igrejas WHERE id = " . $igreja_id)->fetch();

// Filiais (se matriz)
$filiais = [];
if ($is_matrix) {
    $filiais = $pdo->query("SELECT * FROM igrejas WHERE parent_id = " . $igreja_id)->fetchAll();
}

$activeTab = $_GET['tab'] ?? 'dados';
?>

<div class="fade-in">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-cog text-primary mr-2"></i> Configurações</h2>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='saved'): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4 shadow">Configurações salvas com sucesso!</div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="flex gap-4 mb-6 border-b border-gray-200">
        <a href="index.php?page=configuracoes&tab=dados" class="pb-2 border-b-2 <?php echo $activeTab=='dados'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Dados da Igreja
        </a>
        <?php if ($is_matrix): ?>
        <a href="index.php?page=configuracoes&tab=filiais" class="pb-2 border-b-2 <?php echo $activeTab=='filiais'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Minhas Filiais
        </a>
        <?php endif; ?>
        <a href="index.php?page=configuracoes&tab=tema" class="pb-2 border-b-2 <?php echo $activeTab=='tema'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Cores e Tema
        </a>
    </div>

    <!-- TAB: DADOS -->
    <?php if ($activeTab === 'dados'): ?>
    <form method="POST" action="index.php?page=configuracoes&action=save_settings" class="bg-white p-6 rounded-xl shadow">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Col 1 -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Nome da Igreja</label>
                <input type="text" name="nome" value="<?php echo e($tenantData['nome']); ?>" class="w-full border rounded p-2" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Telefone / WhatsApp</label>
                <input type="text" name="telefone" value="<?php echo e($tenantData['telefone'] ?? ''); ?>" class="w-full border rounded p-2">
            </div>
            
            <div class="md:col-span-2"><hr class="my-2 border-gray-100"></div>
            
            <!-- Endereço -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">CEP</label>
                <input type="text" name="cep" value="<?php echo e($tenantData['cep'] ?? ''); ?>" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Endereço (Rua, Nº)</label>
                <input type="text" name="endereco" value="<?php echo e($tenantData['endereco'] ?? ''); ?>" class="w-full border rounded p-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Bairro</label>
                <input type="text" name="bairro" value="<?php echo e($tenantData['bairro'] ?? ''); ?>" class="w-full border rounded p-2">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Cidade</label>
                    <input type="text" name="cidade" value="<?php echo e($tenantData['cidade'] ?? ''); ?>" class="w-full border rounded p-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">UF</label>
                    <select name="estado" class="w-full border rounded p-2">
                        <option value="">--</option>
                        <?php foreach($estados as $uf): ?>
                            <option value="<?php echo $uf; ?>" <?php echo ($tenantData['estado']??'') == $uf ? 'selected' : ''; ?>><?php echo $uf; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Hidden Fields for Theme -->
            <input type="hidden" name="logo_url" value="<?php echo e($tenantData['logo_url']); ?>">
            <input type="hidden" name="cor_primaria" value="<?php echo e($tenantData['cor_primaria']); ?>">
            <input type="hidden" name="cor_secundaria" value="<?php echo e($tenantData['cor_secundaria']); ?>">

            <div class="md:col-span-2 mt-4">
                <button type="submit" class="bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition">
                    Salvar Alterações
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <!-- TAB: FILIAIS -->
    <?php if ($activeTab === 'filiais' && $is_matrix): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Lista -->
        <div class="md:col-span-2 bg-white rounded-xl shadow p-6">
            <h3 class="font-bold text-lg mb-4 text-gray-700">Filiais Cadastradas</h3>
            <?php if (count($filiais) > 0): ?>
                <div class="space-y-3">
                    <?php foreach($filiais as $f): ?>
                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50">
                        <div>
                            <p class="font-bold text-gray-800"><?php echo e($f['nome']); ?></p>
                            <p class="text-xs text-gray-500"><i class="fas fa-map-marker-alt"></i> <?php echo e($f['cidade']); ?> - <?php echo e($f['estado']); ?></p>
                        </div>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">Ativa</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400">Nenhuma filial cadastrada.</p>
            <?php endif; ?>
        </div>

        <!-- Form Nova -->
        <div class="bg-blue-50 rounded-xl shadow p-6 border border-blue-100 h-fit">
            <h3 class="font-bold text-lg mb-4 text-blue-800">Nova Filial</h3>
            <p class="text-xs text-blue-600 mb-4">
                Seu plano permite até <strong><?php echo $limiteFiliais; ?></strong> filiais.
                <br>Uso atual: <strong><?php echo count($filiais); ?></strong>
            </p>
            
            <form method="POST" action="index.php?page=configuracoes&action=create_branch">
                <input type="hidden" name="action" value="create_branch">
                
                <div class="mb-3">
                    <label class="block text-xs font-bold text-blue-800 mb-1">Nome da Filial</label>
                    <input type="text" name="branch_nome" class="w-full border border-blue-200 rounded p-2" required placeholder="Ex: Filial Centro">
                </div>
                <div class="mb-3">
                    <label class="block text-xs font-bold text-blue-800 mb-1">E-mail Admin (Responsável)</label>
                    <input type="email" name="branch_email" class="w-full border border-blue-200 rounded p-2" required placeholder="email@filial.com">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-blue-800 mb-1">Senha Inicial</label>
                    <input type="password" name="branch_senha" class="w-full border border-blue-200 rounded p-2" required>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 rounded hover:bg-blue-700 transition">
                    + Criar Filial
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB: TEMA -->
    <?php if ($activeTab === 'tema'): ?>
    <form method="POST" action="index.php?page=configuracoes&action=save_settings" class="bg-white p-6 rounded-xl shadow">
        
        <?php if ($is_branch): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                <p class="font-bold">Tema Gerenciado pela Matriz</p>
                <p>As filiais utilizam automaticamente a identidade visual definida pela igreja sede.</p>
            </div>
        <?php endif; ?>

        <h3 class="font-bold text-lg mb-4 text-gray-700">Identidade Visual</h3>
        
        <!-- Disabled Overlay if Branch -->
        <fieldset <?php echo $is_branch ? 'disabled class="opacity-50 pointer-events-none"' : ''; ?>>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">URL do Logo</label>
                    <input type="text" name="logo_url" value="<?php echo e($tenantData['logo_url']); ?>" class="w-full border rounded p-2" placeholder="https://...">
                    <?php if($tenantData['logo_url']): ?>
                        <img src="<?php echo e($tenantData['logo_url']); ?>" class="mt-4 h-20 object-contain border p-2 rounded">
                    <?php endif; ?>
                </div>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Cor Primária</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="cor_primaria" value="<?php echo e($tenantData['cor_primaria']); ?>" class="h-10 w-20 border rounded p-1 cursor-pointer">
                    </div>

                    <label class="block text-sm font-bold text-gray-700 mb-1 mt-4">Cor Secundária</label>
                    <div class="flex items-center gap-2">
                        <input type="color" name="cor_secundaria" value="<?php echo e($tenantData['cor_secundaria']); ?>" class="h-10 w-20 border rounded p-1 cursor-pointer">
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- Hidden Data Fields (Always needed) -->
        <input type="hidden" name="nome" value="<?php echo e($tenantData['nome']); ?>">
        <input type="hidden" name="telefone" value="<?php echo e($tenantData['telefone'] ?? ''); ?>">
        <input type="hidden" name="cep" value="<?php echo e($tenantData['cep'] ?? ''); ?>">
        <input type="hidden" name="endereco" value="<?php echo e($tenantData['endereco'] ?? ''); ?>">
        <input type="hidden" name="bairro" value="<?php echo e($tenantData['bairro'] ?? ''); ?>">
        <input type="hidden" name="cidade" value="<?php echo e($tenantData['cidade'] ?? ''); ?>">
        <input type="hidden" name="estado" value="<?php echo e($tenantData['estado'] ?? ''); ?>">
        
        <?php if(!$is_branch): ?>
        <div class="mt-6 border-t pt-4">
            <button type="submit" class="bg-primary text-white font-bold py-2 px-6 rounded hover:opacity-90 transition">
                Salvar Aparência
            </button>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>

</div>
