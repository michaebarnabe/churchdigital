<?php
/**
 * backoffice.php
 * Painel Administrativo do SaaS (Super Admin)
 * Gerencia Igrejas (Tenants) e Planos
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
define('ABSPATH', true);
require_once 'includes/functions.php';

// Simple Auth Protection
$masterKey = getenv('MASTER_KEY') ?: 'admin123'; // Default fallback

if (isset($_GET['logout'])) {
    unset($_SESSION['is_master']);
    header("Location: backoffice.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_login'])) {
    if ($_POST['password'] === $masterKey) {
        $_SESSION['is_master'] = true;
    } else {
        $error = "Senha Mestra Inválida.";
    }
}

if (!isset($_SESSION['is_master'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>SaaS Backoffice</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 flex items-center justify-center h-screen">
        <div class="bg-white p-8 rounded-lg shadow-lg w-96">
            <h1 class="text-2xl font-bold mb-4 text-center">SaaS Master Login</h1>
            <?php if(isset($error)) echo "<p class='text-red-500 mb-4 text-center'>$error</p>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Master Key" class="w-full p-3 border rounded mb-4" required>
                <button type="submit" name="master_login" class="w-full bg-blue-600 text-white font-bold py-3 rounded hover:bg-blue-700">Entrar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- BACKOFFICE LOGIC ---

// Impersonate (Login as) Tenant Admin
if (isset($_GET['impersonate']) && isset($_SESSION['is_master'])) {
    $tenantId = $_GET['impersonate'];
    
    // Find the first admin user for this tenant
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE igreja_id = ? AND nivel = 'admin' LIMIT 1");
    $stmt->execute([$tenantId]);
    $adminUser = $stmt->fetch();

    if ($adminUser) {
        // Log in as this user
        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['user_name'] = $adminUser['nome'];
        $_SESSION['user_email'] = $adminUser['email'];
        $_SESSION['user_type'] = 'staff';
        $_SESSION['igreja_id'] = $adminUser['igreja_id'];
        $_SESSION['real_igreja_id'] = $adminUser['igreja_id']; // For context switching
        
        // Roles & Permissions
        $stmtRoles = $pdo->prepare("
            SELECT p.nome 
            FROM papeis p 
            JOIN papel_usuario pu ON p.id = pu.papel_id 
            WHERE pu.usuario_id = ?
        ");
        $stmtRoles->execute([$adminUser['id']]);
        $_SESSION['user_roles'] = $stmtRoles->fetchAll(PDO::FETCH_COLUMN) ?: ['User'];
        
        // Redirect to Dashboard
        header("Location: index.php");
        exit;
    } else {
        $error = "Nenhum administrador encontrado para esta igreja.";
    }
}

// Create Tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tenant'])) {
    try {
        $pdo->beginTransaction();
        
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $planoId = $_POST['plano_id'];
        
        // 1. Igreja
        $stmt = $pdo->prepare("INSERT INTO igrejas (nome) VALUES (?)");
        $stmt->execute([$nome]);
        $igrejaId = $pdo->lastInsertId();
        
        // 2. Admin User
        $stmt = $pdo->prepare("INSERT INTO usuarios (igreja_id, nome, email, senha, nivel) VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([$igrejaId, 'Administrador', $email, $senha]);
        $userId = $pdo->lastInsertId();

        // 3. Assign Role (RBAC) - Busca ID do papel 'Administrador'
        $roleId = $pdo->query("SELECT id FROM papeis WHERE nome='Administrador' AND is_system=1")->fetchColumn();
        if ($roleId) {
             $pdo->prepare("INSERT INTO papel_usuario (usuario_id, papel_id) VALUES (?, ?)")->execute([$userId, $roleId]);
        }
        
        // 4. Assinatura
        $stmt = $pdo->prepare("INSERT INTO assinaturas (igreja_id, plano_id, data_inicio, data_fim) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))");
        $stmt->execute([$igrejaId, $planoId]);
        
        $pdo->commit();
        $msg = "Tenant '$nome' criado com sucesso!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao criar: " . $e->getMessage();
    }
}

// Delete Pending Tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pending'])) {
    $p_igreja_id = $_POST['delete_id'];
    try {
        $pdo->beginTransaction();
        
        // Delete Dependencies Inverse Order
        // 1. Papel Users
        $pdo->prepare("DELETE pu FROM papel_usuario pu JOIN usuarios u ON pu.usuario_id = u.id WHERE u.igreja_id = ?")->execute([$p_igreja_id]);
        
        // 2. Users
        $pdo->prepare("DELETE FROM usuarios WHERE igreja_id = ?")->execute([$p_igreja_id]);
        
        // 3. Subscriptions
        $pdo->prepare("DELETE FROM assinaturas WHERE igreja_id = ?")->execute([$p_igreja_id]);
        
        // 4. Church
        $pdo->prepare("DELETE FROM igrejas WHERE id = ?")->execute([$p_igreja_id]);

        $pdo->commit();
        $msg = "Tenant pendente removido com sucesso.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao deletar: " . $e->getMessage();
    }
}

// Update Tenant Admin Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_email'])) {
    $u_id = $_POST['admin_user_id'];
    $new_email = $_POST['new_email'];
    
    try {
        $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?")->execute([$new_email, $u_id]);
        $msg = "E-mail do administrador atualizado com sucesso.";
    } catch (PDOException $e) {
        $error = "Erro ao atualizar: " . $e->getMessage();
    }
}

// List Tenants
$tenants = $pdo->query("
    SELECT i.*, 
           MAX(p.nome) as plano_nome, 
           MAX(a.status) as plano_status, 
           COUNT(u.id) as num_users 
    FROM igrejas i 
    LEFT JOIN assinaturas a ON i.id = a.igreja_id
    LEFT JOIN planos p ON a.plano_id = p.id
    LEFT JOIN usuarios u ON i.id = u.igreja_id
    WHERE a.status = 'ativa' OR a.status IS NULL
    GROUP BY i.id
    ORDER BY i.created_at DESC
")->fetchAll();

$pending_tenants = $pdo->query("
    SELECT i.*, 
           MAX(p.nome) as plano_nome, 
           MAX(a.status) as plano_status, 
           MAX(a.data_inicio) as sub_start
    FROM igrejas i 
    JOIN assinaturas a ON i.id = a.igreja_id
    LEFT JOIN planos p ON a.plano_id = p.id
    WHERE a.status IN ('pendente', 'aguardando_pagamento')
    GROUP BY i.id
    ORDER BY i.created_at DESC
")->fetchAll();

$planos = $pdo->query("SELECT * FROM planos")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Church Digital App - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-black text-white flex flex-col">
            <div class="p-4 flex items-center gap-3 border-b border-gray-800">
                <img src="assets/icons/icon-512.png" class="w-8 h-8 rounded bg-white p-1">
                <span class="font-bold text-sm">Church Digital App</span>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="#" class="block p-3 bg-gray-800 rounded text-gray-100 font-medium"><i class="fas fa-church mr-2"></i> Igrejas</a>
                <a href="#" class="block p-3 hover:bg-gray-800 rounded opacity-50 text-gray-400"><i class="fas fa-money-bill mr-2"></i> Planos (Em breve)</a>
            </nav>
            <div class="p-4">
                <a href="?logout=true" class="block text-center text-sm text-gray-500 hover:text-white"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </aside>
        
        <!-- Content -->
        <main class="flex-grow p-8 overflow-y-auto w-full">
            <h1 class="text-3xl font-bold mb-6 text-black">Gestão de Tenants</h1>
            
            <?php if(isset($msg)): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded mb-4 font-bold border-l-4 border-green-500"><?php echo $msg; ?></div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded mb-4 font-bold border-l-4 border-red-500"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <nav class="-mb-px flex gap-6">
                    <a href="?tab=tenants" class="pb-4 px-2 border-b-2 font-bold <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'tenants') ? 'border-black text-black' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        Igrejas Ativas
                    </a>
                    <a href="?tab=pending" class="pb-4 px-2 border-b-2 font-bold <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'pending') ? 'border-amber-500 text-amber-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        Pendentes/Falhas
                    </a>
                    <a href="?tab=plans" class="pb-4 px-2 border-b-2 font-bold <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'plans') ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                        Planos
                    </a>
                </nav>
            </div>

            <?php if (isset($_GET['tab']) && $_GET['tab'] == 'pending'): ?>
                <!-- PENDING TENANTS -->
                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-amber-50 border-b border-amber-100">
                            <tr>
                                <th class="p-4">ID</th>
                                <th class="p-4">Igreja</th>
                                <th class="p-4">Plano</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Data Tentativa</th>
                                <th class="p-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pending_tenants as $pt): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4 text-gray-500 font-mono text-sm">#<?php echo $pt['id']; ?></td>
                                <td class="p-4 font-bold"><?php echo htmlspecialchars($pt['nome']); ?></td>
                                <td class="p-4"><?php echo $pt['plano_nome']; ?></td>
                                <td class="p-4">
                                    <span class="bg-amber-100 text-amber-800 text-xs px-2 py-1 rounded font-bold">
                                        <?php echo $pt['plano_status']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($pt['created_at'])); ?></td>
                                <td class="p-4">
                                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja remover este cadastro incompleto?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $pt['id']; ?>">
                                        <button type="submit" name="delete_pending" class="text-red-500 hover:text-red-700 font-bold text-sm">
                                            <i class="fas fa-trash"></i> Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($pending_tenants)): ?>
                                <tr><td colspan="6" class="p-8 text-center text-gray-500">Nenhum cadastro pendente.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif (isset($_GET['tab']) && $_GET['tab'] == 'plans'): ?>
                <!-- PLANS MANAGEMENT -->
                <?php
                // Handle Plan Update
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plan'])) {
                    $pid = $_POST['plan_id'];
                    $pnome = $_POST['nome'];
                    $ppreco = $_POST['preco'];
                    $pmembros = $_POST['limite_membros'];
                    $pfiliais = $_POST['limite_filiais'];
                    $pextra_filial = $_POST['preco_filial_extra'];
                    
                    $stmt = $pdo->prepare("UPDATE planos SET nome=?, preco=?, limite_membros=?, limite_filiais=?, preco_filial_extra=? WHERE id=?");
                    if ($stmt->execute([$pnome, $ppreco, $pmembros, $pfiliais, $pextra_filial, $pid])) {
                        echo "<script>window.location.href='backoffice.php?tab=plans&msg=updated';</script>";
                        exit;
                    }
                }
                
                $allPlans = $pdo->query("SELECT * FROM planos ORDER BY preco ASC")->fetchAll();
                ?>
                
                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-4">Plano</th>
                                <th class="p-4">Preço (R$)</th>
                                <th class="p-4">Limites</th>
                                <th class="p-4">Extras (R$)</th>
                                <th class="p-4 w-10">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($allPlans as $p): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <form method="POST">
                                    <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                    <td class="p-4">
                                        <input type="text" name="nome" value="<?php echo htmlspecialchars($p['nome']); ?>" class="border rounded p-1 w-full font-bold focus:ring-black focus:border-black">
                                    </td>
                                    <td class="p-4">
                                        <input type="number" step="0.01" name="preco" value="<?php echo $p['preco']; ?>" class="border rounded p-1 w-24 focus:ring-black focus:border-black">
                                    </td>
                                    <td class="p-4 text-sm text-gray-600">
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-users w-4"></i>
                                            <input type="number" name="limite_membros" value="<?php echo $p['limite_membros']; ?>" class="border rounded p-1 w-20 text-xs focus:ring-black focus:border-black"> membros
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-code-branch w-4"></i>
                                            <input type="number" name="limite_filiais" value="<?php echo $p['limite_filiais']; ?>" class="border rounded p-1 w-20 text-xs focus:ring-black focus:border-black"> filiais
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm">
                                        <div class="flex items-center gap-2" title="Preço por Filial Extra">
                                            <span class="text-xs text-gray-500">Filial Ex:</span>
                                            <input type="number" step="0.01" name="preco_filial_extra" value="<?php echo $p['preco_filial_extra']; ?>" class="border rounded p-1 w-20 text-xs focus:ring-black focus:border-black">
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <button type="submit" name="update_plan" class="bg-black text-white p-2 rounded hover:bg-gray-800 text-xs font-bold shadow">
                                            Salvar
                                        </button>
                                    </td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <!-- TENANTS MANAGEMENT (Default) -->
                
                <!-- New Tenant Form -->
                <div class="bg-white p-6 rounded shadow mb-8">
                    <h2 class="text-xl font-bold mb-4 font-poppins">Novo Cliente (Igreja)</h2>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <label class="block text-sm font-bold mb-1">Nome da Igreja</label>
                            <input type="text" name="nome" class="w-full p-2 border rounded focus:ring-2 focus:ring-black focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">E-mail Admin</label>
                            <input type="email" name="email" class="w-full p-2 border rounded focus:ring-2 focus:ring-black focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Senha Admin</label>
                            <input type="password" name="senha" class="w-full p-2 border rounded focus:ring-2 focus:ring-black focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold mb-1">Plano Inicial</label>
                            <select name="plano_id" class="w-full p-2 border rounded focus:ring-2 focus:ring-black focus:outline-none bg-white">
                                <?php foreach($planos as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo $p['nome']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="create_tenant" class="bg-black text-white font-bold h-10 rounded hover:bg-gray-800 md:col-start-4 shadow-lg transition transform hover:scale-105">
                            <i class="fas fa-plus mr-1"></i> Criar Tenant
                        </button>
                    </form>
                </div>

                <!-- List -->
                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-4">ID</th>
                                <th class="p-4">Igreja</th>
                                <th class="p-4">Admin (Email)</th>
                                <th class="p-4">Plano</th>
                                <th class="p-4">Usuários</th>
                                <th class="p-4">Criado em</th>
                                <th class="p-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tenants as $t): 
                                // Fetch Admin
                                $admin = $pdo->query("SELECT id, email FROM usuarios WHERE igreja_id = {$t['id']} AND nivel = 'admin' LIMIT 1")->fetch();
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-4 text-gray-500 font-mono text-sm">#<?php echo $t['id']; ?></td>
                                <td class="p-4 font-bold"><?php echo htmlspecialchars($t['nome']); ?>
                                    <?php if($t['parent_id']): ?>
                                        <span class="text-xs bg-gray-200 text-gray-600 px-1 rounded ml-1">Filial</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-sm text-gray-700">
                                    <?php if ($admin): ?>
                                        <?php echo $admin['email']; ?>
                                        <button onclick="openEditModal('<?php echo $admin['id']; ?>', '<?php echo $admin['email']; ?>')" class="text-gray-500 hover:text-black ml-2" title="Editar E-mail">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-red-400">Sem Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-bold">
                                        <?php echo $t['plano_nome'] ?? 'Sem Plano'; ?>
                                    </span>
                                </td>
                                <td class="p-4"><?php echo $t['num_users']; ?></td>
                                <td class="p-4 text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
                                <td class="p-4">
                                    <a href="?impersonate=<?php echo $t['id']; ?>" class="text-black hover:underline text-sm font-bold" target="_blank">
                                        <i class="fas fa-sign-in-alt mt-1"></i> Acessar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Edit Admin Email Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center backdrop-blur-sm">
        <div class="bg-white p-6 rounded shadow-lg w-96 animate-fade-in-down">
            <h3 class="text-lg font-bold mb-4">Editar E-mail do Admin</h3>
            <form method="POST">
                <input type="hidden" name="update_admin_email" value="1">
                <input type="hidden" name="admin_user_id" id="modal_user_id">
                
                <label class="block mb-2 text-sm text-gray-600 font-bold">Novo E-mail</label>
                <input type="email" name="new_email" id="modal_email" class="w-full border p-2 rounded mb-4 focus:ring-2 focus:ring-black focus:outline-none" required>
                
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 font-bold rounded hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white font-bold rounded hover:bg-gray-800">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, email) {
            document.getElementById('modal_user_id').value = id;
            document.getElementById('modal_email').value = email;
            document.getElementById('editModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
