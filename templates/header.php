<?php
if (!isset($pdo)) {
    // Fallback se incluído diretamente
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
}

// Garante que variáveis de cor existam
$primaryColor = $tenant['cor_primaria'] ?? '#3b82f6';
$secondaryColor = $tenant['cor_secundaria'] ?? '#1e40af';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo e($tenant['nome']); ?> - Gestão</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Configuração Dinâmica do Tailwind e Cores -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--color-primary)',
                        secondary: 'var(--color-secondary)',
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --color-primary: <?php echo $primaryColor; ?>;
            --color-secondary: <?php echo $secondaryColor; ?>;
        }
        
        body {
            background-color: #f3f4f6; /* gray-100 */
            -webkit-tap-highlight-color: transparent;
            font-family: 'Poppins', sans-serif; /* Fallback/Enforce */
        }
        
        /* Ajuste para App Mobile - Padding inferior para não cobrir conteúdo com menu fixo */
        .content-safe-area {
            padding-bottom: 80px; 
        }
    </style>
    
    <!-- FontAwesome (CDN simples para ícones) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    <?php 
        // SEO Scripts Injection
        try {
            // Check if connection exists (header might be included without full context, though unlikely)
            if (isset($pdo)) {
                $stmtSeo = $pdo->query("SELECT config_value FROM admin_config WHERE config_key = 'seo_head_scripts'");
                $seoScripts = $stmtSeo->fetchColumn();
                if ($seoScripts) echo $seoScripts;
            }
        } catch(Exception $e) {}
    ?>
</head>
<body class="text-gray-800 antialiased font-sans flex flex-col min-h-screen">

<!-- Top Bar Mobile -->
<header class="bg-gradient-to-r from-primary to-secondary text-white p-4 shadow-md sticky top-0 z-50 flex justify-between items-center transition-all duration-500">
    
    <!-- Logo ou Nome da Igreja (Área Maior) -->
    <div class="flex items-center overflow-hidden flex-grow">
        <?php if (!empty($tenant['logo_url'])): ?>
            <!-- Aumentado para melhor visibilidade -->
            <img src="<?php echo e($tenant['logo_url']); ?>" alt="<?php echo e($tenant['nome']); ?>" class="h-14 object-contain max-w-[200px]">
        <?php else: ?>
            <div class="flex flex-col">
                <h1 class="text-xl font-bold truncate"><?php echo e($tenant['nome']); ?></h1>
            </div>
        <?php endif; ?>
    </div>

    <!-- Identificação do Usuário e Contexto -->
    <div class="flex items-center gap-4 text-right">
        
        <!-- Bloco de Info (Visible on Desktop/Tablet, simplified on mobile) -->
        <div class="leading-tight mr-1">
            <div class="font-bold text-sm block"><?php echo e($_SESSION['user_name']); ?></div>
            
            <!-- Context Badge / Switcher -->
            <?php 
            $availableTenants = TenantScope::getAvailableTenants($pdo, $_SESSION['user_id'] ?? 0);
            if (count($availableTenants) > 1): 
            ?>
                <!-- Dropdown Trigger -->
                <div class="relative group mt-0.5">
                    <button class="text-[10px] uppercase font-semibold opacity-90 tracking-wide bg-white/20 hover:bg-white/30 px-2 py-0.5 rounded text-center flex items-center gap-1 transition">
                        <span><?php echo substr($tenant['nome'], 0, 15); ?></span>
                        <i class="fas fa-chevron-down text-[8px]"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div class="absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-xl py-1 text-gray-800 hidden group-hover:block z-50 animate-fade-in-down border border-gray-100">
                        <div class="px-3 py-2 border-b border-gray-100 bg-gray-50">
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Alternar Igreja</p>
                        </div>
                        <?php foreach($availableTenants as $t): ?>
                            <a href="index.php?action=switch_tenant&id=<?php echo $t['id']; ?>" class="block px-4 py-2 text-xs hover:bg-blue-50 hover:text-primary <?php echo $t['id'] == $_SESSION['igreja_id'] ? 'font-bold text-primary bg-blue-50' : ''; ?>">
                                <?php echo $t['nome']; ?>
                                <?php if($t['tipo'] == 'Matriz') echo '<span class="ml-1 text-[9px] bg-gray-200 text-gray-600 px-1 rounded">M</span>'; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Single Context Display -->
                <div class="text-[10px] uppercase font-semibold opacity-90 tracking-wide bg-white/20 px-2 py-0.5 rounded text-center mt-0.5">
                    <?php 
                        // Use helper for gender-correct label
                        $rawRole = $_SESSION['user_roles'][0] ?? 'User';
                        // Convert 'Administrador' back to 'admin' for mapping if needed, or simply handle the raw string in helper if map keys match?
                        // The helper expects keys like 'admin', 'tesoureiro'. 
                        // But 'user_roles' has 'Administrador' (capitalized and translated) from legacy auth logic?
                        // Let's check auth.php again. It maps 'admin' -> 'Administrador'.
                        // So the session stores "Administrador". The helper expects 'admin'.
                        // We need to reverse map or adjust helper.
                        // ACTUALLY, simpler is to store the raw 'nivel' in session too?
                        // Or just checking:
                        $roleName = $_SESSION['user_roles'][0] ?? 'User';
                        
                        // If role is normalized (e.g. Administrador), we might need to display it as is if it's masculine, or adapt?
                        // Let's use the 'nivel' from DB if possible. We didn't store raw level in session.
                        // Let's rely on standard map.
                        
                        // Map Display Name back to Key? Or just handle Display Name replacements?
                        // 'Administrador' -> (if F) 'Administradora'
                        // 'Tesoureiro' -> (if F) 'Tesoureira'
                        // 'Secretário' -> (if F) 'Secretária'
                        
                        $sexo = $_SESSION['user_sexo'] ?? 'M';
                        if ($sexo === 'F') {
                            $replacements = [
                                'Administrador' => 'Administradora',
                                'Tesoureiro' => 'Tesoureira',
                                'Secretário' => 'Secretária'
                            ];
                            echo $replacements[$roleName] ?? $roleName;
                        } else {
                            echo $roleName;
                        }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Role Switch: Access Member Card -->
        <?php if (!empty($_SESSION['membro_id'])): ?>
            <a href="index.php?page=minha_carteirinha" class="text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition w-10 h-10 flex items-center justify-center relative group-hover" title="Minha Carteirinha">
                <i class="fas fa-id-card"></i>
                <span class="absolute -bottom-1 -right-1 bg-green-500 rounded-full w-3 h-3 border-2 border-white"></span>
            </a>
        <?php endif; ?>

        <a href="index.php?page=perfil" class="text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition w-10 h-10 flex items-center justify-center" title="Meu Perfil">
            <i class="fas fa-user"></i>
        </a>

        <a href="logout.php" class="text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition w-10 h-10 flex items-center justify-center">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<main class="flex-grow content-safe-area p-4 max-w-7xl mx-auto w-full">
