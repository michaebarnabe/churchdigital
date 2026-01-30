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
<body class="bg-white font-sans text-gray-800 antialiased overflow-x-hidden">

    <!-- Header -->
    <header class="fixed w-full bg-white/90 backdrop-blur-md shadow-sm z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-2 md:gap-3 flex-shrink-0">
                <div class="w-8 h-8 md:w-10 md:h-10 bg-black rounded-lg flex items-center justify-center text-white">
                     <i class="fas fa-church text-base md:text-xl"></i>
                </div>
                <span class="font-extrabold text-lg md:text-2xl tracking-tighter text-gray-900">ChurchDigital</span>
            </div>

            <nav class="hidden md:flex gap-8 text-sm font-medium text-gray-600">
                <a href="#funcionalidades" class="hover:text-black transition">Funcionalidades</a>
                <a href="#planos" class="hover:text-black transition">Planos</a>
                <a href="#faq" class="hover:text-black transition">FAQ</a>
            </nav>

            <div class="flex items-center gap-2 md:gap-4">
                <a href="login.php" class="text-sm font-bold text-gray-900 hover:text-gray-600 transition hidden sm:block">
                    Entrar
                </a>
                <a href="#planos" class="bg-black text-white px-4 py-2 md:px-6 md:py-2.5 rounded-full text-xs md:text-sm font-bold hover:bg-gray-800 transition shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 whitespace-nowrap">
                    Começar Agora
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 lg:pt-48 lg:pb-32 px-4 relative overflow-hidden">
        <!-- Background Elements -->
        <div class="absolute top-0 right-0 -z-10 opacity-10 translate-x-1/3 -translate-y-1/4">
            <svg viewBox="0 0 1000 1000" class="w-[800px] h-[800px] text-gray-900 fill-current"><circle cx="500" cy="500" r="500"/></svg>
        </div>
        <div class="absolute bottom-0 left-0 -z-10 opacity-10 -translate-x-1/2 translate-y-1/4">
            <svg viewBox="0 0 1000 1000" class="w-[600px] h-[600px] text-gray-900 fill-current"><circle cx="500" cy="500" r="500"/></svg>
        </div>

        <div class="max-w-7xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 bg-gray-100 border border-gray-200 rounded-full px-4 py-1.5 mb-8 animate-fade-in-up">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                <span class="text-xs font-bold uppercase tracking-wide text-gray-600">Novidade: Dashboard Financeiro</span>
            </div>
            
            <h1 class="text-5xl md:text-7xl font-extrabold mb-8 leading-tight tracking-tight text-gray-900">
                <span class="block text-transparent bg-clip-text bg-gradient-to-r from-gray-900 via-gray-700 to-gray-900">Gestão Simplificada</span>
                <span class="block text-4xl md:text-6xl text-gray-500 mt-2 font-bold">para igrejas que crescem.</span>
            </h1>
            
            <p class="text-xl text-gray-500 mb-12 max-w-3xl mx-auto leading-relaxed">
                Organize membros, financeiro e eventos em um único lugar. Uma plataforma moderna, rápida e feita para potencializar o seu ministério.
            </p>
            
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="#planos" class="w-full sm:w-auto px-8 py-4 bg-black text-white text-lg font-bold rounded-full shadow-xl hover:shadow-2xl hover:bg-gray-800 transition transform hover:-translate-y-1">
                    Criar Conta Grátis
                </a>
                <a href="#funcionalidades" class="w-full sm:w-auto px-8 py-4 bg-white text-gray-900 border border-gray-200 text-lg font-bold rounded-full hover:bg-gray-50 transition flex items-center justify-center gap-2">
                    <i class="fas fa-play-circle text-gray-400"></i> Ver como funciona
                </a>
            </div>
            
            <!-- Removed Dashboard Placeholder as requested -->
        </div>
    </section>

    <!-- Social Proof (HIDDEN FOR NOW) -->
    <!-- Social Proof -->
    <section class="py-10 border-y border-gray-100 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mb-6">Confiado por grandes ministérios</p>
            <div class="flex flex-wrap justify-center gap-8 md:gap-16 opacity-50 grayscale hover:grayscale-0 transition-all duration-500">
                <i class="fas fa-church text-3xl"></i>
                <i class="fas fa-cross text-3xl"></i>
                <i class="fas fa-bible text-3xl"></i>
                <i class="fas fa-dove text-3xl"></i>
                <i class="fas fa-praying-hands text-3xl"></i>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="funcionalidades" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-20">
                <h2 class="text-sm font-bold text-blue-600 uppercase tracking-widest mb-2">Por que escolher?</h2>
                <h3 class="text-3xl md:text-4xl font-extrabold text-gray-900">Tudo o que você precisa para liderar.</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <!-- Feature 1 -->
                <div class="group p-8 rounded-3xl bg-gray-50 border border-gray-100 hover:border-blue-100 hover:shadow-xl hover:shadow-blue-50/50 transition duration-300">
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Membros & Pessoas</h4>
                    <p class="text-gray-500 leading-relaxed">
                        Cadastro completo, carteirinha digital automática e histórico de cada membro da sua congregação.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="group p-8 rounded-3xl bg-gray-50 border border-gray-100 hover:border-green-100 hover:shadow-xl hover:shadow-green-50/50 transition duration-300">
                    <div class="w-14 h-14 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">Financeiro Seguro</h4>
                    <p class="text-gray-500 leading-relaxed">
                        Controle dízimos, ofertas e despesas com relatórios gráficos claros e precisos.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="group p-8 rounded-3xl bg-gray-50 border border-gray-100 hover:border-purple-100 hover:shadow-xl hover:shadow-purple-50/50 transition duration-300">
                    <div class="w-14 h-14 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center text-2xl mb-6 group-hover:scale-110 transition">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h4 class="text-xl font-bold text-gray-900 mb-3">App PWA Incluso</h4>
                    <p class="text-gray-500 leading-relaxed">
                        Seus membros acessam pelo celular sem precisar baixar nada na loja de aplicativos.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <div id="planos" class="bg-gray-50 py-24 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-20">
            <div class="text-center max-w-3xl mx-auto mb-16">
                 <h2 class="text-sm font-bold text-green-600 uppercase tracking-widest mb-2">Planos Flexíveis</h2>
                <h3 class="text-3xl md:text-5xl font-extrabold text-gray-900 mb-6">Comece pequeno,<br>cresça sem limites.</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
                
                <?php 
                // 1. FREE PLAN
                $freePlan = null;
                foreach ($planos as $p) { if ($p['nome'] == 'Gratuito') $freePlan = $p; }
                ?>
                
                <!-- FREE CARD -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 flex flex-col hover:shadow-xl transition duration-300 relative group">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4"><?php echo $freePlan['nome']; ?></h3>
                    <div class="flex items-baseline mb-8">
                        <span class="text-4xl font-extrabold tracking-tight text-gray-900">Grátis</span>
                    </div>
                    <ul class="space-y-4 mb-8 flex-1">
                        <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">Até <?php echo $freePlan['limite_membros']; ?> membros</span></li>
                        <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">1 Matriz e 1 Filial</span></li>
                        <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3 text-sm"></i><span class="text-gray-600 text-sm">App PWA Básico</span></li>
                    </ul>
                    <a href="checkout_stripe.php?plan_id=<?php echo $freePlan['id']; ?>" class="block w-full py-4 px-6 text-center rounded-xl font-bold transition duration-200 bg-black text-white hover:bg-gray-800 shadow-md transform group-hover:-translate-y-1">
                        Começar Grátis
                    </a>
                </div>

                <?php 
                // 2. PRO PLAN (Standard)
                $proPlan = null;
                foreach ($planos as $p) { if ($p['nome'] == 'Pro') $proPlan = $p; }
                ?>

                <!-- PRO CARD -->
                <div class="bg-black rounded-2xl shadow-2xl p-8 flex flex-col relative overflow-hidden transform md:-translate-y-4 ring-4 ring-gray-100">
                    <div class="absolute top-0 right-0 bg-white text-black text-xs font-bold px-3 py-1 rounded-bl-lg uppercase">Mais Popular</div>
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 to-purple-500"></div>
                    
                    <h3 class="text-lg font-semibold text-gray-300 mb-4"><?php echo $proPlan['nome']; ?></h3>
                    <div class="flex items-baseline mb-8">
                        <span class="text-5xl font-extrabold tracking-tight text-white">R$ <?php echo number_format($proPlan['preco'], 2, ',', '.'); ?></span>
                        <span class="text-gray-400 ml-1">/mês</span>
                    </div>
                    <ul class="space-y-4 mb-8 flex-1">
                         <li class="flex items-start"><i class="fas fa-star text-yellow-400 mt-1 mr-3 text-sm"></i><span class="text-gray-300 text-sm">Recursos Ilimitados*</span></li>
                         <li class="flex items-start"><i class="fas fa-check text-gray-400 mt-1 mr-3 text-sm"></i><span class="text-gray-300 text-sm">Até <?php echo $proPlan['limite_membros']; ?> membros</span></li>
                         <li class="flex items-start"><i class="fas fa-check text-gray-400 mt-1 mr-3 text-sm"></i><span class="text-gray-300 text-sm">Até <?php echo $proPlan['limite_filiais']; ?> filiais</span></li>
                         <li class="flex items-start"><i class="fas fa-check text-gray-400 mt-1 mr-3 text-sm"></i><span class="text-gray-300 text-sm text-purple-400 font-bold">Doações PIX + Uploads</span></li>
                         <li class="flex items-start"><i class="fas fa-headset text-gray-400 mt-1 mr-3 text-sm"></i><span class="text-gray-300 text-sm">Suporte Prioritário</span></li>
                    </ul>
                    <a href="checkout_stripe.php?plan_id=<?php echo $proPlan['id']; ?>" class="block w-full py-4 px-6 text-center rounded-xl font-bold transition duration-200 bg-white text-black hover:bg-gray-200 shadow-[0_0_20px_rgba(255,255,255,0.3)] transform hover:-translate-y-1">
                        Assinar Pro
                    </a>
                </div>

                <!-- CUSTOM CARD -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 flex flex-col hover:shadow-xl transition duration-300 relative group">
                     <div class="absolute top-0 right-0 -mt-3 mr-4 bg-gradient-to-r from-purple-500 to-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide shadow-md">
                        Personalizado
                    </div>
                    <h3 class="text-lg font-semibold text-gray-600 mb-2">Pro Personalizado</h3>
                    <p class="text-gray-400 text-xs mb-6">Monte seu plano ideal partindo do Pro.</p>
                    
                    <!-- Calculator -->
                    <div class="flex-1 space-y-6">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>Membros Extras</span>
                                <span class="font-bold text-purple-600" id="membros-val">0</span>
                            </div>
                            <input type="range" id="membros-slider" min="0" max="2000" step="100" value="0" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-purple-600">
                            <div class="text-xs text-gray-400 mt-1">+ R$ 0,30/membro</div>
                        </div>

                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>Filiais Extras</span>
                                <span class="font-bold text-blue-600" id="filiais-val">0</span>
                            </div>
                            <input type="range" id="filiais-slider" min="0" max="50" step="1" value="0" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600">
                             <div class="text-xs text-gray-400 mt-1">+ R$ 12,90/filial</div>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-gray-100 pt-4">
                        <div class="text-xs text-gray-400 mb-1">Total Estimado</div>
                        <div class="flex items-baseline mb-4">
                            <span class="text-4xl font-extrabold tracking-tight text-gray-900" id="total-price">R$ <?php echo number_format($proPlan['preco'], 2, ',', '.'); ?></span>
                            <span class="text-gray-500 ml-1">/mês</span>
                        </div>
                         <p class="text-xs text-gray-400 mb-4">
                            Base Pro (R$ <?php echo number_format($proPlan['preco'], 2, ',', '.'); ?>) + Extras
                        </p>
                        
                        <a id="custom-checkout-btn" href="checkout_stripe.php?plan_id=<?php echo $proPlan['id']; ?>" class="block w-full py-4 px-6 text-center rounded-xl font-bold transition duration-200 bg-gray-900 text-white hover:bg-black shadow-md transform group-hover:-translate-y-1">
                            Contratar Personalizado
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- FAQ Section (New) -->
    <section id="faq" class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4">
            <h2 class="text-3xl font-extrabold text-center text-gray-900 mb-12">Perguntas Frequentes</h2>
            
            <div class="space-y-6">
                <div class="border-b border-gray-100 pb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">O plano gratuito é realmente grátis?</h3>
                    <p class="text-gray-600">Sim! Você pode usar o plano gratuito para sempre, limitado a 30 membros e 1 filial. Não pedimos cartão de crédito.</p>
                </div>
                <div class="border-b border-gray-100 pb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Posso cancelar a qualquer momento?</h3>
                    <p class="text-gray-600">Claro. Nossos planos Pro não possuem fidelidade. Você pode cancelar ou mudar de plano na sua área administrativa quando quiser.</p>
                </div>
                 <div class="border-b border-gray-100 pb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Preciso instalar algum software?</h3>
                    <p class="text-gray-600">Não. O ChurchDigital é 100% online. Você acessa pelo navegador do se computador ou celular.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="flex items-center justify-center gap-2 mb-6 text-white">
                <i class="fas fa-church text-2xl"></i>
                <span class="font-bold text-xl">ChurchDigital</span>
            </div>
            <p class="mb-4 text-sm">&copy; <?php echo date('Y'); ?> ChurchDigital. Todos os direitos reservados.</p>
            <div class="flex justify-center gap-6 flex-wrap">
                <a href="manual_membro.php" class="hover:text-white transition font-bold text-gray-300">Manual do Membro</a>
                <a href="manual_tesoureiro.php" class="hover:text-white transition font-bold text-gray-300">Manual do Tesoureiro</a>
                <a href="#" class="hover:text-white transition">Termos de Uso</a>
                <a href="#" class="hover:text-white transition">Privacidade</a>
                <a href="#" class="hover:text-white transition">Contato</a>
            </div>
        </div>
    </footer>

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
    <a href="https://wa.me/5511949216688" target="_blank" class="fixed bottom-6 right-6 z-50 bg-[#25D366] text-white w-14 h-14 rounded-full shadow-lg hover:bg-[#128C7E] hover:scale-110 transition duration-300 flex items-center justify-center animate-bounce">
        <i class="fab fa-whatsapp text-3xl"></i>
    </a>

</body>
</html>
