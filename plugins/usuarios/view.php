<?php
// plugins/usuarios/view.php

// Fail-safe ACL Check
if (!has_role('admin')) {
    echo "<div class='bg-red-100 text-red-700 p-4'>Acesso Negado.</div>";
    exit;
}

$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

// --- LOGIC: DELETE ---
if ($action === 'delete' && isset($_GET['id'])) {
    // Evitar auto-deleção
    if ($_GET['id'] == $_SESSION['user_id']) {
        echo "<script>alert('Você não pode apagar seu próprio usuário!'); window.location.href='index.php?page=usuarios';</script>";
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND igreja_id = ?");
    if ($stmt->execute([$_GET['id'], $_SESSION['igreja_id']])) {
        echo "<script>window.location.href='index.php?page=usuarios&msg=deleted';</script>";
        exit;
    }
}

// --- LOGIC: SAVE / UPDATE TYPE HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'save' || $action === 'update')) {
    
    // Fallback View Mode in case of error
    $fallbackAction = ($action === 'save') ? 'new' : 'edit';
    
    $id = $_POST['id'] ?? null;
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $nivel = $_POST['nivel'] ?? 'secretario';
    $sexo = $_POST['sexo'] ?? 'M';
    $membro_id = !empty($_POST['membro_id']) ? $_POST['membro_id'] : null; // [NEW]
    $igreja_id = $_SESSION['igreja_id'];

    if ($id && $action === 'update') {
        // UPDATE
        if (!empty($senha)) {
            $hashed_pass = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "UPDATE usuarios SET nome=?, email=?, sexo=?, senha=?, nivel=?, membro_id=? WHERE id=? AND igreja_id=?";
            $params = [$nome, $email, $sexo, $hashed_pass, $nivel, $membro_id, $id, $igreja_id];
        } else {
            $sql = "UPDATE usuarios SET nome=?, email=?, sexo=?, nivel=?, membro_id=? WHERE id=? AND igreja_id=?";
            $params = [$nome, $email, $sexo, $nivel, $membro_id, $id, $igreja_id];
        }

        try {
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                // REFRESH SESSION IF EDITING SELF
                if ($id == $_SESSION['user_id']) {
                    $_SESSION['user_name'] = $nome;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_sexo'] = $sexo;
                    $_SESSION['membro_id'] = $membro_id; // [NEW] Refresh connection
                }
                echo "<script>window.location.href='index.php?page=usuarios&msg=updated';</script>";
                exit;
            }
        } catch (PDOException $e) {
            $error = "Erro ao atualizar: " . $e->getMessage();
        }

    } else {
        // INSERT
        
        // --- PLAN ENFORCER CHECK ---
        if (!PlanEnforcer::canAdd($pdo, 'usuarios')) {
             PlanEnforcer::renderUpgradeModal("Seu plano atingiu o limite de usuários/tesoureiros.");
        }

        if (empty($senha)) {
            $error = "Senha é obrigatória para novos usuários.";
            $action = $fallbackAction; // <--- Show Form
        } else {
            $hashed_pass = password_hash($senha, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (igreja_id, nome, email, sexo, senha, nivel, membro_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            try {
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$igreja_id, $nome, $email, $sexo, $hashed_pass, $nivel, $membro_id])) {
                    echo "<script>window.location.href='index.php?page=usuarios&msg=success';</script>";
                    exit;
                }
            } catch (PDOException $e) {
                // Provavelmente duplicidade de email
                $error = "Erro ao criar: " . $e->getMessage();
                $action = $fallbackAction; // <--- Show Form
            }
        }
    }
}

