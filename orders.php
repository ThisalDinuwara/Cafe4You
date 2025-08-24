<?php
// orders.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get user's orders
$orders_query = "SELECT o.*, COUNT(oi.id) as item_count 
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                WHERE o.user_id = ? 
                GROUP BY o.id 
                ORDER BY o.created_at DESC";
$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute([$_SESSION['user_id']]);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order details if requested
$order_details = null;
if (isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    $details_query = "SELECT o.*, oi.quantity, oi.price, mi.name as item_name 
                     FROM orders o 
                     JOIN order_items oi ON o.id = oi.order_id 
                     JOIN menu_items mi ON oi.menu_item_id = mi.id 
                     WHERE o.id = ? AND o.user_id = ?";
    $details_stmt = $db->prepare($details_query);
    $details_stmt->execute([$order_id, $_SESSION['user_id']]);
    $order_details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Delicious Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-orange-600">Delicious</h1>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-orange-600 transition">Home</a>
                    <a href="menu.php" class="text-gray-700 hover:text-orange-600 transition">Menu</a>
                    <a href="cart.php" class="text-gray-700 hover:text-orange-600 transition">Cart</a>
                    <a href="orders.php" class="text-orange-600 font-semibold">Orders</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-gray-700 hover:text-orange-600 transition">Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">My Orders</h1>
        
        <?php displayMessage(); ?>
        
        <?php if (empty($orders)): ?>
            <div class="text-center py-16">
                <h3 class="text-2xl text-gray-600 mb-4">No orders found</h3>
                <a href="menu.php" class="bg-orange-600 text-white px-6 py-3 rounded hover:bg-orange-700 transition">
                    Start Ordering
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= $order['id'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $order['item_count'] ?> items
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    $<?= number_format($order['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_colors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'preparing' => 'bg-purple-100 text-purple-800',
                                        'ready' => 'bg-green-100 text-green-800',
                                        'delivered' => 'bg-gray-100 text-gray-800',
                                        'cancelled' => 'bg-red-100 text-red-800'
                                    ];
                                    $color = $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $color ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="orders.php?order_id=<?= $order['id'] ?>" class="text-orange-600 hover:text-orange-900">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- Order Details Modal -->
        <?php if ($order_details): ?>
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="order-modal">
                <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Order Details #<?= $order_details[0]['id'] ?></h3>
                            <a href="orders.php" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </a>
                        </div>
                        
                        <div class="mb-4">
                            <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($order_details[0]['created_at'])) ?></p>
                            <p><strong>Status:</strong> <?= ucfirst($order_details[0]['status']) ?></p>
                            <p><strong>Delivery Address:</strong> <?= htmlspecialchars($order_details[0]['delivery_address']) ?></p>
                            <p><strong>Phone:</strong> <?= htmlspecialchars($order_details[0]['phone']) ?></p>
                            <?php if ($order_details[0]['special_instructions']): ?>
                                <p><strong>Special Instructions:</strong> <?= htmlspecialchars($order_details[0]['special_instructions']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                            <h4 class="font-medium text-gray-900 mb-3">Order Items</h4>
                            <div class="space-y-2">
                                <?php foreach ($order_details as $item): ?>
                                    <div class="flex justify-between">
                                        <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                                        <span>$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="border-t border-gray-200 mt-3 pt-3">
                                <div class="flex justify-between font-semibold">
                                    <span>Total</span>
                                    <span>$<?= number_format($order_details[0]['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>