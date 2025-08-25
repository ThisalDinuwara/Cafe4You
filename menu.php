<?php
// menu.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Get categories
$cat_query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected category
$selected_category = $_GET['category'] ?? '';

// Get menu items
if ($selected_category) {
    $menu_query = "SELECT mi.*, c.name as category_name FROM menu_items mi 
                   JOIN categories c ON mi.category_id = c.id 
                   WHERE mi.status = 'available' AND c.id = ?
                   ORDER BY mi.name";
    $menu_stmt = $db->prepare($menu_query);
    $menu_stmt->execute([$selected_category]);
} else {
    $menu_query = "SELECT mi.*, c.name as category_name FROM menu_items mi 
                   JOIN categories c ON mi.category_id = c.id 
                   WHERE mi.status = 'available' 
                   ORDER BY c.name, mi.name";
    $menu_stmt = $db->prepare($menu_query);
    $menu_stmt->execute();
}
$menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Cafe For You</title>
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
        
        .menu-item-card {
            position: relative;
            overflow: hidden;
        }
        
        .menu-item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .menu-item-card:hover::before {
            left: 100%;
        }
        
        .category-filter {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        
        .hero-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(255, 107, 53, 0.15) 1px, transparent 0);
            background-size: 20px 20px;
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
                    <a href="menu.php" class="text-brand-orange font-semibold relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-0.5 after:bg-brand-orange">Menu</a>
                    <a href="reservations.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Reservations</a>
                    <a href="contact.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Contact</a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Orders</a>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-brand-orange transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-orange after:transition-all after:duration-300 hover:after:w-full">Login</a>
                        <a href="register.php" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Register</a>
                    <?php endif; ?>
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
    <section class="relative bg-gradient-to-br from-brand-orange via-red-500 to-red-600 text-white py-20 overflow-hidden">
        <div class="hero-pattern absolute inset-0 opacity-20"></div>
        <div class="absolute top-10 left-10 w-6 h-6 bg-white/20 rounded-full animate-bounce"></div>
        <div class="absolute top-32 right-20 w-4 h-4 bg-yellow-400/30 rounded-full"></div>
        <div class="absolute bottom-20 left-1/4 w-3 h-3 bg-white/30 rounded-full"></div>
        
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div class="space-y-6">
                    <div class="inline-flex items-center bg-white/20 backdrop-blur-sm rounded-full px-6 py-2 text-sm font-medium mb-4">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span>Explore Our Menu</span>
                    </div>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-4">Our Delicious <span class="text-yellow-300">Menu</span></h1>
                    <p class="text-xl text-white/90 leading-relaxed">
                        Discover our carefully crafted selection of dishes, made with the finest ingredients and served with passion
                    </p>
                    
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-3 gap-6 pt-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-300">50+</div>
                            <div class="text-sm text-white/80">Dishes</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-300">15+</div>
                            <div class="text-sm text-white/80">Categories</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-300">4.9★</div>
                            <div class="text-sm text-white/80">Rating</div>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Food Image -->
                <div class="relative">
                    <!-- Main food showcase -->
                    <div class="relative">
                        <div class="bg-white/10 backdrop-blur-sm rounded-3xl p-8 border border-white/20">
                            <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=500&h=400&fit=crop&crop=center" 
                                 alt="Featured Menu Items" 
                                 class="w-full h-80 object-cover rounded-2xl">
                        </div>
                        
                        <!-- Floating menu cards -->
                        <div class="absolute -top-6 -left-6 bg-white rounded-2xl p-4 shadow-xl">
                            <img src="https://images.unsplash.com/photo-1551782450-17144efb9c50?w=100&h=80&fit=crop&crop=center" 
                                 alt="Pasta Special" 
                                 class="w-20 h-16 object-cover rounded-xl">
                            <div class="mt-2">
                                <div class="text-xs font-semibold text-gray-800">Pasta Special</div>
                                <div class="text-xs text-brand-orange font-bold">$24</div>
                            </div>
                        </div>

                        <div class="absolute -bottom-6 -right-6 bg-white rounded-2xl p-4 shadow-xl">
                            <img src="https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=100&h=80&fit=crop&crop=center" 
                                 alt="Seafood Delight" 
                                 class="w-20 h-16 object-cover rounded-xl">
                            <div class="mt-2">
                                <div class="text-xs font-semibold text-gray-800">Seafood Delight</div>
                                <div class="text-xs text-brand-orange font-bold">$32</div>
                            </div>
                        </div>

                        <div class="absolute top-1/2 -right-4 transform -translate-y-1/2 bg-white rounded-2xl p-3 shadow-xl">
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-brand-orange rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-gray-800">Popular</div>
                                    <div class="text-xs text-gray-600">Choice</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Decorative elements -->
                        <div class="absolute top-8 -left-4 w-12 h-12 bg-yellow-400/20 rounded-full flex items-center justify-center">
                            <span class="text-xl">🍽️</span>
                        </div>
                        <div class="absolute -bottom-4 left-8 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                            <span class="text-lg">⭐</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Filter -->
    <section class="category-filter py-8 sticky top-20 z-40 border-b border-orange-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-wrap justify-center gap-4">
                <a href="menu.php" class="px-6 py-3 rounded-full font-medium transition-all duration-300 transform hover:scale-105 <?= empty($selected_category) ? 'bg-brand-orange text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-orange-50 hover:text-brand-orange shadow-md' ?>">
                    <span class="flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <span>All Items</span>
                    </span>
                </a>
                <?php foreach ($categories as $category): ?>
                    <a href="menu.php?category=<?= $category['id'] ?>" 
                       class="px-6 py-3 rounded-full font-medium transition-all duration-300 transform hover:scale-105 <?= $selected_category == $category['id'] ? 'bg-brand-orange text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-orange-50 hover:text-brand-orange shadow-md' ?>">
                        <?= htmlspecialchars($category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Menu Items -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <?php displayMessage(); ?>
            
            <?php if (empty($menu_items)): ?>
                <div class="text-center py-20">
                    <div class="w-24 h-24 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-2">No items found</h3>
                    <p class="text-gray-600">Try selecting a different category or check back later.</p>
                </div>
            <?php else: ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($menu_items as $item): ?>
                    <div class="bg-white rounded-3xl overflow-hidden card-shadow hover-lift menu-item-card">
                        <div class="relative">
                            <img src="<?= $item['image'] ?: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400&h=300&fit=crop&crop=center' ?>" 
                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                 class="w-full h-56 object-cover">
                            
                            <!-- Category Badge -->
                            <div class="absolute top-4 left-4">
                                <span class="bg-brand-orange text-white text-xs px-3 py-1 rounded-full font-medium">
                                    <?= htmlspecialchars($item['category_name']) ?>
                                </span>
                            </div>
                            
                            <!-- Favorite Button -->
                            <div class="absolute top-4 right-4">
                                <button class="w-10 h-10 bg-white/80 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white transition-all duration-300 group">
                                    <svg class="w-5 h-5 text-gray-600 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="mb-4">
                                <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-gray-600 text-sm leading-relaxed"><?= htmlspecialchars($item['description']) ?></p>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-2">
                                    <span class="text-2xl font-bold text-brand-orange">$<?= number_format($item['price'], 2) ?></span>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        <span>4.8 (125)</span>
                                    </div>
                                </div>
                                
                                <?php if (isLoggedIn()): ?>
                                    <form method="POST" action="add_to_cart.php" class="inline">
                                        <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="bg-brand-orange text-white px-6 py-2.5 rounded-full font-semibold hover:bg-red-500 transform hover:scale-105 transition-all duration-300 text-sm shadow-lg">
                                            Add to Cart
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="login.php" class="bg-gray-400 text-white px-6 py-2.5 rounded-full font-semibold hover:bg-gray-500 transition-all duration-300 text-sm">
                                        Login to Order
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Load More Button (if you want to add pagination later) -->
                <div class="text-center mt-16">
                    <button class="bg-gray-100 text-gray-700 px-8 py-4 rounded-full font-semibold hover:bg-gray-200 transition-all duration-300">
                        View More Items
                    </button>
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
            document.querySelectorAll('.hover-lift, .menu-item-card').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>