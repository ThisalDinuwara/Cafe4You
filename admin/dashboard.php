<?php
// admin/dashboard.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get comprehensive statistics
$stats = [];

// Total orders
$orders_query = "SELECT COUNT(*) as total, SUM(total_amount) as revenue FROM orders";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute();
$orders_data = $orders_stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_orders'] = $orders_data['total'];
$stats['total_revenue'] = $orders_data['revenue'] ?? 0;

// Today's orders and revenue
$today_orders_query = "SELECT COUNT(*) as today_orders, SUM(total_amount) as today_revenue 
                       FROM orders WHERE DATE(created_at) = CURDATE()";
$today_orders_stmt = $db->prepare($today_orders_query);
$today_orders_stmt->execute();
$today_data = $today_orders_stmt->fetch(PDO::FETCH_ASSOC);
$stats['today_orders'] = $today_data['today_orders'] ?? 0;
$stats['today_revenue'] = $today_data['today_revenue'] ?? 0;

// This month's revenue
$month_revenue_query = "SELECT SUM(total_amount) as month_revenue 
                        FROM orders WHERE MONTH(created_at) = MONTH(CURDATE()) 
                        AND YEAR(created_at) = YEAR(CURDATE())";
$month_revenue_stmt = $db->prepare($month_revenue_query);
$month_revenue_stmt->execute();
$stats['month_revenue'] = $month_revenue_stmt->fetchColumn() ?? 0;

// Average order value
$stats['avg_order_value'] = $stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0;

// Total customers
$customers_query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
$customers_stmt = $db->prepare($customers_query);
$customers_stmt->execute();
$stats['total_customers'] = $customers_stmt->fetchColumn();

// New customers this month
$new_customers_query = "SELECT COUNT(*) as new_customers FROM users 
                        WHERE role = 'customer' AND MONTH(created_at) = MONTH(CURDATE()) 
                        AND YEAR(created_at) = YEAR(CURDATE())";
$new_customers_stmt = $db->prepare($new_customers_query);
$new_customers_stmt->execute();
$stats['new_customers'] = $new_customers_stmt->fetchColumn();

// Total menu items
$menu_query = "SELECT COUNT(*) as total FROM menu_items";
$menu_stmt = $db->prepare($menu_query);
$menu_stmt->execute();
$stats['total_menu_items'] = $menu_stmt->fetchColumn();

// Available menu items
$available_menu_query = "SELECT COUNT(*) as available FROM menu_items WHERE status = 'available'";
$available_menu_stmt = $db->prepare($available_menu_query);
$available_menu_stmt->execute();
$stats['available_menu_items'] = $available_menu_stmt->fetchColumn();

// Total reservations
$reservations_query = "SELECT COUNT(*) as total FROM reservations";
$reservations_stmt = $db->prepare($reservations_query);
$reservations_stmt->execute();
$stats['total_reservations'] = $reservations_stmt->fetchColumn();

// Today's reservations
$today_reservations_query = "SELECT COUNT(*) as today_reservations FROM reservations WHERE date = CURDATE()";
$today_reservations_stmt = $db->prepare($today_reservations_query);
$today_reservations_stmt->execute();
$stats['today_reservations'] = $today_reservations_stmt->fetchColumn();

// Pending reservations
$pending_reservations_query = "SELECT COUNT(*) as pending FROM reservations WHERE status = 'pending'";
$pending_reservations_stmt = $db->prepare($pending_reservations_query);
$pending_reservations_stmt->execute();
$stats['pending_reservations'] = $pending_reservations_stmt->fetchColumn();

// Order status breakdown
$order_status_query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$order_status_stmt = $db->prepare($order_status_query);
$order_status_stmt->execute();
$order_status_data = $order_status_stmt->fetchAll(PDO::FETCH_ASSOC);
$stats['order_status'] = [];
foreach ($order_status_data as $status) {
    $stats['order_status'][$status['status']] = $status['count'];
}

