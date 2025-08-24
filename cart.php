<?php
// cart.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $cart_id = (int)$_POST['cart_id'];
        $user_id = $_SESSION['user_id'];
        
        if ($_POST['action'] === 'update') {
            $quantity = (int)$_POST['quantity'];
            if ($quantity > 0) {
                $update_query = "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([$quantity, $cart_id, $user_id]);
                showMessage('Cart updated successfully!');
            }
        } elseif ($_POST['action'] === 'remove') {
            $remove_query = "DELETE FROM cart WHERE id = ? AND user_id = ?";
            $remove_stmt = $db->prepare($remove_query);
            $remove_stmt->execute([$cart_id, $user_id]);
            showMessage('Item removed from cart!');
        }
        redirect('cart.php');
    }
}

// Get cart items
$cart_query = "SELECT c.id, c.quantity, mi.name, mi.price, mi.image, 
               (c.quantity * mi.price) as subtotal
               FROM cart c 
               JOIN menu_items mi ON c.menu_item_id = mi.id 
               WHERE c.user_id = ? 
               ORDER BY c.created_at DESC";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->execute([$_SESSION['user_id']]);
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

$total = array_sum(array_column($cart_items, 'subtotal'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - Delicious Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-orange-600">Delicious</h1>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-orange-600 transition">Home</a>
                    <a href="menu.php" class="text-gray-700 hover:text-orange-600 transition">Menu</a>
                    <a href="reservations.php" class="text-gray-700 hover:text-orange-600 transition">Reservations</a>
                    <a href="contact.php" class="text-gray-700 hover:text-orange-600 transition">Contact</a>
                    <a href="cart.php" class="text-orange-600 font-semibold">Cart</a>
                    <a href="orders.php" class="text-gray-700 hover:text-orange-600 transition">Orders</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin/dashboard.php" class="text-gray-700 hover:text-orange-600 transition">Admin</a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Shopping Cart</h1>
        
        <?php displayMessage(); ?>
        
        <?php if (empty($cart_items)): ?>
            <div class="text-center py-16">
                <h3 class="text-2xl text-gray-600 mb-4">Your cart is empty</h3>
                <a href="menu.php" class="bg-orange-600 text-white px-6 py-3 rounded hover:bg-orange-700 transition">
                    Browse Menu
                </a>
            </div>
        <?php else: ?>
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="p-6 border-b border-gray-200 last:border-b-0">
                                <div class="flex items-center space-x-4">
                                    <img src="<?= $item['image'] ?: 'https://via.placeholder.com/100x100' ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>" 
                                         class="w-20 h-20 object-cover rounded">
                                    
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h3>
                                        <p class="text-gray-600">$<?= number_format($item['price'], 2) ?> each</p>
                                        
                                        <div class="flex items-center space-x-4 mt-2">
                                            <form method="POST" class="flex items-center space-x-2">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                <label class="text-sm text-gray-600">Qty:</label>
                                                <input type="number" name="quantity" value="<?= $item['quantity'] ?>" 
                                                       min="1" max="50" 
                                                       class="w-16 px-2 py-1 border border-gray-300 rounded text-center">
                                                <button type="submit" class="text-orange-600 hover:text-orange-700 text-sm">Update</button>
                                            </form>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-700 text-sm"
                                                        onclick="return confirm('Remove this item from cart?')">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <p class="text-lg font-semibold text-gray-800">$<?= number_format($item['subtotal'], 2) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Summary</h3>
                        
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="text-gray-800">$<?= number_format($total, 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax</span>
                                <span class="text-gray-800">$<?= number_format($total * 0.08, 2) ?></span>
                            </div>
                            <div class="border-t border-gray-200 pt-2">
                                <div class="flex justify-between font-semibold text-lg">
                                    <span>Total</span>
                                    <span>$<?= number_format($total * 1.08, 2) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <a href="checkout.php" class="w-full bg-orange-600 text-white py-3 px-6 rounded font-semibold hover:bg-orange-700 transition text-center block">
                            Proceed to Checkout
                        </a>
                        
                        <a href="menu.php" class="w-full bg-gray-200 text-gray-800 py-3 px-6 rounded font-semibold hover:bg-gray-300 transition text-center block mt-3">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>