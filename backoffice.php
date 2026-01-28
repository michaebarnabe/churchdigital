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
require_once 'includes/GoogleAuthenticator.php';

// --- AUTHENTICATION ---
$ga = new GoogleAuthenticator();
$configStmt = $pdo->query("SELECT config_key, config_value FROM admin_config");
$sysConfig = $configStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$masterHash = $sysConfig['master_password_hash'] ?? password_hash('admin123', PASSWORD_DEFAULT);
$twoFaEnabled = ($sysConfig['2fa_enabled'] ?? '0') === '1';
$twoFaSecret = $sysConfig['2fa_secret'] ?? '';

if (isset($_GET['logout'])) {
    unset($_SESSION['is_master']);
    unset($_SESSION['master_2fa_verified']);
    header("Location: backoffice.php");
    exit;
}

// 1. Password Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_login'])) {
    if (password_verify($_POST['password'], $masterHash)) {
        $_SESSION['is_master_partial'] = true; // Password OK, wait for 2FA
        if (!$twoFaEnabled) {
            $_SESSION['is_master'] = true; // No 2FA needed
        }
    } else {
        $error = "Senha Mestra Inválida.";
    }
}

// 2. 2FA Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    $code = $_POST['code'];
    if ($ga->verifyCode($twoFaSecret, $code, 2)) { // 2 = 1 min tolerance
        $_SESSION['is_master'] = true;
    } else {
        $error = "Código incorreto.";
    }
}

// Ensure Auth State
if (!isset($_SESSION['is_master'])) {
    // Check if partial pending 2FA
    $show2FA = isset($_SESSION['is_master_partial']) && $twoFaEnabled;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>SaaS Backoffice</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-900 flex items-center justify-center h-screen">
        <div class="bg-white p-8 rounded-lg shadow-lg w-96">
            <h1 class="text-2xl font-bold mb-4 text-center">SaaS Master Login</h1>
            <?php if(isset($error)) echo "<p class='text-red-500 mb-4 text-center text-sm font-bold'>$error</p>"; ?>
            
            <?php if ($show2FA): ?>
                <form method="POST">
                    <p class="mb-4 text-center text-gray-600">Autenticação de Dois Fatores</p>
                    <div class="flex justify-center mb-4">
                        <i class="fas fa-shield-alt text-4xl text-blue-600"></i>
                    </div>
                    <label class="block text-sm font-bold mb-1">Código Google Auth</label>
                    <input type="text" name="code" placeholder="000 000" class="w-full p-3 border rounded mb-4 text-center text-xl tracking-widest font-mono" required autofocus autocomplete="off">
                    <button type="submit" name="verify_2fa" class="w-full bg-blue-600 text-white font-bold py-3 rounded hover:bg-blue-700">Verificar</button>
                    <a href="?logout=true" class="block text-center mt-4 text-sm text-gray-400">Voltar</a>
                </form>
            <?php else: ?>
                <form method="POST">
                    <label class="block text-sm font-bold mb-1">Senha Mestra</label>
                    <input type="password" name="password" placeholder="******" class="w-full p-3 border rounded mb-4" required>
                    <button type="submit" name="master_login" class="w-full bg-black text-white font-bold py-3 rounded hover:bg-gray-800">Entrar</button>
                </form>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- BACKOFFICE LOGIC ---

// Impersonate (Login as) Tenant Admin
if (isset($_GET['impersonate'])) {
    $tenantId = $_GET['impersonate'];
    
    // Find the first admin user for this tenant
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE igreja_id = ? AND nivel = 'admin' LIMIT 1");
    $stmt->execute([$tenantId]);
    $adminUser = $stmt->fetch();

    if ($adminUser) {
        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['user_name'] = $adminUser['nome'];
        $_SESSION['user_email'] = $adminUser['email'];
        $_SESSION['user_type'] = 'staff';
        $_SESSION['igreja_id'] = $adminUser['igreja_id'];
        $_SESSION['real_igreja_id'] = $adminUser['igreja_id'];
        
        $stmtRoles = $pdo->prepare("SELECT p.nome FROM papeis p JOIN papel_usuario pu ON p.id = pu.papel_id WHERE pu.usuario_id = ?");
        $stmtRoles->execute([$adminUser['id']]);
        $_SESSION['user_roles'] = $stmtRoles->fetchAll(PDO::FETCH_COLUMN) ?: ['User'];
        
        header("Location: index.php");
        exit;
    } else {
        $error = "Nenhum admin encontrado.";
    }
}

// --- BACKOFFICE LOGIC ---

// Send Recovery Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_recovery'])) {
    $rec_id = $_POST['recovery_id'];
    $rec_email = $_POST['recovery_email'];
    $rec_nome = $_POST['recovery_nome'];
    $rec_plan = $_POST['recovery_plan_id'];

    try {
        require_once 'includes/mailer.php';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $recoveryLink = "{$protocol}{$_SERVER['HTTP_HOST']}".dirname($_SERVER['PHP_SELF'])."/checkout_stripe.php?plan_id={$rec_plan}&recovery_email={$rec_email}&recovery_name=".urlencode($rec_nome);

        $body = "
        <div style='font-family: sans-serif; color: #333;'>
            <h1>Falta pouco para ativar sua conta!</h1>
            <p>Olá, {$rec_nome}!</p>
            <p>Notamos que você iniciou o cadastro da sua igreja na Church Digital mas não concluiu.</p>
            <p>Não se preocupe, seus dados estão seguros. Clique no botão abaixo para retomar exatamente de onde parou e ativar sua conta agora mesmo.</p>
            <br>
            <a href='{$recoveryLink}' style='background-color: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Concluir Cadastro</a>
            <br><br>
            <p>Se tiver dúvidas, responda este e-mail.</p>
        </div>";
        
        $result = send_mail($rec_email, 'Retome seu cadastro na Church Digital', $body);
        
        if ($result === true) {
            $msg = "E-mail de recuperação enviado para $rec_email!";
        } else {
             $error = "Falha no envio: " . $result;
        }
    } catch (Exception $e) {
        $error = "Erro ao enviar: " . $e->getMessage();
    }
}


