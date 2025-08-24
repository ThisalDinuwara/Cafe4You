<?php
// admin/dashboard.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [];

// Total orders
$orders_query = "SELECT COUNT(*) as total, SUM(total_amount) as revenue FROM orders";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute();
$orders_data = $orders_stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_orders'] = $orders_data['total'];
$stats['total_revenue'] = $orders_data['revenue'] ?? 0;

// Total customers
$customers_query = "SELECT COUNT(*) as total FROM users WHERE role = 'customer'";
$customers_stmt = $db->prepare($customers_query);
$customers_stmt->execute();
$stats['total_customers'] = $customers_stmt->fetchColumn();

// Total menu items
$menu_query = "SELECT COUNT(*) as total FROM menu_items";
$menu_stmt = $db->prepare($menu_query);
$menu_stmt->execute();
$stats['total_menu_items'] = $menu_stmt->fetchColumn();

// Total reservations
$reservations_query = "SELECT COUNT(*) as total FROM reservations";
$reservations_stmt = $db->prepare($reservations_query);
$reservations_stmt->execute();
$stats['total_reservations'] = $reservations_stmt->fetchColumn();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Delicious Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-orange-400">Admin Panel</h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition">View Site</a>
                    <a href="../logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg min-h-screen">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-orange-600 font-medium">
                        Dashboard
                    </a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        Orders
                    </a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        Menu Management
                    </a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        Categories
                    </a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        Reservations
                    </a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        Users
                    </a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        Contact Messages
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
                <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
                <p class="text-gray-600 mt-2">Welcome to your restaurant management system</p>
            </div>

            <?php displayMessage(); ?>

            <!-- Statistics Cards -->
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Orders</p>
                            <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['total_orders']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Revenue</p>
                            <p class="text-3xl font-bold text-gray-800">$<?= number_format($stats['total_revenue'], 2) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Customers</p>
                            <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['total_customers']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Menu Items</p>
                            <p class="text-3xl font-bold text-gray-800"><?= number_format($stats['total_menu_items']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8">
                <!-- Recent Orders -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Orders</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-gray-500 text-center py-4">No orders yet</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
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
                                <a href="orders.php" class="text-orange-600 hover:text-orange-700 font-medium">View All Orders →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Reservations -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Reservations</h3>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recent_reservations)): ?>
                            <p class="text-gray-500 text-center py-4">No reservations yet</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_reservations as $reservation): ?>
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
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
                                <a href="reservations.php" class="text-orange-600 hover:text-orange-700 font-medium">View All Reservations →</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>