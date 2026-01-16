<?php
require_once 'config.php';
define('ABSPATH', true);

// Fetch Plans
try {
    // Fetch Plans (Excluding Enterprise/Custom if needed by checking name or simple filter)
    $planos = $pdo->query("SELECT * FROM planos WHERE nome NOT LIKE '%Enterprise%' ORDER BY preco ASC")->fetchAll();
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
        .gradient-text {
            background: linear-gradient(to right, #000000, #4b5563);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
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

    <!-- Hero Section (New) -->
    <div class="bg-black text-white pt-20 pb-24 px-4 relative overflow-hidden">
        <!-- Background Decor -->
        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/stardust.png')]"></div>
        
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <span class="bg-gray-800 text-gray-300 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-widest mb-4 inline-block">
                Gestão Simplificada para Igrejas Pequenas e Médias
            </span>
            <h1 class="text-5xl md:text-6xl font-extrabold mb-6 leading-tight">
                Sua Igreja Organizada, <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">Seus Membros Conectados.</span>
            </h1>
            <p class="text-xl text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                Pare de sofrer com planilhas confusas e sistemas caros. Tenha fichas de membros, carteirinhas digitais, controle financeiro e <strong>Doações via PIX</strong> em um único lugar, acessível pelo celular.
            </p>
            
            <div class="flex flex-col md:flex-row gap-4 justify-center items-center">
                <?php 
                // Find Free plan ID for CTA
                $freePlanId = 1; // Default fallback
                foreach($planos as $p) { if($p['preco'] == 0) $freePlanId = $p['id']; }
                ?>
                <a href="checkout_stripe.php?plan_id=<?php echo $freePlanId; ?>" class="px-8 py-4 bg-white text-black font-bold text-lg rounded-full shadow-lg hover:bg-gray-100 transform hover:scale-105 transition-all flex items-center gap-2">
                    Criar Conta Grátis <i class="fas fa-arrow-right text-sm"></i>
                </a>
                <a href="#planos" class="px-8 py-4 bg-transparent border border-gray-700 text-white font-bold text-lg rounded-full hover:bg-gray-800 transition-colors">
                    Ver Planos PRO
                </a>
            </div>
            
            <div class="mt-12 flex items-center justify-center gap-8 text-gray-500 text-sm font-medium opacity-70">
                <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500"></i> Sem cartão de crédito</span>
                <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500"></i> Cancele quando quiser</span>
                <span class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500"></i> Suporte humanizado</span>
            </div>
        </div>
    </div>

    <!-- Pricing Cards -->
    <div id="planos" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 mt-8 relative z-20">
        <div class="flex flex-wrap justify-center gap-8">
            
            <?php foreach($planos as $plan): ?>
            <?php 
                $isPopular = (strtolower($plan['nome']) == 'pro' || $plan['preco'] > 0); 
                // Simple logic for highlight, adjust as needed
                $features = [
                    "Até {$plan['limite_membros']} membros",
                    "Até {$plan['limite_filiais']} filiais inclusas",
                    "Gestão Financeira Completa",
                    "App (PWA) para Membros",
                    "Suporte Prioritário"
                ];
                
                // Add PRO features dynamically
                if (strtolower($plan['nome']) == 'pro') {
                    $features[] = "<strong>Novo:</strong> Receba Doações via PIX";
                    $features[] = "Upload de Comprovantes";
                }
            ?>
            <div class="w-full max-w-sm relative bg-white rounded-2xl shadow-xl border <?php echo $isPopular ? 'border-black scale-105 z-10' : 'border-gray-200'; ?> p-8 flex flex-col hover:border-black transition duration-300">
                
                <?php if($isPopular): ?>
                <div class="absolute top-0 right-0 -mt-3 mr-4 bg-black text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide shadow-md">
                    Mais Popular
                </div>
                <?php endif; ?>

                <h3 class="text-xl font-bold text-gray-900 mb-4"><?php echo $plan['nome']; ?></h3>
                
                <div class="flex items-baseline mb-8">
                    <span class="text-4xl font-extrabold tracking-tight">R$ <?php echo number_format($plan['preco'], 2, ',', '.'); ?></span>
                    <span class="text-gray-500 ml-1">/mês</span>
                </div>

                <ul class="space-y-4 mb-8 flex-1">
                    <?php foreach($features as $feature): ?>
                    <li class="flex items-start">
                        <i class="fas fa-check text-green-500 mt-1 mr-3 text-sm"></i>
                        <span class="text-gray-600 text-sm"><?php echo $feature; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <a href="checkout_stripe.php?plan_id=<?php echo $plan['id']; ?>" class="block w-full py-3 px-6 text-center rounded-lg font-bold transition duration-200 bg-black text-white hover:bg-gray-800 shadow-lg hover:shadow-xl">
                    <?php echo ($plan['preco'] > 0) ? 'Começar Agora' : 'Criar Conta Grátis'; ?>
                </a>
            </div>
            <?php endforeach; ?>

        </div>
    </div>

    <!-- FAQ -->
    <div class="bg-gray-50 py-16 border-t border-gray-200">
        <div class="max-w-3xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Perguntas Frequentes</h2>
            <div class="space-y-6">
                <details class="group bg-white rounded-lg shadow-sm p-6 cursor-pointer">
                    <summary class="flex justify-between items-center font-medium list-none">
                        <span>Posso mudar de plano depois?</span>
                        <span class="transition group-open:rotate-180">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </summary>
                    <p class="text-gray-600 mt-4 text-sm leading-relaxed">
                        Sim! Você pode fazer upgrade ou downgrade a qualquer momento direto pelo painel administrativo.
                    </p>
                </details>
                <details class="group bg-white rounded-lg shadow-sm p-6 cursor-pointer">
                    <summary class="flex justify-between items-center font-medium list-none">
                        <span>Como funciona o limite de filiais?</span>
                        <span class="transition group-open:rotate-180">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </summary>
                    <p class="text-gray-600 mt-4 text-sm leading-relaxed">
                        Seu plano inclui um número base de filiais. Se precisar de mais, você paga apenas R$ 50,00 por filial extra, sem precisar mudar de plano.
                    </p>
                </details>
            </div>
        </div>
    </div>

    <!-- Floating WhatsApp -->
    <a href="https://wa.me/5511949216688" target="_blank" class="fixed bottom-6 right-6 z-50 bg-green-500 text-white w-16 h-16 rounded-full shadow-lg hover:bg-green-600 hover:scale-110 transition duration-300 flex items-center justify-center animate-bounce">
        <i class="fab fa-whatsapp text-4xl"></i>
    </a>

</body>
</html>
