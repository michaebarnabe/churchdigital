<?php
// plugins/configuracoes/tab_assinatura.php
// Ensure $igreja_id is available (from parent view)
if (!isset($igreja_id)) {
    return;
}

// Fetch Current Plan & Extras
$sub = $pdo->prepare("
    SELECT a.*, p.nome as plano_nome, p.preco, p.limite_membros as base_membros, p.limite_filiais as base_filiais,
            p.preco_extra_membro, p.preco_extra_filial
    FROM assinaturas a 
    JOIN planos p ON a.plano_id = p.id 
    WHERE a.igreja_id = ?
    LIMIT 1
");
$sub->execute([$igreja_id]);
$subscription = $sub->fetch();

if (!$subscription) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded'>Erro: Assinatura não encontrada. Contate o suporte.</div>";
    return; 
}

$totalMembros = $subscription['base_membros'] + $subscription['extra_membros'];
$totalFiliais = $subscription['base_filiais'] + $subscription['extra_filiais'];

// Check if Stripe (Pro Plan)
$isStripe = !empty($subscription['stripe_subscription_id']);
// Check if Plan is Paid/Pro (Price > 0)
$isPro = $subscription['preco'] > 0;
?>

<div class="space-y-6 animate-fade-in-down">
    
    <!-- Header / Status -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-bold text-xl text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-file-signature text-primary"></i> 
            Sua Assinatura
        </h3>
        
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <div>
                <p class="text-3xl font-extrabold text-gray-900"><?php echo htmlspecialchars($subscription['plano_nome']); ?></p>
                <p class="text-gray-500 mt-1">
                    <?php if ($isStripe): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Ativo
                        </span>
                        Assinatura com Renovação Automática
                    <?php elseif ($isPro): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            Ativo
                        </span>
                        Plano Profissional
                    <?php else: ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            Gratuito
                        </span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="text-right">
                <p class="text-2xl font-bold text-gray-900">
                    R$ <?php echo number_format($subscription['preco'], 2, ',', ''); ?> <span class="text-sm text-gray-500 font-normal">/mês</span>
                </p>

                <!-- Action Button -->
                <div class="mt-4">
                    <?php if ($isStripe): ?>
                        <button onclick="document.getElementById('extrasModal').classList.remove('hidden')" class="bg-black text-white px-6 py-2 rounded-lg font-bold hover:bg-gray-800 transition shadow-lg flex items-center gap-2">
                            <i class="fas fa-sliders-h"></i> Gerenciar Extras
                        </button>
                    <?php elseif ($isPro): ?>
                        <!-- Pro Manual - No Extras Management via Stripe -->
                        <button disabled class="bg-gray-100 text-gray-500 px-6 py-2 rounded-lg font-bold cursor-not-allowed flex items-center gap-2">
                            <i class="fas fa-check-circle"></i> Plano Ativo
                        </button>
                    <?php else: ?>
                        <a href="pricing.php" class="inline-block bg-primary text-white px-6 py-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-lg animate-pulse flex items-center gap-2">
                            <i class="fas fa-rocket"></i> Fazer Upgrade Agora
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($isStripe): ?>
            <div class="mt-6 p-4 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-800">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Atenção:</strong> Ao contratar extras, você está renovando o <strong>PLANO PRO</strong>, mesmo que ainda esteja em vigência. O valor será ajustado proporcionalmente na sua próxima fatura.
            </div>
        <?php elseif ($isPro): ?>
             <div class="mt-6 p-4 bg-green-50 border border-green-100 rounded-lg text-sm text-green-800">
                <i class="fas fa-check mr-2"></i>
                Você está aproveitando todos os benefícios do <strong>Plano PRO</strong>. Para ajustes na assinatura, contate o suporte.
            </div>
        <?php else: ?>
             <div class="mt-6 p-4 bg-yellow-50 border border-yellow-100 rounded-lg text-sm text-yellow-800">
                <i class="fas fa-star mr-2"></i>
                Precisa de mais membros ou filiais? <strong>Faça o Upgrade para o Plano PRO</strong> e libere recursos ilimitados!
            </div>
        <?php endif; ?>
    </div>

    <!-- Usage Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Membros -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-5 w-32 h-32 -mr-8 -mt-8 bg-blue-500 rounded-full"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xl">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-500 uppercase text-xs tracking-wider">Membros</h4>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalMembros; ?> <span class="text-sm font-normal text-gray-400">total</span></p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-50 text-xs text-gray-500 flex justify-between">
                <span>Base: <strong><?php echo $subscription['base_membros']; ?></strong></span>
                <span>Extras: <strong class="text-blue-600">+<?php echo $subscription['extra_membros']; ?></strong></span>
            </div>
        </div>

        <!-- Filiais -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-5 w-32 h-32 -mr-8 -mt-8 bg-purple-500 rounded-full"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xl">
                    <i class="fas fa-code-branch"></i>
                </div>
                <div>
                    <h4 class="font-bold text-gray-500 uppercase text-xs tracking-wider">Filiais</h4>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalFiliais; ?> <span class="text-sm font-normal text-gray-400">total</span></p>
                </div>
            </div>
            <div class="mt-4 pt-4 border-t border-gray-50 text-xs text-gray-500 flex justify-between">
                <span>Base: <strong><?php echo $subscription['base_filiais']; ?></strong></span>
                <span>Extras: <strong class="text-purple-600">+<?php echo $subscription['extra_filiais']; ?></strong></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Gerenciar Extras (Somente Stripe) -->