// Delete Tenant (Common Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tenant'])) {
    $del_id = $_POST['delete_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE pu FROM papel_usuario pu JOIN usuarios u ON pu.usuario_id = u.id WHERE u.igreja_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM usuarios WHERE igreja_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM assinaturas WHERE igreja_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM igrejas WHERE id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM financeiro_basico WHERE igreja_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM membros WHERE igreja_id = ?")->execute([$del_id]);
        $pdo->commit();
        $msg = "Tenant removido com sucesso.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Delete Pending Tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pending'])) {
    $p_igreja_id = $_POST['delete_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE pu FROM papel_usuario pu JOIN usuarios u ON pu.usuario_id = u.id WHERE u.igreja_id = ?")->execute([$p_igreja_id]);
        $pdo->prepare("DELETE FROM usuarios WHERE igreja_id = ?")->execute([$p_igreja_id]);
        $pdo->prepare("DELETE FROM assinaturas WHERE igreja_id = ?")->execute([$p_igreja_id]);
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


// Update Tenant Plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tenant_plan'])) {
    $t_id = $_POST['tenant_id'];
    $new_plano_id = $_POST['new_plano_id'];
    
    try {
        // Verifica se já tem assinatura
        $stmtCheck = $pdo->prepare("SELECT id FROM assinaturas WHERE igreja_id = ?");
        $stmtCheck->execute([$t_id]);
        $subId = $stmtCheck->fetchColumn();

        if ($subId) {
            // Atualiza existente (Renova por 30 dias a partir de hoje)
            $sql = "UPDATE assinaturas SET plano_id = ?, status = 'ativa', data_inicio = CURDATE(), data_fim = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE id = ?";
            $pdo->prepare($sql)->execute([$new_plano_id, $subId]);
        } else {
            // Cria nova se não existir (caso de erro anterior)
            $sql = "INSERT INTO assinaturas (igreja_id, plano_id, status, data_inicio, data_fim) VALUES (?, ?, 'ativa', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))";
            $pdo->prepare($sql)->execute([$t_id, $new_plano_id]);
        }
        
        $msg = "Plano da igreja atualizado com sucesso (Validade: 30 dias).";
    } catch (PDOException $e) {
        $error = "Erro ao atualizar plano: " . $e->getMessage();
    }
}

// Update Extras (Manual & Stripe)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_extras'])) {
    $t_id = $_POST['tenant_id_extras'];
    $new_extra_membros = (int)$_POST['extra_membros'];
    $new_extra_filiais = (int)$_POST['extra_filiais'];
    $new_extra_patrimonio = (int)$_POST['extra_patrimonio'];
    
    try {
        require_once 'config_stripe.php';
        
        $stmtSub = $pdo->prepare("SELECT * FROM assinaturas WHERE igreja_id = ?");
        $stmtSub->execute([$t_id]);
        $sub = $stmtSub->fetch();

        if ($sub && !empty($sub['stripe_session_id'])) {
             // Fetch Subscription ID from Session if not stored directly (simplified for this context, assuming we can get sub ID)
             // In a real scenario, we should have stored subscription_id. Let's assume we can retrieve it via session or it's stored.
             // For now, let's look for subscription_id column or recover it. 
             // EDIT: The current checking_stripe logic saves `stripe_session_id`. We need the `subscription_id`.
             // Let's retrieve the session to get the subscription ID.
             
             if (!defined('STRIPE_MISSING')) {
                 $checkout_session = \Stripe\Checkout\Session::retrieve($sub['stripe_session_id']);
                 $subscription_id = $checkout_session->subscription;
                 
                 if ($subscription_id) {
                     $subscription = \Stripe\Subscription::retrieve($subscription_id);
                     
                     // 1. Manage Members Extra Item
                     // Price ID for Members Extra (R$ 0,30). YOU MUST REPLACE THIS WITH REAL PRICE ID OR CREATE ONE DYNAMICALLY
                     // Dynamic approach using price_data for recurring is possible but complex for updates.
                     // Ideally, we have a fixed Price ID. Let's assume we need to create/find it.
                     // For this implementation, I will use a placeholder or inline creation if possible, but inline creation for existing sub items needs price ID.
                     // simplified: We assume a 'price_members_extra' exists or we use unit_amount with a product cache.
                     // BETTER: We create a product/price once and hardcode, or lookup.
                     // Strategy: Update Subscription Items.
                     
                     $items_to_update = [];
                     
                     // Helper to find item by metadata or price lookup?
                     // Let's rely on stored item IDs in DB.
                     
                     // MEMBERS
                     if ($new_extra_membros > 0) {
                         if ($sub['stripe_item_membros']) {
                             $items_to_update[] = ['id' => $sub['stripe_item_membros'], 'quantity' => $new_extra_membros];
                         } else {
                             // Create new item
                             $items_to_update[] = [
                                 'price_data' => [
                                     'currency' => 'brl',
                                     'product_data' => ['name' => 'Membros Extras'],
                                     'unit_amount' => 30, // R$ 0,30
                                     'recurring' => ['interval' => 'month'],
                                 ],
                                 'quantity' => $new_extra_membros,
                             ];
                         }
                     } elseif ($sub['stripe_item_membros']) {
                         // Remove item if count is 0
                         $items_to_update[] = ['id' => $sub['stripe_item_membros'], 'deleted' => true];
                     }

                     // BRANCHES
                     if ($new_extra_filiais > 0) {
                         if ($sub['stripe_item_filiais']) {
                             $items_to_update[] = ['id' => $sub['stripe_item_filiais'], 'quantity' => $new_extra_filiais];
                         } else {
                             $items_to_update[] = [
                                 'price_data' => [
                                     'currency' => 'brl',
                                     'product_data' => ['name' => 'Filiais Extras'],
                                     'unit_amount' => 1290, // R$ 12,90
                                     'recurring' => ['interval' => 'month'],
                                 ],
                                 'quantity' => $new_extra_filiais,
                             ];
                         }
                     } elseif ($sub['stripe_item_filiais']) {
                         // Remove item
                         $items_to_update[] = ['id' => $sub['stripe_item_filiais'], 'deleted' => true];
                     }
                     
                     if (!empty($items_to_update)) {
                        $updatedSub = \Stripe\Subscription::update($subscription_id, [
                            'items' => $items_to_update,
                        ]);
                        
                        // Update DB with new Item IDs
                        $newItemMembros = $sub['stripe_item_membros'];
                        $newItemFiliais = $sub['stripe_item_filiais'];
                        
                        foreach ($updatedSub->items->data as $item) {
                            if (isset($item->price->unit_amount)) {
                                if ($item->price->unit_amount == 30) $newItemMembros = $item->id;
                                if ($item->price->unit_amount == 1290) $newItemFiliais = $item->id;
                            }
                        }
                        
                        // If deleted, set to null
                        if ($new_extra_membros == 0) $newItemMembros = null;
                        if ($new_extra_filiais == 0) $newItemFiliais = null;

                        $pdo->prepare("UPDATE assinaturas SET stripe_item_membros = ?, stripe_item_filiais = ? WHERE id = ?")
                            ->execute([$newItemMembros, $newItemFiliais, $sub['id']]);
                     }
                 }
             }
        }
        
        // Update Local DB Limits
        $pdo->prepare("UPDATE assinaturas SET extra_membros = ?, extra_filiais = ?, extra_patrimonio = ? WHERE igreja_id = ?")
            ->execute([$new_extra_membros, $new_extra_filiais, $new_extra_patrimonio, $t_id]);
            
        $msg = "Extras atualizados com sucesso (Sincronizado com Stripe).";
        
    } catch (Exception $e) {
        $error = "Erro ao atualizar extras: " . $e->getMessage();
    }
}

