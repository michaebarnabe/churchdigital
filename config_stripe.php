<?php
// Configuração do Stripe
// Certifique-se de que a biblioteca foi baixada e extraída em includes/stripe-php/
$stripe_init_path = __DIR__ . '/includes/stripe-php/init.php';

if (file_exists($stripe_init_path)) {
    require_once $stripe_init_path;
} else {
    // Fallback amigável se o usuário ainda não instalou
    // Não paramos o script aqui para evitar erro fatal imediato se apenas incluído, 
    // mas funções que dependem dele devem verificar.
    define('STRIPE_MISSING', true);
} // End else

// Chaves de API (Substitua pelas suas chaves de teste ou produção)
// Idealmente isso viria de variáveis de ambiente (.env)
$stripe_secret_key = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_...'; 
$stripe_public_key = getenv('STRIPE_PUBLIC_KEY') ?: 'pk_test_...'; 

if (!defined('STRIPE_MISSING')) {
    // Defines the path to the local CA certificate bundle to fix SSL errors
    // We use realpath to verify existence AND get the correct OS-specific path format
    $ca_cert_source = __DIR__ . '/includes/cacert.pem';
    $ca_cert_path = realpath($ca_cert_source);
    
    // Configure PHP runtime settings for this script execution if file exists
    if ($ca_cert_path) {
        ini_set('curl.cainfo', $ca_cert_path);
        ini_set('openssl.cafile', $ca_cert_path);
        
        // CRITICAL FIX: Force Stripe library to use our working CA bundle
        // The library ignores php.ini if it has its own default, so we override it.
        \Stripe\Stripe::setCABundlePath($ca_cert_path);
    } else {
         error_log("Stripe Config Error: cacert.pem not found at " . $ca_cert_source);
    }

    try {
        \Stripe\Stripe::setApiKey($stripe_secret_key);
    } catch (Exception $e) {
        // Silent error in config, handled in usage
    }
}
?>
