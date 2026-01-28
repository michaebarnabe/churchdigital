<?php
require_once 'config.php';
define('ABSPATH', true); // Security flag
require_once 'includes/auth.php';
require_once 'includes/TenantScope.php';
require_once 'includes/PlanEnforcer.php';
require_once 'includes/functions.php';

// --- ROTEAMENTO INTELIGENTE (PWA vs WEB) ---
if (!is_logged_in()) {
    // 1. Se for PWA (via manifest start_url) ou App Nativo -> Login
    if (isset($_GET['mode']) && $_GET['mode'] === 'pwa') {
        header('Location: login.php');
        exit;
    }
    
    // 2. Se for Visitante Web (Padrão) -> Landing Page
    // Evita loop infinito se pricing.php usar require_login (não deve usar)
    // Redireciona para LP
    header('Location: pricing.php');
    exit;
}

// require_login(); // Redundante agora, mas mantido por segurança em outros pontos
// require_login(); 
// Melhor remover o require_login() padrão e confiar na verificação acima, 
// mas para garantir que $user_id esteja setado para o resto do script:
// O is_logged_in() já confere a sessão.
// A execução continua abaixo apenas se logado.

// --- FORCE PASSWORD CHANGE ---
if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1) {
    if ((!isset($_GET['page']) || $_GET['page'] !== 'perfil')) {
        header("Location: index.php?page=perfil");
        exit;
    }
}

load_plugins(); // Carrega plugins para popular o menu

// --- CONTEXT SWITCHER HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'switch_tenant' && isset($_GET['id'])) {
    if (TenantScope::switchTenant($pdo, $_GET['id'])) {
        header("Location: index.php"); // Refresh to apply
        exit;
    }
}

// REDIRECIONAMENTO DE MEMBROS
// Se for membro e tentar acessar a home (dashboard), vai para carteirinha
// REDIRECIONAMENTO (REMOVIDO: Membros agora acessam o dashboard)
$page = $_GET['page'] ?? 'dashboard';