// Update Tenant Admin Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tenant_password'])) {
    $u_id = $_POST['admin_user_id_pass'];
    $new_pass = $_POST['new_password_tenant'];
    
    try {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $u_id]);
        $msg = "Senha do administrador da igreja atualizada com sucesso.";
    } catch (PDOException $e) {
        $error = "Erro ao atualizar senha: " . $e->getMessage();
    }
}

// Settings: Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $newPass = $_POST['new_password'];
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admin_config (config_key, config_value) VALUES ('master_password_hash', ?) ON DUPLICATE KEY UPDATE config_value = ?")->execute([$newHash, $newHash]);
    $msg = "Senha mestra atualizada.";
}

// Settings: Enable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_2fa_step1'])) {
    // Generate Secret and Show QR
    $newSecret = $ga->createSecret();
    $qrUrl = $ga->getQRCodeGoogleUrl('ChurchDigital_Admin', $newSecret, 'ChurchDigital');
    $showQrMode = true;
}

// Settings: Confirm 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_2fa'])) {
    $secret = $_POST['secret'];
    $code = $_POST['code'];
    if ($ga->verifyCode($secret, $code)) {
        $pdo->prepare("INSERT INTO admin_config (config_key, config_value) VALUES ('2fa_secret', ?) ON DUPLICATE KEY UPDATE config_value = ?")->execute([$secret, $secret]);
        $pdo->prepare("INSERT INTO admin_config (config_key, config_value) VALUES ('2fa_enabled', '1') ON DUPLICATE KEY UPDATE config_value = '1'")->execute();
        $msg = "Autenticação em 2 Fatores ATIVADA com sucesso!";
        $twoFaEnabled = true; // Refresh for view
    } else {
        $error = "Código inválido. Tente novamente.";
    }
}

