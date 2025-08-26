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
                        'brand-orange': '#FF6B35',
                        'brand-cream': '#FFF8F0'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-brand-orange rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <h1 class="text-2xl font-bold text-brand-orange">Cafe For You - Admin</h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition">View Site</a>
                    <a href="../logout.php" class="bg-brand-orange text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg min-h-screen">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-brand-orange font-medium">
                        📊 Dashboard
                    </a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        🛒 Orders
                    </a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        🍽️ Menu Management
                    </a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📂 Categories
                    </a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📅 Reservations
                    </a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        👥 Users
                    </a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📧 Messages
                        <?php if ($stats['unread_messages'] > 0): ?>
                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-2">
                                <?= $stats['unread_messages'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800">📈 Dashboard Overview</h1>
                <p class="text-gray-600 mt-2">Welcome to your restaurant management system - Real-time insights and analytics</p>
            </div>

            <?php displayMessage(); ?>

            <!-- Key Performance Indicators -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Revenue Card -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Revenue</p>
                            <p class="text-3xl font-bold">$<?= number_format($stats['total_revenue'], 2) ?></p>
                            <p class="text-green-100 text-xs mt-1">All time</p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9M19 21H5V3H13V9H19Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Orders Card -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Orders</p>
                            <p class="text-3xl font-bold"><?= number_format($stats['total_orders']) ?></p>
                            <p class="text-blue-100 text-xs mt-1">Average: $<?= number_format($stats['avg_order_value'], 2) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 18C5.9 18 5 18.9 5 20S5.9 22 7 22 9 21.1 9 20 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5H5.21L4.27 3H1ZM17 18C15.9 18 15 18.9 15 20S15.9 22 17 22 19 21.1 19 20 18.1 18 17 18Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Customers Card -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Total Customers</p>
                            <p class="text-3xl font-bold"><?= number_format($stats['total_customers']) ?></p>
                            <p class="text-purple-100 text-xs mt-1">+<?= $stats['new_customers'] ?> this month</p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16 4C18.2 4 20 5.8 20 8S18.2 12 16 12 12 10.2 12 8 13.8 4 16 4M16 14C20.4 14 24 15.8 24 18V20H8V18C8 15.8 11.6 14 16 14M12.5 11.5C14.4 11.5 16 9.9 16 8S14.4 4.5 12.5 4.5 9 6.1 9 7.5 10.6 11.5 12.5 11.5M12.5 13C8.1 13 0 15.1 0 19.5V22H12.5C11 21.2 10 19.9 10 18.5C10 16.1 11.1 14 12.5 13Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Menu Items Card -->
                <div class="bg-gradient-to-r from-brand-orange to-red-500 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Menu Items</p>
                            <p class="text-3xl font-bold"><?= number_format($stats['total_menu_items']) ?></p>
                            <p class="text-orange-100 text-xs mt-1"><?= $stats['available_menu_items'] ?> available</p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.1 13.34L2.91 8.15C2.35 7.59 2.35 6.66 2.91 6.1S4.25 5.54 4.81 6.1L9.5 10.79L19.19 1.1C19.75 .54 20.68 .54 21.24 1.1S21.8 2.44 21.24 3L10.81 13.43C10.25 13.99 9.32 13.99 8.76 13.43L8.1 13.34Z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Statistics -->
            <div class="grid md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                    <h3 class="text-sm font-medium text-gray-500">Today's Revenue</h3>
                    <p class="text-2xl font-bold text-gray-900">$<?= number_format($stats['today_revenue'], 2) ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                    <h3 class="text-sm font-medium text-gray-500">Today's Orders</h3>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['today_orders']) ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                    <h3 class="text-sm font-medium text-gray-500">Today's Reservations</h3>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['today_reservations']) ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                    <h3 class="text-sm font-medium text-gray-500">Pending Reservations</h3>
                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['pending_reservations']) ?></p>
                </div>
            </div>

            <!-- Charts and Analytics Section -->
            <div class="grid lg:grid-cols-2 gap-8 mb-8">
                <!-- Revenue Chart -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">📈 Monthly Revenue (Last 6 Months)</h3>
                    <canvas id="revenueChart" width="400" height="200"></canvas>
                </div>

                <!-- Order Status Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">📊 Order Status Distribution</h3>
                    <div class="space-y-4">
                        <?php
                        $status_colors = [
                            'pending' => 'bg-yellow-500',
                            'confirmed' => 'bg-blue-500',
                            'preparing' => 'bg-purple-500',
                            'ready' => 'bg-green-500',
                            'delivered' => 'bg-gray-500',
                            'cancelled' => 'bg-red-500'
                        ];
                        
                        foreach ($stats['order_status'] as $status => $count):
                            $percentage = $stats['total_orders'] > 0 ? ($count / $stats['total_orders']) * 100 : 0;
                        ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 <?= $status_colors[$status] ?? 'bg-gray-400' ?> rounded mr-3"></div>
                                    <span class="text-sm font-medium text-gray-700"><?= ucfirst($status) ?></span>
                                </div>
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-600 mr-2"><?= $count ?></span>
                                    <div class="w-20 bg-gray-200 rounded-full h-2">
                                        <div class="<?= $status_colors[$status] ?? 'bg-gray-400' ?> h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Popular Items -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">🍽️ Most Popular Menu Items</h3>
                <div class="grid md:grid-cols-5 gap-4">
                    <?php foreach ($popular_items as $index => $item): ?>
                        <div class="text-center p-4 rounded-lg bg-gray-50">
                            <div class="w-8 h-8 bg-brand-orange text-white rounded-full flex items-center justify-center mx-auto mb-2 text-sm font-bold">
                                <?= $index + 1 ?>
                            </div>
                            <h4 class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($item['name']) ?></h4>
                            <p class="text-xs text-gray-500"><?= $item['total_ordered'] ?> orders</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Recent Orders -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">🛒 Recent Orders</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-gray-500 text-center py-4">No orders yet</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                        <div>
                                            <p class="font-medium text-gray-900">Order #<?= $order['id'] ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($order['full_name']) ?></p>
                                            <p class="text-xs text-gray-400"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900">$<?= number_format($order['total_amount'], 2) ?></p>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'confirmed' => 'bg-blue-100 text-blue-800',
                                                    'preparing' => 'bg-purple-100 text-purple-800',
                                                    'ready' => 'bg-green-100 text-green-800',
                                                    'delivered' => 'bg-gray-100 text-gray-800',
                                                    'cancelled' => 'bg-red-100 text-red-800'
                                                ];
                                                echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="orders.php" class="text-brand-orange hover:text-orange-700 font-medium">View All Orders →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">📅 Recent Reservations</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_reservations)): ?>
                            <p class="text-gray-500 text-center py-4">No reservations yet</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_reservations as $reservation): ?>
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                        <div>
                                            <p class="font-medium text-gray-900"><?= htmlspecialchars($reservation['name']) ?></p>
                                            <p class="text-sm text-gray-500"><?= date('M j, Y', strtotime($reservation['date'])) ?> at <?= date('g:i A', strtotime($reservation['time'])) ?></p>
                                            <p class="text-xs text-gray-400"><?= $reservation['guests'] ?> guests</p>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'confirmed' => 'bg-green-100 text-green-800',
                                                    'cancelled' => 'bg-red-100 text-red-800'
                                                ];
                                                echo $status_colors[$reservation['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?= ucfirst($reservation['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4 text-center">
                                <a href="reservations.php" class="text-brand-orange hover:text-orange-700 font-medium">View All Reservations →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?= json_encode(array_reverse($monthly_data)) ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue ($)',
                    data: monthlyData.map(item => parseFloat(item.revenue || 0)),
                    borderColor: '#FF6B35',
                    backgroundColor: 'rgba(255, 107, 53, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Auto-refresh dashboard data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);

        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.bg-gradient-to-r');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'transform 0.2s ease-in-out';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // Add click-to-copy functionality for order IDs
        function copyOrderId(orderId) {
            navigator.clipboard.writeText('Order #' + orderId).then(function() {
                // Show temporary success message
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                toast.textContent = 'Order ID copied to clipboard!';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 2000);
            });
        }
    </script>
</body>
</html>