// Include Header
// Include Header
include 'templates/header.php';

    if ($page === 'dashboard') {
        $igreja_id = $_SESSION['igreja_id'];
        $is_admin_fin = has_role('admin') || has_role('tesoureiro');
        $is_staff = has_role('admin') || has_role('tesoureiro') || has_role('secretario');
        
        // --- QUERY: NEXT EVENTS ---
        try {
            $stmtEvt = $pdo->prepare("SELECT * FROM eventos WHERE igreja_id = ? AND data_inicio >= CURDATE() ORDER BY data_inicio ASC LIMIT 3");
            $stmtEvt->execute([$igreja_id]);
            $next_events = $stmtEvt->fetchAll();
        } catch(Exception $e) { $next_events = []; }

        // --- QUERY: FINANCIALS (Only Admin/Tesoureiro) ---
        $saldo = 0; $total_dizimos = 0; $total_ofertas = 0; $total_saidas = 0;
        
        if ($is_admin_fin) {
            try {
                // Saldo
                $stmt = $pdo->prepare("
                    SELECT 
                        SUM(CASE WHEN tipo IN ('dizimo', 'oferta') THEN valor ELSE 0 END) - 
                        SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saldo 
                    FROM financeiro_basico 
                    WHERE igreja_id = ?
                ");
                $stmt->execute([$igreja_id]);
                $saldo = $stmt->fetchColumn() ?: 0.00;

                // Gráfico
                $stmt = $pdo->prepare("SELECT tipo, SUM(valor) as total FROM financeiro_basico WHERE igreja_id = ? GROUP BY tipo");
                $stmt->execute([$igreja_id]);
                $fin_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $total_dizimos = $fin_data['dizimo'] ?? 0;
                $total_ofertas = $fin_data['oferta'] ?? 0;
                $total_saidas = $fin_data['saida'] ?? 0;
            } catch (PDOException $e) {}
        }

        // Total Membros (Visible to all Staff)
        $total_membros = 0;
        if ($is_staff) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM membros WHERE igreja_id = ?");
            $stmt->execute([$igreja_id]);
            $total_membros = $stmt->fetchColumn();
        }
    ?>
    
    <!-- Chart.js CDN (Only load if needed) -->
    <?php if ($is_admin_fin): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>

    <!-- Dashboard Content -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 fade-in pb-20">
        
        <!-- Welcome Card -->
        <div class="bg-gradient-to-r from-primary to-secondary rounded-xl shadow-lg p-6 md:col-span-2 lg:col-span-3 text-white flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold">Olá, <?php echo e($_SESSION['user_name']); ?>!</h2>
                <p class="opacity-90 text-sm">Bem-vindo ao painel da <?php echo e($tenant['nome']); ?>.</p>
            </div>
            <i class="fas fa-church text-4xl opacity-20"></i>
        </div>

        <!-- PWA INSTALL CARD (Android/Chrome) -->
        <div id="pwa-install-card" class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl shadow-lg p-5 md:col-span-2 lg:col-span-3 text-white hidden relative overflow-hidden transition-all duration-500">
            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-3 rounded-full backdrop-blur-sm">
                        <i class="fas fa-mobile-alt text-2xl"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">Instalar Aplicativo</h3>
                        <p class="text-sm opacity-90">Tenha acesso rápido e notificações direto no seu celular.</p>
                    </div>
                </div>
                <button id="pwa-install-btn" class="bg-white text-purple-700 font-bold py-2 px-6 rounded-full shadow-md hover:bg-gray-100 transition transform hover:scale-105 active:scale-95 w-full md:w-auto text-center">
                    📲 Instalar aplicativo
                </button>
            </div>
            <!-- Decorative circle -->
            <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
        </div>

        <!-- iOS INSTALL CARD (Safari) -->
        <div id="ios-install-card" class="bg-gray-800 rounded-xl shadow-lg p-5 md:col-span-2 lg:col-span-3 text-white hidden relative border border-gray-700">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                <div class="bg-gray-700 p-3 rounded-lg shrink-0">
                    <i class="fab fa-apple text-2xl text-gray-300"></i>
                </div>
                <div class="flex-grow">
                    <h3 class="font-bold text-lg mb-1">Instalar no iPhone/iPad</h3>
                    <p class="text-sm text-gray-300 mb-2">Para instalar este aplicativo e ter uma experiência melhor:</p>
                    <ol class="text-sm text-gray-300 space-y-1 ml-4 list-decimal">
                        <li>Toque no botão <strong>Compartilhar</strong> <i class="fas fa-share-square mx-1 text-blue-400"></i> abaixo na barra do navegador.</li>
                        <li>Role e selecione <strong>Adicionar à Tela de Início</strong> <i class="fas fa-plus-square mx-1"></i>.</li>
                    </ol>
                </div>
                <button onclick="document.getElementById('ios-install-card').style.display='none'" class="absolute top-2 right-2 text-gray-500 hover:text-white p-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- 1. MÓDULO: MEMBROS (Only Staff) -->
        <?php if ($is_staff): ?>
            <div class="bg-white rounded-xl shadow p-5 border-l-4 border-blue-500 hover:shadow-md transition cursor-pointer" onclick="window.location='index.php?page=membros'">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider">Membros</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1"><?php echo $total_membros; ?></p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-full text-blue-500">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-blue-500 font-bold mt-3">Gerenciar Membros <i class="fas fa-arrow-right ml-1"></i></p>
            </div>
        <?php endif; ?>

        <!-- 2. MÓDULO: FINANCEIRO (Restricted) -->
        <?php if ($is_admin_fin): ?>
            <div class="bg-white rounded-xl shadow p-5 border-l-4 border-green-500 hover:shadow-md transition cursor-pointer" onclick="window.location='index.php?page=financeiro'">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider">Saldo em Caixa</p>
                        <p class="text-2xl font-bold text-gray-800 mt-1">R$ <?php echo number_format($saldo, 2, ',', '.'); ?></p>
                    </div>
                    <div class="bg-green-50 p-3 rounded-full text-green-500">
                        <i class="fas fa-wallet text-xl"></i>
                    </div>
                </div>
                <p class="text-xs text-green-500 font-bold mt-3">Ver Extrato Completo <i class="fas fa-arrow-right ml-1"></i></p>
            </div>

            <!-- Gráfico (Full Row on Mobile, 1 col on Desktop) -->
            <div class="bg-white rounded-xl shadow p-5 md:row-span-2">
                <h3 class="font-bold text-gray-700 mb-4 text-sm uppercase">Visão Mensal</h3>
                <div class="flex flex-col items-center">
                    <div class="relative h-40 w-40">
                        <canvas id="financeChart"></canvas>
                    </div>
                    <!-- Mini Legenda -->
                    <div class="mt-4 w-full text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded bg-blue-500"></span> Dízimos</span>
                            <span class="font-bold">R$ <?php echo number_format($total_dizimos, 2, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded bg-green-600"></span> Ofertas</span>
                            <span class="font-bold">R$ <?php echo number_format($total_ofertas, 2, ',', '.'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded bg-red-600"></span> Saídas</span>
                            <span class="font-bold">R$ <?php echo number_format($total_saidas, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 3. MÓDULO: PRÓXIMOS EVENTOS -->
        <div class="bg-white rounded-xl shadow p-5 md:col-span-2">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-700 text-sm uppercase">Próximos Eventos</h3>
                <a href="index.php?page=agenda" class="text-xs text-primary font-bold hover:underline">Ver Agenda Completa</a>
            </div>
            
            <?php if (count($next_events) > 0): ?>
                <div class="space-y-3">
                    <?php foreach ($next_events as $ev): 
                        $d = date('d', strtotime($ev['data_inicio']));
                        $m = substr(date('F', strtotime($ev['data_inicio'])), 0, 3); // Simplificado
                        $h = date('H:i', strtotime($ev['data_inicio']));
                        $colorClass = 'border-' . ($ev['cor'] == 'blue' ? 'blue-500' : 'primary'); // Fallback logic
                    ?>
                        <div class="flex items-center bg-gray-50 rounded-lg p-3 border-l-4 <?php echo $colorClass; ?>">
                            <div class="bg-white px-3 py-1 rounded border border-gray-200 text-center mr-4">
                                <span class="block text-xl font-black text-gray-800 leading-none"><?php echo $d; ?></span>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800 text-sm"><?php echo e($ev['titulo']); ?></h4>
                                <p class="text-xs text-gray-500"><i class="far fa-clock mr-1"></i> <?php echo $h; ?> • <?php echo e($ev['local']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-400 text-sm text-center py-4">Nenhum evento próximo.</p>
            <?php endif; ?>
        </div>

        <!-- 4. GRID MENU PRINCIPAL (Substitui Footer Items) -->
        <div class="md:col-span-3">
            <h3 class="font-bold text-gray-700 mb-3 text-sm uppercase">Menu Principal</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
                
                <a href="index.php?page=agenda" class="flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                    <div class="w-10 h-10 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span class="text-sm font-bold text-gray-700">Agenda</span>
                </a>

                <a href="index.php?page=aniversariantes" class="flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                    <div class="w-10 h-10 rounded-full bg-pink-50 text-pink-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <span class="text-xs font-bold text-gray-700 text-center">Aniversariantes</span>
                </a>

                <?php if ($is_admin_fin): ?>
                    <a href="index.php?page=relatorios" class="flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                        <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <i class="fas fa-print"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Relatórios</span>
                    </a>
                <?php endif; ?>

                <?php if (has_role('admin')): ?>
                    <a href="index.php?page=configuracoes" class="flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                        <div class="w-10 h-10 rounded-full bg-gray-50 text-gray-600 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Ajustes</span>
                    </a>
                    
                    <a href="index.php?page=usuarios" class="flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <span class="text-sm font-bold text-gray-700">Equipe</span>
                    </a>
                <?php endif; ?>

                <a href="index.php?page=doacoes" class="flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                    <div class="w-10 h-10 rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                        <i class="fas fa-heart"></i>
                    </div>
                    <span class="text-sm font-bold text-gray-700">Ofertas</span>
                </a>

            </div>
            
            <!-- Dynamic Plugins Menu -->
            <?php if (count($global_menu_items) > 0): ?>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mt-4">
                    <?php foreach ($global_menu_items as $item): ?>
                        <?php 
                            // Check Role Access
                            if ($item['role'] && !has_role($item['role'])) continue;
                            
                            $isPatrimonio = (strpos($item['label'], 'Patrimônio') !== false);
                        ?>
                        <a href="<?php echo $item['url']; ?>" <?php if($isPatrimonio): ?>onclick="localStorage.setItem('clicked_patrimonio', 'true');"<?php endif; ?> class="relative flex flex-col items-center justify-center p-4 bg-white shadow-sm hover:shadow-md rounded-xl transition group">
                            <div class="w-10 h-10 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center mb-2 group-hover:scale-110 transition">
                                <i class="fas <?php echo $item['icon']; ?>"></i>
                            </div>
                            <span class="text-sm font-bold text-gray-700"><?php echo $item['label']; ?></span>
                            
                            <?php if($isPatrimonio): ?>
                                <span id="badge-patrimonio" class="absolute top-2 right-2 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full animate-pulse hidden">NOVO</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <!-- Badge Logic -->
                <script>
                    if (!localStorage.getItem('clicked_patrimonio')) {
                        const badge = document.getElementById('badge-patrimonio');
                        if (badge) badge.classList.remove('hidden');
                    }
                </script>
            <?php endif; ?>
        </div>

    </div>

    <!-- Script do Gráfico -->
    <script>
        const ctx = document.getElementById('financeChart').getContext('2d');
        const financeChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Dízimos', 'Ofertas', 'Saídas'],
                datasets: [{
                    label: 'Valores (R$)',
                    data: [<?php echo $total_dizimos; ?>, <?php echo $total_ofertas; ?>, <?php echo $total_saidas; ?>],
                    backgroundColor: [
                        '#3b82f6', // blue-500
                        '#16a34a', // green-600
                        '#dc2626'  // red-600
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
    
    <?php
} else {
    // --- CARREGAMENTO DE PLUGINS ---
    $plugin_name = preg_replace('/[^a-z0-9_]/', '', $page);
    $plugin_view = __DIR__ . "/plugins/$plugin_name/view.php";
    
    // --- ACL (Controle de Acesso) ---
    // Verifica se o plugin requisitado tem restrição de acesso registrada no menu
    $access_allowed = true;
    
    foreach ($global_menu_items as $item) {
        // Verifica simples se a URL do item contém o parâmetro da página atual
        if (strpos($item['url'], "page=$plugin_name") !== false) {
            if (!has_role($item['role'])) {
                $access_allowed = false;
            }
            break; 
        }
    }

    if (!$access_allowed) {
        echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 m-4 rounded shadow'>";
        echo "<p class='font-bold'><i class='fas fa-lock'></i> Acesso Negado</p>";
        echo "<p>Você não tem permissão para acessar este módulo.</p>";
        echo "</div>";
    } elseif (file_exists($plugin_view)) {
        include $plugin_view;
    } else {
        echo "<div class='bg-yellow-100 text-yellow-700 p-4 rounded m-4'>Página não encontrada ou módulo indisponível.</div>";
    }
}

?>
    <!-- PWA Logic Script -->
    <script>
        // 1. Android / Desktop (Chrome/Edge) Installation Logic
        let deferredPrompt;
        const pwaCard = document.getElementById('pwa-install-card');
        const installBtn = document.getElementById('pwa-install-btn');

        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later.
            deferredPrompt = e;
            // Update UI notify the user they can install the PWA
            console.log('PWA: Install Prompt intercepted');
            if(pwaCard) pwaCard.classList.remove('hidden');
        });

        if(installBtn) {
            installBtn.addEventListener('click', async () => {
                // Hide the app provided install promotion
                if(pwaCard) pwaCard.classList.add('hidden');
                // Show the install prompt
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    // Wait for the user to respond to the prompt
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log(`PWA: User response to install prompt: ${outcome}`);
                    // We've used the prompt, and can't use it again, throw it away
                    deferredPrompt = null;
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            // Hide the app-provided install promotion
            if(pwaCard) pwaCard.classList.add('hidden');
            // Clear the deferredPrompt so it can be garbage collected
            deferredPrompt = null;
            console.log('PWA: App was installed.');
        });

        // 2. iOS Installation Instruction Logic
        // Detects if it's an iOS device AND NOT in standalone mode
        const isIos = /iphone|ipad|ipod/.test( window.navigator.userAgent.toLowerCase() );
        const isInStandaloneMode = ('standalone' in window.navigator) && (window.navigator.standalone);
        
        // Also check if display-mode is standalone (Generic)
        const isGenericStandalone = window.matchMedia('(display-mode: standalone)').matches;

        if (isIos && !isInStandaloneMode && !isGenericStandalone) {
             const iosCard = document.getElementById('ios-install-card');
             // Show iOS card
             if(iosCard) iosCard.classList.remove('hidden');
        }

        // 3. General Cleanup if running as App
        if (isInStandaloneMode || isGenericStandalone) {
            if(pwaCard) pwaCard.classList.add('hidden'); // Ensure hidden
            const iosCard = document.getElementById('ios-install-card');
            if(iosCard) iosCard.classList.add('hidden');
        }
    </script>

<?php
// Include Footer
include 'templates/footer.php'; 
?>
