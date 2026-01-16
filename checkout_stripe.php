<?php
require_once 'config.php';
define('ABSPATH', true);
require_once 'config_stripe.php';

$plan_id = $_GET['plan_id'] ?? null;
$recovery_email = $_GET['recovery_email'] ?? '';
$recovery_name = $_GET['recovery_name'] ?? '';

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

            // CHECK DUPLICATE EMAIL LOGIC (With Recovery)
            $checkStmt = $pdo->prepare("SELECT u.id, u.igreja_id, a.status, a.id as sub_id FROM usuarios u JOIN assinaturas a ON u.igreja_id = a.igreja_id WHERE u.email = ?");
            $checkStmt->execute([$admin_email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if ($existing['status'] === 'ativa') {
                    throw new Exception("Já existe uma conta ativa com este e-mail. Por favor, faça login.");
                } else {
                    // RECOVERY MODE: Update Existing Pending Record
                    $userId = $existing['id'];
                    $igrejaId = $existing['igreja_id'];
                    $subId = $existing['sub_id'];

                    // Update User Info
                    $hashedInfo = password_hash($admin_senha, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET nome = ?, senha = ? WHERE id = ?")->execute([$admin_nome, $hashedInfo, $userId]);
                    $pdo->prepare("UPDATE igrejas SET nome = ? WHERE id = ?")->execute([$nome_igreja, $igrejaId]);
                    
                    // Update Subscription Plan (if changed)
                    $pdo->prepare("UPDATE assinaturas SET plano_id = ? WHERE id = ?")->execute([$plan['id'], $subId]);
                }
            } else {
                // NEW USER FLOW
                // 1. Create Tenant (Igreja)
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

                // 4. Create Subscription (PENDING or ACTIVE)
                $isFree = ((float)$plan['preco'] <= 0.00);
                $initialStatus = $isFree ? 'ativa' : 'pendente';
                
                $stmtSub = $pdo->prepare("INSERT INTO assinaturas (igreja_id, plano_id, status, data_inicio, data_fim) VALUES (?, ?, ?, CURDATE(), NULL)");
                $stmtSub->execute([$igrejaId, $plan['id'], $initialStatus]);
                $subId = $pdo->lastInsertId();
            }

            // IF FREE PLAN -> Bypass Stripe, Auto-Login & Redirect
            if ($isFree) {
                $pdo->commit();

                // Auto-Login Setup
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_nome'] = $admin_nome; // Ajustado para match com Payment Success
                $_SESSION['user_email'] = $admin_email;
                $_SESSION['igreja_id'] = $igrejaId;
                $_SESSION['user_type'] = 'staff';

                // Roles Setup
                if ($roleId) {
                    $stmtR = $pdo->prepare("SELECT nome FROM papeis WHERE id = ?");
                    $stmtR->execute([$roleId]);
                    $_SESSION['user_roles'] = [$stmtR->fetchColumn()];
                } else {
                    $_SESSION['user_roles'] = ['Administrador'];
                }

                // Permissões (Simples fetch)
                $stmtPerms = $pdo->prepare("
                    SELECT DISTINCT per.slug 
                    FROM permissoes per
                    JOIN papel_permissoes pp ON per.id = pp.permissao_id
                    JOIN papel_usuario pu ON pp.papel_id = pu.papel_id
                    WHERE pu.usuario_id = ?
                ");
                $stmtPerms->execute([$userId]);
                $_SESSION['user_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);

                // Welcome Email
                require_once 'includes/mailer.php';
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $body = "
                <div style='font-family: sans-serif; color: #333;'>
                    <h1>Bem-vindo à Church Digital!</h1>
                    <p>Olá, {$admin_nome}!</p>
                    <p>Sua conta foi criada com sucesso no plano gratuito.</p>
                    <br>
                    <h3>📱 Instale o Aplicativo</h3>
                    <ul>
                        <li><a href='{$protocol}{$_SERVER['HTTP_HOST']}/ChurchDigital/install.php'>Ver Passo a Passo de Instalação</a></li>
                        <li><a href='{$protocol}{$_SERVER['HTTP_HOST']}/ChurchDigital/index.php?mode=pwa'><strong>Abrir App Direto</strong></a></li>
                    </ul>
                </div>";
                send_mail($admin_email, 'Bem-vindo ao Church Digital', $body);

                // Redirect
                header("Location: index.php?msg=welcome_free");
                exit;
            }

            // 5. Create Stripe Checkout Session (ONLY FOR PAID PLANS)
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
                            <input type="text" name="admin_nome" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" required value="<?php echo $_POST['admin_nome'] ?? $recovery_name; ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Seu E-mail</label>
                            <input type="email" name="admin_email" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" required value="<?php echo $_POST['admin_email'] ?? $recovery_email; ?>">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 mb-1">Crie uma Senha</label>
                        <input type="password" name="admin_senha" class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-black outline-none transition" placeholder="Mínimo 8 caracteres" required>
                    </div>

                    <div class="mt-8">
                        <button type="submit" class="w-full bg-black text-white font-bold py-4 rounded-lg hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2 group">
                            <span><?php echo ((float)$plan['preco'] > 0.00) ? 'Ir para Pagamento' : 'Finalizar Cadastro'; ?></span>
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