// Most popular menu items
$popular_items_query = "SELECT mi.name, SUM(oi.quantity) as total_ordered 
                        FROM order_items oi 
                        JOIN menu_items mi ON oi.menu_item_id = mi.id 
                        GROUP BY mi.id, mi.name 
                        ORDER BY total_ordered DESC 
                        LIMIT 5";
$popular_items_stmt = $db->prepare($popular_items_query);
$popular_items_stmt->execute();
$popular_items = $popular_items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent orders
$recent_orders_query = "SELECT o.id, o.total_amount, o.status, o.created_at, u.full_name, u.email 
                       FROM orders o 
                       JOIN users u ON o.user_id = u.id 
                       ORDER BY o.created_at DESC 
                       LIMIT 5";
$recent_orders_stmt = $db->prepare($recent_orders_query);
$recent_orders_stmt->execute();
$recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent reservations
$recent_reservations_query = "SELECT * FROM reservations ORDER BY created_at DESC LIMIT 5";
$recent_reservations_stmt = $db->prepare($recent_reservations_query);
$recent_reservations_stmt->execute();
$recent_reservations = $recent_reservations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending contact messages
$messages_query = "SELECT COUNT(*) as total FROM contact_messages WHERE status = 'unread'";
$messages_stmt = $db->prepare($messages_query);
$messages_stmt->execute();
$stats['unread_messages'] = $messages_stmt->fetchColumn();

// Monthly revenue chart data (last 6 months)
$monthly_revenue_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(total_amount) as revenue,
    COUNT(*) as orders
    FROM orders 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6";
