<?php
require_once 'config.php';
define('ABSPATH', true);
require_once 'includes/auth.php';
require_once 'includes/GoogleAuthenticator.php';

// Verify if we have a pending login
if (!isset($_SESSION['2fa_pending_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $userId = $_SESSION['2fa_pending_user_id'];
    $userType = $_SESSION['2fa_pending_type'];
    
    // Fetch user secret from DB
    $table = ($userType === 'member') ? 'membros' : 'usuarios';
    $stmt = $pdo->prepare("SELECT two_factor_secret FROM $table WHERE id = ?");
    $stmt->execute([$userId]);
    $secret = $stmt->fetchColumn();
    
    $ga = new GoogleAuthenticator();
    if ($ga->verifyCode($secret, $code, 2)) {
        // Code Valid! Complete Login
        // We need to call a function to "hydrate" the session or do it manually here.
        // Since `login()` function does everything, we might need a `force_login_session()` or just replicate logic.
        // Replicating logic is safer to avoid loop or modifying `login()` too much.
        
        // Fetch full user again
        $stmtUser = $pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch();
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome']; // Note: login() uses 'user_name' mostly but payment_success used 'user_nome'. standardized below.
        $_SESSION['user_name'] = $user['nome']; // Standard
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['igreja_id'] = $user['igreja_id'];
        $_SESSION['user_type'] = $userType;
        
        if ($userType === 'staff') {
             $_SESSION['user_sexo'] = $user['sexo'] ?? 'M';
             
             // RBAC Load
            $stmtRoles = $pdo->prepare("SELECT p.nome FROM papeis p JOIN papel_usuario pu ON p.id = pu.papel_id WHERE pu.usuario_id = ?");
            $stmtRoles->execute([$user['id']]);
            $_SESSION['user_roles'] = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

            if (empty($_SESSION['user_roles']) && !empty($user['nivel'])) {
                $map = ['admin' => 'Administrador', 'tesoureiro' => 'Tesoureiro', 'secretario' => 'Secretário'];
                $_SESSION['user_roles'][] = $map[$user['nivel']] ?? $user['nivel'];
            }
            
            $stmtPerms = $pdo->prepare("SELECT DISTINCT per.slug FROM permissoes per JOIN papel_permissoes pp ON per.id = pp.permissao_id JOIN papel_usuario pu ON pp.papel_id = pu.papel_id WHERE pu.usuario_id = ?");
            $stmtPerms->execute([$user['id']]);
            $_SESSION['user_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
            
        } else {
            // Member
            $_SESSION['user_photo'] = $user['foto'] ?? '';
            $_SESSION['user_roles'] = ['Membro'];
            $_SESSION['user_permissions'] = [];
            $_SESSION['must_change_password'] = $user['must_change_password'] ?? false;
        }
        
        // Clear pending
        unset($_SESSION['2fa_pending_user_id']);
        unset($_SESSION['2fa_pending_type']);
        
        header('Location: index.php');
        exit;
        
    } else {
        $error = "Código incorreto. Tente novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de 2 Passos - Church Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center h-screen px-4">

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md text-center border border-gray-100">
        <div class="mb-6">
            <div class="bg-black p-4 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4 shadow-lg">
                <!-- SVG Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Verificação de Segurança</h1>
            <p class="text-gray-500 text-sm mt-1">Digite o código de 6 dígitos do seu app autenticador.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 mb-4 text-sm rounded" role="alert">
                <p><strong><i class="fas fa-exclamation-triangle"></i> Ops!</strong> <?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-6">
                <input class="shadow-sm appearance-none border rounded-lg w-full py-4 px-3 text-gray-800 leading-tight focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent text-center text-3xl tracking-[1rem] font-mono transition" id="code" name="code" type="text" placeholder="000000" maxlength="6" required autofocus autocomplete="off">
            </div>
            
            <button class="bg-black hover:bg-gray-800 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 transform hover:scale-[1.01] shadow-lg" type="submit">
                Verificar <i class="fas fa-check-circle ml-2"></i>
            </button>
        </form>
        
        <p class="mt-8 text-xs text-gray-400">
            <a href="login.php" class="hover:text-black transition flex items-center justify-center gap-1"><i class="fas fa-arrow-left"></i> Voltar para Login</a>
        </p>
    </div>

</body>
</html>
