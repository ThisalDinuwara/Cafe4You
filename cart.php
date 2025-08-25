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
    <title>Shopping Cart - Cafe For You</title>
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
        
        .cart-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .quantity-input {
            transition: all 0.3s ease;
        }
        
        .quantity-input:focus {
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
            border-color: #FF6B35;
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
                    <a href="cart.php" class="text-brand-orange font-semibold relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-0.5 after:bg-brand-orange">Cart</a>
                    <a href="orders.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Orders</a>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m1.6 8L5 3H3m4 10a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span>Shopping Cart</span>
                </div>
                <h1 class="text-5xl lg:text-6xl font-bold mb-4">Your <span class="text-yellow-300">Selections</span></h1>
                <p class="text-xl text-white/90 leading-relaxed max-w-2xl mx-auto">
                    Review your delicious choices and proceed to checkout when ready
                </p>
            </div>
        </div>
    </section>

    <!-- Cart Content -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <?php displayMessage(); ?>
            
            <?php if (empty($cart_items)): ?>
                <!-- Empty Cart State -->
                <div class="text-center py-20">
                    <div class="bg-white rounded-3xl card-shadow p-12 max-w-md mx-auto hover-lift">
                        <div class="w-20 h-20 bg-gradient-to-br from-brand-orange/20 to-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                            <svg class="w-10 h-10 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m1.6 8L5 3H3m4 10a2 2 0 11-4 0 2 2 0 014 0zm12 0a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">Your cart is empty</h3>
                        <p class="text-gray-600 mb-6">Discover our delicious menu items and start building your perfect meal</p>
                        <a href="menu.php" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-3 rounded-2xl font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300 inline-flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span>Browse Menu</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid lg:grid-cols-3 gap-12">
                    <!-- Cart Items -->
                    <div class="lg:col-span-2">
                        <div class="mb-6">
                            <h2 class="text-3xl font-bold text-gray-800 mb-2">Cart Items</h2>
                            <p class="text-gray-600"><?= count($cart_items) ?> item<?= count($cart_items) !== 1 ? 's' : '' ?> in your cart</p>
                        </div>
                        
                        <div class="space-y-6">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item rounded-3xl p-6 hover-lift">
                                    <div class="flex items-center space-x-6">
                                        <!-- Item Image -->
                                        <div class="relative w-20 h-20 flex-shrink-0">
                                            <?php if ($item['image'] && !empty($item['image'])): ?>
                                                <img src="<?= htmlspecialchars($item['image']) ?>" 
                                                     alt="<?= htmlspecialchars($item['name']) ?>" 
                                                     class="w-full h-full object-cover rounded-2xl border-2 border-brand-orange/20"
                                                     loading="lazy"
                                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <?php endif; ?>
                                            <!-- Fallback Icon (shown if no image or image fails to load) -->
                                            <div class="w-full h-full bg-gradient-to-br from-brand-orange to-red-500 rounded-2xl flex items-center justify-center <?= ($item['image'] && !empty($item['image'])) ? 'hidden' : 'flex' ?>">
                                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        
                                        <!-- Item Details -->
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                                            <p class="text-brand-orange font-semibold text-lg">$<?= number_format($item['price'], 2) ?> each</p>
                                            
                                            <!-- Quantity Controls -->
                                            <div class="flex items-center space-x-4 mt-4">
                                                <form method="POST" class="flex items-center space-x-3">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <label class="text-sm font-semibold text-gray-700">Quantity:</label>
                                                    <div class="flex items-center bg-gray-50 rounded-xl border border-gray-200">
                                                        <input type="number" name="quantity" value="<?= $item['quantity'] ?>" 
                                                               min="1" max="50" 
                                                               class="quantity-input w-20 px-3 py-2 bg-transparent border-none rounded-xl text-center font-semibold focus:outline-none">
                                                    </div>
                                                    <button type="submit" class="bg-brand-orange text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-500 transition-all duration-300 flex items-center space-x-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        <span>Update</span>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-semibold flex items-center space-x-1 transition-colors duration-300"
                                                            onclick="return confirm('Remove this item from cart?')">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        <span>Remove</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <!-- Item Total -->
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-gray-800">$<?= number_format($item['subtotal'], 2) ?></div>
                                            <div class="text-sm text-gray-500"><?= $item['quantity'] ?> × $<?= number_format($item['price'], 2) ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-24 space-y-6">
                            <!-- Order Summary Card -->
                            <div class="bg-white rounded-3xl card-shadow p-8 hover-lift">
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-brand-orange to-red-500 rounded-2xl flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-800">Order Summary</h3>
                                </div>
                                
                                <div class="space-y-4 mb-6">
                                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl">
                                        <span class="text-gray-700 font-medium">Subtotal</span>
                                        <span class="text-gray-800 font-semibold text-lg">$<?= number_format($total, 2) ?></span>
                                    </div>
                                    <div class="flex justify-between items-center p-4 bg-gray-50 rounded-xl">
                                        <span class="text-gray-700 font-medium">Tax (8%)</span>
                                        <span class="text-gray-800 font-semibold text-lg">$<?= number_format($total * 0.08, 2) ?></span>
                                    </div>
                                    <div class="border-t-2 border-gray-200 pt-4">
                                        <div class="flex justify-between items-center p-4 bg-gradient-to-r from-orange-50 to-red-50 rounded-xl border-2 border-brand-orange/20">
                                            <span class="text-gray-800 font-bold text-xl">Total</span>
                                            <span class="text-brand-orange font-bold text-2xl">$<?= number_format($total * 1.08, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-3">
                                    <a href="checkout.php" class="w-full bg-gradient-to-r from-brand-orange to-red-500 text-white py-4 px-6 rounded-2xl font-bold hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 text-center block text-lg flex items-center justify-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                        </svg>
                                        <span>Proceed to Checkout</span>
                                    </a>
                                    
                                    <a href="menu.php" class="w-full bg-white text-brand-orange border-2 border-brand-orange py-4 px-6 rounded-2xl font-bold hover:bg-orange-50 transition-all duration-300 text-center block text-lg flex items-center justify-center space-x-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                        <span>Continue Shopping</span>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div class="bg-gradient-to-br from-orange-50 to-red-50 rounded-3xl p-6 border border-orange-100">
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="w-8 h-8 bg-brand-orange/20 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-bold text-gray-800">Quick Actions</h4>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center space-x-2 text-sm text-gray-700">
                                        <span class="w-2 h-2 bg-brand-orange rounded-full"></span>
                                        <span>Free delivery over $50</span>
                                    </div>
                                    <div class="flex items-center space-x-2 text-sm text-gray-700">
                                        <span class="w-2 h-2 bg-brand-orange rounded-full"></span>
                                        <span>Ready in 15-30 minutes</span>
                                    </div>
                                    <div class="flex items-center space-x-2 text-sm text-gray-700">
                                        <span class="w-2 h-2 bg-brand-orange rounded-full"></span>
                                        <span>Special dietary options available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-16">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-brand-orange to-red-500 rounded-xl flex items-center justify-center">
                            <span class="text-white font-bold text-xl">C</span>
                        </div>
                        <h3 class="text-2xl font-bold">Cafe For You</h3>
                    </div>
                    <p class="text-gray-400 leading-relaxed">Experience fine dining at its best with our exquisite menu and exceptional service crafted with passion.</p>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="menu.php" class="hover:text-white transition-colors duration-300 hover:text-brand-orange">Menu</a></li>
                        <li><a href="reservations.php" class="hover:text-white transition-colors duration-300 hover:text-brand-orange">Reservations</a></li>
                        <li><a href="contact.php" class="hover:text-white transition-colors duration-300 hover:text-brand-orange">Contact</a></li>
                    </ul>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Contact Info</h4>
                    <ul class="space-y-3 text-gray-400">
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

        // Quantity input validation
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            quantityInputs.forEach(input => {
                input.addEventListener('input', function() {
                    let value = parseInt(this.value);
                    if (isNaN(value) || value < 1) {
                        this.value = 1;
                    } else if (value > 50) {
                        this.value = 50;
                    }
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.value = 1;
                    }
                });
            });
        });

        // Auto-submit quantity updates after a delay
        document.addEventListener('DOMContentLoaded', function() {
            let quantityTimeout;
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            quantityInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const form = this.closest('form');
                    clearTimeout(quantityTimeout);
                    
                    quantityTimeout = setTimeout(() => {
                        if (this.value !== this.defaultValue) {
                            // Show loading state
                            const updateBtn = form.querySelector('button[type="submit"]');
                            const originalText = updateBtn.innerHTML;
                            updateBtn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> <span>Updating...</span>';
                            updateBtn.disabled = true;
                            
                            form.submit();
                        }
                    }, 1000);
                });
            });
        });
    </script>
</body>
</html>