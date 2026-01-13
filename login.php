<?php
require_once 'config.php';
define('ABSPATH', true); // Security flag
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    $loginResult = login($pdo, $email, $senha);
    
    if ($loginResult === true) {
        header('Location: index.php');
        exit;
    } elseif ($loginResult === '2FA_REQUIRED') {
        header('Location: login_2fa.php');
        exit;
    } else {
        $error = $loginResult;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Church Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center h-screen px-4">

    <!-- PWA & Meta Tags -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ChurchApp">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="assets/icons/icon-512.png">
    <link rel="shortcut icon" href="assets/icons/icon-192.png">

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(reg => console.log('SW registered!', reg))
                    .catch(err => console.log('SW failed', err));
            });
        }
    </script>

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="text-center mb-8">
            <img src="assets/icons/icon-512.png" alt="Church Digital" class="w-20 h-20 mx-auto rounded-xl shadow-md bg-white p-2 mb-4">
            <h1 class="text-2xl font-bold text-gray-900">Acesso ao Sistema</h1>
            <p class="text-gray-500 text-sm mt-1">Gestão para Pequenas Igrejas</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
                <p class="font-bold"><i class="fas fa-exclamation-circle"></i> Erro:</p>
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                    E-mail
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-envelope"></i>
                    </span>
                    <input class="pl-10 shadow-sm appearance-none border rounded-lg w-full py-3 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent transition" id="email" name="email" type="email" placeholder="admin@igreja.com" required>
                </div>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="senha">
                    Senha
                </label>
                <div class="relative">
                     <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input class="pl-10 shadow-sm appearance-none border rounded-lg w-full py-3 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent transition" id="senha" name="senha" type="password" placeholder="******************" required>
                </div>
                <div class="text-right">
                    <a href="forgot_password.php" class="text-sm text-gray-500 hover:text-black font-semibold transition">Esqueci minha senha</a>
                </div>
            </div>
            
            <div class="flex items-center justify-between">
                <button class="bg-black hover:bg-gray-800 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline w-full transition duration-150 transform hover:scale-[1.01] shadow-lg" type="submit">
                    Entrar <i class="fas fa-arrow-right ml-2 opacity-80"></i>
                </button>
            </div>
        </form>

        <div class="mt-8 text-center border-t pt-4">
            <p class="text-xs text-gray-400">Ainda não tem conta?</p>
            <a href="pricing.php" class="text-sm font-bold text-black hover:underline mt-1 inline-block">Criar conta para minha igreja</a>
        </div>
    </div>

</body>
</html>
