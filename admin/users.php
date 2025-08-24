<?php
// admin/users.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get all users with stats
$users_query = "SELECT u.*, 
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_amount) as total_spent,
                COUNT(DISTINCT r.id) as total_reservations
                FROM users u 
                LEFT JOIN orders o ON u.id = o.user_id 
                LEFT JOIN reservations r ON u.id = r.user_id 
                GROUP BY u.id 
                ORDER BY u.created_at DESC";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user details if requested
$user_details = null;
if (isset($_GET['view'])) {
    $user_id = (int)$_GET['view'];
    
    // Get user info
    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_details) {
        // Get user's recent orders
        $orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
        $orders_stmt = $db->prepare($orders_query);
        $orders_stmt->execute([$user_id]);
        $user_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get user's recent reservations
        $reservations_query = "SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
        $reservations_stmt = $db->prepare($reservations_query);
        $reservations_stmt->execute([$user_id]);
        $user_reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
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
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Dashboard</a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Orders</a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Menu Management</a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Categories</a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Reservations</a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-orange-600 font-medium">Users</a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Contact Messages</a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
                <p class="text-gray-600 mt-2">View and manage customer accounts</p>
            </div>

            <?php displayMessage(); ?>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservations</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                                            <span class="text-orange-600 font-medium text-sm">
                                                <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['full_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $user['total_orders'] ?: 0 ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    $<?= number_format($user['total_spent'] ?: 0, 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $user['total_reservations'] ?: 0 ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="users.php?view=<?= $user['id'] ?>" class="text-orange-600 hover:text-orange-900">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- User Details Modal -->
    <?php if ($user_details): ?>
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="user-modal">
            <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-medium text-gray-900">User Details: <?= htmlspecialchars($user_details['full_name']) ?></h3>
                        <a href="users.php" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <!-- User Information -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-800 mb-3">Personal Information</h4>
                            <div class="space-y-2 text-sm">
                                <p><strong>Username:</strong> <?= htmlspecialchars($user_details['username']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($user_details['email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($user_details['phone'] ?: 'Not provided') ?></p>
                                <p><strong>Role:</strong> <?= ucfirst($user_details['role']) ?></p>
                                <p><strong>Joined:</strong> <?= date('M j, Y g:i A', strtotime($user_details['created_at'])) ?></p>
                            </div>
                            <?php if ($user_details['address']): ?>
                                <div class="mt-3">
                                    <strong class="text-sm">Address:</strong>
                                    <p class="text-sm text-gray-600 mt-1"><?= nl2br(htmlspecialchars($user_details['address'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Statistics -->
                        <div class="space-y-4">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h5 class="font-semibold text-blue-800">Orders</h5>
                                <p class="text-2xl font-bold text-blue-600"><?= count($user_orders ?? []) ?></p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4">
                                <h5 class="font-semibold text-green-800">Reservations</h5>
                                <p class="text-2xl font-bold text-green-600"><?= count($user_reservations ?? []) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Recent Orders -->
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3">Recent Orders</h4>
                            <?php if (empty($user_orders)): ?>
                                <p class="text-gray-500 text-sm">No orders found</p>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($user_orders as $order): ?>
                                        <div class="bg-white border rounded-lg p-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium">Order #<?= $order['id'] ?></span>
                                                <span class="text-sm text-gray-500">$<?= number_format($order['total_amount'], 2) ?></span>
                                            </div>
                                            <div class="flex justify-between items-center mt-1">
                                                <span class="text-xs text-gray-400"><?= date('M j, Y', strtotime($order['created_at'])) ?></span>
                                                <span class="px-2 py-1 text-xs rounded-full
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
                            <?php endif; ?>
                        </div>
                        
                        <!-- Recent Reservations -->
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3">Recent Reservations</h4>
                            <?php if (empty($user_reservations)): ?>
                                <p class="text-gray-500 text-sm">No reservations found</p>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <?php foreach ($user_reservations as $reservation): ?>
                                        <div class="bg-white border rounded-lg p-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium"><?= date('M j, Y', strtotime($reservation['date'])) ?></span>
                                                <span class="text-sm text-gray-500"><?= $reservation['guests'] ?> guests</span>
                                            </div>
                                            <div class="flex justify-between items-center mt-1">
                                                <span class="text-xs text-gray-400"><?= date('g:i A', strtotime($reservation['time'])) ?></span>
                                                <span class="px-2 py-1 text-xs rounded-full
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>