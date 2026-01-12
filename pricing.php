<?php
require_once 'config.php';
define('ABSPATH', true);

// Fetch Plans
try {
    $planos = $pdo->query("SELECT * FROM planos ORDER BY preco ASC")->fetchAll();
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
            <nav class="hidden md:flex gap-6 text-sm font-medium text-gray-500">
                <a href="#" class="hover:text-black">Funcionalidades</a>
                <a href="#" class="hover:text-black">Benefícios</a>
                <a href="#" class="text-black font-bold">Preços</a>
                <a href="index.php" class="hover:text-black">Entrar</a>
            </nav>
            <a href="index.php" class="bg-black text-white px-4 py-2 rounded-full text-sm font-bold hover:bg-gray-800 transition shadow-lg">
                Área do Cliente
            </a>
        </div>
    </header>

    <!-- Hero -->
    <div class="text-center py-16 px-4 bg-white">
        <h1 class="text-4xl md:text-5xl font-extrabold mb-4">
            Escolha o plano ideal para sua <span class="gradient-text">Igreja</span>
        </h1>
        <p class="text-xl text-gray-500 max-w-2xl mx-auto">
            Comece pequeno e cresça conosco. Sem contratos de fidelidade, cancele quando quiser.
        </p>
    </div>

    <!-- Pricing Cards -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-24">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
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
            ?>
            <div class="relative bg-white rounded-2xl shadow-xl border <?php echo $isPopular ? 'border-black scale-105 z-10' : 'border-gray-200'; ?> p-8 flex flex-col hover:border-black transition duration-300">
                
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

                <a href="checkout_stripe.php?plan_id=<?php echo $plan['id']; ?>" class="block w-full py-3 px-6 text-center rounded-lg font-bold transition duration-200 <?php echo $isPopular ? 'bg-black text-white hover:bg-gray-800 shadow-lg hover:shadow-xl' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Começar Agora
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

</body>
</html>
