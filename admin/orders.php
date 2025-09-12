<?php
// admin/orders.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
// keep if you have other helpers here; it does NOT define sendOrderStatusEmail in your project
require_once __DIR__ . '/../includes/mailer.php';

// ===== PHPMailer setup (Manual include) =====
// If you use Composer instead, comment these 3 lines and UNcomment vendor/autoload.php below.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../includes/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../includes/PHPMailer/src/SMTP.php';

// ---- Composer alternative (use ONE approach only) ----
// require_once __DIR__ . '/../vendor/autoload.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

/**
 * Development mode email function (writes to logs/email_notifications.txt)
 */
function sendOrderStatusEmailDev($customer_email, $customer_name, $order_id, $new_status, $order_details) {
    $status_messages = [
        'pending'   => 'Your order has been received and is pending confirmation.',
        'confirmed' => 'Your order has been confirmed and will be prepared soon.',
        'preparing' => 'Your order is currently being prepared by our kitchen staff.',
        'ready'     => 'Your order is ready for pickup/delivery!',
        'delivered' => 'Your order has been successfully delivered.',
        'cancelled' => 'Unfortunately, your order has been cancelled.'
    ];
    $message = $status_messages[$new_status] ?? 'Your order status has been updated.';

    $email_content = "
=============================================================
EMAIL NOTIFICATION (Development Mode)
=============================================================
To: $customer_email
Subject: Order #$order_id Status Update - Cafe For You
Date: " . date('Y-m-d H:i:s') . "

Hello $customer_name,

Your order #$order_id status has been updated to: " . ucfirst($new_status) . "

$message

Order Details:
- Order Date: " . date('F j, Y g:i A', strtotime($order_details['created_at'])) . "
- Total Amount: Rs" . number_format($order_details['total_amount'], 2) . "
- Delivery Address: " . htmlspecialchars($order_details['delivery_address']) . "

Thank you for choosing Cafe For You!

Best regards,
The Cafe For You Team
=============================================================

";
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/email_notifications.txt';
    file_put_contents($log_file, $email_content, FILE_APPEND | LOCK_EX);
    return true;
}

/**
 * Production email function using PHPMailer
 */
