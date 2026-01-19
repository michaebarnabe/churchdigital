<?php
// plugins/configuracoes/update_subscription.php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/TenantScope.php';
require_once '../../config_stripe.php';

// Authentication Check
if (!is_logged_in()) {
    header("Location: ../../login.php");
    exit;
}

$igreja_id = TenantScope::getId();

// Validate Input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acesso inválido.");
}

$newExtraMembros = intval($_POST['extra_membros']);
$newExtraFiliais = intval($_POST['extra_filiais']);

try {
    // 1. Fetch Current Subscription Info
    $stmt = $pdo->prepare("SELECT * FROM assinaturas WHERE igreja_id = ? AND status = 'ativa' LIMIT 1");
    $stmt->execute([$igreja_id]);
    $sub = $stmt->fetch();

    if (!$sub || empty($sub['stripe_subscription_id'])) {
        die("Assinatura não encontrada ou não gerenciada pela Stripe.");
    }

    $stripeSubId = $sub['stripe_subscription_id'];
    $currentItemMembros = $sub['stripe_item_membros']; // ID stored in DB
    $currentItemFiliais = $sub['stripe_item_filiais']; // ID stored in DB

    // 2. Interact with Stripe
    $stripe = new \Stripe\StripeClient($stripe_secret_key);
    $subscription = $stripe->subscriptions->retrieve($stripeSubId);

    // --- HANDLE MEMBROS EXTRA ---
    if ($currentItemMembros) {
        // Update or Delete existing item
        if ($newExtraMembros > 0) {
            $stripe->subscriptionItems->update($currentItemMembros, ['quantity' => $newExtraMembros]);
        } else {
            $stripe->subscriptionItems->delete($currentItemMembros);
            $currentItemMembros = null; // Removed
        }
    } elseif ($newExtraMembros > 0) {
        // Create new item
        $newItem = $stripe->subscriptionItems->create([
            'subscription' => $stripeSubId,
            'price_data' => [
                'currency' => 'brl',
                'product_data' => ['name' => 'Membros Extras'],
                'unit_amount' => 30, // Default fallback, should ideally fetch from Plan metadata or duplicate logic
                'recurring' => ['interval' => 'month'],
            ],
            'quantity' => $newExtraMembros,
        ]);
        $currentItemMembros = $newItem->id;
    }

    // --- HANDLE FILIAIS EXTRA ---
    if ($currentItemFiliais) {
        // Update or Delete existing item
        if ($newExtraFiliais > 0) {
            $stripe->subscriptionItems->update($currentItemFiliais, ['quantity' => $newExtraFiliais]);
        } else {
            $stripe->subscriptionItems->delete($currentItemFiliais);
            $currentItemFiliais = null; // Removed
        }
    } elseif ($newExtraFiliais > 0) {
        // Create new item
        $newItem = $stripe->subscriptionItems->create([
            'subscription' => $stripeSubId,
            'price_data' => [
                'currency' => 'brl',
                'product_data' => ['name' => 'Filiais Extras'],
                'unit_amount' => 1290, 
                'recurring' => ['interval' => 'month'],
            ],
            'quantity' => $newExtraFiliais,
        ]);
        $currentItemFiliais = $newItem->id;
    }

    // 3. Update Local DB
    $update = $pdo->prepare("UPDATE assinaturas SET extra_membros = ?, extra_filiais = ?, stripe_item_membros = ?, stripe_item_filiais = ? WHERE id = ?");
    $update->execute([$newExtraMembros, $newExtraFiliais, $currentItemMembros, $currentItemFiliais, $sub['id']]);

    // 4. Redirect Back
    header("Location: ../../index.php?page=configuracoes&tab=assinatura&msg=extras_updated");
    exit;

} catch (Exception $e) {
    echo "Erro ao atualizar assinatura: " . $e->getMessage();
    // Log error
}
?>
