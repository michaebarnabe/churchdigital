<?php
require_once 'config.php';
require_once 'includes/mailer.php';

header('Content-Type: text/plain');

echo "--- Diagnóstico de E-mail ---\n";

$useApi = getenv('USE_BREVO_API');
$apiKey = getenv('BREVO_API_KEY');

echo "USE_BREVO_API: " . ($useApi ? 'true/set' : 'false/empty') . "\n";
echo "BREVO_API_KEY Length: " . strlen($apiKey) . "\n";

if (strlen($apiKey) > 5) {
    echo "BREVO_API_KEY Start: " . substr($apiKey, 0, 8) . "...\n";
    echo "BREVO_API_KEY End: ..." . substr($apiKey, -4) . "\n";
    
    if (strpos($apiKey, 'xkeysib-') !== 0) {
        echo "\n[ALERTA] A chave NÃO começa com 'xkeysib-'. \n";
        echo "Isso indica que você provavelmente está usando uma chave SMTP ou uma chave antiga (v2).\n";
        echo "Para a API funcionar, você precisa gerar uma nova chave em 'SMTP & API > Chaves de API' no painel do Brevo.\n";
    } else {
        echo "\n[OK] O formato da chave parece correto (começa com xkeysib).\n";
    }
} else {
    echo "[ERRO] A chave parece vazia ou muito curta.\n";
}

echo "\n--- Tentativa de Envio (Teste) ---\n";
// Tenta enviar para o próprio remetente ou um email ficticio de teste
// O usuário deve rodar isso e ver o output.
$from = getenv('SMTP_FROM_EMAIL') ?: 'teste@example.com';
echo "Enviando de: $from\n";

// Simula chamada
if ($useApi == 'true') {
     // Copia da logica do mailer para debug verbose
     $ch = curl_init();
     curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     curl_setopt($ch, CURLOPT_POST, true);
     // Dados minimos
     $data = [
        "sender" => ["name" => "Debug", "email" => $from],
        "to" => [["email" => $from]],
        "subject" => "Teste de Debug API",
        "htmlContent" => "<p>Teste</p>"
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . trim($apiKey),
        'content-type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo "\nHTTP Code: " . $info['http_code'] . "\n";
    echo "Response: " . $response . "\n";
    if ($err) echo "Curl Error: $err\n";
} else {
    echo "O sistema não está configurado para usar a API (USE_BREVO_API != true).\n";
}
