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
    <title>My Orders - Cafe For You</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-orange': '#FF6B35',
                        'brand-cream': '#FFF8F0',
                        'brand-brown': '#8B4513',
                        'brand-gray': '#F5F5F5'
                    },
                    fontFamily: {
                        'display': ['Georgia', 'serif'],
                        'body': ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        .card-shadow {
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
        }
        
        .hero-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 107, 53, 0.15) 1px, transparent 0);
            background-size: 20px 20px;
        }
        
        .order-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        }
        
        .status-badge {
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="bg-brand-cream font-body">
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-orange-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-brand-orange to-red-500 rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-xl">C</span>
                    </div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-brand-orange to-red-500 bg-clip-text text-transparent">Cafe For You</h1>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Home</a>
                    <a href="menu.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Menu</a>
                    <a href="reservations.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Reservations</a>
                    <a href="contact.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Contact</a>
                    <a href="cart.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Cart</a>
                    <a href="orders.php" class="text-brand-orange font-semibold relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-0.5 after:bg-brand-orange">Orders</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Logout</a>
                </div>

                <!-- Mobile menu button -->
                <button class="md:hidden p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="relative bg-gradient-to-br from-brand-orange via-red-500 to-red-600 text-white py-16 overflow-hidden">
        <div class="hero-pattern absolute inset-0 opacity-20"></div>
        <div class="absolute top-10 left-10 w-6 h-6 bg-white/20 rounded-full animate-bounce"></div>
        <div class="absolute top-32 right-20 w-4 h-4 bg-yellow-400/30 rounded-full"></div>
        <div class="absolute bottom-20 left-1/4 w-3 h-3 bg-white/30 rounded-full"></div>
        
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="text-center">
                <div class="inline-flex items-center bg-white/20 backdrop-blur-sm rounded-full px-6 py-2 text-sm font-medium mb-4">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    <span>Order History</span>
                </div>
                <h1 class="text-5xl lg:text-6xl font-bold mb-4">Your <span class="text-yellow-300">Orders</span></h1>
                <p class="text-xl text-white/90 leading-relaxed max-w-2xl mx-auto">
                    Track your delicious orders and explore your dining journey with us
                </p>
            </div>
        </div>
    </section>

    <!-- Orders Content -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <?php displayMessage(); ?>
            
            <?php if (empty($orders)): ?>
                <!-- Empty Orders State -->
                <div class="text-center py-20">
                    <div class="bg-white rounded-3xl card-shadow p-12 max-w-md mx-auto hover-lift">
                        <div class="w-20 h-20 bg-gradient-to-br from-brand-orange/20 to-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">No orders found</h3>
                        <p class="text-gray-600 mb-6">Start your culinary journey by placing your first order</p>
                        <a href="menu.php" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-3 rounded-2xl font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300 inline-flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span>Start Ordering</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Order History</h2>
                    <p class="text-gray-600"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?> found</p>
                </div>
                
                <!-- Orders Grid -->
                <div class="grid gap-6">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card rounded-3xl p-6 hover-lift">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                                <!-- Order Info -->
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 bg-gradient-to-br from-brand-orange to-red-500 rounded-2xl flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-bold text-lg">#<?= $order['id'] ?></span>
                                    </div>
                                    
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-800">Order #<?= $order['id'] ?></h3>
                                        <p class="text-gray-600"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                                        <p class="text-sm text-gray-500"><?= $order['item_count'] ?> item<?= $order['item_count'] !== 1 ? 's' : '' ?></p>
                                    </div>
                                </div>
                                
                                <!-- Order Status & Total -->
                                <div class="flex items-center space-x-6">
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-gray-800">$<?= number_format($order['total_amount'], 2) ?></div>
                                        <div class="text-sm text-gray-500">Total Amount</div>
                                    </div>
                                    
                                    <!-- Status Badge -->
                                    <div class="flex flex-col items-center space-y-2">
                                        <?php
                                        $status_info = [
                                            'pending' => ['bg-yellow-100 text-yellow-800 border-yellow-200', '⏳'],
                                            'confirmed' => ['bg-blue-100 text-blue-800 border-blue-200', '✅'],
                                            'preparing' => ['bg-purple-100 text-purple-800 border-purple-200', '👨‍🍳'],
                                            'ready' => ['bg-green-100 text-green-800 border-green-200', '🎉'],
                                            'delivered' => ['bg-gray-100 text-gray-800 border-gray-200', '📦'],
                                            'cancelled' => ['bg-red-100 text-red-800 border-red-200', '❌']
                                        ];
                                        $status_class = $status_info[$order['status']][0] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                        $status_icon = $status_info[$order['status']][1] ?? '📋';
                                        ?>
                                        <div class="status-badge px-4 py-2 rounded-xl text-sm font-semibold <?= $status_class ?> flex items-center space-x-2">
                                            <span><?= $status_icon ?></span>
                                            <span><?= ucfirst($order['status']) ?></span>
                                        </div>
                                        
                                        <a href="orders.php?order_id=<?= $order['id'] ?>" class="bg-brand-orange text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-500 transition-all duration-300 flex items-center space-x-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            <span>View Details</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Order Details Modal -->
    <?php if ($order_details): ?>
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm overflow-y-auto h-full w-full z-50" id="order-modal">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="relative w-full max-w-2xl bg-white rounded-3xl card-shadow">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 rounded-t-3xl bg-gradient-to-r from-brand-orange to-red-500 text-white">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/20 rounded-2xl flex items-center justify-center">
                                <span class="font-bold">#<?= $order_details[0]['id'] ?></span>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Order Details</h3>
                                <p class="text-white/80 text-sm"><?= date('M j, Y g:i A', strtotime($order_details[0]['created_at'])) ?></p>
                            </div>
                        </div>
                        <a href="orders.php" class="text-white/80 hover:text-white transition-colors duration-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                    
                    <!-- Modal Content -->
                    <div class="p-6">
                        <!-- Order Info -->
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div class="space-y-3">
                                <h4 class="font-semibold text-gray-800 flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>Order Information</span>
                                </h4>
                                <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Status:</span>
                                        <span class="font-semibold text-brand-orange"><?= ucfirst($order_details[0]['status']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total:</span>
                                        <span class="font-bold text-gray-800">$<?= number_format($order_details[0]['total_amount'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-3">
                                <h4 class="font-semibold text-gray-800 flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span>Delivery Details</span>
                                </h4>
                                <div class="bg-gray-50 rounded-xl p-4 space-y-2">
                                    <div>
                                        <span class="text-gray-600 block text-sm">Address:</span>
                                        <span class="font-medium"><?= htmlspecialchars($order_details[0]['delivery_address']) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600 block text-sm">Phone:</span>
                                        <span class="font-medium"><?= htmlspecialchars($order_details[0]['phone']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($order_details[0]['special_instructions']): ?>
                            <div class="mb-6">
                                <h4 class="font-semibold text-gray-800 mb-2 flex items-center space-x-2">
                                    <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                    </svg>
                                    <span>Special Instructions</span>
                                </h4>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                                    <p class="text-gray-800"><?= htmlspecialchars($order_details[0]['special_instructions']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Order Items -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                <span>Order Items</span>
                            </h4>
                            <div class="space-y-3">
                                <?php foreach ($order_details as $item): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-brand-orange to-red-500 rounded-xl flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <span class="font-semibold text-gray-800"><?= htmlspecialchars($item['item_name']) ?></span>
                                                <span class="text-gray-600 text-sm block">Quantity: <?= $item['quantity'] ?></span>
                                            </div>
                                        </div>
                                        <span class="font-bold text-gray-800">$<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="border-t border-gray-200 mt-4 pt-4">
                                <div class="flex justify-between items-center p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl border-2 border-brand-orange/20">
                                    <span class="text-lg font-bold text-gray-800">Total Amount</span>
                                    <span class="text-2xl font-bold text-brand-orange">$<?= number_format($order_details[0]['total_amount'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-16">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Contact Info</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>123 Restaurant Street</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span class="ml-8">City, State 12345</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <span>(555) 123-4567</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span>info@cafeforyou.com</span>
                        </li>
                    </ul>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Hours</h4>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li class="flex justify-between">
                            <span>Monday - Thursday:</span>
                            <span class="text-white">11am - 10pm</span>
                        </li>
                        <li class="flex justify-between">
                            <span>Friday - Saturday:</span>
                            <span class="text-white">11am - 11pm</span>
                        </li>
                        <li class="flex justify-between">
                            <span>Sunday:</span>
                            <span class="text-white">12pm - 9pm</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <div class="text-gray-400 text-center md:text-left">
                        <p>&copy; 2025 Cafe For You. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect for navbar
        window.addEventListener('scroll', function() {
            const nav = document.querySelector('nav');
            if (window.scrollY > 100) {
                nav.classList.add('bg-white/95');
            } else {
                nav.classList.remove('bg-white/95');
            }
        });

        // Intersection Observer for animations
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            document.querySelectorAll('.hover-lift').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });

        // Modal backdrop click to close
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('order-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        window.location.href = 'orders.php';
                    }
                });
            }
        });

        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('order-modal')) {
                window.location.href = 'orders.php';
            }
        });

        // Add loading states for view details buttons
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('a[href*="order_id"]');
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<svg class="w-4 h-4 animate-spin mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Loading...';
                    this.classList.add('opacity-75', 'cursor-not-allowed');
                });
            });
        });
    </script>
</body>
</html>