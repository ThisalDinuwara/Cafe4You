<?php
// includes/mailer.php
// ----------------------------------------------------------------------------
// Requirements:
//   - config/mail.php defines:
//       define('MAIL_MODE', 'dev');        // 'dev' logs to /logs/email_notifications.txt
//                                          // 'mail' uses PHP mail()
//       define('ADMIN_EMAIL', 'admin@example.com');
//       define('FROM_EMAIL', 'no-reply@yourdomain.local');
// ----------------------------------------------------------------------------

declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

/**
 * Safely turn arrays/objects into readable text (for logs / debug).
 */
function as_pretty_text($value): string {
    return print_r($value, true);
}

/**
 * Minimal HTML escape helper.
 */
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Core send function.
 * - In DEV mode: appends to logs/email_notifications.txt
 * - In MAIL mode: uses PHP mail()
 *
 * @param string $subject
 * @param string $html_body
 * @param string $alt_text
 * @param string $to          Recipient (defaults to ADMIN_EMAIL)
 */
function send_mail(string $subject, string $html_body, string $alt_text = '', string $to = ADMIN_EMAIL): void {
    // Basic subject sanitization to avoid header injection
    $subject = trim(str_replace(["\r", "\n"], ' ', $subject));

    if (MAIL_MODE === 'dev') {
        $logfile = __DIR__ . '/../logs/email_notifications.txt';
        if (!is_dir(dirname($logfile))) {
            @mkdir(dirname($logfile), 0775, true);
        }
        $entry  = "---------------- EMAIL LOG ----------------\n";
        $entry .= "Time:    " . date('c') . "\n";
        $entry .= "To:      {$to}\n";
        $entry .= "From:    " . FROM_EMAIL . "\n";
        $entry .= "Subject: {$subject}\n";
        $entry .= "Alt:\n{$alt_text}\n\n";
        $entry .= "HTML:\n{$html_body}\n";
        $entry .= "-------------------------------------------\n\n";
        file_put_contents($logfile, $entry, FILE_APPEND);
        return;
    }

    // MAIL_MODE === 'mail'
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . FROM_EMAIL . "\r\n";

    // Fallback plain text if client doesn't render HTML (rare with mail()).
    // (Some MTAs support multipart; keeping it simple here.)
    @mail($to, $subject, $html_body, $headers);
}

/**
 * Fetch a single order row by id (PDO expected).
 * Adjust table/columns to match your schema.
 *
 * @param PDO    $db
 * @param int    $order_id
 * @return array|null
 */
function fetch_order(PDO $db, int $order_id): ?array {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Build a very simple HTML card for an order payload.
 *
 * @param array $order
 * @return string
 */
function render_order_card(array $order): string {
    $safe = array_map('h', $order);
    $lines = '';
    foreach ($safe as $k => $v) {
        $lines .= "<tr><td style=\"padding:6px 8px;border:1px solid #eee;\"><b>".h((string)$k)."</b></td><td style=\"padding:6px 8px;border:1px solid #eee;\">{$v}</td></tr>";
    }

    return <<<HTML
<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #eee;width:100%;max-width:640px;">
  <tbody>
    {$lines}
  </tbody>
</table>
HTML;
}

/**
 * Notify ADMIN: new order created.
 *
 * @param PDO $db
 * @param int $order_id
 */
function notify_admin_order_created(PDO $db, int $order_id): void {
    $order = fetch_order($db, $order_id);
    if (!$order) return;

    $subject = "[Orders] New Order #{$order_id}";
    $html    = "<h2 style=\"font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;\">New Order Received</h2>"
             . render_order_card($order);
    $alt     = "New order #{$order_id}\n\n" . as_pretty_text($order);

    send_mail($subject, $html, $alt, ADMIN_EMAIL);
}

/**
 * Notify ADMIN: order status changed.
 *
 * @param PDO    $db
 * @param int    $order_id
 * @param string $old_status
 * @param string $new_status
 */
function notify_admin_order_status_changed(PDO $db, int $order_id, string $old_status, string $new_status): void {
    $order = fetch_order($db, $order_id);
    if (!$order) return;

    $subject = "[Orders] Order #{$order_id} Status Changed ({$old_status} → {$new_status})";
    $html    = "<h2 style=\"font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;\">Order Status Updated</h2>"
             . "<p>Order <b>#".h((string)$order_id)."</b> changed from <b>".h($old_status)."</b> to <b>".h($new_status)."</b>.</p>"
             . render_order_card($order);
    $alt     = "Order #{$order_id} status changed from {$old_status} to {$new_status}\n\n" . as_pretty_text($order);

    send_mail($subject, $html, $alt, ADMIN_EMAIL);
}

/**
 * Notify CUSTOMER: order placed.
 *
 * @param string $customer_email
 * @param int    $order_id
 */
function notify_user_order_created(string $customer_email, int $order_id): void {
    if (!$customer_email) return;

    $subject = "Your order #{$order_id} has been placed";
    $html    = "<h2 style=\"font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;\">Thanks for your order!</h2>"
             . "<p>We’ve received your order <b>#".h((string)$order_id)."</b>. We’ll notify you when it moves to the next stage.</p>";
    $alt     = "Your order #{$order_id} has been placed.";

    send_mail($subject, $html, $alt, $customer_email);
}

/**
 * Notify CUSTOMER: order status changed.
 *
 * @param string $customer_email
 * @param int    $order_id
 * @param string $old_status
 * @param string $new_status
 */
function notify_user_order_status_changed(string $customer_email, int $order_id, string $old_status, string $new_status): void {
    if (!$customer_email) return;

    $subject = "Update: Order #{$order_id} is now {$new_status}";
    $html    = "<h2 style=\"font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;\">Order Update</h2>"
             . "<p>Your order <b>#".h((string)$order_id)."</b> changed from <b>".h($old_status)."</b> to <b>".h($new_status)."</b>.</p>";
    $alt     = "Order #{$order_id} status changed from {$old_status} to {$new_status}.";

    send_mail($subject, $html, $alt, $customer_email);
}
