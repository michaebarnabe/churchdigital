<?php
require_once 'config.php';
define('ABSPATH', true);
require_once 'config_stripe.php';

$session_id = $_GET['session_id'] ?? null;
$sub_id = $_GET['sub_id'] ?? null;

if (!$session_id || !$sub_id) {
    header("Location: index.php");
    exit;
}

try {
    if (defined('STRIPE_MISSING')) {
        throw new Exception("Biblioteca Stripe não encontrada.");
    }

    // Retrieve the session from Stripe
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status === 'paid' || $session->payment_status === 'no_payment_required') {
        // Payment Success!
        
        // 1. Activate Subscription
        $stmt = $pdo->prepare("UPDATE assinaturas SET status = 'ativa' WHERE id = ? AND status IN ('pendente', 'aguardando_pagamento')");
        $stmt->execute([$sub_id]);

        if ($stmt->rowCount() > 0) {
            // Subscription Activated
            
            // Get Church ID from Subscription
            $stmtGet = $pdo->prepare("SELECT igreja_id FROM assinaturas WHERE id = ?");
            $stmtGet->execute([$sub_id]);
            $igrejaId = $stmtGet->fetchColumn();

            // Find the Admin User for this church to auto-login (Optional, but good UX)
            $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE igreja_id = ? AND nivel = 'admin' LIMIT 1");
            $stmtUser->execute([$igrejaId]);
            $user = $stmtUser->fetch();

            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['igreja_id'] = $user['igreja_id'];
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

                // Fallback p/ backward compatibility
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
                
                // Define Protocol
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                
                // --- SEND WELCOME EMAIL ---
                require_once 'includes/mailer.php';
                $body = "
                <div style='font-family: sans-serif; color: #333;'>
                    <h1>Bem-vindo à Church Digital!</h1>
                    <p>Olá, {$user['nome']}!</p>
                    <p>Obrigado por assinar o Church Digital. Seu pagamento foi confirmado e sua igreja (Matriz) já está ativa.</p>
                    <p>Aproveite todos os recursos para gerenciar sua igreja com excelência.</p>
                    <br>
                    <h3>📱 Instale o Aplicativo</h3>
                    <p>Para facilitar o acesso, instale nosso App no seu celular:</p>
                    <ul>
                        <li><a href='{$protocol}{$_SERVER['HTTP_HOST']}/ChurchDigital/install.php'>Ver Passo a Passo de Instalação</a></li>
                        <li><a href='{$protocol}{$_SERVER['HTTP_HOST']}/ChurchDigital/index.php?mode=pwa'><strong>Abrir App Direto</strong></a> (Se já estiver no celular)</li>
                    </ul>
                    <br>
                    <p>Atenciosamente,<br>Equipe Church Digital</p>
                </div>
                ";
                send_mail($user['email'], 'Bem-vindo ao Church Digital', $body);
                
                header("Location: index.php?msg=welcome_premium");
                exit;
            }
        } else {
             // Maybe already activated or invalid ID
             // Check if already active
             header("Location: login.php?msg=payment_processed");
             exit;
        }

    } else {
        // Payment not clear
        header("Location: payment_cancel.php?reason=not_paid");
        exit;
    }

} catch (Exception $e) {
    die("Erro ao processar pagamento: " . $e->getMessage());
}
?>
