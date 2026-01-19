<?php
require_once 'config.php';
define('ABSPATH', true);

// Fetch Plans
try {
    // Fetch Plans (Only Base Plans: Free and Pro)
    $planos = $pdo->query("SELECT * FROM planos WHERE nome IN ('Gratuito', 'Pro') ORDER BY preco ASC")->fetchAll();
} catch (PDOException $e) {
    die("Erro ao carregar planos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos e Preços - Church Digital</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
    <?php 
        // SEO Scripts Injection
        try {
            $stmtSeo = $pdo->query("SELECT config_value FROM admin_config WHERE config_key = 'seo_head_scripts'");
            $seoScripts = $stmtSeo->fetchColumn();
            if ($seoScripts) echo $seoScripts;
        } catch(Exception $e) {}
    ?>
</head>
<body class="bg-gray-50 font-sans text-gray-800">

    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <img src="assets/icons/icon-512.png" class="h-8 w-8 rounded">
                <span class="font-bold text-xl tracking-tight">ChurchDigital</span>
            </div>

            <a href="login.php" class="bg-black text-white px-4 py-2 rounded-full text-sm font-bold hover:bg-gray-800 transition shadow-lg">
                Área do Cliente
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="bg-black text-white pt-20 pb-24 px-4 relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/stardust.png')]"></div>
        
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <span class="bg-gray-800 text-gray-300 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-widest mb-4 inline-block">
                Gestão Simplificada
            </span>
            <h1 class="text-5xl md:text-6xl font-extrabold mb-6 leading-tight">
                Escalável para <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">Qualquer Tamanho.</span>
            </h1>
            <p class="text-xl text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                Comece grátis ou personalize seu plano Pro com exatamente o que sua igreja precisa.
            </p>
        </div>
    </div>

    <!-- Pricing Cards -->
    <div id="planos" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 mt-8 relative z-20">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <?php 
            // 1. FREE PLAN
            $freePlan = null;
            foreach ($planos as $p) { if ($p['nome'] == 'Gratuito') $freePlan = $p; }
            ?>
            
            <!-- FREE CARD -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8 flex flex-col hover:border-black transition duration-300">
                <h3 class="text-xl font-bold text-gray-900 mb-4"><?php echo $freePlan['nome']; ?></h3>
                <div class="flex items-baseline mb-8">
                    <span class="text-4xl font-extrabold tracking-tight">Grátis</span>
                </div>
                <ul class="space-y-4 mb-8 flex-1">
                    <li class="flex items-start"><i class="fas fa-users text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">Até <?php echo $freePlan['limite_membros']; ?> membros</span></li>
                    <li class="flex items-start"><i class="fas fa-warehouse text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">1 Sede (Sem filiais)</span></li>
                    <li class="flex items-start"><i class="fas fa-mobile-alt text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">App PWA Básico</span></li>
                </ul>
                <a href="checkout_stripe.php?plan_id=<?php echo $freePlan['id']; ?>" class="block w-full py-3 px-6 text-center rounded-lg font-bold transition duration-200 bg-gray-100 text-black hover:bg-gray-200">
                    Começar Grátis
                </a>
            </div>

            <?php 
            // 2. PRO PLAN (Standard)
            $proPlan = null;
            foreach ($planos as $p) { if ($p['nome'] == 'Pro') $proPlan = $p; }
            ?>

            <!-- PRO CARD -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8 flex flex-col hover:border-black transition duration-300 relative overflow-hidden">
                <div class="absolute top-0 right-0 bg-black text-white text-xs font-bold px-3 py-1 rounded-bl-lg uppercase">Padrão</div>
                <h3 class="text-xl font-bold text-gray-900 mb-4"><?php echo $proPlan['nome']; ?></h3>
                <div class="flex items-baseline mb-8">
                    <span class="text-4xl font-extrabold tracking-tight">R$ <?php echo number_format($proPlan['preco'], 2, ',', '.'); ?></span>
                    <span class="text-gray-500 ml-1">/mês</span>
                </div>
                <ul class="space-y-4 mb-8 flex-1">
                     <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">Recursos Ilimitados*</span></li>
                     <li class="flex items-start"><i class="fas fa-users text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">Até <?php echo $proPlan['limite_membros']; ?> membros</span></li>
                     <li class="flex items-start"><i class="fas fa-warehouse text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">Até <?php echo $proPlan['limite_filiais']; ?> filiais</span></li>
                     <li class="flex items-start"><i class="fas fa-qrcode text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm text-purple-600 font-bold">Doações PIX + Uploads</span></li>
                </ul>
                <a href="checkout_stripe.php?plan_id=<?php echo $proPlan['id']; ?>" class="block w-full py-3 px-6 text-center rounded-lg font-bold transition duration-200 bg-black text-white hover:bg-gray-800 shadow-lg">
                    Assinar Pro
                </a>
            </div>

            <!-- CUSTOM CARD -->
            <div class="bg-gradient-to-br from-gray-900 to-black rounded-2xl shadow-2xl border border-gray-700 p-8 flex flex-col transform hover:scale-105 transition duration-300 text-white relative">
                 <div class="absolute top-0 right-0 -mt-3 mr-4 bg-gradient-to-r from-purple-500 to-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide shadow-md animate-pulse">
                    Personalizado
                </div>
                <h3 class="text-xl font-bold mb-2">Pro Personalizado</h3>
                <p class="text-gray-400 text-xs mb-6">Monte seu plano ideal partindo do Pro.</p>
                
                <!-- Calculator -->
                <div class="flex-1 space-y-6">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Membros Extras</span>
                            <span class="font-bold text-purple-400" id="membros-val">0</span>
                        </div>
                        <input type="range" id="membros-slider" min="0" max="2000" step="100" value="0" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-purple-500">
                        <div class="text-xs text-gray-500 mt-1">+ R$ 0,30/membro</div>
                    </div>

                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Filiais Extras</span>
                            <span class="font-bold text-blue-400" id="filiais-val">0</span>
                        </div>
                        <input type="range" id="filiais-slider" min="0" max="50" step="1" value="0" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                         <div class="text-xs text-gray-500 mt-1">+ R$ 12,90/filial</div>
                    </div>
                </div>

                <div class="mt-8 border-t border-gray-800 pt-4">
                    <div class="text-xs text-gray-400 mb-1">Total Estimado</div>
                    <div class="flex items-baseline mb-4">
                        <span class="text-4xl font-extrabold tracking-tight" id="total-price">R$ <?php echo number_format($proPlan['preco'], 2, ',', '.'); ?></span>
                        <span class="text-gray-500 ml-1">/mês</span>
                    </div>
                     <p class="text-xs text-gray-500 mb-4">
                        Base Pro (R$ <?php echo number_format($proPlan['preco'], 2, ',', '.'); ?>) + Extras
                    </p>
                    
                    <a id="custom-checkout-btn" href="checkout_stripe.php?plan_id=<?php echo $proPlan['id']; ?>" class="block w-full py-3 px-6 text-center rounded-lg font-bold transition duration-200 bg-white text-black hover:bg-gray-200 hover:shadow-lg">
                        Contratar Personalizado
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Script for Calculator -->
    <script>
        const basePrice = <?php echo $proPlan['preco']; ?>;
        const pricePerMember = 0.30;
        const pricePerBranch = 12.90;
        const planId = <?php echo $proPlan['id']; ?>;

        const mSlider = document.getElementById('membros-slider');
        const fSlider = document.getElementById('filiais-slider');
        const mVal = document.getElementById('membros-val');
        const fVal = document.getElementById('filiais-val');
        const totalDisplay = document.getElementById('total-price');
        const btn = document.getElementById('custom-checkout-btn');

        function update() {
            const m = parseInt(mSlider.value);
            const f = parseInt(fSlider.value);
            
            mVal.textContent = '+' + m;
            fVal.textContent = '+' + f;

            const total = basePrice + (m * pricePerMember) + (f * pricePerBranch);
            
            totalDisplay.textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // Update Link
            btn.href = `checkout_stripe.php?plan_id=${planId}&extra_membros=${m}&extra_filiais=${f}`;
        }

        mSlider.addEventListener('input', update);
        fSlider.addEventListener('input', update);
    </script>
    
    <!-- WhatsApp Float -->
    <a href="https://wa.me/5511949216688" target="_blank" class="fixed bottom-6 right-6 z-50 bg-green-500 text-white w-16 h-16 rounded-full shadow-lg hover:bg-green-600 hover:scale-110 transition duration-300 flex items-center justify-center animate-bounce">
        <i class="fab fa-whatsapp text-4xl"></i>
    </a>

</body>
</html>
