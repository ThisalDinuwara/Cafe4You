<?php
// admin/orders.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = sanitize($_POST['status']);
    
    $update_query = "UPDATE orders SET status = ? WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    if ($update_stmt->execute([$new_status, $order_id])) {
        showMessage('Order status updated successfully!');
    } else {
        showMessage('Failed to update order status', 'error');
    }
}

// Get orders with enhanced statistics
$orders_query = "SELECT o.*, u.full_name, u.email, COUNT(oi.id) as item_count 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                GROUP BY o.id 
                ORDER BY o.created_at DESC";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute();
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate order statistics
$stats = [];
$stats['total_orders'] = count($orders);
$stats['total_revenue'] = array_sum(array_column($orders, 'total_amount'));
$stats['avg_order_value'] = $stats['total_orders'] > 0 ? $stats['total_revenue'] / $stats['total_orders'] : 0;

// Status breakdown
$status_counts = [];
foreach ($orders as $order) {
    $status = $order['status'];
    $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
}

// Today's orders
$today_orders = array_filter($orders, function($order) {
    return date('Y-m-d', strtotime($order['created_at'])) === date('Y-m-d');
});
$stats['today_orders'] = count($today_orders);
$stats['today_revenue'] = array_sum(array_column($today_orders, 'total_amount'));

