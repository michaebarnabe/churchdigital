<?php
require_once 'config.php';
define('ABSPATH', true);
require_once 'includes/mailer.php';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    // Check if email exists in Usuarios or Membros
    $found = false;
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $found = true;
    
    if (!$found) {
        $stmt = $pdo->prepare("SELECT id FROM membros WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $found = true;
    }
    
    if ($found) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expiry]);
        
        // Send Email
        // Assuming localhost requires full URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        // Simple heuristic for base URL, might need adjustment
        $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $link = $baseUrl . "/reset_password.php?token=$token&email=" . urlencode($email);
        
        $body = "
        <h2>Recuperação de Senha</h2>
        <p>Recebemos uma solicitação para redefinir sua senha.</p>
        <p>Clique no link abaixo para criar uma nova senha:</p>
        <p><a href='$link'>$link</a></p>
        <p>Este link expira em 1 hora.</p>
        <p>Se você não solicitou, ignore este e-mail.</p>
        ";
        
        $result = send_mail($email, 'Redefinir Senha - Church Digital', $body);
        
        if ($result === true) {
            $msg = 'Se o e-mail estiver cadastrado, você receberá um link de recuperação em instantes.';
            $msgType = 'success';
        } else {
            $msg = 'Erro ao enviar e-mail: ' . $result;
            $msgType = 'error';
        }
    } else {
        $msg = 'E-mail não encontrado no sistema.';
        $msgType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Church Digital</title>
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
            <h1 class="text-2xl font-bold text-gray-900">Recuperar Senha</h1>
            <p class="text-gray-500 text-sm mt-1">Informe seu e-mail para continuar</p>
        </div>

        <?php if ($msg): ?>
            <div class="<?php echo $msgType === 'success' ? 'bg-green-100 text-green-700 border-green-500' : 'bg-red-100 text-red-700 border-red-500'; ?> border-l-4 p-4 mb-4 rounded" role="alert">
                <p class="font-bold"><?php echo $msgType === 'success' ? '<i class="fas fa-check-circle"></i> Sucesso:' : '<i class="fas fa-exclamation-circle"></i> Erro:'; ?></p>
                <p><?php echo $msg; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">E-mail</label>
                <div class="relative">
                     <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input class="pl-10 shadow-sm appearance-none border rounded-lg w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent transition" id="email" name="email" type="email" placeholder="seu@email.com" required>
                </div>
            </div>
            
            <div class="flex flex-col gap-4">
                <button class="bg-black hover:bg-gray-800 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition shadow-lg transform hover:scale-[1.01]" type="submit">
                    Enviar Link <i class="fas fa-paper-plane ml-2"></i>
                </button>
                <a href="login.php" class="text-center text-sm text-gray-500 hover:text-black font-semibold transition">Voltar para Login</a>
            </div>
        </form>
    </div>

</body>
</html>
