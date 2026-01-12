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
function send_mail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.example.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USER') ?: 'user@example.com';
        $mail->Password   = getenv('SMTP_PASS') ?: 'secret';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
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
        // Log error
        error_log("Mail Error: {$mail->ErrorInfo}");
        return "Erro ao enviar e-mail: {$mail->ErrorInfo}";
    }
}
?>
