<?php
require_once 'config.php';
define('ABSPATH', true);

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$msg = '';
$error = '';

// Basic validation logic
$valid = false;
if ($token && $email) {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$email, $token]);
    $resetRequest = $stmt->fetch();
    
    if ($resetRequest) {
        $valid = true;
    } else {
        $error = 'Link inválido ou expirado.';
    }
} else {
    $error = 'Link inválido.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $nova_senha = $_POST['senha'];
    $conf_senha = $_POST['confirmar_senha'];
    
    if ($nova_senha === $conf_senha) {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        
        // Update User (Try both tables)
        $updated = false;
        
        // Try Usuarios
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
        $stmt->execute([$hash, $email]);
        if ($stmt->rowCount() > 0) $updated = true;
        
        // Try Membros
        if (!$updated) {
            $stmt = $pdo->prepare("UPDATE membros SET senha = ? WHERE email = ?");
            $stmt->execute([$hash, $email]);
            if ($stmt->rowCount() > 0) $updated = true;
        }
        
        if ($updated) {
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->execute([$resetRequest['id']]);
            
            $msg = 'Senha alterada com sucesso! Você pode fazer login agora.';
            $valid = false; // Disable form
        } else {
            $error = 'Erro ao atualizar senha. Usuário não encontrado.';
        }
        
    } else {
        $error = 'As senhas não conferem.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Church Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center h-screen px-4">

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="text-center mb-8">
            <img src="assets/icons/icon-512.png" alt="Church Digital" class="w-16 h-16 mx-auto rounded-xl shadow-md bg-white p-1 mb-4">
            <h1 class="text-2xl font-bold text-gray-900">Nova Senha</h1>
        </div>

        <?php if ($msg): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow-sm">
                <p class="font-bold"><i class="fas fa-check-circle"></i> Sucesso!</p>
                <p><?php echo $msg; ?></p>
                <div class="mt-4">
                    <a href="login.php" class="bg-black text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-800 transition inline-block shadow">Ir para Login</a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow-sm">
                <p class="font-bold"><i class="fas fa-exclamation-circle"></i> Erro:</p>
                <p><?php echo $error; ?></p>
                <div class="mt-4">
                     <a href="forgot_password.php" class="text-red-800 font-bold hover:underline">Solicitar novo link</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($valid && empty($msg)): ?>
        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nova Senha</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input class="pl-10 shadow-sm appearance-none border rounded-lg w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent transition" name="senha" type="password" required minlength="6" placeholder="******">
                </div>
            </div>
            
             <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Confirmar Senha</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input class="pl-10 shadow-sm appearance-none border rounded-lg w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent transition" name="confirmar_senha" type="password" required minlength="6" placeholder="******">
                </div>
            </div>
            
            <button class="bg-black hover:bg-gray-800 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition shadow-lg transform hover:scale-[1.01]" type="submit">
                Alterar Senha <i class="fas fa-save ml-2"></i>
            </button>
        </form>
        <?php endif; ?>
    </div>

</body>
</html>
