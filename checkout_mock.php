<?php
require_once 'config.php';

$plan_id = $_GET['plan_id'] ?? null;

if (!$plan_id) {
    header("Location: pricing.php");
    exit;
}

// Fetch Plan Details
$stmt = $pdo->prepare("SELECT * FROM planos WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    die("Plano não encontrado.");
}

// Handle Form Submission (Provisioning)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_igreja = $_POST['nome_igreja'];
    $admin_nome = $_POST['admin_nome'];
    $admin_email = $_POST['admin_email'];
    $admin_senha = $_POST['admin_senha']; // In real app, confirm pwd
    
    // Simulate Random Processing Delay
    sleep(1);

    try {
        $pdo->beginTransaction();

        // 1. Create Tenant (Igreja)
        $stmt = $pdo->prepare("INSERT INTO igrejas (nome, created_at) VALUES (?, NOW())");
        $stmt->execute([$nome_igreja]);
        $igrejaId = $pdo->lastInsertId();

        // 2. Create Admin User
        $hashedInfo = password_hash($admin_senha, PASSWORD_DEFAULT);
        // Nivel 'admin' (Fixed legacy column)
        $stmt = $pdo->prepare("INSERT INTO usuarios (igreja_id, nome, email, senha, nivel, sexo, must_change_password) VALUES (?, ?, ?, ?, 'admin', 'M', 0)");
        $stmt->execute([$igrejaId, $admin_nome, $admin_email, $hashedInfo]);
        $userId = $pdo->lastInsertId();

        // 3. Assign RBAC Role 'Administrador'
        // Find system role ID for 'Administrador'
        $stmtRole = $pdo->prepare("SELECT id FROM papeis WHERE nome = 'Administrador' LIMIT 1");
        $stmtRole->execute();
        $roleId = $stmtRole->fetchColumn();

        if ($roleId) {
            $pdo->prepare("INSERT INTO papel_usuario (usuario_id, papel_id) VALUES (?, ?)")->execute([$userId, $roleId]);
        }

        // 4. Create Subscription
        // Start today, end in 1 year (or 1 month depending on logic, let's say Monthly)
        $stmtSub = $pdo->prepare("INSERT INTO assinaturas (igreja_id, plano_id, status, data_inicio, data_fim) VALUES (?, ?, 'ativa', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH))");
        $stmtSub->execute([$igrejaId, $plan['id']]);

        $pdo->commit();

        // Success - Auto Login or Redirect to Login?
        // Redirect to Login with message
        header("Location: index.php?msg=welcome_new_tenant");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro ao processar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Seguro - ChurchDigital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen py-10">

    <div class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-3 gap-8 p-4">
        
        <!-- Summary Column -->
        <div class="col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 sticky top-10">
                <h3 class="text-gray-500 uppercase text-xs font-bold tracking-wider mb-4">Resumo do Pedido</h3>
                
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="font-bold text-xl text-gray-800"><?php echo $plan['nome']; ?></h2>
                        <span class="text-sm text-gray-500">Assinatura Mensal</span>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600">R$ <?php echo number_format($plan['preco'], 2, ',', '.'); ?></div>
                    </div>
                </div>

                <hr class="border-gray-100 my-4">

                <ul class="text-sm space-y-2 text-gray-600 mb-6">
                    <li><i class="fas fa-check text-green-500 mr-2"></i> Cobrança imediata de R$ <?php echo number_format($plan['preco'], 2, ',', '.'); ?></li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i> Renovação automática</li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i> Cancelamento a qualquer momento</li>
                </ul>

                <a href="pricing.php" class="text-xs text-blue-500 hover:underline">Trocar de plano</a>
            </div>
        </div>

        <!-- Checkout Form Column -->
        <div class="col-span-2">
            <div class="bg-white rounded-xl shadow-lg p-8">
                
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Finalizar Assinatura</h1>
                        <p class="text-sm text-gray-500">Ambiente 100% Seguro (Simulado)</p>
                    </div>
                </div>

                <?php if(isset($error)): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded mb-6 border-l-4 border-red-500">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">1. Dados da Igreja</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Nome da Organização</label>
                        <input type="text" name="nome_igreja" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ex: Igreja Batista Renovada" required>
                    </div>

                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 mt-8">2. Administrador Responsável</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Seu Nome</label>
                            <input type="text" name="admin_nome" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Seu E-mail</label>
                            <input type="email" name="admin_email" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Crie uma Senha</label>
                        <input type="password" name="admin_senha" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Mínimo 8 caracteres" required>
                    </div>

                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 mt-8">3. Pagamento (Simulação)</h3>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Número do Cartão</label>
                            <div class="relative">
                                <input type="text" value="4242 4242 4242 4242" class="w-full p-3 pl-10 border rounded-lg bg-white font-mono text-gray-600" readonly>
                                <i class="fab fa-cc-visa absolute left-3 top-3.5 text-gray-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">Validade</label>
                                <input type="text" value="12/30" class="w-full p-3 border rounded-lg bg-white font-mono text-gray-600" readonly>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">CVC</label>
                                <input type="text" value="123" class="w-full p-3 border rounded-lg bg-white font-mono text-gray-600" readonly>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2 text-center"> <i class="fas fa-info-circle"></i> Cartão de testes do Stripe</p>
                    </div>

                    <button type="submit" class="w-full bg-green-600 text-white font-bold py-4 rounded-lg hover:bg-green-700 transition shadow-lg flex items-center justify-center gap-2 group">
                        <span>Confirmar Assinatura</span>
                        <i class="fas fa-arrow-right group-hover:translate-x-1 transition"></i>
                    </button>
                    <p class="text-center text-xs text-gray-400 mt-4">Ao confirmar, você concorda com nossos Termos de Uso.</p>

                </form>
            </div>
        </div>

    </div>

</body>
</html>
