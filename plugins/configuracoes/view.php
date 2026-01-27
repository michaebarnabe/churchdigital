<?php
// plugins/configuracoes/view.php

$action = $_GET['action'] ?? 'view';
$igreja_id = TenantScope::getId();
$currentTenant = $pdo->query("SELECT * FROM igrejas WHERE id = $igreja_id")->fetch();
$parent_id = $currentTenant['parent_id'];
$is_branch = !empty($parent_id);

// 1. Identificar se é Matriz (pode ter filiais)
// Verifica limites do plano
$limiteFiliais = PlanEnforcer::getLimit($pdo, 'filiais');

$is_matrix = (!$is_branch && $limiteFiliais > 0);

// Helpers
$estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

// --- ACTIONS ---

// DELETE PIX KEY
if ($action === 'delete_pix' && isset($_GET['id'])) {
    if (!PlanEnforcer::canUseFeature($pdo, 'pix_module')) {
        PlanEnforcer::renderUpgradeModal("Gestão de PIX é recurso PRO.");
    }
    $stmt = $pdo->prepare("DELETE FROM pix_keys WHERE id = ? AND igreja_id = ?");
    $stmt->execute([$_GET['id'], $igreja_id]);
    echo "<script>window.location.href='index.php?page=configuracoes&tab=pix&msg=deleted';</script>";
    exit;
}

// SAVE PIX KEY
if ($action === 'save_pix' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!PlanEnforcer::canUseFeature($pdo, 'pix_module')) {
        PlanEnforcer::renderUpgradeModal("Gestão de PIX é recurso PRO.");
    }

    $tipo = $_POST['tipo'];
    $chave = $_POST['chave'];
    $titular = $_POST['titular'];
    $filial_id = !empty($_POST['filial_id']) ? $_POST['filial_id'] : null;

    $stmt = $pdo->prepare("INSERT INTO pix_keys (igreja_id, filial_id, tipo, chave, titular) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$igreja_id, $filial_id, $tipo, $chave, $titular])) {
        echo "<script>window.location.href='index.php?page=configuracoes&tab=pix&msg=saved';</script>";
        exit;
    }
}

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
        $cnpj = $_POST['cnpj']; // [NEW] CNPJ UPDATE
        
        $sql = "UPDATE igrejas SET nome=?, telefone=?, endereco=?, bairro=?, cidade=?, estado=?, cep=?, logo_url=?, cor_primaria=?, cor_secundaria=?, cnpj=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $telefone, $endereco, $bairro, $cidade, $estado, $cep, $logo_url, $cor_primaria, $cor_secundaria, $cnpj, $igreja_id]);
    } else {
        // Filial só atualiza dados cadastrais
        $sql = "UPDATE igrejas SET nome=?, telefone=?, endereco=?, bairro=?, cidade=?, estado=?, cep=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $telefone, $endereco, $bairro, $cidade, $estado, $cep, $igreja_id]);
    }

    echo "<script>window.location.href='index.php?page=configuracoes&msg=saved';</script>";
    exit;
}

// DELETE BRANCH
if ($action === 'delete_branch' && isset($_GET['id'])) {
    if (!$is_matrix) die("Acesso Negado.");
    
    $branchId = $_GET['id'];
    
    // Check ownership
    $check = $pdo->prepare("SELECT id FROM igrejas WHERE id = ? AND parent_id = ?");
    $check->execute([$branchId, $igreja_id]);
    if (!$check->fetch()) die("Filial não encontrada.");

    try {
        $pdo->beginTransaction();
        
        // 1. Delete Dependencies
        // Papel Users
        $pdo->prepare("DELETE pu FROM papel_usuario pu JOIN usuarios u ON pu.usuario_id = u.id WHERE u.igreja_id = ?")->execute([$branchId]);
        
        // Users
        $pdo->prepare("DELETE FROM usuarios WHERE igreja_id = ?")->execute([$branchId]);

        // Members, Financials, etc would need cascade delete here ideally, OR rely on FK ON DELETE CASCADE if configured.
        // Assuming strict schema manually:
        $pdo->prepare("DELETE FROM membros WHERE igreja_id = ?")->execute([$branchId]);
        $pdo->prepare("DELETE FROM financeiro_basico WHERE igreja_id = ?")->execute([$branchId]);
        $pdo->prepare("DELETE FROM eventos WHERE igreja_id = ?")->execute([$branchId]);
        
        // Church
        $pdo->prepare("DELETE FROM igrejas WHERE id = ?")->execute([$branchId]);
        
        $pdo->commit();
        echo "<script>window.location.href='index.php?page=configuracoes&tab=filiais&msg=branch_deleted';</script>";
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Erro ao deletar: " . $e->getMessage();
    }
}