<?php if ($isStripe): ?>
<div id="extrasModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-80 flex items-center justify-center backdrop-blur-sm z-50">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in-down">
        <form method="POST" action="plugins/configuracoes/update_subscription.php">
            <div class="p-6 border-b bg-gray-50 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">Ajustar Extras</h3>
                    <p class="text-sm text-gray-500">Aumente sua capacidade instantaneamente.</p>
                </div>
                <button type="button" onclick="document.getElementById('extrasModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-8 space-y-8">
                <!-- Membros Slider -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="font-bold text-gray-700">Membros Extras</label>
                        <span class="text-sm font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded">R$ <?php echo number_format($subscription['preco_extra_membro'], 2, ',', '.'); ?> / un</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <input type="range" name="extra_membros" min="0" max="5000" step="50" value="<?php echo $subscription['extra_membros']; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-black" oninput="updateCost()">
                        <input type="number" id="inputMembros" value="<?php echo $subscription['extra_membros']; ?>" class="w-20 p-2 border rounded text-center font-bold" readonly>
                    </div>
                </div>

                <!-- Filiais Slider -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="font-bold text-gray-700">Filiais Extras</label>
                        <span class="text-sm font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded">R$ <?php echo number_format($subscription['preco_extra_filial'], 2, ',', '.'); ?> / un</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <input type="range" name="extra_filiais" min="0" max="50" step="1" value="<?php echo $subscription['extra_filiais']; ?>" class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-black" oninput="updateCost()">
                        <input type="number" id="inputFiliais" value="<?php echo $subscription['extra_filiais']; ?>" class="w-20 p-2 border rounded text-center font-bold" readonly>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 text-center">
                    <p class="text-sm font-bold text-gray-700 mb-1">Novo Valor Mensal Estimado:</p>
                    <p class="text-3xl font-extrabold text-gray-900">R$ <span id="newTotal">0,00</span></p>
                    <p class="text-xs text-gray-500 mt-2">Ao confirmar, o valor será atualizado na sua assinatura.</p>
                </div>
            </div>

            <div class="p-6 bg-gray-50 border-t flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('extrasModal').classList.add('hidden')" class="px-6 py-3 rounded-xl font-bold text-gray-600 hover:bg-gray-200 transition">Cancelar</button>
                <button type="submit" class="px-6 py-3 rounded-xl font-bold bg-black text-white hover:bg-gray-800 transition shadow-lg transform hover:scale-105">
                    Confirmar Alterações
                </button>
            </div>

            <!-- Hidden Prices for JS -->
            <input type="hidden" id="basePrice" value="<?php echo $subscription['preco']; ?>">
            <input type="hidden" id="priceMembro" value="<?php echo $subscription['preco_extra_membro']; ?>">
            <input type="hidden" id="priceFilial" value="<?php echo $subscription['preco_extra_filial']; ?>">
        </form>
    </div>
</div>

<script>
    function updateCost() {
        const eMembros = parseInt(document.querySelector('input[name="extra_membros"]').value) || 0;
        const eFiliais = parseInt(document.querySelector('input[name="extra_filiais"]').value) || 0;
        
        document.getElementById('inputMembros').value = eMembros;
        document.getElementById('inputFiliais').value = eFiliais;

        const base = parseFloat(document.getElementById('basePrice').value) || 0;
        const pMem = parseFloat(document.getElementById('priceMembro').value) || 0;
        const pFil = parseFloat(document.getElementById('priceFilial').value) || 0;

        const total = base + (eMembros * pMem) + (eFiliais * pFil);
        
        document.getElementById('newTotal').innerText = total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    // Init on load
    document.addEventListener('DOMContentLoaded', updateCost);
    // Init immediate (in case DOM already loaded)
    updateCost();
</script>
<?php endif; ?>
