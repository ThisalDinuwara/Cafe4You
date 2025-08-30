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
    <title>Checkout - Cafe For You</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-yellow': '#FCD34D',
                        'brand-amber': '#F59E0B',
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
        
        .hero-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(252, 211, 77, 0.15) 1px, transparent 0);
            background-size: 20px 20px;
        }
        
        .floating-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(252, 211, 77, 0.2);
            border-color: #FCD34D;
        }
        
        .checkout-gradient {
            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
        }
        
        .nav-gradient {
            background: linear-gradient(90deg, rgba(252, 211, 77, 0.1) 0%, rgba(245, 158, 11, 0.1) 100%);
            backdrop-filter: blur(20px);
        }
    </style>
</head>
<body class="bg-brand-cream font-body">
    <!-- Background decorative elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="hero-pattern absolute inset-0 opacity-20"></div>
        <div class="absolute top-32 left-20 w-6 h-6 bg-brand-yellow/20 rounded-full animate-bounce"></div>
        <div class="absolute top-60 right-32 w-8 h-8 bg-yellow-400/30 rounded-full"></div>
        <div class="absolute bottom-32 left-16 w-4 h-4 bg-brand-amber/20 rounded-full"></div>
        <div class="absolute top-96 left-1/4 w-3 h-3 bg-yellow-300/25 rounded-full"></div>
        <div class="absolute bottom-40 right-1/4 w-5 h-5 bg-brand-yellow/15 rounded-full"></div>
    </div>

    <!-- Navigation -->
    <nav class="nav-gradient border-b border-yellow-100/50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">Cafe For You</h1>
                </div>
                <a href="cart.php" class="flex items-center space-x-2 text-brand-yellow hover:text-brand-amber font-semibold transition-colors duration-300 bg-white/70 px-4 py-2 rounded-2xl backdrop-blur-sm border border-yellow-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Back to Cart</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Checkout Content -->
    <div class="max-w-6xl mx-auto px-4 py-12 relative z-10">
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center space-x-3 mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-2xl flex items-center justify-center shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5H17M7 13v6a2 2 0 002 2h8a2 2 0 002-2v-6"></path>
                    </svg>
                </div>
            </div>
            <h1 class="text-5xl font-bold text-gray-900 mb-3">Complete Your Order</h1>
            <p class="text-gray-600 text-lg">Almost there! Just a few more details and your delicious food will be on its way.</p>
        </div>
        
        <?php displayMessage(); ?>
        
        <div class="grid lg:grid-cols-2 gap-8">
            <!-- Delivery Information Form -->
            <div class="floating-card rounded-3xl shadow-2xl p-8 border border-yellow-100">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Delivery Information</h2>
                </div>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Delivery Address *</label>
                        <div class="relative">
                            <div class="absolute top-3 left-0 pl-4 flex items-start pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <textarea name="delivery_address" required rows="4" 
                                      class="input-focus block w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium resize-none"
                                      placeholder="Enter your complete delivery address including street, city, and any landmarks"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Phone Number *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <input type="tel" name="phone" required 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   class="input-focus block w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium"
                                   placeholder="Your contact number for delivery">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Special Instructions</label>
                        <div class="relative">
                            <div class="absolute top-3 left-0 pl-4 flex items-start pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <textarea name="special_instructions" rows="4" 
                                      class="input-focus block w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium resize-none"
                                      placeholder="Any special instructions for your order (e.g., spice level, cooking preferences, delivery notes)"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full checkout-gradient text-white py-4 px-6 rounded-2xl font-bold text-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 flex items-center justify-center space-x-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Place Order</span>
                        <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                            </svg>
                        </div>
                    </button>
                </form>
            </div>
            
            <!-- Order Summary -->
            <div class="floating-card rounded-3xl shadow-2xl p-8 border border-yellow-100">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Order Summary</h2>
                </div>
                
                <div class="space-y-4 mb-8">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-start py-4 border-b border-gray-100 last:border-b-0">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($item['name']) ?></h4>
                                <div class="flex items-center space-x-4 mt-2">
                                    <span class="bg-brand-yellow/10 text-brand-amber px-3 py-1 rounded-full text-sm font-medium">
                                        Qty: <?= $item['quantity'] ?>
                                    </span>
                                    <span class="text-gray-600 text-sm">
                                        $<?= number_format($item['price'], 2) ?> each
                                    </span>
                                </div>
                            </div>
                            <div class="text-right ml-4">
                                <span class="text-xl font-bold text-gray-800">$<?= number_format($item['subtotal'], 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="bg-gray-50/50 rounded-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center text-gray-700">
                        <span class="font-medium">Subtotal</span>
                        <span class="font-semibold">$<?= number_format($subtotal, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center text-gray-700">
                        <span class="font-medium">Tax (8%)</span>
                        <span class="font-semibold">$<?= number_format($tax, 2) ?></span>
                    </div>
                    <hr class="border-gray-200">
                    <div class="flex justify-between items-center text-xl font-bold text-gray-800">
                        <span>Total Amount</span>
                        <span class="text-2xl bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">
                            $<?= number_format($total, 2) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Delivery Info -->
                <div class="mt-8 p-6 bg-brand-yellow/5 rounded-2xl border border-brand-yellow/20">
                    <div class="flex items-center space-x-3 mb-4">
                        <svg class="w-6 h-6 text-brand-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="font-semibold text-gray-800">Delivery Information</h3>
                    </div>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Estimated delivery: 30-45 minutes</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Free delivery on orders over $25</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span>Order tracking via SMS</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus effects to inputs
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const svg = this.closest('div').querySelector('svg');
                    if (svg) {
                        svg.classList.remove('text-gray-400');
                        svg.classList.add('text-brand-yellow');
                    }
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        const svg = this.closest('div').querySelector('svg');
                        if (svg) {
                            svg.classList.remove('text-brand-yellow');
                            svg.classList.add('text-gray-400');
                        }
                    }
                });
            });

            // Add floating animation to decorative elements
            const floatingElements = document.querySelectorAll('.absolute');
            floatingElements.forEach((element, index) => {
                if (element.classList.contains('rounded-full')) {
                    element.style.animation = `float ${3 + index}s ease-in-out infinite`;
                    element.style.animationDelay = `${index * 0.5}s`;
                }
            });

            // Form validation enhancement
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const phone = document.querySelector('input[name="phone"]').value;
                const address = document.querySelector('textarea[name="delivery_address"]').value;
                
                if (!phone.trim() || !address.trim()) {
                    e.preventDefault();
                    // Add visual feedback for required fields
                    if (!phone.trim()) {
                        document.querySelector('input[name="phone"]').classList.add('border-red-300');
                    }
                    if (!address.trim()) {
                        document.querySelector('textarea[name="delivery_address"]').classList.add('border-red-300');
                    }
                }
            });
        });

        // Add floating keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                33% { transform: translateY(-10px) rotate(2deg); }
                66% { transform: translateY(5px) rotate(-2deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>