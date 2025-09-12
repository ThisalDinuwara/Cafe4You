<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

function sendEmail($to, $subject, $htmlMessage) {
    $mail = new PHPMailer(true);

    try {
        // SMTP server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';     // e.g. Gmail SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ishara2468herath@gmail.com';  // your email
        $mail->Password   = 'zkmpoqgxazubqaiw';    // app password, not your real password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & recipient
        $mail->setFrom('ishara2468herath@gmail.com', 'Cafe For You');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlMessage;
        $mail->AltBody = strip_tags($htmlMessage);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}