$monthly_revenue_stmt = $db->prepare($monthly_revenue_query);
$monthly_revenue_stmt->execute();
$monthly_data = $monthly_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cafe For You</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        'pulse-soft': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.8 }
                        },
                        'slide-up': {
                            '0%': { transform: 'translateY(30px)', opacity: 0 },
                            '100%': { transform: 'translateY(0)', opacity: 1 }
                        },
                        'fade-in': {
                            '0%': { opacity: 0 },
                            '100%': { opacity: 1 }
                        },
                        'bounce-gentle': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 20px rgba(255, 199, 40, 0.3)' },
                            '100%': { boxShadow: '0 0 30px rgba(255, 199, 40, 0.6)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-1000px 0' },
                            '100%': { backgroundPosition: '1000px 0' }
                        }
                    },
                    backdropBlur: {
                        xs: '2px'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        
        body { 
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #FFFDF7 0%, #FFF9E6 25%, #FFF0B8 50%, #FFE388 75%, #FFD558 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        .glass-morphism {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 199, 40, 0.18);
            box-shadow: 0 8px 32px rgba(255, 199, 40, 0.37);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 199, 40, 0.25);
            box-shadow: 0 12px 40px rgba(255, 184, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(255, 184, 0, 0.25);
        }

        .gradient-card {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .gradient-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                transparent 30%, 
                rgba(255, 255, 255, 0.1) 50%, 
                transparent 70%);
            transform: rotate(45deg);
            transition: all 0.6s ease;
            opacity: 0;
        }

        .gradient-card:hover::before {
            opacity: 1;
            transform: rotate(45deg) translate(50%, 50%);
        }

        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }

        .nav-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 16px;
            margin: 6px 12px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 199, 40, 0.2), transparent);
            transition: left 0.5s;
        }

        .nav-item:hover::before {
            left: 100%;
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, #FFC728, #FFB800, #F5B800);
            color: #8B4513;
            box-shadow: 0 8px 32px rgba(255, 199, 40, 0.4);
            font-weight: 600;
            transform: translateX(8px);
        }

        .nav-item.active svg {
            color: #8B4513;
            filter: drop-shadow(0 2px 4px rgba(139, 69, 19, 0.2));
        }

        .nav-item:not(.active):hover {
            background: rgba(255, 199, 40, 0.15);
            color: #F5B800;
            transform: translateX(8px) translateY(-2px);
            box-shadow: 0 4px 20px rgba(255, 199, 40, 0.3);
        }

        .stat-number {
            background: linear-gradient(135deg, #F5B800 0%, #FFC728 50%, #FFB800 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 8px rgba(255, 199, 40, 0.3);
        }

        .icon-container {
            background: linear-gradient(135deg, #FFC728, #FFB800);
            box-shadow: 0 8px 32px rgba(255, 199, 40, 0.4);
            position: relative;
            overflow: hidden;
        }

        .icon-container::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            animation: glow 3s ease-in-out infinite;
        }

        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.33);
                opacity: 1;
            }
            80%, 100% {
                transform: scale(2.33);
                opacity: 0;
            }
        }

        .status-indicator {
            position: relative;
        }

        .status-indicator::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: inherit;
            border-radius: inherit;
            animation: pulse-ring 2s infinite;
            z-index: -1;
        }

        .shimmer-bg {
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.4),
                transparent
            );
            background-size: 200% 100%;
            animation: shimmer 2.5s infinite;
        }

        .hover-lift {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .hover-lift:hover {
            transform: translateY(-10px) rotateX(5deg);
            box-shadow: 0 25px 80px rgba(255, 184, 0, 0.3);
        }

        .notification-badge {
            animation: bounce-gentle 2s ease-in-out infinite;
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        .chart-container {
            background: linear-gradient(135deg, rgba(255, 253, 247, 0.9), rgba(255, 249, 230, 0.9));
            border-radius: 24px;
            padding: 32px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 199, 40, 0.2);
        }

        .section-header {
            background: linear-gradient(135deg, rgba(255, 199, 40, 0.1), rgba(255, 184, 0, 0.05));
            border-left: 6px solid #FFC728;
            backdrop-filter: blur(10px);
        }

        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        .card-border-glow {
            position: relative;
        }

        .card-border-glow::before {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(45deg, #FFC728, #FFB800, #FFC728, #FFB800);
            background-size: 300% 300%;
            border-radius: inherit;
            z-index: -1;
            animation: shimmer 3s ease infinite;
            opacity: 0.6;
        }

        .golden-text {
            background: linear-gradient(135deg, #FFB800 0%, #FFC728 25%, #FFD700 50%, #FFC728 75%, #FFB800 100%);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
            animation: shimmer 3s linear infinite;
        }

        .interactive-element {
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .interactive-element:hover {
            transform: scale(1.05);
        }

        .interactive-element:active {
            transform: scale(0.98);
        }

        @media (max-width: 768px) {
            .glass-card:hover {
                transform: none;
            }
        }
    </style>
</head>
<body class="font-sans">
    <!-- Floating Background Elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-32 h-32 bg-golden-200 rounded-full opacity-20 floating-animation"></div>
        <div class="absolute top-3/4 right-1/4 w-24 h-24 bg-golden-300 rounded-full opacity-15 floating-animation" style="animation-delay: -2s;"></div>
        <div class="absolute top-1/2 left-3/4 w-40 h-40 bg-golden-100 rounded-full opacity-10 floating-animation" style="animation-delay: -4s;"></div>
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
                        <p class="text-warm-gray font-medium text-lg">Admin Dashboard</p>
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
                        <a href="dashboard.php" class="nav-item active flex items-center px-6 py-4 transition-all duration-300 font-semibold text-lg rounded-2xl">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                </svg>
                            </div>
                            Dashboard
                        </a>
                        <a href="orders.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
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
                        <a href="messages.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg relative">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            Contact Messages
                            <?php if ($stats['unread_messages'] > 0): ?>
                                <span class="notification-badge text-white text-xs px-3 py-1 rounded-full ml-auto font-bold">
                                    <?= $stats['unread_messages'] ?>
                                </span>
                            <?php endif; ?>
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="animate-slide-up">
                            <h1 class="text-4xl font-black golden-text mb-2">Dashboard Overview</h1>
                            <p class="text-warm-gray text-xl font-medium">Real-time insights and analytics for your restaurant</p>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Key Performance Indicators -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12 animate-slide-up">
                    <!-- Total Revenue Card -->
                    <div class="gradient-card from-emerald-500 to-emerald-600 rounded-3xl p-8 text-white hover-lift card-border-glow">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Revenue</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3">Rs<?= number_format($stats['total_revenue'], 2) ?></div>
                        <div class="text-white/80 text-base font-medium">All time earnings</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Total Orders Card -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-3xl p-8 text-white hover-lift card-border-glow">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Orders</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= number_format($stats['total_orders']) ?></div>
                        <div class="text-white/80 text-base font-medium">Avg: Rs<?= number_format($stats['avg_order_value'], 2) ?></div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Total Customers Card -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-3xl p-8 text-white hover-lift card-border-glow">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Customers</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= number_format($stats['total_customers']) ?></div>
                        <div class="text-white/80 text-base font-medium">+<?= $stats['new_customers'] ?> this month</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Menu Items Card -->
                    <div class="gradient-card from-golden-500 to-golden-600 rounded-3xl p-8 text-white hover-lift card-border-glow">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Menu Items</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= number_format($stats['total_menu_items']) ?></div>
                        <div class="text-white/80 text-base font-medium"><?= $stats['available_menu_items'] ?> available</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>
                </div>

                <!-- Today's Statistics -->
                <div class="grid md:grid-cols-4 gap-8 mb-12">
                    <div class="glass-card rounded-2xl p-8 border-l-8 border-emerald-400 hover-lift">
                        <h3 class="text-lg font-bold text-warm-gray mb-3">Today's Revenue</h3>
                        <p class="text-3xl font-black stat-number">Rs<?= number_format($stats['today_revenue'], 2) ?></p>
                        <div class="mt-3 text-base text-emerald-600 font-semibold">💰 Daily earnings</div>
                        <div class="mt-4 flex items-center">
                            <div class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></div>
                            <div class="ml-2 text-xs text-emerald-600 font-medium">Live tracking</div>
                        </div>
                    </div>
                    <div class="glass-card rounded-2xl p-8 border-l-8 border-blue-400 hover-lift">
                        <h3 class="text-lg font-bold text-warm-gray mb-3">Today's Orders</h3>
                        <p class="text-3xl font-black stat-number"><?= number_format($stats['today_orders']) ?></p>
                        <div class="mt-3 text-base text-blue-600 font-semibold">📦 Orders today</div>
                        <div class="mt-4 flex items-center">
                            <div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
                            <div class="ml-2 text-xs text-blue-600 font-medium">Real-time</div>
                        </div>
                    </div>
                    <div class="glass-card rounded-2xl p-8 border-l-8 border-purple-400 hover-lift">
                        <h3 class="text-lg font-bold text-warm-gray mb-3">Today's Reservations</h3>
                        <p class="text-3xl font-black stat-number"><?= number_format($stats['today_reservations']) ?></p>
                        <div class="mt-3 text-base text-purple-600 font-semibold">📅 Bookings today</div>
                        <div class="mt-4 flex items-center">
                            <div class="w-2 h-2 bg-purple-400 rounded-full animate-pulse"></div>
                            <div class="ml-2 text-xs text-purple-600 font-medium">Updated</div>
                        </div>
                    </div>
                    <div class="glass-card rounded-2xl p-8 border-l-8 border-amber-400 hover-lift">
                        <h3 class="text-lg font-bold text-warm-gray mb-3">Pending Reservations</h3>
                        <p class="text-3xl font-black stat-number"><?= number_format($stats['pending_reservations']) ?></p>
                        <div class="mt-3 text-base text-amber-600 font-semibold">⏳ Awaiting confirmation</div>
                        <div class="mt-4 flex items-center">
                            <div class="w-2 h-2 bg-amber-400 rounded-full animate-bounce-gentle"></div>
                            <div class="ml-2 text-xs text-amber-600 font-medium">Needs attention</div>
                        </div>
                    </div>
                </div>

                <!-- Charts and Analytics Section -->
                <div class="grid lg:grid-cols-2 gap-10 mb-12">
                    <!-- Revenue Chart -->
                    <div class="glass-card rounded-3xl shadow-2xl p-8 hover-lift">
                        <h3 class="text-2xl font-bold text-cafe-brown mb-6 flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-r from-golden-400 to-golden-600 rounded-xl flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <span class="golden-text">Monthly Revenue (Last 6 Months)</span>
                        </h3>
                        <div class="chart-container">
                            <canvas id="revenueChart" width="400" height="250"></canvas>
                        </div>
                    </div>

                    <!-- Order Status Distribution -->
                    <div class="glass-card rounded-3xl shadow-2xl p-8 hover-lift">
                        <h3 class="text-2xl font-bold text-cafe-brown mb-6 flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-r from-golden-400 to-golden-600 rounded-xl flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 2.05V9h6.95A7.002 7.002 0 0013 2.05z"></path>
                                </svg>
                            </div>
                            <span class="golden-text">Order Status Distribution</span>
                        </h3>
                        <div class="space-y-6">
                            <?php
                            $status_colors = [
                                'pending' => 'bg-yellow-400',
                                'confirmed' => 'bg-blue-400',
                                'preparing' => 'bg-purple-400',
                                'ready' => 'bg-green-400',
                                'delivered' => 'bg-gray-400',
                                'cancelled' => 'bg-red-400'
                            ];
                            
                            $status_icons = [
                                'pending' => '⏳',
                                'confirmed' => '✅',
                                'preparing' => '👨‍🍳',
                                'ready' => '📋',
                                'delivered' => '🚚',
                                'cancelled' => '❌'
                            ];
                            
                            foreach ($stats['order_status'] as $status => $count):
                                $percentage = $stats['total_orders'] > 0 ? ($count / $stats['total_orders']) * 100 : 0;
                            ?>
                                <div class="interactive-element flex items-center justify-between p-5 rounded-2xl bg-gradient-to-r from-white/80 to-white/60 hover:from-golden-50 hover:to-golden-100 transition-all duration-300 border border-golden-200/50">
                                    <div class="flex items-center">
                                        <div class="w-6 h-6 <?= $status_colors[$status] ?? 'bg-gray-400' ?> rounded-lg mr-4 status-indicator"></div>
                                        <span class="text-lg font-bold text-cafe-brown"><?= $status_icons[$status] ?? '📦' ?> <?= ucfirst($status) ?></span>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <span class="text-lg font-bold text-warm-gray"><?= $count ?></span>
                                        <div class="w-32 bg-gray-200 rounded-full h-3 overflow-hidden">
                                            <div class="h-full <?= $status_colors[$status] ?? 'bg-gray-400' ?> rounded-full transition-all duration-1000 ease-out shimmer-bg" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <span class="text-sm text-warm-gray font-bold w-12 text-right"><?= number_format($percentage, 1) ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Popular Items -->
                <div class="glass-card rounded-3xl shadow-2xl p-10 mb-12 hover-lift">
                    <h3 class="text-2xl font-bold text-cafe-brown mb-8 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-golden-400 to-golden-600 rounded-xl flex items-center justify-center mr-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                        </div>
                        <span class="golden-text">Most Popular Menu Items</span>
                    </h3>
                    <div class="grid md:grid-cols-5 gap-6">
                        <?php foreach ($popular_items as $index => $item): ?>
                            <div class="text-center p-6 rounded-3xl bg-gradient-to-br from-golden-50 to-golden-100 hover-lift transition-all duration-300 border border-golden-200">
                                <div class="relative mb-6">
                                    <div class="w-16 h-16 bg-gradient-to-r from-golden-400 to-golden-600 text-white rounded-2xl flex items-center justify-center mx-auto shadow-2xl">
                                        <span class="text-2xl font-black"><?= $index + 1 ?></span>
                                    </div>
                                    <div class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs font-bold">🏆</div>
                                </div>
                                <h4 class="font-bold text-cafe-brown text-lg mb-4"><?= htmlspecialchars($item['name']) ?></h4>
                                <div class="bg-gradient-to-r from-golden-400 to-golden-600 px-4 py-2 rounded-2xl">
                                    <p class="text-sm font-bold text-white"><?= $item['total_ordered'] ?> orders</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="grid lg:grid-cols-2 gap-10">
                    <!-- Recent Orders -->
                    <div class="glass-card rounded-3xl shadow-2xl overflow-hidden hover-lift">
                        <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-8 py-6">
                            <h3 class="text-white font-bold text-2xl flex items-center">
                                <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                                Recent Orders
                            </h3>
                        </div>
                        <div class="p-8">
                            <?php if (empty($recent_orders)): ?>
                                <div class="text-center py-12">
                                    <div class="w-20 h-20 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-warm-gray text-xl">No orders yet</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php foreach ($recent_orders as $order): ?>
                                        <div class="interactive-element flex items-center justify-between p-6 border-2 border-golden-200/50 rounded-2xl hover:bg-golden-50 transition-all duration-300 cursor-pointer" onclick="copyOrderId(<?= $order['id'] ?>)">
                                            <div class="flex items-center">
                                                <div class="w-12 h-12 bg-gradient-to-r from-golden-400 to-golden-600 rounded-xl flex items-center justify-center mr-4">
                                                    <span class="text-white font-bold">#</span>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-cafe-brown text-lg">Order #<?= $order['id'] ?></p>
                                                    <p class="text-base text-golden-700 font-medium"><?= htmlspecialchars($order['full_name']) ?></p>
                                                    <p class="text-sm text-warm-gray"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-black text-cafe-brown text-xl">Rs<?= number_format($order['total_amount'], 2) ?></p>
                                                <span class="inline-flex px-4 py-2 text-sm font-bold rounded-2xl
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                                                        'confirmed' => 'bg-blue-100 text-blue-800 border border-blue-300',
                                                        'preparing' => 'bg-purple-100 text-purple-800 border border-purple-300',
                                                        'ready' => 'bg-green-100 text-green-800 border border-green-300',
                                                        'delivered' => 'bg-gray-100 text-gray-800 border border-gray-300',
                                                        'cancelled' => 'bg-red-100 text-red-800 border border-red-300'
                                                    ];
                                                    echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800 border border-gray-300';
                                                    ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-8 text-center">
                                    <a href="orders.php" class="inline-flex items-center text-golden-700 hover:text-golden-800 font-bold text-lg transition-colors group">
                                        View All Orders
                                        <svg class="w-5 h-5 ml-2 transform group-hover:translate-x-2 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Reservations -->
                    <div class="glass-card rounded-3xl shadow-2xl overflow-hidden hover-lift">
                        <div class="bg-gradient-to-r from-golden-600 to-amber-600 px-8 py-6">
                            <h3 class="text-white font-bold text-2xl flex items-center">
                                <svg class="w-7 h-7 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                                Recent Reservations
                            </h3>
                        </div>
                        <div class="p-8">
                            <?php if (empty($recent_reservations)): ?>
                                <div class="text-center py-12">
                                    <div class="w-20 h-20 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-warm-gray text-xl">No reservations yet</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php foreach ($recent_reservations as $reservation): ?>
                                        <div class="flex items-center justify-between p-6 border-2 border-golden-200/50 rounded-2xl hover:bg-golden-50 transition-all duration-300">
                                            <div class="flex items-center">
                                                <div class="w-12 h-12 bg-gradient-to-r from-amber-400 to-amber-600 rounded-xl flex items-center justify-center mr-4">
                                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-cafe-brown text-lg"><?= htmlspecialchars($reservation['name']) ?></p>
                                                    <p class="text-base text-amber-700 font-medium"><?= date('M j, Y', strtotime($reservation['date'])) ?> at <?= date('g:i A', strtotime($reservation['time'])) ?></p>
                                                    <p class="text-sm text-warm-gray"><?= $reservation['guests'] ?> guests</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="inline-flex px-4 py-2 text-sm font-bold rounded-2xl
                                                    <?php
                                                    $status_colors = [
                                                        'pending' => 'bg-yellow-100 text-yellow-800 border border-yellow-300',
                                                        'confirmed' => 'bg-green-100 text-green-800 border border-green-300',
                                                        'cancelled' => 'bg-red-100 text-red-800 border border-red-300'
                                                    ];
                                                    echo $status_colors[$reservation['status']] ?? 'bg-gray-100 text-gray-800 border border-gray-300';
                                                    ?>">
                                                    <?= ucfirst($reservation['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-8 text-center">
                                    <a href="reservations.php" class="inline-flex items-center text-amber-700 hover:text-amber-800 font-bold text-lg transition-colors group">
                                        View All Reservations
                                        <svg class="w-5 h-5 ml-2 transform group-hover:translate-x-2 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Section -->
                <div class="mt-12 glass-card rounded-3xl shadow-2xl p-10">
                    <h3 class="text-2xl font-bold text-cafe-brown mb-8 flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-r from-golden-400 to-golden-600 rounded-xl flex items-center justify-center mr-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <span class="golden-text">Quick Actions</span>
                    </h3>
                    <div class="grid md:grid-cols-4 gap-6">
                        <a href="orders.php" class="interactive-element group bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white hover:shadow-2xl transition-all duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                                <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold mb-2">Manage Orders</h4>
                            <p class="text-white/80 text-sm">View and update order status</p>
                        </a>

                        <a href="menu.php" class="interactive-element group bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white hover:shadow-2xl transition-all duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"></path>
                                </svg>
                                <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold mb-2">Update Menu</h4>
                            <p class="text-white/80 text-sm">Add or modify menu items</p>
                        </a>

                        <a href="reservations.php" class="interactive-element group bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white hover:shadow-2xl transition-all duration-300">
                            <div class="flex items-center justify-between mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                                <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold mb-2">Reservations</h4>
                            <p class="text-white/80 text-sm">Manage table bookings</p>
                        </a>

                        <a href="messages.php" class="interactive-element group bg-gradient-to-br from-golden-500 to-golden-600 rounded-2xl p-6 text-white hover:shadow-2xl transition-all duration-300 relative">
                            <div class="flex items-center justify-between mb-4">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold mb-2">Messages</h4>
                            <p class="text-white/80 text-sm">View customer inquiries</p>
                            <?php if ($stats['unread_messages'] > 0): ?>
                                <div class="absolute -top-2 -right-2 notification-badge text-white text-xs px-3 py-1 rounded-full font-bold">
                                    <?= $stats['unread_messages'] ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-3xl p-8 flex items-center space-x-4">
            <div class="w-8 h-8 border-4 border-golden-400 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-cafe-brown font-semibold">Loading...</span>
        </div>
    </div>

    <script>
        // Enhanced Chart Configuration
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?= json_encode(array_reverse($monthly_data)) ?>;
        
        // Create gradient for the chart
        const gradient = revenueCtx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(255, 199, 40, 0.4)');
        gradient.addColorStop(1, 'rgba(255, 199, 40, 0.05)');

        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue (Rs)',
                    data: monthlyData.map(item => parseFloat(item.revenue || 0)),
                    borderColor: '#FFC728',
                    backgroundColor: gradient,
                    tension: 0.4,
                    fill: true,
                    borderWidth: 4,
                    pointBackgroundColor: '#FFFFFF',
                    pointBorderColor: '#FFC728',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#FFC728',
                    pointHoverBorderColor: '#FFFFFF',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 199, 40, 0.95)',
                        titleColor: '#8B4513',
                        bodyColor: '#8B4513',
                        borderColor: '#FFC728',
                        borderWidth: 2,
                        cornerRadius: 12,
                        displayColors: false,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13,
                            weight: '600'
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: Rs' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 199, 40, 0.15)',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'Rs' + value.toLocaleString();
                            },
                            color: '#78716C',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            padding: 10
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 199, 40, 0.15)',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: '#78716C',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            padding: 10
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });

        // Enhanced interactions and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states
            const showLoading = () => {
                document.getElementById('loadingOverlay').classList.remove('hidden');
            };

            const hideLoading = () => {
                document.getElementById('loadingOverlay').classList.add('hidden');
            };

            // Enhanced card hover effects
            const cards = document.querySelectorAll('.hover-lift');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) rotateX(5deg)';
                    this.style.boxShadow = '0 25px 80px rgba(255, 184, 0, 0.3)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });

            // Interactive elements
            const interactiveElements = document.querySelectorAll('.interactive-element');
            interactiveElements.forEach(element => {
                element.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.98)';
                });
                element.addEventListener('mouseup', function() {
                    this.style.transform = '';
                });
                element.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });

            // Navigation enhancement
            const navItems = document.querySelectorAll('.nav-item:not(.active)');
            navItems.forEach(item => {
                let hoverTimeout;
                item.addEventListener('mouseenter', function() {
                    clearTimeout(hoverTimeout);
                    this.style.transform = 'translateX(8px) translateY(-2px)';
                    this.style.background = 'rgba(255, 199, 40, 0.15)';
                    this.style.boxShadow = '0 4px 20px rgba(255, 199, 40, 0.3)';
                });
                item.addEventListener('mouseleave', function() {
                    hoverTimeout = setTimeout(() => {
                        this.style.transform = '';
                        this.style.background = '';
                        this.style.boxShadow = '';
                    }, 150);
                });
            });

            // Auto-refresh with visual feedback
            let refreshInterval = setInterval(function() {
                showLoading();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }, 300000); // 5 minutes

            // Add refresh button functionality
            window.refreshDashboard = function() {
                showLoading();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            };

            // Notification animations
            const notifications = document.querySelectorAll('.notification-badge');
            notifications.forEach(notification => {
                setInterval(() => {
                    notification.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        notification.style.transform = '';
                    }, 200);
                }, 3000);
            });
        });

        // Enhanced copy functionality with better feedback
        function copyOrderId(orderId) {
            const orderText = 'Order #' + orderId;
            navigator.clipboard.writeText(orderText).then(function() {
                // Create enhanced toast notification
                const toast = document.createElement('div');
                toast.className = 'fixed top-8 right-8 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-2xl shadow-2xl z-50 flex items-center space-x-3 transform translate-x-full transition-transform duration-300';
                toast.innerHTML = `
                    <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <span class="font-semibold">Order ID copied to clipboard!</span>
                `;
                document.body.appendChild(toast);
                
                // Animate in
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                }, 100);
                
                // Animate out
                setTimeout(() => {
                    toast.style.transform = 'translateX(full)';
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 300);
                }, 3000);
            }).catch(function() {
                // Fallback notification
                alert('Order ID: ' + orderText);
            });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        refreshDashboard();
                        break;
                    case '1':
                        e.preventDefault();
                        window.location.href = 'orders.php';
                        break;
                    case '2':
                        e.preventDefault();
                        window.location.href = 'menu.php';
                        break;
                    case '3':
                        e.preventDefault();
                        window.location.href = 'reservations.php';
                        break;
                }
            }
        });

        // Performance optimization - lazy load heavy elements
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '50px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe elements for lazy animations
        document.querySelectorAll('.glass-card, .gradient-card').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>