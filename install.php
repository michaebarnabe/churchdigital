<?php
require_once 'config.php';
define('ABSPATH', true);
require_once 'includes/functions.php';
// Get current URL for QR Code
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$current_url = $protocol . $_SERVER['HTTP_HOST'] . str_replace('install.php', 'index.php?mode=pwa', $_SERVER['REQUEST_URI']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar App - <?php echo e($tenant['nome']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .phone-mockup {
            border: 8px solid #333;
            border-radius: 30px;
            overflow: hidden;
            position: relative;
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex flex-col items-center justify-center p-4 text-white">

    <!-- Logo / Header -->
    <div class="text-center mb-8">
        <?php if (!empty($tenant['logo_url'])): ?>
            <img src="<?php echo e($tenant['logo_url']); ?>" alt="Logo" class="h-24 mx-auto mb-4 object-contain">
        <?php else: ?>
            <img src="assets/icons/icon-512.png" alt="Church Digital" class="h-24 w-24 mx-auto mb-4 rounded-xl shadow-lg bg-white p-2">
        <?php endif; ?>
        <h1 class="text-3xl font-bold mt-4">Instalar App <?php echo e($tenant['nome']); ?></h1>
        <p class="text-gray-400">Tenha sua carteirinha e agenda sempre à mão.</p>
    </div>

    <!-- DEVICE DETECTION CONTAINERS -->
    
    <!-- 1. ANDROID INSTRUCTIONS (Dynamic) -->
    <div id="android-guide" class="hidden max-w-md w-full bg-white text-gray-800 rounded-xl shadow-lg p-6 text-center border-t-4 border-black">
        <div class="text-black text-5xl mb-4"><i class="fab fa-android"></i></div>
        <h2 class="text-xl font-bold mb-2">Instalar no Android</h2>
        
        <!-- Botão Dinâmico de Instalação -->
        <div id="install-container" class="hidden">
            <p class="text-gray-600 mb-6 text-sm">Clique no botão abaixo para instalar o App Oficial:</p>
            <button id="install-btn" class="w-full bg-black text-white font-bold py-4 rounded-xl shadow-lg hover:bg-gray-800 transition flex items-center justify-center gap-2 text-lg animate-bounce">
                <i class="fas fa-download"></i> INSTALAR AGORA
            </button>
        </div>

        <!-- Fallback Manual (caso o botão não apareça) -->
        <div id="manual-install" class="block">
            <p class="text-gray-600 mb-6 text-sm">Siga os passos abaixo para adicionar à sua tela inicial:</p>
            <ol class="text-left space-y-4 text-sm text-gray-700 bg-gray-50 p-4 rounded-lg">
                <li class="flex items-start gap-3">
                    <span class="bg-black text-white font-bold rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0">1</span>
                    <span>Toque no botão de opções do navegador (três pontinhos <i class="fas fa-ellipsis-v text-xs"></i>).</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="bg-black text-white font-bold rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0">2</span>
                    <span>Selecione <strong>"Instalar aplicativo"</strong> ou <strong>"Adicionar à tela inicial"</strong>.</span>
                </li>
            </ol>
            
            <button onclick="window.location='index.php'" class="mt-6 w-full bg-gray-900 text-white font-bold py-3 rounded-lg shadow hover:bg-black transition">
                Abrir App no Navegador
            </button>
        </div>
    </div>

    <!-- 2. IOS INSTRUCTIONS -->
    <div id="ios-guide" class="hidden max-w-md w-full bg-white text-gray-800 rounded-xl shadow-lg p-6 text-center border-t-4 border-black">
        <div class="text-black text-5xl mb-4"><i class="fab fa-apple"></i></div>
        <h2 class="text-xl font-bold mb-2">Instalar no iPhone (iOS)</h2>
        <p class="text-gray-600 mb-6 text-sm">Disponível apenas no navegador <strong>Safari</strong>.</p>
        
        <div class="bg-gray-100 rounded-xl p-4 mb-6 relative overflow-hidden">
            <!-- Animation Mockap -->
            <div class="flex flex-col items-center gap-4 animate-pulse">
                <div class="flex items-center gap-2 text-blue-600 font-bold">
                    <i class="fas fa-share-square text-2xl"></i> 
                    <span>1. Toque em Compartilhar</span>
                </div>
                <div class="h-6 w-0.5 bg-gray-300"></div>
                <div class="flex items-center gap-2 text-black font-bold">
                    <i class="fas fa-plus-square text-2xl"></i>
                    <span>2. Adicionar à Tela de Início</span>
                </div>
            </div>
        </div>

        <button onclick="window.location='index.php'" class="w-full bg-black text-white font-bold py-3 rounded-lg shadow hover:bg-gray-800 transition">
            Abrir App no Navegador
        </button>
    </div>

    <!-- 3. DESKTOP / QR CODE -->
    <div id="desktop-guide" class="hidden max-w-md w-full bg-white text-gray-800 rounded-xl shadow-lg p-6 text-center">
        <h2 class="text-xl font-bold mb-2">Instale no seu Celular</h2>
        <p class="text-gray-600 mb-6 text-sm">Aponte a câmera do seu celular para o QR Code abaixo:</p>
        
        <div class="flex justify-center mb-4">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode($current_url); ?>" alt="QR Code" class="w-48 h-48">
        </div>

        <p class="text-xs text-gray-400 mt-4">Ou acesse: <span class="text-blue-500 font-mono"><?php echo $current_url; ?></span></p>
        
        <div class="mt-6 pt-6 border-t border-gray-100">
            <a href="index.php" class="text-black font-bold hover:underline">Acessar Versão Web <i class="fas fa-arrow-right ml-1"></i></a>
        </div>
    </div>

    <!-- JS Detection -->
    <script>
        // PWA Install Prompt Logic
        let deferredPrompt;
        const installBtn = document.getElementById('install-btn');
        const installContainer = document.getElementById('install-container');
        const manualInstall = document.getElementById('manual-install');

        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later.
            deferredPrompt = e;
            
            // Update UI
            if (installContainer) {
                installContainer.classList.remove('hidden');
                manualInstall.classList.add('hidden'); // Hide manual steps if automatic is available
            }
        });

        if (installBtn) {
            installBtn.addEventListener('click', (e) => {
                // Hide the button
                installContainer.classList.add('hidden');
                // Show the prompt
                deferredPrompt.prompt();
                // Wait for the user to respond to the prompt
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    } else {
                        console.log('User dismissed the A2HS prompt');
                        // Show manual again if they cancelled
                        manualInstall.classList.remove('hidden');
                    }
                    deferredPrompt = null;
                });
            });
        }

        function detectOS() {
            const userAgent = window.navigator.userAgent.toLowerCase();
            const ios = /iphone|ipad|ipod/.test(userAgent);
            const android = /android/.test(userAgent);
            
            if (ios) {
                document.getElementById('ios-guide').classList.remove('hidden');
            } else if (android) {
                document.getElementById('android-guide').classList.remove('hidden');
            } else {
                document.getElementById('desktop-guide').classList.remove('hidden');
            }
        }
        detectOS();
    </script>

</body>
</html>
