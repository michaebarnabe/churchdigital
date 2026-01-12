<?php
/**
 * PlanEnforcer.php
 * Verifica limites do plano contratado
 */
class PlanEnforcer {
    
    public static function canAdd($pdo, $resource) {
        $igrejaId = TenantScope::getId();
        
        // 1. Buscar assinatura ativa e limites do plano
        $sql = "
            SELECT p.* 
            FROM assinaturas a
            JOIN planos p ON a.plano_id = p.id
            WHERE a.igreja_id = ? AND a.status = 'ativa' AND (a.data_fim IS NULL OR a.data_fim >= CURDATE())
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$igrejaId]);
        $plano = $stmt->fetch();
        
        if (!$plano) {
            // Se não tem assinatura ativa, assume plano Free (fallback)
            // Ou bloqueia tudo. Vamos assumir Free hardcoded para evitar bloqueio total sem querer.
            $plano = ['limite_membros' => 50, 'limite_usuarios' => 1];
        }
        
        // 2. Verificar limites
        if ($resource === 'membros') {
            $count = $pdo->prepare("SELECT COUNT(*) FROM membros WHERE igreja_id = ?");
            $count->execute([$igrejaId]);
            $current = $count->fetchColumn();
            
            return $current < $plano['limite_membros'];
        }
        
        if ($resource === 'usuarios') {
            $count = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE igreja_id = ?");
            $count->execute([$igrejaId]);
            $current = $count->fetchColumn();
            
            return $current < $plano['limite_usuarios'];
        }

        if ($resource === 'filiais') {
            // Conta quantas igrejas têm este ID como parent_id
            $count = $pdo->prepare("SELECT COUNT(*) FROM igrejas WHERE parent_id = ?");
            $count->execute([$igrejaId]);
            $current = $count->fetchColumn();
            
            return $current < $plano['limite_filiais'];
        }
        
        return true;
    }
    /**
     * Exibe um Modal de Upgrade Bloqueante/Invasivo
     */
    public static function renderUpgradeModal($message = "Você atingiu o limite do seu plano.") {
        // Usa Tailwind e JS para criar modal
        ?>
        <div id="upgradeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-90 backdrop-blur-sm p-4 fade-in">
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-8 text-center relative overflow-hidden transform scale-100 transition-all">
                <!-- Decorative Background Element -->
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-purple-500 to-blue-500"></div>
                
                <div class="mb-6 inline-flex p-4 rounded-full bg-red-100 text-red-500 text-4xl shadow-inner">
                    <i class="fas fa-lock"></i>
                </div>
                
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Limite Atingido!</h2>
                <p class="text-gray-600 text-lg mb-8"><?php echo $message; ?></p>
                
                <div class="space-y-4">
                    <a href="https://wa.me/5511999999999?text=Quero%20fazer%20upgrade%20do%20plano" target="_blank" class="block w-full bg-black text-white font-bold py-4 rounded-xl text-xl shadow-lg hover:scale-105 transition transform flex items-center justify-center gap-3">
                        <i class="fas fa-rocket"></i> Fazer Upgrade Agora
                    </a>
                    
                    <button onclick="history.back()" class="block w-full bg-gray-100 text-gray-500 font-bold py-3 rounded-xl hover:bg-gray-200 transition">
                        Voltar
                    </button>
                </div>

                <p class="mt-6 text-xs text-gray-400">Liberte todo o potencial da sua igreja com nossos planos Premium.</p>
            </div>
        </div>
        <?php
        exit; // Stop execution to prevent form rendering
    }
}
?>
