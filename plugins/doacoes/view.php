<?php
// plugins/doacoes/view.php

$igreja_id = TenantScope::getId();
$filial_id = $_SESSION['user_filial_id'] ?? null; // Se logado como membro, pode ter filial

// Buscar Chaves
// Mostra chaves da Matriz (Global) E chaves específicas da filial do membro
$sql = "
    SELECT p.*, i.nome as filial_nome 
    FROM pix_keys p
    LEFT JOIN igrejas i ON p.filial_id = i.id
    WHERE p.igreja_id = ? 
    AND (p.filial_id IS NULL OR p.filial_id = ?)
    ORDER BY p.filial_id DESC, p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$igreja_id, $filial_id ?: 0]); // 0 se não tiver filial, para não quebrar a query
$keys = $stmt->fetchAll();
?>

<div class="fade-in pb-20 max-w-lg mx-auto">
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800 flex items-center justify-center gap-2">
            <i class="fas fa-hand-holding-heart text-red-500"></i> Doações & Dízimos
        </h2>
        <?php if(!PlanEnforcer::canUseFeature($pdo, 'pix_module')): ?>
             <span class="inline-block mt-2 bg-gray-200 text-gray-600 px-2 py-1 rounded text-xs">Modo Demonstração</span>
        <?php endif; ?>
    </div>

    <?php if(!PlanEnforcer::canUseFeature($pdo, 'pix_module')): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 text-sm text-center">
            <p class="font-bold text-yellow-700">Atenção</p>
            <p class="text-yellow-600">O módulo de Doações via PIX não está ativo neste plano. Abaixo apenas uma demonstração visual.</p>
        </div>
    <?php endif; ?>

    <div class="space-y-6">
        <?php if (count($keys) > 0): ?>
            <?php foreach($keys as $k): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition transform hover:-translate-y-1">
                    <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-4 text-white flex justify-between items-center">
                        <i class="fas fa-qrcode text-2xl opacity-50"></i>
                        <span class="text-xs font-bold uppercase tracking-widest bg-white bg-opacity-20 px-2 py-1 rounded">PIX</span>
                    </div>
                    
                    <div class="p-6 text-center">
                        <p class="text-gray-500 text-sm mb-1 uppercase tracking-wide"><?php echo $k['tipo']; ?></p>
                        <h3 class="text-lg text-gray-800 break-all mb-2"><?php echo $k['chave']; ?></h3>
                        <p class="text-sm text-gray-500 mb-6">Titular: <strong class="text-gray-700"><?php echo $k['titular']; ?></strong></p>
                        
                        <!-- QRCodeJS Generation -->
                        <div id="qrcode-<?php echo $k['id']; ?>" class="flex justify-center mb-6"></div>

                        <button onclick="copiarChave('<?php echo $k['chave']; ?>')" class="w-full bg-blue-50 text-blue-600 font-bold py-3 rounded-xl hover:bg-blue-100 transition flex items-center justify-center gap-2">
                            <i class="far fa-copy"></i> Copiar Chave
                        </button>
                    </div>
                    
                    <?php if($k['filial_nome']): ?>
                        <div class="bg-gray-50 px-4 py-2 text-center border-t border-gray-100">
                            <span class="text-xs text-gray-400">Exclusivo: <?php echo $k['filial_nome']; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-12 bg-white rounded-xl border border-dashed">
                <i class="fas fa-hand-holding-heart text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-500">Nenhuma chave de doação cadastrada no momento.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- QRCode Lib (CDN) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        <?php foreach($keys as $k): ?>
            new QRCode(document.getElementById("qrcode-<?php echo $k['id']; ?>"), {
                text: "<?php echo $k['chave']; ?>", 
                width: 128,
                height: 128,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        <?php endforeach; ?>

        function copiarChave(chave) {
            navigator.clipboard.writeText(chave).then(function() {
                alert('Chave copiada: ' + chave);
            }, function(err) {
                console.error('Erro ao copiar', err);
            });
        }
    </script>

    <!-- Instruções -->
    <div class="mt-8 bg-blue-50 rounded-xl p-6 border border-blue-100 flex gap-4 items-start text-left">
        <i class="fas fa-info-circle text-blue-500 text-xl mt-1"></i>
        <div>
            <h4 class="font-bold text-blue-800">Como doar?</h4>
            <p class="text-sm text-blue-700 mt-1">
                1. Abra o app do seu banco.<br>
                2. Escolha a opção <strong>PIX</strong> > <strong>Pagar</strong>.<br>
                3. Leia o QR Code acima ou escolha <strong>Chave PIX</strong> e cole a chave copiada.<br>
                4. Confira o nome do titular antes de confirmar.
            </p>
        </div>
    </div>
</div>
