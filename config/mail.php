<?php
define('MAIL_MODE', 'dev'); // 'dev' to log, 'smtp' to send
define('ADMIN_EMAIL', 'admin@example.com');
define('FROM_EMAIL',  'no-reply@cafeforyou.local');
define('FROM_NAME',   'Cafe For You');

// SMTP (only used when MAIL_MODE === 'smtp')
define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_PORT',   587);
define('SMTP_USER',   'tkandepola@gmail.com');
define('SMTP_PASS',   'xpuo unfe kvvt dwat'); // never commit real secrets
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

?>
