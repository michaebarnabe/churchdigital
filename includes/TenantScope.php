<?php
/**
 * TenantScope.php
 * Helper para garantir acesso seguro aos dados do Tenant (Igreja)
 */
class TenantScope {

    /**
     * Retorna o ID da igreja atual da sessão
     * Lança exceção se não estiver definido (Sessão inválida ou erro lógico)
     */
    public static function getId() {
        if (!isset($_SESSION['igreja_id'])) {
            // Em produção, redirecionaria para login. 
            // Aqui, garantimos que nunca retorne um ID vazio ou null em query crítica.
            throw new Exception("Tenant Context Missing: igreja_id not found in session.");
        }
        return (int) $_SESSION['igreja_id'];
    }

    /**
     * Retorna o ID da Matriz (se houver) ou null
     */
    public static function getParentId($pdo) {
        $id = self::getId();
        $stmt = $pdo->prepare("SELECT parent_id FROM igrejas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Retorna lista de Tenants disponíveis para o usuário (Matriz + Filiais)
     */
    public static function getAvailableTenants($pdo, $userId) {
        // Assume que o igreja_id original está na sessão em 'real_igreja_id' ou 'igreja_id'
        // Se ainda não tiver 'real_igreja_id', define na primeira chamada
        if (!isset($_SESSION['real_igreja_id']) && isset($_SESSION['igreja_id'])) {
            $_SESSION['real_igreja_id'] = $_SESSION['igreja_id'];
        }
        
        $realId = $_SESSION['real_igreja_id'] ?? 0;
        if (!$realId) return [];

        // Members see ONLY their own church
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'member') {
            // Fetch own church name just for consistency
            $stmt = $pdo->prepare("SELECT id, nome, 'Membro' as tipo FROM igrejas WHERE id = ?");
            $stmt->execute([self::getId()]);
            return $stmt->fetchAll();
        }

        // Verifica se é Matriz (tem filiais) ou é Filial
        // Lógica simplificada: Se eu sou matriz, vejo eu + filiais. Se sou filial, vejo só eu.
        // TODO: Melhorar para hierarquia recursiva se precisar
        $stmt = $pdo->prepare("
            SELECT id, nome, 'Matriz' as tipo FROM igrejas WHERE id = ?
            UNION
            SELECT id, nome, 'Filial' as tipo FROM igrejas WHERE parent_id = ?
        ");
        $stmt->execute([$realId, $realId]);
        return $stmt->fetchAll();
    }

    /**
     * Troca o contexto atual (apenas se permitido)
     */
    public static function switchTenant($pdo, $targetId) {
        $allowed = self::getAvailableTenants($pdo, $_SESSION['user_id'] ?? 0);
        $ids = array_column($allowed, 'id');
        
        if (in_array($targetId, $ids)) {
            $_SESSION['igreja_id'] = $targetId;
            return true;
        }
        return false;
    }
}
?>