// Get order details if requested
$order_details = null;
if (isset($_GET['view'])) {
    $order_id = (int)$_GET['view'];
    
    $details_query = "SELECT o.*, u.full_name, u.email, u.phone as user_phone,
                     oi.quantity, oi.price, mi.name as item_name 
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id 
                     JOIN order_items oi ON o.id = oi.order_id 
                     JOIN menu_items mi ON oi.menu_item_id = mi.id 
                     WHERE o.id = ?";
    $details_stmt = $db->prepare($details_query);
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
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📊 Dashboard
                    </a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-brand-orange font-medium">
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
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header Section -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800">🛒 Order Management</h1>
                <p class="text-gray-600 mt-2">Track and manage customer orders in real-time</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid md:grid-cols-4 gap-6 mb-8">
                <!-- Total Orders Card -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Orders</p>
                            <p class="text-3xl font-bold"><?= $stats['total_orders'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 18C5.9 18 5 18.9 5 20S5.9 22 7 22 9 21.1 9 20 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5H5.21L4.27 3H1ZM17 18C15.9 18 15 18.9 15 20S15.9 22 17 22 19 21.1 19 20 18.1 18 17 18Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue Card -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Total Revenue</p>
                            <p class="text-3xl font-bold">$<?= number_format($stats['total_revenue'], 0) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9M19 21H5V3H13V9H19Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Today's Orders Card -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Today's Orders</p>
                            <p class="text-3xl font-bold"><?= $stats['today_orders'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L13.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Average Order Value Card -->
                <div class="bg-gradient-to-r from-brand-orange to-yellow-500 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Avg Order Value</p>
                            <p class="text-3xl font-bold">$<?= number_format($stats['avg_order_value'], 2) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16 6L18.29 8.29L13.41 13.17L9.41 9.17L2 16.59L3.41 18L9.41 12L13.41 16L19.71 9.71L22 12V6H16Z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview -->
            <div class="grid md:grid-cols-6 gap-4 mb-8">
                <?php
                $status_colors = [
                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                    'confirmed' => 'bg-blue-100 text-blue-800 border-blue-300',
                    'preparing' => 'bg-purple-100 text-purple-800 border-purple-300',
                    'ready' => 'bg-green-100 text-green-800 border-green-300',
                    'delivered' => 'bg-gray-100 text-gray-800 border-gray-300',
                    'cancelled' => 'bg-red-100 text-red-800 border-red-300'
                ];
                
                foreach (['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'] as $status):
                    $count = $status_counts[$status] ?? 0;
                ?>
                    <div class="bg-white rounded-lg shadow-md p-4 border-l-4 <?= $status_colors[$status] ?>">
                        <h3 class="text-sm font-medium text-gray-500 capitalize"><?= $status ?></h3>
                        <p class="text-2xl font-bold text-gray-900"><?= $count ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php displayMessage(); ?>

            <!-- Orders Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-brand-orange to-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white">🛒 Customer Orders</h3>
                            <p class="text-orange-100 text-sm">Manage and track all customer orders (<?= count($orders) ?> orders)</p>
                        </div>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-white font-bold text-lg"><?= count($orders) ?></span>
                            <span class="text-orange-100 text-sm block">Total Orders</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders as $order): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-brand-orange rounded-lg flex items-center justify-center mr-3">
                                                <span class="text-white font-bold text-sm">#<?= $order['id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <span class="text-blue-600 font-bold text-sm"><?= strtoupper(substr($order['full_name'], 0, 2)) ?></span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($order['full_name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($order['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?= date('M j, Y', strtotime($order['created_at'])) ?></div>
                                        <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-sm font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                            <?= $order['item_count'] ?> items
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-green-600">
                                        $<?= number_format($order['total_amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <select name="status" onchange="confirmStatusChange(this)" 
                                                    class="text-sm border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-orange focus:border-transparent p-2 font-semibold <?php
                                                    echo match($order['status']) {
                                                        'pending' => 'bg-yellow-50 text-yellow-800',
                                                        'confirmed' => 'bg-blue-50 text-blue-800',
                                                        'preparing' => 'bg-purple-50 text-purple-800',
                                                        'ready' => 'bg-green-50 text-green-800',
                                                        'delivered' => 'bg-gray-50 text-gray-800',
                                                        'cancelled' => 'bg-red-50 text-red-800',
                                                        default => 'bg-gray-50 text-gray-800'
                                                    };
                                                    ?>">
                                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                                <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                                                <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>👨‍🍳 Preparing</option>
                                                <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>🍽️ Ready</option>
                                                <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>🚚 Delivered</option>
                                                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="orders.php?view=<?= $order['id'] ?>" 
                                           class="bg-brand-orange text-white px-4 py-2 rounded-lg font-semibold hover:bg-orange-600 transition-all duration-300 inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($orders)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No Orders Yet</h3>
                    <p class="text-gray-500">Orders from customers will appear here once they start placing orders.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Enhanced Order Details Modal -->
    <?php if ($order_details): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center" id="order-modal">
            <div class="relative mx-4 p-0 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl rounded-2xl bg-white">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-brand-orange to-red-500 px-8 py-6 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-bold text-white">🛒 Order Details</h3>
                            <p class="text-orange-100">Order #<?= $order_details[0]['id'] ?></p>
                        </div>
                        <a href="orders.php" class="text-white hover:text-orange-200 transition-colors">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-8">
                    <!-- Customer Information -->
                    <div class="mb-8">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-orange" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9M19 21H5V3H13V9H19Z"/>
                            </svg>
                            Customer Information
                        </h4>
                        <div class="bg-gray-50 rounded-xl p-6 space-y-3">
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Name:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($order_details[0]['full_name']) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Email:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($order_details[0]['email']) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Phone:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($order_details[0]['phone']) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Date:</span>
                                <span class="text-gray-900"><?= date('M j, Y g:i A', strtotime($order_details[0]['created_at'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Status:</span>
                                <span class="px-3 py-1 rounded-full text-sm font-bold <?php
                                    echo match($order_details[0]['status']) {
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'preparing' => 'bg-purple-100 text-purple-800',
                                        'ready' => 'bg-green-100 text-green-800',
                                        'delivered' => 'bg-gray-100 text-gray-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    ?>">
                                    <?= ucfirst($order_details[0]['status']) ?>
                                </span>
                            </div>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-24">Address:</span>
                                <span class="text-gray-900 flex-1"><?= htmlspecialchars($order_details[0]['delivery_address']) ?></span>
                            </div>
                            <?php if ($order_details[0]['special_instructions']): ?>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-24">Notes:</span>
                                <span class="text-gray-900 flex-1"><?= htmlspecialchars($order_details[0]['special_instructions']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div>
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-orange" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 18C5.9 18 5 18.9 5 20S5.9 22 7 22 9 21.1 9 20 8.1 18 7 18ZM1 2V4H3L6.6 11.59L5.25 14.04C5.09 14.32 5 14.65 5 15C5 16.1 5.9 17 7 17H19V15H7.42C7.28 15 7.17 14.89 7.17 14.75L7.2 14.63L8.1 13H15.55C16.3 13 16.96 12.59 17.3 11.97L20.88 5H5.21L4.27 3H1ZM17 18C15.9 18 15 18.9 15 20S15.9 22 17 22 19 21.1 19 20 18.1 18 17 18Z"/>
                            </svg>
                            Order Items
                        </h4>
                        <div class="bg-gray-50 rounded-xl p-6">
                            <div class="space-y-4">
                                <?php foreach ($order_details as $item): ?>
                                    <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow-sm border border-gray-200">
                                        <div class="flex-1">
                                            <h5 class="font-semibold text-gray-900"><?= htmlspecialchars($item['item_name']) ?></h5>
                                            <p class="text-sm text-gray-500">Quantity: <?= $item['quantity'] ?> × $<?= number_format($item['price'], 2) ?></p>
                                        </div>
                                        <div class="text-lg font-bold text-brand-orange">
                                            $<?= number_format($item['price'] * $item['quantity'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Order Total -->
                            <div class="border-t border-gray-300 mt-6 pt-6">
                                <div class="flex justify-between items-center">
                                    <span class="text-xl font-bold text-gray-900">Order Total</span>
                                    <span class="text-2xl font-bold text-brand-orange">$<?= number_format($order_details[0]['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-4 mt-8 pt-6 border-t border-gray-200">
                        <a href="orders.php" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold hover:bg-gray-300 transition-all duration-300 text-center">
                            Close Details
                        </a>
                        <button onclick="window.print()" class="flex-1 bg-gradient-to-r from-brand-orange to-red-500 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                            Print Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function confirmStatusChange(selectElement) {
            const form = selectElement.form;
            const newStatus = selectElement.value;
            const orderNumber = form.querySelector('input[name="order_id"]').value;
            
            if (confirm(`Are you sure you want to change order #${orderNumber} status to "${newStatus}"?`)) {
                form.submit();
            } else {
                // Reset to previous value if cancelled
                selectElement.selectedIndex = 0;
            }
        }
        
        // Auto-hide success messages
        setTimeout(function() {
            const successMessages = document.querySelectorAll('.bg-green-500');
            successMessages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(function() {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            });
        }, 4000);
        
        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(4px)';
                    this.style.boxShadow = '4px 0 8px rgba(255, 107, 53, 0.1)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.boxShadow = 'none';
                });
            });
            
            // Add gradient cards hover effects
            const gradientCards = document.querySelectorAll('.bg-gradient-to-r');
            gradientCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'transform 0.2s ease-in-out';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Real-time order updates (optional - requires WebSocket or polling)
        function refreshOrderStatus() {
            // This could be enhanced to periodically check for order status updates
            // For now, it's just a placeholder for future real-time functionality
        }
        
        // Add click-to-copy functionality for order IDs
        function copyOrderId(orderId) {
            navigator.clipboard.writeText('Order #' + orderId).then(function() {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                toast.textContent = 'Order ID copied to clipboard!';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 2000);
            });
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('order-modal');
            if (modal && event.target === modal) {
                window.location.href = 'orders.php';
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('order-modal');
                if (modal) {
                    window.location.href = 'orders.php';
                }
            }
        });
    </script>
</body>
</html>