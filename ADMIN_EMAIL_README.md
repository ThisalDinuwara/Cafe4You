# Admin Email Notifications

This project has been updated to send **admin notifications** on order changes.

## Where it triggers
- **New order (checkout)**: after a user places an order (`checkout.php`), it calls `notify_admin_order_created($db, $order_id)`.
- **Status updates (admin)**: when an admin changes an order status in `admin/orders.php`, it calls `notify_admin_order_status_changed($db, $order_id, $old_status, $new_status)`.

## Configuration
Edit `config/mail.php`:
```php
define('MAIL_MODE', 'dev');            // 'dev' = write emails to logs/admin_email_notifications.txt
define('ADMIN_EMAIL', 'admin@example.com');
define('FROM_EMAIL', 'no-reply@cafeforyou.local');
```
- In **dev mode**, messages are appended to `logs/admin_email_notifications.txt`.
- To attempt sending real mail via PHP's `mail()` function, set `MAIL_MODE` to `'mail'`. (Server must be configured to deliver mail.)

## Files added
- `config/mail.php` — settings
- `includes/mailer.php` — helper functions to send/log emails