// UPDATE BRANCH
if ($action === 'update_branch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_matrix) die("Acesso Negado.");
    
    $b_id = $_POST['branch_id'];
    $b_nome = $_POST['nome'];
    $admin_email = $_POST['admin_email'];
    $admin_pass = $_POST['admin_senha'] ?? '';
    
    // Check ownership
    $check = $pdo->prepare("SELECT id FROM igrejas WHERE id = ? AND parent_id = ?");
    $check->execute([$b_id, $igreja_id]);
    if (!$check->fetch()) die("Filial não encontrada.");

    try {
        $pdo->beginTransaction();
        
        // Update Church Name
        $pdo->prepare("UPDATE igrejas SET nome = ? WHERE id = ?")->execute([$b_nome, $b_id]);
        
        // Update Admin User
        // Find admin
        $stmtAdmin = $pdo->prepare("SELECT id FROM usuarios WHERE igreja_id = ? AND nivel = 'admin' LIMIT 1");
        $stmtAdmin->execute([$b_id]);
        $adminId = $stmtAdmin->fetchColumn();
        
        if ($adminId) {
            if (!empty($admin_pass)) {
                $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE usuarios SET email = ?, senha = ? WHERE id = ?")->execute([$admin_email, $hash, $adminId]);
            } else {
                $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?")->execute([$admin_email, $adminId]);
            }
        }
        
        $pdo->commit();
        echo "<script>window.location.href='index.php?page=configuracoes&tab=filiais&msg=branch_updated';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Erro: " . $e->getMessage();
    }
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

// Buscar chaves PIX
$pixKeys = [];
if ($activeTab === 'pix') {
    $stmt = $pdo->prepare("
        SELECT p.*, i.nome as filial_nome 
        FROM pix_keys p
        LEFT JOIN igrejas i ON p.filial_id = i.id
        WHERE p.igreja_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->execute([$igreja_id]);
    $pixKeys = $stmt->fetchAll();
}
?>

<div class="fade-in pb-20">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-cog text-primary mr-2"></i> Configurações</h2>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']=='saved'): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4 shadow">Salvo com sucesso!</div>
    <?php elseif(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4 shadow">Removido com sucesso.</div>
     <?php elseif(isset($_GET['msg']) && $_GET['msg']=='branch_deleted'): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4 shadow">Filial removida com sucesso.</div>
     <?php elseif(isset($_GET['msg']) && $_GET['msg']=='branch_updated'): ?>
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4 shadow">Filial atualizada com sucesso.</div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="flex gap-4 mb-6 border-b border-gray-200 overflow-x-auto whitespace-nowrap no-scrollbar pb-1">
        <a href="index.php?page=configuracoes&tab=dados" class="pb-2 border-b-2 <?php echo $activeTab=='dados'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Dados da Igreja
        </a>
        <a href="index.php?page=configuracoes&tab=pix" class="pb-2 border-b-2 <?php echo $activeTab=='pix'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?> flex items-center gap-1">
            Chaves PIX
            <?php if(!PlanEnforcer::canUseFeature($pdo, 'pix_module')): ?>
                <span class="text-[0.6rem] bg-black text-white px-1 rounded uppercase font-bold">PRO</span>
            <?php endif; ?>
        </a>
        <?php if ($is_matrix): ?>
        <a href="index.php?page=configuracoes&tab=filiais" class="pb-2 border-b-2 <?php echo $activeTab=='filiais'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Minhas Filiais
        </a>
        <?php endif; ?>
        <a href="index.php?page=configuracoes&tab=tema" class="pb-2 border-b-2 <?php echo $activeTab=='tema'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Cores e Tema
        </a>
        <a href="index.php?page=configuracoes&tab=assinatura" class="pb-2 border-b-2 <?php echo $activeTab=='assinatura'?'border-primary text-primary font-bold':'border-transparent text-gray-500'; ?>">
            Assinatura
        </a>
    </div>

    <!-- TAB: PIX -->
    <?php if ($activeTab === 'pix'): ?>
        <?php if (!PlanEnforcer::canUseFeature($pdo, 'pix_module')): ?>
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-8 text-center">
                <div class="inline-flex p-4 rounded-full bg-black text-white text-3xl mb-4"><i class="fas fa-lock"></i></div>
                <h3 class="text-xl font-bold text-gray-800">Recurso Premium</h3>
                <p class="text-gray-600 mb-6">A gestão de chaves PIX e Doações é exclusiva para assinantes PRO.</p>
                <div class="w-full h-4 bg-gray-200 rounded-full mb-2 overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 w-full animate-pulse"></div>
                </div>
            </div>
            <!-- Mock visual -->
            <div class="opacity-20 pointer-events-none filter blur-sm mt-4 select-none">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded shadow">Mock Form PIX...</div>
                    <div class="bg-white p-6 rounded shadow">Mock List PIX...</div>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- FORM -->
                <div class="bg-white rounded-xl shadow p-6 h-fit">
                    <h3 class="font-bold text-lg mb-4 text-gray-700">Nova Chave PIX</h3>
                    <form method="POST" action="index.php?page=configuracoes&action=save_pix">
                        <div class="mb-3">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Tipo de Chave</label>
                            <select name="tipo" class="w-full p-2 border rounded" required>
                                <option value="cnpj">CNPJ</option>
                                <option value="cpf">CPF</option>
                                <option value="email">E-mail</option>
                                <option value="telefone">Telefone</option>
                                <option value="aleatoria">Chave Aleatória</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Chave</label>
                            <input type="text" name="chave" class="w-full p-2 border rounded" required placeholder="Digite a chave...">
                        </div>
                        <div class="mb-3">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nome do Titular</label>
                            <input type="text" name="titular" class="w-full p-2 border rounded" required placeholder="Quem vai receber?">
                        </div>
                        
                        <?php if($is_matrix && count($filiais) > 0): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Vincular a Filial (Opcional)</label>
                            <select name="filial_id" class="w-full p-2 border rounded">
                                <option value="">-- Toda a Igreja / Matriz --</option>
                                <?php foreach($filiais as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo e($f['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                             <p class="text-xs text-gray-500 mt-1">Se selecionado, apenas membros desta filial verão esta chave.</p>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="w-full bg-green-600 text-white font-bold py-2 rounded hover:brightness-90 transition">
                            <i class="fas fa-plus"></i> Adicionar Chave
                        </button>
                    </form>
                </div>

                <!-- LIST -->
                <div class="md:col-span-2 space-y-4">
                    <h3 class="font-bold text-lg text-gray-700">Chaves Cadastradas</h3>
                    <?php if(count($pixKeys) > 0): ?>
                        <?php foreach($pixKeys as $k): ?>
                            <div class="bg-white rounded-xl shadow p-4 flex justify-between items-center group">
                                <div class="flex items-center gap-4">
                                    <div class="bg-green-50 text-green-600 w-12 h-12 rounded-full flex items-center justify-center text-xl">
                                        <i class="fas fa-qrcode"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-800"><?php echo e($k['titular']); ?></p>
                                        <p class="text-sm text-gray-600 font-mono"><?php echo e($k['chave']); ?> (<?php echo strtoupper($k['tipo']); ?>)</p>
                                        <?php if($k['filial_nome']): ?>
                                            <span class="text-xs bg-blue-100 text-blue-700 px-2 rounded-full">Filial: <?php echo e($k['filial_nome']); ?></span>
                                        <?php elseif($is_matrix): ?>
                                            <span class="text-xs bg-gray-100 text-gray-600 px-2 rounded-full">Global / Matriz</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="index.php?page=configuracoes&action=delete_pix&id=<?php echo $k['id']; ?>" class="text-gray-300 hover:text-red-500 transition" onclick="return confirm('Apagar chave?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                         <div class="text-center py-10 text-gray-400 bg-white rounded-xl border border-dashed">
                            <i class="fas fa-wallet text-4xl mb-2 opacity-50"></i>
                            <p>Nenhuma chave PIX cadastrada.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

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
            
            <?php if(!$is_branch): ?>
            <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">CNPJ</label>
                <input type="text" name="cnpj" value="<?php echo e($tenantData['cnpj'] ?? ''); ?>" class="w-full border rounded p-2 font-mono bg-gray-50 focus:bg-white transition" placeholder="00.000.000/0000-00">
            </div>
            <?php endif; ?>
            
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
                    <?php foreach($filiais as $f): 
                        // Get Admin Email
                        $adminF = $pdo->query("SELECT email FROM usuarios WHERE igreja_id={$f['id']} AND nivel='admin' LIMIT 1")->fetchColumn();
                    ?>
                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50 group">
                        <div>
                            <p class="font-bold text-gray-800"><?php echo e($f['nome']); ?></p>
                            <p class="text-xs text-gray-500"><i class="fas fa-user-shield"></i> <?php echo e($adminF); ?></p>
                        </div>
                        <div class="flex gap-2">
                             <button onclick="openEditBranch('<?php echo $f['id']; ?>', '<?php echo htmlspecialchars(addslashes($f['nome'])); ?>', '<?php echo $adminF; ?>')" class="text-blue-500 hover:text-blue-700 p-2 hover:bg-blue-50 rounded" title="Editar">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <a href="index.php?page=configuracoes&action=delete_branch&id=<?php echo $f['id']; ?>" class="text-red-400 hover:text-red-700 p-2 hover:bg-red-50 rounded" onclick="return confirm('Tem certeza que deseja EXCLUIR esta filial? Esta ação removerá TODOS os dados dela e é irreversível.');" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
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

<!-- Edit Branch Modal -->
<?php if($activeTab === 'filiais'): ?>
<div id="editBranchModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center backdrop-blur-sm z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-96 animate-fade-in-down">
        <h3 class="text-lg font-bold mb-4">Editar Filial</h3>
        <form method="POST" action="index.php?page=configuracoes&action=update_branch">
            <input type="hidden" name="branch_id" id="modal_branch_id">
            
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-700 mb-1">Nome da Filial</label>
                <input type="text" name="nome" id="modal_branch_nome" class="w-full border rounded p-2 focus:ring-2 focus:ring-black focus:outline-none" required>
            </div>
            
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-700 mb-1">E-mail Admin</label>
                <input type="email" name="admin_email" id="modal_branch_email" class="w-full border rounded p-2 focus:ring-2 focus:ring-black focus:outline-none" required>
            </div>
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-700 mb-1">Nova Senha Admin (Opcional)</label>
                <input type="password" name="admin_senha" class="w-full border rounded p-2 focus:ring-2 focus:ring-black focus:outline-none" placeholder="Deixe em branco para manter">
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('editBranchModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 font-bold rounded hover:bg-gray-300 transition">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-black text-white font-bold rounded hover:bg-gray-800 transition">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditBranch(id, nome, email) {
    document.getElementById('modal_branch_id').value = id;
    document.getElementById('modal_branch_nome').value = nome;
    document.getElementById('modal_branch_email').value = email;
    document.getElementById('editBranchModal').classList.remove('hidden');
}
</script>
<?php endif; ?>

<?php
    if ($activeTab === 'assinatura') {
        include __DIR__ . '/tab_assinatura.php';
    }
?>

</div>

