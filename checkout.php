<?php
// checkout.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get cart items
$cart_query = "SELECT c.id, c.quantity, c.menu_item_id, mi.name, mi.price, 
               (c.quantity * mi.price) as subtotal
               FROM cart c 
               JOIN menu_items mi ON c.menu_item_id = mi.id 
               WHERE c.user_id = ?";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$_SESSION['user_id']]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    showMessage('Your cart is empty', 'error');
    redirect('menu.php');
}

$subtotal = array_sum(array_column($cart_items, 'subtotal'));
$tax = $subtotal * 0.08;
$total = $subtotal + $tax;

// Get user info
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_address = sanitize($_POST['delivery_address']);
    $phone = sanitize($_POST['phone']);
    $special_instructions = sanitize($_POST['special_instructions']);
    
    if (empty($delivery_address) || empty($phone)) {
        showMessage('Please fill in all required fields', 'error');
    } else {
        try {
            $db->beginTransaction();
            
            // Create order
            $order_query = "INSERT INTO orders (user_id, total_amount, delivery_address, phone, special_instructions) 
                           VALUES (?, ?, ?, ?, ?)";
            $order_stmt = $db->prepare($order_query);
            $order_stmt->execute([$_SESSION['user_id'], $total, $delivery_address, $phone, $special_instructions]);
            $order_id = $db->lastInsertId();
            
            // Add order items
            foreach ($cart_items as $item) {
                $item_query = "INSERT INTO order_items (order_id, menu_item_id, quantity, price) 
                              VALUES (?, ?, ?, ?)";
                $item_stmt = $db->prepare($item_query);
                $item_stmt->execute([$order_id, $item['menu_item_id'], $item['quantity'], $item['price']]);
            }
            
            // Clear cart
            $clear_query = "DELETE FROM cart WHERE user_id = ?";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->execute([$_SESSION['user_id']]);
            
            $db->commit();
            
            showMessage('Order placed successfully! Order #' . $order_id);
            redirect('orders.php');
            
        } catch (Exception $e) {
            $db->rollback();
            showMessage('Error placing order. Please try again.', 'error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Delicious Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <h1 class="text-2xl font-bold text-orange-600">Delicious</h1>
                <a href="cart.php" class="text-orange-600 hover:text-orange-700">← Back to Cart</a>
            </div>
        </div>
    </nav>

    <!-- Checkout Form -->
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Checkout</h1>
        
        <?php displayMessage(); ?>
        
        <div class="grid lg:grid-cols-2 gap-8">
            <!-- Order Form -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Delivery Information</h2>
                
                <form method="POST">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Address *</label>
                            <textarea name="delivery_address" required rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                                      placeholder="Enter your delivery address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                            <input type="tel" name="phone" required 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                                   placeholder="Your phone number">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Special Instructions</label>
                            <textarea name="special_instructions" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                                      placeholder="Any special instructions for your order"></textarea>
                        </div>
                        
                        <button type="submit" class="w-full bg-orange-600 text-white py-3 px-6 rounded-md font-semibold hover:bg-orange-700 transition">
                            Place Order
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Order Summary</h2>
                
                <div class="space-y-4 mb-6">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-center py-2 border-b border-gray-200">
                            <div>
                                <h4 class="font-medium"><?= htmlspecialchars($item['name']) ?></h4>
                                <p class="text-sm text-gray-600">Qty: <?= $item['quantity'] ?> × $<?= number_format($item['price'], 2) ?></p>
                            </div>
                            <span class="font-semibold">$<?= number_format($item['subtotal'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="space-y-2 border-t border-gray-200 pt-4">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span>$<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tax (8%)</span>
                        <span>$<?= number_format($tax, 2) ?></span>
                    </div>
                    <div class="flex justify-between font-semibold text-lg border-t border-gray-200 pt-2">
                        <span>Total</span>
                        <span>$<?= number_format($total, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>