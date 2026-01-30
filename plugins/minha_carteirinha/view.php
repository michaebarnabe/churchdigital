<?php
// plugins/minha_carteirinha/view.php

// Apenas membros podem ver isso
// Se for staff (admin), pode ver também para debugging
// Apenas membros podem ver isso
// Se for staff (admin/tesoureiro) vinculado, pode ver também
if (has_role('Membro') || !empty($_SESSION['membro_id'])) {
    // Busca dados atualizados do membro
    if (!empty($_SESSION['membro_id'])) {
         // Staff vinculado ou Membro com ID explícito
        $membro_id = $_SESSION['membro_id'];
    } elseif (has_role('Membro')) {
        // Membro logado diretamente (sem vínculo staff)
        $membro_id = $_SESSION['user_id'];
    } else {
        // Staff without link trying to access - Redirect
        echo "<script>window.location.href='index.php?page=membros';</script>";
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
    $stmt->execute([$membro_id]);
    $membro = $stmt->fetch();

    $igrejastmt = $pdo->prepare("SELECT * FROM igrejas WHERE id = ?");
    $igrejastmt->execute([$membro['igreja_id']]);
    $igreja = $igrejastmt->fetch();

    // HIERARCHY LOGIC FOR ID CARD
    if ($igreja['parent_id']) {
        // It is a Branch (Filial)
        // Fetch Matrix Data
        $stmtMatriz = $pdo->prepare("SELECT * FROM igrejas WHERE id = ?");
        $stmtMatriz->execute([$igreja['parent_id']]);
        $matriz = $stmtMatriz->fetch();
        
        $display_church_name = $matriz['nome']; // Always Matrix Name
        $display_logo = $matriz['logo_url'];    // Always Matrix Logo
        $display_branch_name = $igreja['nome']; // Branch Name
    } else {
        // It is the Matrix (Sede)
        $display_church_name = $igreja['nome'];
        $display_logo = $igreja['logo_url'];
        $display_branch_name = "Sede";
    }

} else {
    echo "Acesso restrito.";
    exit;
}
?>

<div class="flex flex-col items-center justify-center min-h-[60vh] p-4 fade-in">
    
    <!-- ID Card Container -->
    <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl overflow-hidden border border-gray-100 relative">
        
        <!-- Watermark (Marca D'água) -->
        <?php if (!empty($display_logo)): ?>
            <div class="absolute inset-0 z-0 flex items-center justify-center opacity-10 pointer-events-none">
                <img src="<?php echo e($display_logo); ?>" class="w-64 h-64 object-contain grayscale">
            </div>
        <?php endif; ?>

        <!-- Header Background -->
        <div class="h-24 bg-gradient-to-r from-primary to-secondary relative">
            <div class="absolute -bottom-10 left-1/2 transform -translate-x-1/2">
                <div class="w-24 h-24 rounded-full border-4 border-white bg-gray-200 overflow-hidden shadow-md">
                    <?php if (!empty($membro['foto'])): ?>
                        <img src="<?php echo e($membro['foto']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gray-300 text-gray-500 text-3xl font-bold">
                            <?php echo strtoupper(substr($membro['nome'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="pt-12 pb-6 px-6 text-center">
            
            <h2 class="text-xl font-bold text-gray-800 leading-tight mb-1"><?php echo e($membro['nome']); ?></h2>
            <p class="text-base text-primary font-bold uppercase tracking-wide bg-primary/5 inline-block px-3 py-1 rounded-full border border-primary/20">
                <?php echo e($membro['cargo'] ?? 'Membro'); ?>
            </p>

            <div class="mt-8 flex flex-col gap-4 text-left bg-gray-50 p-5 rounded-2xl border border-gray-100 shadow-inner">
                <div class="flex flex-col border-b pb-3 border-gray-200">
                    <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-1">Igreja</span>
                    <span class="text-lg font-bold text-gray-800 leading-tight"><?php echo e($display_church_name); ?></span>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-1">Membro Desde</span>
                        <span class="text-sm font-semibold text-gray-700">
                            <?php echo !empty($membro['data_inicio']) ? date('d/m/Y', strtotime($membro['data_inicio'])) : '-'; ?>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-1">Batismo</span>
                        <span class="text-sm font-semibold text-gray-700">
                            <?php echo !empty($membro['data_batismo']) ? date('d/m/Y', strtotime($membro['data_batismo'])) : '-'; ?>
                        </span>
                    </div>
                </div>

                <div class="flex flex-col border-t pt-3 border-gray-200">
                    <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-1">Filial / Congregação</span>
                    <span class="text-sm font-semibold text-gray-700"><?php echo e($display_branch_name); ?></span>
                </div>
            </div>

            <!-- QR Code Removed per User Request (Pro Version Feature) -->
            <div class="mt-8 text-center">
                <div class="text-xs text-green-600 font-bold uppercase flex items-center justify-center gap-1">
                    <i class="fas fa-check-circle"></i> Carteirinha Digital Válida
                </div>
                <div class="text-[10px] text-gray-400 mt-1">
                    Gerada em <span id="local-time">...</span>
                </div>
            </div>

        </div>
    </div>
    
    <div class="mt-6 text-center">
        <a href="logout.php" class="text-red-500 font-medium hover:underline">Sair do App</a>
    </div>

</div>

<script>
    // Set Local Time
    document.addEventListener('DOMContentLoaded', () => {
        const now = new Date();
        const options = { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit' 
        };
        // Format: dd/mm/yyyy às HH:MM
        const dateStr = now.toLocaleDateString('pt-BR', options).replace(',', ' às');
        document.getElementById('local-time').textContent = dateStr;
    });
</script>
