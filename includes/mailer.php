<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Envia um e-mail usando PHPMailer e configurações do .env
 * 
 * @param string $to E-mail do destinatário
 * @param string $subject Assunto
 * @param string $body Corpo HTML
 * @param string $altBody Corpo Texto (Opcional)
 * @return bool|string True se sucesso, mensagem de erro se falha
 */
/**
 * Envia um e-mail usando PHPMailer (SMTP) ou Brevo Code (API)
 * 
 * @param string $to E-mail do destinatário
 * @param string $subject Assunto
 * @param string $body Corpo HTML
 * @param string $altBody Corpo Texto (Opcional)
 * @return bool|string True se sucesso, mensagem de erro se falha
 */
function send_mail($to, $subject, $body, $altBody = '') {
    // Verificar se deve usar API do Brevo
    $useBrevoApi = getenv('USE_BREVO_API') === 'true';
    $brevoApiKey = trim(getenv('BREVO_API_KEY'));

    // Modo API (Brevo) via Curl
    if ($useBrevoApi && $brevoApiKey) {
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@churchdigital.com';
        $fromName  = getenv('SMTP_FROM_NAME') ?: 'Church Digital';

        $data = [
            "sender" => ["name" => $fromName, "email" => $fromEmail],
            "to" => [["email" => $to]],
            "subject" => $subject,
            "htmlContent" => $body
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $brevoApiKey,
            'content-type: application/json'
        ]);
        
        // Fix SSL: Load local CA Cert
        if (file_exists(__DIR__ . '/cacert.pem')) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Brevo API Curl Error: $error");
            return "Erro de conexão API: $error";
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("Brevo API Error ($httpCode): $response");
            return "Erro API Brevo: $response";
        }
    }

    // Modo SMTP (PHPMailer)
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER');
        $mail->Password   = getenv('SMTP_PASS');
        
        // Adjust Port/Secure logic
        $port = getenv('SMTP_PORT') ?: 587;
        $mail->Port       = $port;
        if ($port == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet    = 'UTF-8';

        //Recipients
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@churchdigital.com';
        $fromName  = getenv('SMTP_FROM_NAME') ?: 'Church Digital';
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        //Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return "Erro SMTP: {$mail->ErrorInfo}";
    }
}
?>