// --- VIEW: FORM ---
if ($action === 'new' || $action === 'edit') {
    
    // Check Limit on NEW
    if ($action === 'new' && !PlanEnforcer::canAdd($pdo, 'usuarios')) {
         PlanEnforcer::renderUpgradeModal("Seu plano atingiu o limite de usuários/tesoureiros.");
    }
    
    $user = null;
    if ($action === 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? AND igreja_id = ?");
        $stmt->execute([$_GET['id'], $_SESSION['igreja_id']]);
        $user = $stmt->fetch();
    }

    $nome = $user ? $user['nome'] : '';
    $email = $user ? $user['email'] : '';
    $nivel = $user ? $user['nivel'] : 'secretario';
    $sexo = $user ? $user['sexo'] : 'M';
    $id = $user ? $user['id'] : '';
    
    $formAction = $action === 'edit' ? 'update' : 'save';
    $titulo = $action === 'edit' ? 'Editar Usuário' : 'Novo Usuário';
?>
    <div class="bg-white rounded-xl shadow p-6 mb-20 fade-in">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800"><?php echo $titulo; ?></h2>
            <a href="index.php?page=usuarios" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="index.php?page=usuarios&action=<?php echo $formAction; ?>" method="POST" class="space-y-4">
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo $id; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-1">
                    <label class="block text-gray-700 font-bold mb-2">Sexo</label>
                    <select name="sexo" id="sexoSelect" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white">
                        <option value="M" <?php echo $sexo === 'M' ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo $sexo === 'F' ? 'selected' : ''; ?>>Feminino</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-gray-700 font-bold mb-2">Nome</label>
                    <input type="text" name="nome" value="<?php echo e($nome); ?>" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 font-bold mb-2">Email (Login)</label>
                <input type="email" name="email" value="<?php echo e($email); ?>" required class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">
                        <?php echo $action === 'edit' ? 'Nova Senha (deixe em branco para manter)' : 'Senha'; ?>
                    </label>
                    <input type="password" name="senha" <?php echo $action === 'new' ? 'required' : ''; ?> class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none" placeholder="******">
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">Nível de Acesso (Função no Sistema)</label>
                    <select name="nivel" id="nivelSelect" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white">
                        <option value="admin" <?php echo $nivel === 'admin' ? 'selected' : ''; ?>>Administrador (Acesso Total)</option>
                        <option value="tesoureiro" <?php echo $nivel === 'tesoureiro' ? 'selected' : ''; ?>>Tesoureiro (Financeiro + Membros)</option>
                        <option value="secretario" <?php echo $nivel === 'secretario' ? 'selected' : ''; ?>>Secretário (Apenas Membros)</option>
                    </select>
                </div>
            </div>

            <!-- Vincular a Membro (Role Switch) -->
            <?php
            // Buscar membros para vincular
            $stmtMembros = $pdo->prepare("SELECT id, nome FROM membros WHERE igreja_id = ? ORDER BY nome ASC");
            $stmtMembros->execute([$_SESSION['igreja_id']]);
            $listaMembros = $stmtMembros->fetchAll();
            $membro_id = $user['membro_id'] ?? '';
            ?>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <label class="block text-gray-700 font-bold mb-2 flex items-center gap-2">
                    <i class="fas fa-link text-blue-500"></i> Vincular a um Membro (Opcional)
                </label>
                <p class="text-sm text-gray-500 mb-2">Ao vincular, este usuário poderá alternar para sua visão de "Minha Carteirinha" e "Meu Perfil" sem sair do sistema.</p>
                <select name="membro_id" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-primary focus:outline-none bg-white">
                    <option value="">-- Sem vínculo --</option>
                    <?php foreach ($listaMembros as $lm): ?>
                        <option value="<?php echo $lm['id']; ?>" <?php echo $membro_id == $lm['id'] ? 'selected' : ''; ?>>
                            <?php echo e($lm['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <script>
                // Logic to swap labels based on gender
                document.addEventListener('DOMContentLoaded', function() {
                    const sexoSelect = document.getElementById('sexoSelect');
                    const nivelSelect = document.getElementById('nivelSelect');
                    
                    const labelsM = {
                        'admin': 'Administrador (Acesso Total)',
                        'tesoureiro': 'Tesoureiro (Financeiro + Membros)',
                        'secretario': 'Secretário (Apenas Membros)'
                    };
                    
                    const labelsF = {
                        'admin': 'Administradora (Acesso Total)',
                        'tesoureiro': 'Tesoureira (Financeiro + Membros)',
                        'secretario': 'Secretária (Apenas Membros)'
                    };

                    function updateLabels() {
                        const gender = sexoSelect.value;
                        const labels = gender === 'M' ? labelsM : labelsF;
                        
                        // Iterate options and update text (values remain same)
                        for (let i = 0; i < nivelSelect.options.length; i++) {
                            const opt = nivelSelect.options[i];
                            if (labels[opt.value]) {
                                opt.text = labels[opt.value];
                            }
                        }
                    }

                    sexoSelect.addEventListener('change', updateLabels);
                    updateLabels(); // Init
                });
            </script>

            <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:brightness-90 transition mt-6 shadow-md">
                Salvar Usuário
            </button>
        </form>
    </div>
<?php
}
// --- VIEW: LIST USERS ---
else {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE igreja_id = ? ORDER BY nome ASC");
    $stmt->execute([$_SESSION['igreja_id']]);
    $usuarios = $stmt->fetchAll();
?>
    <div class="space-y-4 fade-in">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Equipe & Acessos</h2>
                <p class="text-gray-500 text-sm">Gerencie quem pode acessar o sistema.</p>
            </div>
            <a href="index.php?page=usuarios&action=new" class="bg-primary text-white p-3 rounded-full w-12 h-12 flex items-center justify-center shadow-lg hover:scale-105 transition transform">
                <i class="fas fa-plus"></i>
            </a>
        </div>

        <?php if ($msg == 'success'): ?>
             <div class="bg-green-100 text-green-700 p-3 rounded-lg text-sm border-l-4 border-green-500">Usuário criado com sucesso!</div>
        <?php elseif ($msg == 'updated'): ?>
             <div class="bg-blue-100 text-blue-700 p-3 rounded-lg text-sm border-l-4 border-blue-500">Dados atualizados!</div>
        <?php elseif ($msg == 'deleted'): ?>
             <div class="bg-red-100 text-red-700 p-3 rounded-lg text-sm border-l-4 border-red-500">Usuário removido.</div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pb-20">
            <?php foreach ($usuarios as $u): ?>
                <div class="bg-white rounded-xl shadow p-6 border-t-4 <?php echo $u['nivel'] === 'admin' ? 'border-purple-500' : ($u['nivel'] === 'tesoureiro' ? 'border-green-500' : 'border-gray-400'); ?> relative group">
                    
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xl">
                            <?php echo strtoupper(substr($u['nome'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800"><?php echo e($u['nome']); ?></h3>
                            <span class="text-xs uppercase font-bold tracking-wider <?php echo $u['nivel'] === 'admin' ? 'text-purple-600' : 'text-gray-500'; ?>">
                                <?php 
                                    $roleMap = [
                                        'admin' => ['M' => 'Administrador', 'F' => 'Administradora'],
                                        'tesoureiro' => ['M' => 'Tesoureiro', 'F' => 'Tesoureira'],
                                        'secretario' => ['M' => 'Secretário', 'F' => 'Secretária'],
                                    ];
                                    $sexo = $u['sexo'] ?? 'M'; // Fallback
                                    echo $roleMap[$u['nivel']][$sexo] ?? ucfirst($u['nivel']);
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <p class="text-gray-500 text-sm mb-4">
                        <i class="fas fa-envelope mr-2"></i> <?php echo e($u['email']); ?>
                    </p>

                    <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                        <a href="index.php?page=usuarios&action=edit&id=<?php echo $u['id']; ?>" class="flex-1 bg-gray-50 text-gray-700 py-2 rounded text-center text-sm font-medium hover:bg-gray-100">
                            Editar
                        </a>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="index.php?page=usuarios&action=delete&id=<?php echo $u['id']; ?>" onclick="return confirm('Tem certeza? Essa ação não pode ser desfeita.')" class="bg-red-50 text-red-600 px-4 py-2 rounded text-center hover:bg-red-100">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php
}
?>
