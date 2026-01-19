<?php
if (!defined('ABSPATH')) exit;

/**
 * Verifica se o usuário está logado
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Exige login para acessar a página. Redireciona se não estiver logado.
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verifica se o usuário tem um nível de permissão específico (Legacy + RBAC Support)
 *
 * @param string|null $allowed_roles Papeis permitidos (ex: 'admin' ou 'Administrador')
 * @return bool
 */
function has_role($allowed_roles) {
    if (!is_logged_in()) return false;
    if ($allowed_roles === null) return true;

    // Normalização para compatibilidade (admin -> Administrador)
    $map = [
        'admin' => 'Administrador',
        'tesoureiro' => 'Tesoureiro',
        'secretario' => 'Secretário',
        'membro' => 'Membro'
    ];

    $userRoles = $_SESSION['user_roles'] ?? []; // Array de nomes de papeis
    
    // Converte lista solicitada em array
    $roles = explode(',', $allowed_roles);
    $roles = array_map(function($r) use ($map) {
        $r = trim($r);
        return $map[$r] ?? $r;
    }, $roles);

    // Verifica se tem agum dos papeis
    foreach ($roles as $role) {
        if (in_array($role, $userRoles)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Nova função: Verifica se o usuário tem uma permissão específica (Capability)
 * Ex: has_permission('financeiro_view')
 */
function has_permission($slug) {
    if (!is_logged_in()) return false;
    
    // Admin tem tudo
    if (has_role('Administrador')) return true;

    $userPerms = $_SESSION['user_permissions'] ?? [];
    return in_array($slug, $userPerms);
}

/**
 * Tenta realizar o login e carregar permissões
 */
function login($pdo, $email, $senha) {
    // 1. Tentar como Equipe (Usuarios)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
        // --- 2FA CHECK ---
        if (!empty($user['two_factor_enabled'])) {
            $_SESSION['2fa_pending_user_id'] = $user['id'];
            $_SESSION['2fa_pending_type'] = 'staff';
            return '2FA_REQUIRED';
        }
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['igreja_id'] = $user['igreja_id'];
        
        // --- SUBSCRIPTION CHECK ---
        $stmtSub = $pdo->prepare("SELECT status, data_fim FROM assinaturas WHERE igreja_id = ? AND status = 'ativa'");
        $stmtSub->execute([$user['igreja_id']]);
        $sub = $stmtSub->fetch();
        
        // Bypass for Master Admin impersonation (optional, but good practice if implemented) or if user is Super Admin
        
        if ($sub) {
            if ($sub['data_fim'] && strtotime($sub['data_fim']) < time()) {
                return "Sua assinatura expirou em " . date('d/m/Y', strtotime($sub['data_fim'])) . ". Entre em contato com o suporte.";
            }
        } else {
             // If no active subscription found
             // Check if it's a new pending account or really expired/cancelled
             // For safety, block access if not Master Impersonation (which usually bypasses auth.php or sets session directly)
             // But here we are in standard login.
             return "Assinatura inativa ou expirada.";
        }
        
        $_SESSION['user_sexo'] = $user['sexo'] ?? 'M'; // Store Gender
        $_SESSION['user_type'] = 'staff';
        
        // --- CARREGAR PAPEIS E PERMISSÕES (RBAC) ---
        // Buscar papeis
        $stmtRoles = $pdo->prepare("
            SELECT p.nome 
            FROM papeis p 
            JOIN papel_usuario pu ON p.id = pu.papel_id 
            WHERE pu.usuario_id = ?
        ");
        $stmtRoles->execute([$user['id']]);
        $_SESSION['user_roles'] = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

        // Fallback p/ backward compatibility se a migração falhou ou usuário sem papel
        if (empty($_SESSION['user_roles']) && !empty($user['nivel'])) {
            $map = [
                'admin' => 'Administrador', 
                'tesoureiro' => 'Tesoureiro', 
                'secretario' => 'Secretário'
            ];
            $_SESSION['user_roles'][] = $map[$user['nivel']] ?? $user['nivel'];
        }
        
        // Buscar permissões (slugs)
        $stmtPerms = $pdo->prepare("
            SELECT DISTINCT per.slug 
            FROM permissoes per
            JOIN papel_permissoes pp ON per.id = pp.permissao_id
            JOIN papel_usuario pu ON pp.papel_id = pu.papel_id
            WHERE pu.usuario_id = ?
        ");
        $stmtPerms->execute([$user['id']]);
        $_SESSION['user_permissions'] = $stmtPerms->fetchAll(PDO::FETCH_COLUMN);
        
        return true;
    }

    // 2. Tentar como Membro
    try {
        $stmt = $pdo->prepare("SELECT * FROM membros WHERE email = ?");
        $stmt->execute([$email]);
        $membro = $stmt->fetch();

        if ($membro && !empty($membro['senha']) && password_verify($senha, $membro['senha'])) {
            // --- 2FA CHECK ---
            if (!empty($membro['two_factor_enabled'])) {
                $_SESSION['2fa_pending_user_id'] = $membro['id'];
                $_SESSION['2fa_pending_type'] = 'member';
                return '2FA_REQUIRED';
            }
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $membro['id'];
            $_SESSION['user_name'] = $membro['nome'];
            $_SESSION['user_email'] = $membro['email'];
            $_SESSION['igreja_id'] = $membro['igreja_id'];
            $_SESSION['user_type'] = 'member';
            $_SESSION['user_photo'] = $membro['foto'] ?? '';
            
            // Membros tem papel fixo 'Membro' (virtual)
            $_SESSION['user_roles'] = ['Membro'];
            $_SESSION['user_permissions'] = []; // Membros acessam área pública/restrita específica, não o painel admin
            
            // Forced Password Change
            $_SESSION['must_change_password'] = $membro['must_change_password'] ?? false;

            return true;
        }
    } catch (Exception $e) {
        return "Erro de configuração de login para membros.";
    }

    return "E-mail ou senha inválidos.";
}

/**
 * Realiza logout
 */
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
