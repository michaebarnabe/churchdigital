<?php
require_once 'config.php';
define('ABSPATH', true);
require_once 'config_stripe.php';

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

// Handle Form Submission (Provisioning -> Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_igreja = $_POST['nome_igreja'];
    $admin_nome = $_POST['admin_nome'];
    $admin_email = $_POST['admin_email'];
    $admin_senha = $_POST['admin_senha']; 
    
    // Validar Senha
    if (strlen($admin_senha) < 8) {
        $error = "A senha deve ter pelo menos 8 caracteres.";
    } else {
        try {
            if (defined('STRIPE_MISSING')) {
                throw new Exception("Biblioteca Stripe não encontrada. Verifique a instalação.");
            }

            $pdo->beginTransaction();

            // 1. Create Tenant (Igreja) - Status 'pending' logic implied by lack of payment
            // We can add a 'status' column to 'igrejas' too, checking schema... but let's assume active but locked by subscription
            $stmt = $pdo->prepare("INSERT INTO igrejas (nome, created_at) VALUES (?, NOW())");
            $stmt->execute([$nome_igreja]);
            $igrejaId = $pdo->lastInsertId();

            // 2. Create Admin User
            $hashedInfo = password_hash($admin_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (igreja_id, nome, email, senha, nivel, sexo, must_change_password) VALUES (?, ?, ?, ?, 'admin', 'M', 0)");
            $stmt->execute([$igrejaId, $admin_nome, $admin_email, $hashedInfo]);
            $userId = $pdo->lastInsertId();

            // 3. Assign Role if exists
            $stmtRole = $pdo->prepare("SELECT id FROM papeis WHERE nome = 'Administrador' LIMIT 1");
            $stmtRole->execute();
            $roleId = $stmtRole->fetchColumn();
            if ($roleId) {
                $pdo->prepare("INSERT INTO papel_usuario (usuario_id, papel_id) VALUES (?, ?)")->execute([$userId, $roleId]);
            }

            // 4. Create Subscription (PENDING)
            $stmtSub = $pdo->prepare("INSERT INTO assinaturas (igreja_id, plano_id, status, data_inicio, data_fim) VALUES (?, ?, 'pendente', CURDATE(), NULL)");
            $stmtSub->execute([$igrejaId, $plan['id']]);
            $subId = $pdo->lastInsertId();

            // 5. Create Stripe Checkout Session
            $domain_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); // Auto-detect base URL
            
            $checkout_session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'brl',
                        'unit_amount' => $plan['preco'] * 100, // Centavos
                        'product_data' => [
                            'name' => $plan['nome'] . ' - Mensal',
                            'description' => 'Assinatura ChurchDigital',
                        ],
                        'recurring' => [
                            'interval' => 'month',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $domain_url . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&sub_id=' . $subId,
                'cancel_url' => $domain_url . '/payment_cancel.php',
                'customer_email' => $admin_email,
                'metadata' => [
                    'igreja_id' => $igrejaId,
                    'sub_id' => $subId,
                    'plan_id' => $plan['id']
                ]
            ]);

            // Save session ID for verification later
            $pdo->prepare("UPDATE assinaturas SET stripe_session_id = ? WHERE id = ?")->execute([$checkout_session->id, $subId]);

            // Commit Transaction ONLY after Stripe success
            $pdo->commit();

            // Redirect to Stripe
            header("HTTP/1.1 303 See Other");
            header("Location: " . $checkout_session->url);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Erro ao iniciar pagamento: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ChurchDigital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen py-10">
    <div class="w-full max-w-4xl grid grid-cols-1 md:grid-cols-3 gap-8 p-4">
        <!-- Summary Column -->
        <div class="col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 sticky top-10 border border-gray-100">
                <h3 class="text-gray-500 uppercase text-xs font-bold tracking-wider mb-4">Resumo do Pedido</h3>
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h2 class="font-bold text-xl text-gray-900"><?php echo $plan['nome']; ?></h2>
                        <span class="text-sm text-gray-500">Assinatura Mensal</span>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-black">R$ <?php echo number_format($plan['preco'], 2, ',', '.'); ?></div>
                    </div>
                </div>
                <hr class="border-gray-100 my-4">
                <ul class="text-sm space-y-2 text-gray-600 mb-6">
                    <li><i class="fas fa-check text-green-500 mr-2"></i> Cobrança imediata de R$ <?php echo number_format($plan['preco'], 2, ',', '.'); ?></li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i> Renovação automática</li>
                    <li><i class="fas fa-check text-green-500 mr-2"></i> Cancelamento a qualquer momento</li>
                </ul>
                <a href="pricing.php" class="text-xs text-gray-400 hover:text-black hover:underline font-bold">Trocar de plano</a>
            </div>
        </div>

        <!-- Form Column -->
        <div class="col-span-2">
            <div class="bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-black flex items-center justify-center text-white font-bold shadow-md">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">Assinar ChurchDigital</h1>
                        <p class="text-sm text-gray-500">Você será redirecionado para o Stripe para pagamento seguro.</p>
                    </div>
                </div>

                <?php if(isset($error)): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded mb-6 border-l-4 border-red-500">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <h3 class="font-bold text-gray-900 mb-4 border-b pb-2">1. Dados da Igreja</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Nome da Organização</label>
                        <input type="text" name="nome_igreja" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" required value="<?php echo $_POST['nome_igreja'] ?? ''; ?>">
                    </div>

                    <h3 class="font-bold text-gray-900 mb-4 border-b pb-2 mt-8">2. Administrador Responsável</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Seu Nome</label>
                            <input type="text" name="admin_nome" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" required value="<?php echo $_POST['admin_nome'] ?? ''; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Seu E-mail</label>
                            <input type="email" name="admin_email" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" required value="<?php echo $_POST['admin_email'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Crie uma Senha</label>
                        <input type="password" name="admin_senha" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" placeholder="Mínimo 8 caracteres" required>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full bg-black text-white font-bold py-4 rounded-lg hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2 group">
                            <span>Ir para Pagamento</span>
                            <i class="fas fa-external-link-alt group-hover:translate-x-1 transition"></i>
                        </button>
                        <div class="flex justify-center items-center gap-2 mt-4 text-gray-400">
                             <i class="fas fa-lock text-xs"></i>
                             <p class="text-xs">Plataforma segura e criptografada.</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