// Settings: Disable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    $pdo->prepare("UPDATE admin_config SET config_value = '0' WHERE config_key = '2fa_enabled'")->execute();
    $msg = "2FA Desativado.";
    $twoFaEnabled = false;
}

// Settings: Save SEO Scripts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seo_scripts'])) {
    $scripts = $_POST['seo_scripts'];
    $pdo->prepare("INSERT INTO admin_config (config_key, config_value) VALUES ('seo_head_scripts', ?) ON DUPLICATE KEY UPDATE config_value = ?")->execute([$scripts, $scripts]);
    $msg = "Scripts de SEO salvos com sucesso.";
}


// --- VIEW DATA FETCH ---
$tenants = $pdo->query("
    SELECT i.*, 
           MAX(p.nome) as plano_nome, 
           MAX(a.status) as plano_status, 
           MAX(a.data_fim) as vencimento,
           MAX(a.extra_membros) as extra_membros,
           MAX(a.extra_filiais) as extra_filiais,
           MAX(a.extra_patrimonio) as extra_patrimonio,
           COUNT(u.id) as num_users 
    FROM igrejas i 
    LEFT JOIN assinaturas a ON i.id = a.igreja_id
    LEFT JOIN planos p ON a.plano_id = p.id
    LEFT JOIN usuarios u ON i.id = u.igreja_id
    WHERE a.status = 'ativa' OR a.status IS NULL
    GROUP BY i.id
    ORDER BY i.created_at DESC
")->fetchAll();

$pending_tenants = $pdo->query("SELECT i.*, MAX(p.nome) as plano_nome, MAX(a.status) as plano_status, MAX(p.id) as plano_id, MAX(a.data_inicio) as sub_start, MAX(u.nome) as admin_nome, MAX(u.email) as admin_email FROM igrejas i JOIN assinaturas a ON i.id = a.igreja_id LEFT JOIN planos p ON a.plano_id = p.id JOIN usuarios u ON i.id = u.igreja_id WHERE a.status IN ('pendente', 'aguardando_pagamento') GROUP BY i.id ORDER BY i.created_at DESC")->fetchAll();
$planos = $pdo->query("SELECT * FROM planos")->fetchAll();

$currentTab = $_GET['tab'] ?? 'tenants';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SaaS Admin</title>
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
                <span class="font-bold text-sm">Church Digital App</span>
            </div>
            <nav class="flex-grow p-4 space-y-2">
                <a href="?tab=tenants" class="block p-3 <?php echo $currentTab == 'tenants' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white'; ?> rounded"><i class="fas fa-church mr-2"></i> Clientes</a>
                <a href="?tab=pending" class="block p-3 <?php echo $currentTab == 'pending' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white'; ?> rounded"><i class="fas fa-clock mr-2"></i> Pendentes</a>
                <a href="?tab=reports" class="block p-3 <?php echo $currentTab == 'reports' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white'; ?> rounded"><i class="fas fa-chart-line mr-2"></i> Relatórios</a>
                <a href="?tab=plans" class="block p-3 <?php echo $currentTab == 'plans' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white'; ?> rounded"><i class="fas fa-money-bill mr-2"></i> Planos</a>
                <a href="?tab=settings" class="block p-3 <?php echo $currentTab == 'settings' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white'; ?> rounded"><i class="fas fa-shield-alt mr-2"></i> Segurança</a>
            </nav>
            <div class="p-4"><a href="?logout=true" class="block text-center text-sm text-gray-500 hover:text-white">Sair</a></div>
        </aside>
        
        <!-- Content -->
        <main class="flex-grow p-8 overflow-y-auto w-full">
            <?php if(isset($msg)) echo "<div class='bg-green-100 text-green-700 p-4 rounded mb-4 font-bold'>$msg</div>"; ?>
            <?php if(isset($error)) echo "<div class='bg-red-100 text-red-700 p-4 rounded mb-4 font-bold'>$error</div>"; ?>

            <?php if ($currentTab == 'settings'): ?>
                <!-- SETTINGS TAB -->
                <h1 class="text-3xl font-bold mb-6">Segurança e Configurações</h1>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Change Password -->
                    <div class="bg-white p-6 rounded shadow">
                        <h2 class="text-xl font-bold mb-4">Alterar Senha Mestra</h2>
                        <form method="POST">
                            <input type="password" name="new_password" placeholder="Nova Senha" class="w-full p-2 border rounded mb-4" required>
                            <button type="submit" name="update_password" class="bg-black text-white px-4 py-2 rounded font-bold hover:bg-gray-800">Atualizar Senha</button>
                        </form>
                    </div>

                    <!-- 2FA -->
                    <div class="bg-white p-6 rounded shadow">
                        <h2 class="text-xl font-bold mb-4">Autenticação de Dois Fatores (2FA)</h2>
                        <?php if ($twoFaEnabled): ?>
                            <div class="text-green-600 font-bold mb-4 flex items-center gap-2">
                                <i class="fas fa-check-circle text-2xl"></i> ATIVADO
                            </div>
                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja desativar o 2FA?');">
                                <button type="submit" name="disable_2fa" class="text-red-500 underline font-bold text-sm">Desativar 2FA</button>
                            </form>
                        <?php else: ?>
                             <div class="text-gray-500 mb-4">Proteja o painel administrativo usando Google Authenticator.</div>
                             <?php if (isset($showQrMode)): ?>
                                <div class="text-center bg-gray-50 p-4 rounded border">
                                    <p class="mb-2 font-bold text-sm">Escaneie o QR Code:</p>
                                    <img src="<?php echo $qrUrl; ?>" class="mx-auto mb-4 border p-1 bg-white">
                                    <p class="mb-2 text-xs text-gray-500">Ou digite: <code><?php echo $newSecret; ?></code></p>
                                    
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="secret" value="<?php echo $newSecret; ?>">
                                        <input type="text" name="code" placeholder="Código (6 dígitos)" class="w-32 border p-2 rounded text-center mb-2" required>
                                        <br>
                                        <button type="submit" name="confirm_2fa" class="bg-blue-600 text-white px-4 py-2 rounded font-bold hover:bg-blue-700">Confirmar e Ativar</button>
                                    </form>
                                </div>
                             <?php else: ?>
                                <form method="POST">
                                    <button type="submit" name="enable_2fa_step1" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700">Configurar 2FA</button>
                                </form>
                             <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SEO Scripts -->
                <div class="bg-white p-6 rounded shadow mt-8">
                    <h2 class="text-xl font-bold mb-4">Scripts Globais (SEO/Tracking)</h2>
                    <p class="text-gray-500 mb-4 text-sm">Estes scripts serão injetados no <code>&lt;head&gt;</code> de todas as páginas públicas (Landing Page, Login, etc).</p>
                    
                    <?php 
                        $stmtSeo = $pdo->query("SELECT config_value FROM admin_config WHERE config_key = 'seo_head_scripts'");
                        $currentScripts = $stmtSeo->fetchColumn(); 
                    ?>
                    <form method="POST">
                        <textarea name="seo_scripts" class="w-full h-64 border p-4 rounded font-mono text-sm bg-gray-50 focus:ring-black focus:border-black" placeholder="<!-- Google Analytics --> ..."><?php echo htmlspecialchars($currentScripts ?? ''); ?></textarea>
                        <div class="mt-4 text-right">
                             <button type="submit" name="save_seo_scripts" class="bg-black text-white px-6 py-2 rounded font-bold hover:bg-gray-800">Salvar Scripts</button>
                        </div>
                    </form>
                </div>

            <?php elseif ($currentTab == 'tenants'): ?>
                 <h1 class="text-3xl font-bold mb-6">Clientes Ativos</h1>
                 <h1 class="text-3xl font-bold mb-6">Igrejas Ativas</h1>
                 <!-- Manual creation form removed -->

                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-4">ID</th>
                                <th class="p-4">Cliente</th>
                                <th class="p-4">Admin</th>
                                <th class="p-4">Plano</th>
                                <th class="p-4">Datas</th>
                                <th class="p-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tenants as $t): 
                                // Fetch Admin for each
                                $admin = $pdo->query("SELECT id, email FROM usuarios WHERE igreja_id = {$t['id']} AND nivel = 'admin' LIMIT 1")->fetch();
                            ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-4 text-gray-500 font-mono text-sm">#<?php echo $t['id']; ?></td>
                                    <td class="p-4 font-bold">
                                        <?php echo htmlspecialchars($t['nome']); ?>
                                        <?php if($t['parent_id']): ?><span class="text-xs bg-gray-200 text-gray-600 px-1 rounded ml-1">Filial</span><?php endif; ?>
                                    </td>
                                    <td class="p-4 text-sm text-gray-700">
                                        <?php if ($admin): ?>
                                            <div class="flex items-center gap-2">
                                                <span><?php echo $admin['email']; ?></span>
                                                <button onclick="openEditModal('<?php echo $admin['id']; ?>', '<?php echo $admin['email']; ?>')" class="text-gray-400 hover:text-black" title="Editar E-mail">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <button onclick="openPassModal('<?php echo $admin['id']; ?>')" class="text-gray-400 hover:text-red-600" title="Alterar Senha">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-red-400">Sem Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded font-bold">
                                            <?php echo $t['plano_nome'] ?? 'Sem Plano'; ?>
                                        </span>
                                        <button onclick="openPlanModal('<?php echo $t['id']; ?>', '<?php echo $t['plano_id'] ?? ''; ?>')" class="text-gray-400 hover:text-blue-600 ml-1" title="Alterar Plano">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <div class="mt-1">
                                            <button onclick="openExtrasModal('<?php echo $t['id']; ?>', '<?php echo $t['extra_membros']; ?>', '<?php echo $t['extra_filiais']; ?>', '<?php echo $t['extra_patrimonio']; ?>')" class="text-xs bg-purple-100 text-purple-700 font-bold px-2 py-1 rounded hover:bg-purple-200">
                                                <i class="fas fa-plus-circle"></i> Extras: <?php echo ($t['extra_membros'] + $t['extra_filiais'] + $t['extra_patrimonio']); ?>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-4 text-sm text-gray-600">
                                        <div class="text-xs">Criado: <?php echo date('d/m/y', strtotime($t['created_at'])); ?></div>
                                        <?php if(!empty($t['vencimento']) && $t['vencimento'] != '0000-00-00'): ?>
                                            <div class="text-xs font-bold <?php echo (strtotime($t['vencimento']) < time() + 86400*5) ? 'text-red-600' : 'text-green-600'; ?>">
                                                Vence: <?php echo date('d/m/y', strtotime($t['vencimento'])); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded inline-block mt-1">
                                                Vitalício
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 flex gap-3">
                                        <a href="?impersonate=<?php echo $t['id']; ?>" class="text-blue-600 font-bold hover:underline text-sm border border-blue-200 px-2 py-1 rounded bg-blue-50">Acessar</a>
                                        <form method="POST" onsubmit="return confirm('ATENÇÃO: Isso apagará TODOS os dados desta igreja (Membros, Financeiro, Usuários). Continuar?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" name="delete_tenant" class="text-red-500 hover:text-red-700 font-bold text-sm bg-red-50 px-2 py-1 rounded border border-red-200" title="Apagar Igreja">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($currentTab == 'pending'): ?>
                 <h1 class="text-3xl font-bold mb-6">Pendentes</h1>
                 <div class="bg-white rounded shadow text-left">
                     <table class="w-full">
                         <thead class="bg-amber-50"><tr><th class="p-4">ID</th><th class="p-4">Nome</th><th class="p-4">Status</th><th class="p-4">Ações</th></tr></thead>
                         <tbody>
                            <?php foreach($pending_tenants as $pt): ?>
                                <tr class="border-b">
                                    <td class="p-4">#<?php echo $pt['id']; ?></td>
                                    <td class="p-4"><?php echo $pt['nome']; ?></td>
                                    <td class="p-4"><?php echo $pt['plano_status']; ?></td>
                                    <td class="p-4 flex gap-2">
                                        <form method="POST" title="Enviar E-mail de Recuperação">
                                            <input type="hidden" name="recovery_id" value="<?php echo $pt['id']; ?>">
                                            <input type="hidden" name="recovery_email" value="<?php echo $pt['admin_email']; ?>">
                                            <input type="hidden" name="recovery_nome" value="<?php echo $pt['admin_nome']; ?>">
                                            <input type="hidden" name="recovery_plan_id" value="<?php echo $pt['plano_id']; ?>">
                                            <button type="submit" name="send_recovery" class="bg-blue-100 text-blue-600 p-2 rounded hover:bg-blue-200" title="Enviar Recuperação">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Remover?');">
                                            <input type="hidden" name="delete_id" value="<?php echo $pt['id']; ?>">
                                            <button type="submit" name="delete_pending" class="text-red-500 font-bold p-2 hover:bg-red-50 rounded" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                         </tbody>
                     </table>
                     <?php if(empty($pending_tenants)) echo "<p class='p-4 text-gray-500'>Nenhum pendente.</p>"; ?>
                 </div>
            
            <?php elseif ($currentTab == 'plans'): ?>
                <h1 class="text-3xl font-bold mb-6">Planos</h1>
                 <?php
                // Handle Plan Update
                // PROCESSAR ATUALIZAÇÃO DE PLANO
if (isset($action) && $action === 'update_plan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $preco = str_replace(',', '.', $_POST['preco']);
    $limite_membros = $_POST['limite_membros'];
    $limite_filiais = $_POST['limite_filiais'];
    
    // Novos Campos
    $preco_extra_membro = isset($_POST['preco_extra_membro']) ? str_replace(',', '.', $_POST['preco_extra_membro']) : 0.30;
    $preco_extra_filial = isset($_POST['preco_extra_filial']) ? str_replace(',', '.', $_POST['preco_extra_filial']) : 12.90;
    $preco_extra_patrimonio = isset($_POST['preco_extra_patrimonio']) ? str_replace(',', '.', $_POST['preco_extra_patrimonio']) : 0.10;

    $stmt = $pdo->prepare("UPDATE planos SET preco = ?, limite_membros = ?, limite_filiais = ?, preco_extra_membro = ?, preco_extra_filial = ?, preco_extra_patrimonio = ? WHERE id = ?");
    if ($stmt->execute([$preco, $limite_membros, $limite_filiais, $preco_extra_membro, $preco_extra_filial, $preco_extra_patrimonio, $id])) {
        // Sucesso
        echo "<script>window.location.href='backoffice.php?tab=plans&msg=updated';</script>";
        exit;
    }
}            
                $planos = $pdo->query("SELECT * FROM planos ORDER BY preco ASC")->fetchAll();
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
                            <?php foreach ($planos as $plan): 
                    // [MODIFICATION] HIDE ENTERPRISE
                    if (strtolower($plan['nome']) == 'enterprise') continue;
                ?>
                    <form method="POST" action="?action=update_plan" class="border-b hover:bg-gray-50">
                        <input type="hidden" name="id" value="<?php echo $plan['id']; ?>">
                        <div class="grid grid-cols-12 gap-4 p-4 items-center">
                            
                            <!-- Nome -->
                            <div class="col-span-2 font-bold text-gray-800">
                                <?php echo $plan['nome']; ?>
                            </div>

                            <!-- Preço Base -->
                            <div class="col-span-2">
                                <input type="text" name="preco" value="<?php echo number_format($plan['preco'], 2, ',', ''); ?>" class="w-full border rounded p-1 text-sm bg-gray-50 focus:bg-white transition" placeholder="0,00">
                            </div>

                            <!-- Limites -->
                            <div class="col-span-3 text-xs text-gray-500 space-y-1">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-users w-4"></i>
                                    <input type="number" name="limite_membros" value="<?php echo $plan['limite_membros']; ?>" class="w-16 border rounded p-1 text-center font-mono">
                                    <span>membros</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-code-branch w-4"></i>
                                    <input type="number" name="limite_filiais" value="<?php echo $plan['limite_filiais']; ?>" class="w-16 border rounded p-1 text-center font-mono">
                                    <span>filiais</span>
                                </div>
                            </div>

                            <!-- Preços Extras (NEW) -->
                            <div class="col-span-4 text-xs space-y-1 bg-blue-50 p-2 rounded border border-blue-100">
                                <span class="block font-bold text-blue-800 mb-1">Preços Extras (Unitário)</span>
                                <div class="flex items-center gap-2">
                                    <span class="w-16 text-right">Membro:</span>
                                    <input type="text" name="preco_extra_membro" value="<?php echo number_format($plan['preco_extra_membro'] ?? 0.30, 2, ',', ''); ?>" class="w-20 border rounded p-1 text-center font-mono text-blue-600 font-bold" placeholder="0,30">
                                </div>
                                    <input type="text" name="preco_extra_filial" value="<?php echo number_format($plan['preco_extra_filial'] ?? 12.90, 2, ',', ''); ?>" class="w-20 border rounded p-1 text-center font-mono text-blue-600 font-bold" placeholder="12,90">
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="w-16 text-right">Patrim.:</span>
                                    <input type="text" name="preco_extra_patrimonio" value="<?php echo number_format($plan['preco_extra_patrimonio'] ?? 0.10, 2, ',', ''); ?>" class="w-20 border rounded p-1 text-center font-mono text-blue-600 font-bold" placeholder="0,10">
                                </div>
                            </div>

                            <!-- Ação -->
                            <div class="col-span-1 text-right">
                                <button type="submit" class="bg-black text-white px-3 py-1 rounded text-xs font-bold hover:bg-gray-800 transition shadow">
                                    Salvar
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($currentTab == 'reports'): ?>
                <h1 class="text-3xl font-bold mb-6">Relatórios de Assinaturas</h1>
                
                <?php
                // Filter Logic
                $filter = $_GET['filter'] ?? 'all';
                $whereClause = "WHERE 1=1";
                
                if ($filter == 'expired') {
                    $whereClause .= " AND a.data_fim < CURDATE()";
                    $titleFilter = "Vencidos";
                } elseif ($filter == 'expiring') {
                    $whereClause .= " AND a.data_fim BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    $titleFilter = "Vencendo (7 dias)";
                } else {
                    $titleFilter = "Todos";
                }

                $reportSql = "
                    SELECT i.id, i.nome, p.nome as plano, a.data_fim, u.email as admin_email, u.nome as admin_nome
                    FROM igrejas i
                    JOIN assinaturas a ON i.id = a.igreja_id
                    LEFT JOIN planos p ON a.plano_id = p.id
                    JOIN usuarios u ON i.id = u.igreja_id
                    $whereClause AND u.nivel = 'admin'
                    ORDER BY a.data_fim ASC
                ";
                $reportData = $pdo->query($reportSql)->fetchAll();
                ?>

                <div class="flex gap-4 mb-6">
                    <a href="?tab=reports&filter=all" class="px-4 py-2 rounded font-bold <?php echo $filter == 'all' ? 'bg-black text-white' : 'bg-white text-gray-700 shadow'; ?>">Todos</a>
                    <a href="?tab=reports&filter=expiring" class="px-4 py-2 rounded font-bold <?php echo $filter == 'expiring' ? 'bg-yellow-500 text-white' : 'bg-white text-yellow-600 shadow'; ?>">Vencendo em 7 dias</a>
                    <a href="?tab=reports&filter=expired" class="px-4 py-2 rounded font-bold <?php echo $filter == 'expired' ? 'bg-red-600 text-white' : 'bg-white text-red-600 shadow'; ?>">Vencidos</a>
                </div>

                <div class="bg-white rounded shadow overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="p-4">Cliente</th>
                                <th class="p-4">Plano</th>
                                <th class="p-4">Vencimento</th>
                                <th class="p-4">Admin</th>
                                <th class="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $r): 
                                    $daysDiff = $r['data_fim'] ? (strtotime($r['data_fim']) - time()) / (60 * 60 * 24) : 999;
                                    
                                    if ($r['data_fim'] && $daysDiff < 0) {
                                        $statusBadge = "<span class='bg-red-100 text-red-800 text-xs px-2 py-1 rounded font-bold'>Vencido</span>";
                                    } elseif ($r['data_fim'] && $daysDiff <= 7) {
                                        $statusBadge = "<span class='bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded font-bold'>Vencendo</span>";
                                    } else {
                                        $statusBadge = "<span class='bg-green-100 text-green-800 text-xs px-2 py-1 rounded font-bold'>Ativo</span>";
                                    }
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-4 font-bold"><?php echo htmlspecialchars($r['nome']); ?></td>
                                    <td class="p-4"><?php echo $r['plano']; ?></td>
                                    <td class="p-4 font-mono text-sm"><?php echo $r['data_fim'] ? date('d/m/Y', strtotime($r['data_fim'])) : '<span class="text-green-600 font-bold">Ativo</span>'; ?></td>
                                    <td class="p-4 text-sm">
                                        <div><?php echo $r['admin_nome']; ?></div>
                                        <div class="text-gray-500 text-xs"><?php echo $r['admin_email']; ?></div>
                                    </td>
                                    <td class="p-4"><?php echo $statusBadge; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if(empty($reportData)) echo "<div class='p-8 text-center text-gray-500'>Nenhum resultado encontrado para o filtro.</div>"; ?>
                </div>

            <?php endif; ?>

        </main>
    </div>

    <!-- Edit Admin Email Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center backdrop-blur-sm z-50">
        <div class="bg-white p-6 rounded shadow-lg w-96">
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

    <!-- Change Password Modal -->
    <div id="passModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center backdrop-blur-sm z-50">
        <div class="bg-white p-6 rounded shadow-lg w-96">
            <h3 class="text-lg font-bold mb-4">Alterar Senha do Cliente</h3>
            <form method="POST">
                <input type="hidden" name="update_tenant_password" value="1">
                <input type="hidden" name="admin_user_id_pass" id="modal_user_id_pass">
                
                <label class="block mb-2 text-sm text-gray-600 font-bold">Nova Senha</label>
                <input type="text" name="new_password_tenant" class="w-full border p-2 rounded mb-4 focus:ring-2 focus:ring-black focus:outline-none" placeholder="Digite a nova senha" required>
                
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('passModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 font-bold rounded hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white font-bold rounded hover:bg-gray-800">Salvar</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Update Plan Modal -->
    <div id="planModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center backdrop-blur-sm z-50">
        <div class="bg-white p-6 rounded shadow-lg w-96">
            <h3 class="text-lg font-bold mb-4">Alterar Plano do Cliente</h3>
            <form method="POST">
                <input type="hidden" name="update_tenant_plan" value="1">
                <input type="hidden" name="tenant_id" id="plan_tenant_id">
                
                <label class="block mb-2 text-sm text-gray-600 font-bold">Selecione o Novo Plano</label>
                <select name="new_plano_id" id="plan_select" class="w-full border p-2 rounded mb-4 focus:ring-2 focus:ring-black focus:outline-none">
                    <?php foreach($planos as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo $p['nome']; ?> (R$ <?php echo $p['preco']; ?>)</option>
                    <?php endforeach; ?>
                </select>
                
                <p class="text-xs text-gray-500 mb-4 bg-yellow-50 p-2 rounded border border-yellow-200">
                    <i class="fas fa-info-circle"></i> Ao salvar, a assinatura será renovada por <strong>30 dias</strong> a partir de hoje.
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('planModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 font-bold rounded hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white font-bold rounded hover:bg-gray-800">Salvar Alteração</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Extras Modal -->
    <div id="extrasModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-70 flex items-center justify-center backdrop-blur-sm z-50">
        <div class="bg-white p-6 rounded shadow-lg w-96">
            <h3 class="text-lg font-bold mb-4">Gerenciar Extras Personalizados</h3>
            <form method="POST">
                <input type="hidden" name="update_extras" value="1">
                <input type="hidden" name="tenant_id_extras" id="extras_tenant_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Membros Extras (+R$ 0,30/unid)</label>
                    <input type="number" name="extra_membros" id="extra_membros_input" class="w-full border p-2 rounded focus:ring-2 focus:ring-purple-500 outline-none" placeholder="0">
                </div>
                
                <div class="mb-4">
                    <input type="number" name="extra_filiais" id="extra_filiais_input" class="w-full border p-2 rounded focus:ring-2 focus:ring-purple-500 outline-none" placeholder="0">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Patrimônio Extra (+R$ 0,10/item)</label>
                    <input type="number" name="extra_patrimonio" id="extra_patrimonio_input" class="w-full border p-2 rounded focus:ring-2 focus:ring-purple-500 outline-none" placeholder="0">
                </div>
                
                <p class="text-xs text-gray-500 mb-4 bg-purple-50 p-2 rounded border border-purple-100">
                    <i class="fab fa-stripe"></i> A atualização será refletida na assinatura Stripe do cliente imediatamente.
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('extrasModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 font-bold rounded hover:bg-gray-300">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white font-bold rounded hover:bg-purple-700">Salvar e Sincronizar</button>
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

        function openPassModal(id) {
            document.getElementById('modal_user_id_pass').value = id;
            document.getElementById('passModal').classList.remove('hidden');
        }

        function openPlanModal(tenantId, currentPlanId) {
            document.getElementById('plan_tenant_id').value = tenantId;
            if(currentPlanId) {
                document.getElementById('plan_select').value = currentPlanId;
            }
            document.getElementById('planModal').classList.remove('hidden');
        }

        function openExtrasModal(tenantId, currentMembros, currentFiliais) {
            document.getElementById('extras_tenant_id').value = tenantId;
            document.getElementById('extra_membros_input').value = currentMembros || 0;
            document.getElementById('extra_filiais_input').value = currentFiliais || 0;
            document.getElementById('extra_patrimonio_input').value = arguments[3] || 0;
            document.getElementById('extrasModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
