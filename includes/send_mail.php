<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

function sendOrderEmail($to, $name, $orderId, $total) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation - #$orderId";
        $mail->Body    = "
            <h2>Thank you for your order, $name!</h2>
            <p>Your order ID is <b>#$orderId</b>.</p>
            <p>Total Amount: Rs.$total</p>
            <p>We will notify you once your order is shipped.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}
