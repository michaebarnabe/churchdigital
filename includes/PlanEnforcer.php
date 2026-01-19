<?php
/**
 * PlanEnforcer.php
 * Verifica limites do plano contratado
 */
class PlanEnforcer {
    
    /**
     * Resolve quem é o dono do plano (Se filial, retorna a Matriz)
     */
    private static function getPlanOwner($pdo, $tenantId) {
        $stmt = $pdo->prepare("SELECT id, parent_id FROM igrejas WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        return ($tenant && !empty($tenant['parent_id'])) ? $tenant['parent_id'] : $tenantId;
    }

    /**
     * Retorna o limite numérico (Base + Extras) para o recurso
     */
    public static function getLimit($pdo, $resource) {
        $currentTenantId = TenantScope::getId();
        $planOwnerId = self::getPlanOwner($pdo, $currentTenantId);

        // Buscar dados do plano + extras
        $sql = "
            SELECT p.*, a.extra_membros, a.extra_filiais 
            FROM assinaturas a
            JOIN planos p ON a.plano_id = p.id
            WHERE a.igreja_id = ? AND a.status = 'ativa' AND (a.data_fim IS NULL OR a.data_fim >= CURDATE())
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$planOwnerId]);
        $plano = $stmt->fetch();

        if (!$plano) {
            // Fallback Free
            $plano = ['limite_membros' => 50, 'limite_usuarios' => 1, 'limite_filiais' => 0];
        }

        if ($resource === 'membros') {
            return $plano['limite_membros'] + ($plano['extra_membros'] ?? 0);
        }
        if ($resource === 'filiais') {
            return $plano['limite_filiais'] + ($plano['extra_filiais'] ?? 0);
        }
        if ($resource === 'usuarios') {
            return $plano['limite_usuarios'];
        }
        
        return 999999;
    }

    public static function canAdd($pdo, $resource) {
        $currentTenantId = TenantScope::getId();
        $planOwnerId = self::getPlanOwner($pdo, $currentTenantId);
        
        // Obter Limite
        $limit = self::getLimit($pdo, $resource);
        
        // Obter Uso Atual
        $current = 0;

        if ($resource === 'membros') {
            // Conta membros da Matriz + Membros de todas as filiais
            $sqlCount = "
                SELECT COUNT(*) 
                FROM membros m 
                JOIN igrejas i ON m.igreja_id = i.id 
                WHERE i.id = ? OR i.parent_id = ?
            ";
            $count = $pdo->prepare($sqlCount);
            $count->execute([$planOwnerId, $planOwnerId]);
            $current = $count->fetchColumn();
        }
        elseif ($resource === 'usuarios') {
            // Conta usuários da Matriz + Todas as filiais
            $sqlCount = "
                SELECT COUNT(*) 
                FROM usuarios u 
                JOIN igrejas i ON u.igreja_id = i.id 
                WHERE i.id = ? OR i.parent_id = ?
            ";
            $count = $pdo->prepare($sqlCount);
            $count->execute([$planOwnerId, $planOwnerId]);
            $current = $count->fetchColumn();
        }
        elseif ($resource === 'filiais') {
            // Conta quantas filiais o dono do plano tem
            $count = $pdo->prepare("SELECT COUNT(*) FROM igrejas WHERE parent_id = ?");
            $count->execute([$planOwnerId]);
            $current = $count->fetchColumn();
        }
        
        return $current < $limit;
    }

    /**
     * Verifica se o recurso PRO está disponível no plano
     */
    public static function canUseFeature($pdo, $feature) {
        $currentTenantId = TenantScope::getId();
        $planOwnerId = self::getPlanOwner($pdo, $currentTenantId);
        
        // Buscar plano do DONO
        $sql = "SELECT p.nome FROM assinaturas a JOIN planos p ON a.plano_id = p.id WHERE a.igreja_id = ? AND a.status = 'ativa' LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$planOwnerId]);
        $planoNome = strtolower($stmt->fetchColumn() ?: '');

        // Features PRO
        $proFeatures = ['upload_comprovantes', 'pix_module', 'reports_advanced'];

        if (in_array($feature, $proFeatures)) {
            if (strpos($planoNome, 'pro') !== false || strpos($planoNome, 'enterprise') !== false) {
                return true;
            }
            return false;
        }

        return true; // Features básicas liberadas
    }

    /**
     * Exibe um Modal de Upgrade Bloqueante/Invasivo
     */
    public static function renderUpgradeModal($message = "Você atingiu o limite do seu plano.") {
        ?>
        <div id="upgradeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-90 backdrop-blur-sm p-4 fade-in">
            <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-8 text-center relative overflow-hidden transform scale-100 transition-all">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-purple-500 to-blue-500"></div>
                <div class="mb-6 inline-flex p-4 rounded-full bg-red-100 text-red-500 text-4xl shadow-inner"><i class="fas fa-lock"></i></div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Limite Atingido!</h2>
                <p class="text-gray-600 text-lg mb-8"><?php echo $message; ?></p>
                <div class="space-y-4">
                    <a href="https://wa.me/5511999999999?text=Quero%20fazer%20upgrade%20do%20plano" target="_blank" class="block w-full bg-black text-white font-bold py-4 rounded-xl text-xl shadow-lg hover:scale-105 transition transform flex items-center justify-center gap-3">
                        <i class="fas fa-rocket"></i> Fazer Upgrade Agora
                    </a>
                    <button onclick="history.back()" class="block w-full bg-gray-100 text-gray-500 font-bold py-3 rounded-xl hover:bg-gray-200 transition">Voltar</button>
                </div>
                <p class="mt-6 text-xs text-gray-400">Liberte todo o potencial da sua igreja com nossos planos Premium.</p>
            </div>
        </div>
        <?php
        exit;
    }
}
?>