function sendOrderStatusEmail($customer_email, $customer_name, $order_id, $new_status, $order_details) {
    $mail = new PHPMailer(true);

    try {
        // Server settings (Gmail SMTP)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'tkandepola@gmail.com';   // your Gmail address
        $mail->Password   = 'xpuo unfe kvvt dwat';             // <-- replace with Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Recipients (From should usually match the authenticated account for Gmail)
        $mail->setFrom('isharasathsaranih@gmail.com', 'Cafe For You');
        $mail->addAddress($customer_email, $customer_name);
        $mail->addReplyTo('support@cafeforyou.com', 'Cafe For You Support');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Order #$order_id Status Update - Cafe For You";

        // Status messages and colors
        $status_messages = [
            'pending'   => 'Your order has been received and is pending confirmation.',
            'confirmed' => 'Your order has been confirmed and will be prepared soon.',
            'preparing' => 'Your order is currently being prepared by our kitchen staff.',
            'ready'     => 'Your order is ready for pickup/delivery!',
            'delivered' => 'Your order has been successfully delivered.',
            'cancelled' => 'Unfortunately, your order has been cancelled.'
        ];
        $status_colors = [
            'pending'   => '#F59E0B',
            'confirmed' => '#3B82F6',
            'preparing' => '#8B5CF6',
            'ready'     => '#10B981',
            'delivered' => '#6B7280',
            'cancelled' => '#EF4444'
        ];
        $message = $status_messages[$new_status] ?? 'Your order status has been updated.';
        $color   = $status_colors[$new_status] ?? '#6B7280';

        $html_body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Order Status Update</title>
            <style>
                body { font-family: 'Arial', sans-serif; margin: 0; padding: 0; background: #FFFDF7; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #FFC728, #FFB800); color: white; padding: 30px; text-align: center; border-radius: 15px 15px 0 0; }
                .content { background: white; padding: 30px; border-radius: 0 0 15px 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
                .status-badge { display: inline-block; background: {$color}; color: white; padding: 10px 20px; border-radius: 25px; font-weight: bold; margin: 20px 0; }
                .order-details { background: #FFF9E6; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 5px solid #FFC728; }
                .footer { text-align: center; padding: 20px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin:0;font-size:28px;'>Cafe For You</h1>
                    <p style='margin:10px 0 0 0;font-size:16px;opacity:.9;'>Order Status Update</p>
                </div>
                <div class='content'>
                    <h2 style='color:#8B4513;margin-top:0;'>Hello " . htmlspecialchars($customer_name) . ",</h2>
                    <p style='font-size:16px;line-height:1.6;color:#333;'>We wanted to update you on the status of your order.</p>
                    <div class='order-details'>
                        <h3 style='color:#8B4513;margin-top:0;'>Order #$order_id</h3>
                        <p><strong>Current Status:</strong> <span class='status-badge'>" . ucfirst($new_status) . "</span></p>
                        <p><strong>Status Message:</strong> {$message}</p>
                        <p><strong>Order Date:</strong> " . date('F j, Y g:i A', strtotime($order_details['created_at'])) . "</p>
                        <p><strong>Total Amount:</strong> Rs" . number_format($order_details['total_amount'], 2) . "</p>
                    </div>
                    <p style='font-size:16px;line-height:1.6;color:#333;'>If you have any questions about your order, please don't hesitate to contact us.</p>
                    <p style='font-size:14px;color:#666;margin-bottom:0;'>Best regards,<br><strong>The Cafe For You Team</strong></p>
                </div>
                <div class='footer'>
                    <p style='margin:0;font-size:14px;'>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
        $mail->Body = $html_body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the PHPMailer error for debugging
        error_log('Email sending failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// ===== Handle status updates =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id   = (int)$_POST['order_id'];
    $new_status = sanitize($_POST['status']);
    $old_status = sanitize($_POST['old_status']);

    // Only proceed if status actually changed
    if ($new_status !== $old_status) {
        $update_stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        if ($update_stmt->execute([$new_status, $order_id])) {
            // Get order and customer details for email
            $email_stmt = $db->prepare("
                SELECT o.*, u.full_name, u.email, u.phone
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $email_stmt->execute([$order_id]);
            $order_details = $email_stmt->fetch(PDO::FETCH_ASSOC);

            if ($order_details) {
                // Toggle this: true = dev (log), false = production (send email)
                $is_development = false;

                if ($is_development) {
                    $email_sent = sendOrderStatusEmailDev(
                        $order_details['email'],
                        $order_details['full_name'],
                        $order_id,
                        $new_status,
                        $order_details
                    );
                    if ($email_sent) {
                        showMessage("Order status updated successfully! Email notification logged to logs/email_notifications.txt (Development Mode).", 'success');
                    } else {
                        showMessage("Order status updated, but failed to log email notification.", 'warning');
                    }
                } else {
                    $email_sent = sendOrderStatusEmail(
                        $order_details['email'],
                        $order_details['full_name'],
                        $order_id,
                        $new_status,
                        $order_details
                    );
                    if ($email_sent) {
                        showMessage("Order status updated successfully! Email notification sent to customer.", 'success');
                    } else {
                        showMessage("Order status updated, but failed to send email notification.", 'warning');
                    }
                }
            } else {
                showMessage('Order status updated successfully!', 'success');
                if (function_exists('notify_admin_order_status_changed')) {
                    @notify_admin_order_status_changed($db, $order_id, $old_status, $new_status);
                }
            }
        } else {
            showMessage('Failed to update order status', 'error');
        }
    } else {
        showMessage('No changes made to order status.', 'info');
    }
}

// ===== Pull orders & stats for UI =====
$orders_stmt = $db->prepare("
    SELECT o.*, u.full_name, u.email, COUNT(oi.id) AS item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders_stmt->execute();
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats = [];
$stats['total_orders']   = count($orders);
$stats['total_revenue']  = array_sum(array_column($orders, 'total_amount'));
$stats['avg_order_value']= $stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0;

// Status counts
$status_counts = [];
foreach ($orders as $order) {
    $status_counts[$order['status']] = ($status_counts[$order['status']] ?? 0) + 1;
}

// Today's orders
$today_orders = array_filter($orders, function($order) {
    return date('Y-m-d', strtotime($order['created_at'])) === date('Y-m-d');
});
$stats['today_orders']  = count($today_orders);
$stats['today_revenue'] = array_sum(array_column($today_orders, 'total_amount'));

// Fetch order details if requested
$order_details = null;
if (isset($_GET['view'])) {
    $order_id = (int)$_GET['view'];
    $details_stmt = $db->prepare("
        SELECT o.*, u.full_name, u.email, u.phone AS user_phone,
               oi.quantity, oi.price, mi.name AS item_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE o.id = ?
    ");
    $details_stmt->execute([$order_id]);
    $order_details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Cafe For You Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'golden': {
                            50: '#FFFDF7',
                            100: '#FFF9E6',
                            200: '#FFF0B8',
                            300: '#FFE388',
                            400: '#FFD558',
                            500: '#FFC728',
                            600: '#F5B800',
                            700: '#CC9900',
                            800: '#A37A00',
                            900: '#7A5B00'
                        },
                        'amber-glow': '#FFB800',
                        'honey': '#FFCC33',
                        'cafe-brown': '#8B4513',
                        'warm-gray': '#78716C'
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-soft': 'pulse-soft 2s ease-in-out infinite',
                        'slide-up': 'slide-up 0.8s ease-out',
                        'fade-in': 'fade-in 0.6s ease-out',
                        'bounce-gentle': 'bounce-gentle 2s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                        'shimmer': 'shimmer 2.5s linear infinite'
                    },
                    keyframes: {
                        float: {'0%,100%':{transform:'translateY(0px)'},'50%':{transform:'translateY(-10px)'}},
                        'pulse-soft': {'0%,100%':{opacity:1},'50%':{opacity:0.8}},
                        'slide-up': {'0%':{transform:'translateY(30px)',opacity:0},'100%':{transform:'translateY(0)',opacity:1}},
                        'fade-in': {'0%':{opacity:0},'100%':{opacity:1}},
                        'bounce-gentle': {'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-5px)'}},
                        glow: {'0%':{boxShadow:'0 0 20px rgba(255, 199, 40, 0.3)'},'100%':{boxShadow:'0 0 30px rgba(255, 199, 40, 0.6)'}},
                        shimmer: {'0%':{backgroundPosition:'-1000px 0'},'100%':{backgroundPosition:'1000px 0'}}
                    },
                    backdropBlur: { xs: '2px' }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        body { font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#FFFDF7 0%,#FFF9E6 25%,#FFF0B8 50%,#FFE388 75%,#FFD558 100%); background-attachment:fixed; min-height:100vh; }
        .glass-morphism{background:rgba(255,255,255,0.25);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,199,40,0.18);box-shadow:0 8px 32px rgba(255,199,40,0.37);}
        .glass-card{background:rgba(255,255,255,0.85);backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);border:1px solid rgba(255,199,40,0.25);box-shadow:0 12px 40px rgba(255,184,0,0.15);transition:all .4s cubic-bezier(.4,0,.2,1);}
        .glass-card:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 20px 60px rgba(255,184,0,.25);}
        .gradient-card{background:linear-gradient(135deg,var(--tw-gradient-stops));position:relative;overflow:hidden;transition:all .4s ease;}
        .floating-animation{animation:float 6s ease-in-out infinite;}
        .nav-item{transition:all .3s cubic-bezier(.4,0,.2,1);border-radius:16px;margin:6px 12px;position:relative;overflow:hidden;}
        .nav-item.active{background:linear-gradient(135deg,#FFC728,#FFB800,#F5B800);color:#8B4513;box-shadow:0 8px 32px rgba(255,199,40,.4);font-weight:600;transform:translateX(8px);}
        .nav-item:not(.active):hover{background:rgba(255,199,40,.15);color:#F5B800;transform:translateX(8px) translateY(-2px);box-shadow:0 4px 20px rgba(255,199,40,.3);}
        .icon-container{background:linear-gradient(135deg,#FFC728,#FFB800);box-shadow:0 8px 32px rgba(255,199,40,.4);position:relative;overflow:hidden;}
        .hover-lift{transition:all .4s cubic-bezier(.25,.46,.45,.94);}
        .hover-lift:hover{transform:translateY(-10px) rotateX(5deg);box-shadow:0 25px 80px rgba(255,184,0,.3);}
        .section-header{background:linear-gradient(135deg,rgba(255,199,40,.1),rgba(255,184,0,.05));border-left:6px solid #FFC728;backdrop-filter:blur(10px);}
        .golden-text{background:linear-gradient(135deg,#FFB800 0%,#FFC728 25%,#FFD700 50%,#FFC728 75%,#FFB800 100%);background-size:200% auto;color:transparent;-webkit-background-clip:text;background-clip:text;animation:shimmer 3s linear infinite;}
        .shimmer-bg{background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);background-size:200% 100%;animation:shimmer 2.5s infinite;}
        .order-card{background:rgba(255,255,255,.9);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,199,40,.3);transition:all .4s cubic-bezier(.4,0,.2,1);position:relative;overflow:hidden;}
        .order-card:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 20px 60px rgba(255,184,0,.3);border-color:rgba(255,199,40,.6);}
        .status-pending{background:linear-gradient(135deg,#FEF3C7,#FDE68A);color:#92400E;border:2px solid #F59E0B;}
        .status-confirmed{background:linear-gradient(135deg,#DBEAFE,#BFDBFE);color:#1E40AF;border:2px solid #3B82F6;}
        .status-preparing{background:linear-gradient(135deg,#E9D5FF,#DDD6FE);color:#7C2D12;border:2px solid #8B5CF6;}
        .status-ready{background:linear-gradient(135deg,#D1FAE5,#A7F3D0);color:#065F46;border:2px solid #10B981;}
        .status-delivered{background:linear-gradient(135deg,#F3F4F6,#E5E7EB);color:#374151;border:2px solid #6B7280;}
        .status-cancelled{background:linear-gradient(135deg,#FEE2E2,#FECACA);color:#991B1B;border:2px solid #EF4444;}
        .pulse-ring{animation:pulse-ring 2s cubic-bezier(.455,.03,.515,.955) infinite;}
        @keyframes pulse-ring{0%{transform:scale(.33);opacity:1;}80%,100%{transform:scale(2.33);opacity:0;}}
        .modal-backdrop{background:rgba(0,0,0,.7);backdrop-filter:blur(10px);}
        .modal-content{background:rgba(255,255,255,.95);backdrop-filter:blur(25px);-webkit-backdrop-filter:blur(25px);border:1px solid rgba(255,199,40,.2);box-shadow:0 30px 80px rgba(255,184,0,.3);}
        .save-button{background:linear-gradient(135deg,#10B981,#059669);color:#fff;border:none;padding:12px 24px;border-radius:12px;font-weight:bold;cursor:pointer;transition:all .3s ease;box-shadow:0 4px 15px rgba(16,185,129,.3);}
        .save-button:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(16,185,129,.4);}
        .save-button:disabled{background:#9CA3AF;cursor:not-allowed;transform:none;box-shadow:none;}
    </style>
</head>
<body class="font-sans">
    <!-- Floating Background Elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-32 h-32 bg-golden-200 rounded-full opacity-20 floating-animation"></div>
        <div class="absolute top-3/4 right-1/4 w-24 h-24 bg-golden-300 rounded-full opacity-15 floating-animation" style="animation-delay:-2s;"></div>
        <div class="absolute top-1/2 left-3/4 w-40 h-40 bg-golden-100 rounded-full opacity-10 floating-animation" style="animation-delay:-4s;"></div>
    </div>

    <!-- Top Navigation -->
    <nav class="glass-morphism shadow-2xl border-b-2 border-golden-400/30 relative z-10">
        <div class="max-w-7xl mx-auto px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-6">
                    <div class="relative">
                        <div class="w-16 h-16 icon-container rounded-2xl flex items-center justify-center shadow-2xl animate-glow">
                            <span class="text-white font-black text-2xl">C</span>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full pulse-ring"></div>
                    </div>
                    <div class="animate-slide-up">
                        <h1 class="text-3xl font-black golden-text">Cafe For You</h1>
                        <p class="text-warm-gray font-medium text-lg">Order Management</p>
                    </div>
                </div>

                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-4 glass-card px-6 py-3 rounded-2xl hover:shadow-xl transition-all duration-300">
                        <div class="relative">
                            <div class="w-12 h-12 bg-gradient-to-br from-golden-400 to-golden-600 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="text-white font-bold text-lg">
                                    <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
                                </span>
                            </div>
                            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
                        </div>
                        <div>
                            <p class="text-warm-gray font-semibold">Welcome back,</p>
                            <p class="font-bold text-cafe-brown"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
                        </div>
                    </div>
                    <a href="../index.php" class="text-warm-gray hover:text-golden-600 transition-colors duration-300 font-semibold text-lg hover:scale-105 transform">View Site</a>
                    <a href="../logout.php" class="bg-gradient-to-r from-golden-500 to-golden-600 text-white px-8 py-3 rounded-2xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300 font-bold text-lg shimmer-bg">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen relative z-10">
        <!-- Sidebar -->
        <aside class="w-72 glass-morphism shadow-2xl relative">
            <div class="sticky top-0">
                <nav class="mt-8 pb-8">
                    <div class="px-6 space-y-3">
                        <a href="dashboard.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                </svg>
                            </div>
                            Dashboard
                        </a>
                        <a href="orders.php" class="nav-item active flex items-center px-6 py-4 transition-all duration-300 font-semibold text-lg rounded-2xl">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            Orders
                        </a>
                        <a href="menu.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            Menu Management
                        </a>
                        <a href="categories.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                            Categories
                        </a>
                        <a href="reservations.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                            Reservations
                        </a>
                        <a href="users.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                            Users
                        </a>
                        <a href="messages.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            Contact Messages
                        </a>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <div class="p-10">
                <!-- Header Section -->
                <div class="section-header rounded-3xl p-8 mb-10 animate-fade-in">
                    <div class="flex items-center">
                        <div class="relative">
                            <div class="w-20 h-20 icon-container rounded-3xl flex items-center justify-center mr-6 animate-glow">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="animate-slide-up">
                            <h1 class="text-4xl font-black golden-text mb-2">Order Management</h1>
                            <p class="text-warm-gray text-xl font-medium">Track and manage customer orders in real-time</p>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12 animate-slide-up">
                    <div class="gradient-card from-emerald-500 to-emerald-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Orders</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['total_orders'] ?></div>
                        <div class="text-white/80 text-base font-medium">All customer orders</div>
                    </div>
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Revenue</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path></svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3">Rs<?= number_format($stats['total_revenue'], 0) ?></div>
                        <div class="text-white/80 text-base font-medium">From all orders</div>
                    </div>
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Today's Orders</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['today_orders'] ?></div>
                        <div class="text-white/80 text-base font-medium">Orders placed today</div>
                    </div>
                    <div class="gradient-card from-golden-500 to-golden-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Avg Order Value</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3">Rs<?= number_format($stats['avg_order_value'], 2) ?></div>
                        <div class="text-white/80 text-base font-medium">Per order average</div>
                    </div>
                </div>

                <!-- Status Overview -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-12">
                    <?php
                    $status_config = [
                        'pending'   => ['icon' => '⏳', 'color' => 'from-yellow-400 to-yellow-600'],
                        'confirmed' => ['icon' => '✅', 'color' => 'from-blue-400 to-blue-600'],
                        'preparing' => ['icon' => '👨‍🍳', 'color' => 'from-purple-400 to-purple-600'],
                        'ready'     => ['icon' => '📋', 'color' => 'from-green-400 to-green-600'],
                        'delivered' => ['icon' => '🚚', 'color' => 'from-gray-400 to-gray-600'],
                        'cancelled' => ['icon' => '❌', 'color' => 'from-red-400 to-red-600']
                    ];
                    foreach ($status_config as $status => $config):
                        $count = $status_counts[$status] ?? 0;
                    ?>
                        <div class="glass-card rounded-2xl p-6 hover-lift border-l-4 border-golden-400">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-3xl"><?= $config['icon'] ?></span>
                                <div class="w-12 h-12 bg-gradient-to-br <?= $config['color'] ?> rounded-xl flex items-center justify-center shadow-lg">
                                    <span class="text-white font-bold text-lg"><?= $count ?></span>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-cafe-brown capitalize"><?= $status ?></h3>
                            <p class="text-sm text-warm-gray font-medium">Total <?= $status ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Orders Grid -->
                <div class="glass-card rounded-3xl shadow-2xl overflow-hidden">
                    <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-2xl mb-2">Customer Orders</h3>
                                    <p class="text-white/80 text-lg font-medium">Manage and track all customer orders</p>
                                </div>
                            </div>
                            <div class="text-white/90 text-lg">
                                <span class="bg-white/20 px-6 py-3 rounded-2xl font-black backdrop-blur-sm">
                                    <?= count($orders) ?> Total Orders
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-10">
                        <?php if (empty($orders)): ?>
                        <div class="text-center py-24">
                            <div class="w-40 h-40 mx-auto mb-10 bg-golden-200/20 rounded-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-golden-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                            <h3 class="text-2xl font-black text-cafe-brown mb-4">No Orders Yet</h3>
                            <p class="text-warm-gray mb-8 text-xl font-medium">Orders from customers will appear here once they start placing orders.</p>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card rounded-3xl shadow-2xl overflow-hidden hover-lift">
                                    <div class="bg-gradient-to-br from-golden-50 to-golden-100 p-6 border-b-2 border-golden-200">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-16 h-16 bg-gradient-to-br from-golden-500 to-golden-600 rounded-2xl flex items-center justify-center shadow-xl">
                                                <span class="text-white font-black text-lg">#<?= $order['id'] ?></span>
                                            </div>
                                            <div class="status-<?= $order['status'] ?> px-4 py-2 rounded-2xl text-sm font-black shadow-lg">
                                                <?= ucfirst($order['status']) ?>
                                            </div>
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                                                <span class="text-white font-bold text-lg">
                                                    <?= strtoupper(substr($order['full_name'], 0, 2)) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-black text-cafe-brown"><?= htmlspecialchars($order['full_name']) ?></h4>
                                                <p class="text-base text-golden-600 font-medium"><?= htmlspecialchars($order['email']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-6">
                                        <div class="space-y-4 mb-6">
                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Date & Time</span>
                                                <div class="text-right">
                                                    <div class="text-sm font-bold text-cafe-brown"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                                                    <div class="text-xs text-warm-gray"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
                                                </div>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Items</span>
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">
                                                    <?= $order['item_count'] ?> items
                                                </span>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Total Amount</span>
                                                <span class="text-2xl font-black text-golden-600">Rs<?= number_format($order['total_amount'], 2) ?></span>
                                            </div>
                                        </div>

                                        <div class="space-y-4 pt-6 border-top border-golden-200/50">
                                            <form method="POST" class="space-y-3" id="statusForm<?= $order['id'] ?>">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="old_status" value="<?= $order['status'] ?>">
                                                <input type="hidden" name="update_status" value="1">
                                                <label class="block text-sm font-bold text-cafe-brown">Update Status</label>
                                                <select name="status" onchange="toggleSaveButton(<?= $order['id'] ?>, '<?= $order['status'] ?>')"
                                                        class="w-full px-4 py-3 border-2 border-golden-200/50 rounded-xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold">
                                                    <option value="pending"   <?= $order['status'] === 'pending'   ? 'selected' : '' ?>>⏳ Pending</option>
                                                    <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                                                    <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>👨‍🍳 Preparing</option>
                                                    <option value="ready"     <?= $order['status'] === 'ready'     ? 'selected' : '' ?>>📋 Ready</option>
                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>🚚 Delivered</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                                </select>
                                                <button type="button"
                                                        onclick="saveOrderStatus(<?= $order['id'] ?>)"
                                                        id="saveButton<?= $order['id'] ?>"
                                                        class="w-full save-button flex items-center justify-center space-x-2"
                                                        disabled>
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                    <span>Save Status & Send Email</span>
                                                </button>
                                            </form>

                                            <a href="orders.php?view=<?= $order['id'] ?>"
                                               class="w-full bg-gradient-to-r from-golden-500 to-golden-600 text-white px-6 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg text-center block">
                                                View Full Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Enhanced Order Details Modal -->
    <?php if ($order_details): ?>
        <div class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" id="order-modal">
            <div class="modal-content rounded-3xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8 rounded-t-3xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-white mb-2">Order Details</h3>
                                <p class="text-white/80 text-lg font-medium">Order #<?= $order_details[0]['id'] ?> - <?= ucfirst($order_details[0]['status']) ?></p>
                            </div>
                        </div>
                        <a href="orders.php" class="text-white/80 hover:text-white p-2 hover:bg-white/10 rounded-xl transition-all duration-300" title="Close">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    </div>
                </div>

                <div class="p-10">
                    <div class="mb-10">
                        <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Customer Information
                        </h4>
                        <div class="bg-gradient-to-br from-golden-50 to-golden-100 rounded-3xl p-8 border-2 border-golden-200/50">
                            <div class="grid md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Name:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= htmlspecialchars($order_details[0]['full_name']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Email:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= htmlspecialchars($order_details[0]['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Phone:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= htmlspecialchars($order_details[0]['phone']) ?></span>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Date:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= date('M j, Y g:i A', strtotime($order_details[0]['created_at'])) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Status:</span>
                                        <span class="status-<?= $order_details[0]['status'] ?> px-4 py-2 rounded-2xl text-lg font-black">
                                            <?= ucfirst($order_details[0]['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 pt-6 border-t-2 border-golden-200/50">
                                <div class="flex justify-between items-start">
                                    <span class="text-lg font-bold text-warm-gray">Delivery Address:</span>
                                    <span class="text-lg font-medium text-cafe-brown text-right max-w-md"><?= htmlspecialchars($order_details[0]['delivery_address']) ?></span>
                                </div>
                                <?php if (!empty($order_details[0]['special_instructions'])): ?>
                                <div class="flex justify-between items-start mt-4">
                                    <span class="text-lg font-bold text-warm-gray">Special Notes:</span>
                                    <span class="text-lg font-medium text-cafe-brown text-right max-w-md"><?= htmlspecialchars($order_details[0]['special_instructions']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-10">
                        <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                            Order Items
                        </h4>
                        <div class="bg-gradient-to-br from-golden-50 to-golden-100 rounded-3xl p-8 border-2 border-golden-200/50">
                            <div class="space-y-6">
                                <?php foreach ($order_details as $item): ?>
                                <div class="flex items-center justify-between p-6 bg-white rounded-2xl shadow-lg border border-golden-200">
                                    <div class="flex-1">
                                        <h5 class="font-black text-xl text-cafe-brown mb-2"><?= htmlspecialchars($item['item_name']) ?></h5>
                                        <div class="flex items-center space-x-4">
                                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">Qty: <?= $item['quantity'] ?></span>
                                            <span class="text-lg font-bold text-warm-gray">× Rs<?= number_format($item['price'], 2) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-2xl font-black text-golden-600">Rs<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="border-t-2 border-golden-300 mt-8 pt-8">
                                <div class="flex justify-between items-center bg-white p-6 rounded-2xl shadow-lg">
                                    <span class="text-2xl font-black text-cafe-brown">Order Total</span>
                                    <span class="text-3xl font-black text-golden-600">Rs<?= number_format($order_details[0]['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-6 pt-8 border-t-2 border-golden-200/50">
                        <a href="orders.php" class="flex-1 bg-warm-gray/20 text-warm-gray px-8 py-4 rounded-2xl font-black text-lg hover:bg-warm-gray/30 transition-all duration-300 transform hover:scale-105 text-center flex items-center justify-center space-x-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            <span>Back to Orders</span>
                        </a>
                        <button onclick="window.print()" class="flex-1 bg-gradient-to-r from-golden-500 to-golden-600 text-white px-8 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg flex items-center justify-center space-x-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2-2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Print Order</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function toggleSaveButton(orderId, originalStatus) {
            const form = document.getElementById(`statusForm${orderId}`);
            const saveButton = document.getElementById(`saveButton${orderId}`);
            const statusSelect = form.querySelector('select[name="status"]');
            const hasChanged = statusSelect.value !== originalStatus;
            saveButton.disabled = !hasChanged;
            if (hasChanged) {
                saveButton.style.background = 'linear-gradient(135deg, #10B981, #059669)';
                saveButton.style.color = 'white';
            } else {
                saveButton.style.background = '#9CA3AF';
                saveButton.style.color = '#6B7280';
            }
        }
        function saveOrderStatus(orderId) {
            const form = document.getElementById(`statusForm${orderId}`);
            const saveButton = document.getElementById(`saveButton${orderId}`);
            const statusSelect = form.querySelector('select[name="status"]');
            const oldStatusInput = form.querySelector('input[name="old_status"]');
            const newStatus = statusSelect.value;
            const oldStatus = oldStatusInput.value;
            if (newStatus === oldStatus) { alert('No changes to save.'); return; }
            const statusLabels = {
                'pending': 'Pending', 'confirmed': 'Confirmed', 'preparing': 'Preparing',
                'ready': 'Ready', 'delivered': 'Delivered', 'cancelled': 'Cancelled'
            };
            if (confirm(`Are you sure you want to change order #${orderId} status from "${statusLabels[oldStatus]}" to "${statusLabels[newStatus]}"?\n\nAn email notification will be sent to the customer.`)) {
                saveButton.disabled = true;
                saveButton.innerHTML = `
                    <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    <span>Saving & Sending Email...</span>`;
                form.submit();
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[id^="saveButton"]').forEach(b => b.disabled = true);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('order-modal')) {
                    window.location.href = 'orders.php';
                }
            });
            const messages = document.querySelectorAll('.alert, .success, .error');
            messages.forEach(message => {
                setTimeout(() => {
                    if (message.parentElement) {
                        message.style.transition = 'opacity 0.5s ease-out';
                        message.style.opacity = '0';
                        setTimeout(() => { if (message.parentElement) message.remove(); }, 500);
                    }
                }, 8000);
            });
        });
    </script>
</body>
</html>
