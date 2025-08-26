<?php
// index.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Get featured menu items
$query = "SELECT mi.*, c.name as category_name FROM menu_items mi 
          JOIN categories c ON mi.category_id = c.id 
          WHERE mi.status = 'available' 
          ORDER BY mi.created_at DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$featured_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe For You - Modern Dining Experience</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
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
        
        .hero-gradient {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8E53 100%);
        }
        
        .card-shadow {
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
        }
        
        .floating-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .dish-card {
            position: relative;
            overflow: hidden;
        }
        
        .dish-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .dish-card:hover::before {
            left: 100%;
        }
        
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.7;
            animation: float 6s ease-in-out infinite;
        }
        
        .blob-1 {
            top: 20%;
            left: 10%;
            width: 300px;
            height: 300px;
            background: linear-gradient(45deg, #FF6B35, #FFB84D);
            animation-delay: 0s;
        }
        
        .blob-2 {
            top: 60%;
            right: 10%;
            width: 200px;
            height: 200px;
            background: linear-gradient(45deg, #FFB84D, #FF6B35);
            animation-delay: 2s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(5deg); }
            66% { transform: translateY(10px) rotate(-5deg); }
        }

        /* Slideshow specific styles for better image display */
        .slideshow-container {
            position: relative;
        }
        
        .slide-image {
            display: block;
            width: 100%;
            height: 100%;
        }
        
        .slide-image:first-child {
            position: relative !important;
            z-index: 10 !important;
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

    <!-- Hero Section -->
    <section class="relative overflow-hidden bg-brand-cream min-h-screen flex items-center">
        <!-- Decorative elements -->
        <div class="absolute top-20 left-20 w-4 h-4 bg-brand-orange rounded-full animate-bounce"></div>
        <div class="absolute top-40 right-32 w-6 h-6 bg-yellow-400 rounded-full"></div>
        <div class="absolute bottom-32 left-16 w-3 h-3 bg-red-400 rounded-full"></div>
        <div class="absolute top-60 left-1/3 w-2 h-2 bg-green-400 rounded-full"></div>
        
        <div class="max-w-7xl mx-auto px-6 py-20 relative z-10">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Left Content -->
                <div class="space-y-8">
                    <div class="space-y-6">
                        <h1 class="text-6xl lg:text-7xl font-bold leading-tight">
                            <span class="text-gray-800">WELCOME</span><br>
                            <span class="text-gray-800">TO </span>
                            <span class="bg-gradient-to-r from-brand-orange to-yellow-500 bg-clip-text text-transparent">CAFE FOR YOU</span>
                        </h1>
                        <p class="text-xl text-gray-600 leading-relaxed max-w-lg">
                            Discover culinary delights that awaken your senses and transport you to a world of exceptional flavors.
                        </p>
                    </div>
                    
                    <div class="flex flex-wrap gap-4">
                        <a href="menu.php" class="bg-gray-800 text-white px-8 py-4 rounded-full font-semibold hover:bg-gray-700 transition-all duration-300 flex items-center space-x-2">
                            <span>View Menu</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                            </svg>
                        </a>
                        <a href="reservations.php" class="border-2 border-gray-300 text-gray-700 px-8 py-4 rounded-full font-semibold hover:border-gray-800 hover:text-gray-800 transition-all duration-300 bg-white">
                            Get Directions
                        </a>
                    </div>

                    <!-- Info Cards -->
                    <div class="grid grid-cols-3 gap-6 pt-8">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-800 text-sm mb-1">Fast Delivery</h3>
                            <p class="text-xs text-gray-600">Experience lightning-fast delivery that brings restaurant-quality meals to your door.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-800 text-sm mb-1">Fresh Recipe</h3>
                            <p class="text-xs text-gray-600">Our expert chefs use only the freshest ingredients, sourced locally when possible.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-800 text-sm mb-1">Best Price</h3>
                            <p class="text-xs text-gray-600">Enjoy premium quality at competitive prices with our everyday value offerings.</p>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Large Circle Slideshow -->
                <div class="relative" id="hero-slideshow">
                    <!-- Main slideshow container -->
                    <div class="relative">
                        <!-- Large Slideshow images - Made bigger (w-[28rem] h-[28rem] = 448px) -->
                        <div class="slideshow-container relative w-[28rem] h-[28rem] mx-auto">
                            <img src="https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=500&h=500&fit=crop&crop=center" 
                                 alt="Delicious Pizza" 
                                 class="slide-image w-[28rem] h-[28rem] object-cover rounded-full transition-opacity duration-500 opacity-100 shadow-2xl">
                            <img src="https://images.unsplash.com/photo-1551782450-17144efb9c50?w=500&h=500&fit=crop&crop=center" 
                                 alt="Gourmet Pasta" 
                                 class="slide-image w-[28rem] h-[28rem] object-cover rounded-full transition-opacity duration-500 opacity-0 absolute inset-0 shadow-2xl">
                            <img src="https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=500&h=500&fit=crop&crop=center" 
                                 alt="Fresh Seafood" 
                                 class="slide-image w-[28rem] h-[28rem] object-cover rounded-full transition-opacity duration-500 opacity-0 absolute inset-0 shadow-2xl">
                            <img src="https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=500&h=500&fit=crop&crop=center" 
                                 alt="Garden Salad" 
                                 class="slide-image w-[28rem] h-[28rem] object-cover rounded-full transition-opacity duration-500 opacity-0 absolute inset-0 shadow-2xl">
                        </div>
                        
                        <!-- Utensils - Adjusted for larger circle -->
                        <div class="absolute -left-20 top-1/2 transform -translate-y-1/2 -rotate-45">
                            <div class="w-1.5 h-40 bg-gray-700 rounded-full shadow-md"></div>
                        </div>
                        <div class="absolute -right-20 top-1/2 transform -translate-y-1/2 rotate-45">
                            <div class="w-1.5 h-40 bg-gray-700 rounded-full relative shadow-md">
                                <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 w-5 h-8 border-2 border-gray-700 rounded-t-lg"></div>
                                <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 w-2 h-5 border-l-2 border-gray-700"></div>
                                <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 translate-x-1 w-2 h-5 border-l-2 border-gray-700"></div>
                                <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 -translate-x-1 w-2 h-5 border-l-2 border-gray-700"></div>
                            </div>
                        </div>
                        
                        <!-- Floating elements - Scaled and repositioned for large circle -->
                        <div class="absolute -top-10 -right-12 w-20 h-20 bg-white rounded-full shadow-xl flex items-center justify-center animate-bounce" style="animation-duration: 3s;">
                            <span class="text-3xl">🍅</span>
                        </div>
                        <div class="absolute -bottom-6 -left-12 w-16 h-16 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 5s ease-in-out infinite;">
                            <span class="text-2xl">🥗</span>
                        </div>
                        <div class="absolute top-10 -left-16 w-18 h-18 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 6s ease-in-out infinite; animation-delay: 1.5s;">
                            <span class="text-2xl">🧄</span>
                        </div>
                        <div class="absolute top-20 right-8 w-14 h-14 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 4s ease-in-out infinite; animation-delay: 3s;">
                            <span class="text-xl">🌶️</span>
                        </div>
                        <div class="absolute bottom-16 right-16 w-12 h-12 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 4.5s ease-in-out infinite; animation-delay: 2s;">
                            <span class="text-lg">🥑</span>
                        </div>
                    </div>
                    
                    <!-- Slide indicators -->
                    <div class="flex justify-center space-x-3 mt-8" id="slide-indicators">
                        <button class="slide-indicator w-8 h-3 rounded-full bg-brand-orange transition-all duration-300 shadow-md" data-slide="0"></button>
                        <button class="slide-indicator w-3 h-3 rounded-full bg-gray-300 transition-all duration-300 shadow-md hover:bg-gray-400" data-slide="1"></button>
                        <button class="slide-indicator w-3 h-3 rounded-full bg-gray-300 transition-all duration-300 shadow-md hover:bg-gray-400" data-slide="2"></button>
                        <button class="slide-indicator w-3 h-3 rounded-full bg-gray-300 transition-all duration-300 shadow-md hover:bg-gray-400" data-slide="3"></button>
                    </div>
                    
                    <!-- Rating Card - Repositioned for large circle -->
                    <div class="absolute -bottom-12 -right-8 bg-white rounded-3xl p-5 shadow-2xl border border-gray-100">
                        <div class="flex items-center space-x-4">
                            <img id="rating-card-image" src="https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=80&h=80&fit=crop&crop=center" alt="Current Dish" class="w-16 h-16 object-cover rounded-2xl">
                            <div>
                                <h4 class="font-semibold text-base text-gray-800">Culinary Excellence</h4>
                                <p class="text-sm text-gray-600">and Gourmet</p>
                                <div class="flex items-center mt-2">
                                    <div class="flex text-yellow-400 text-sm">
                                        ★★★★★
                                    </div>
                                    <span class="text-sm text-gray-600 ml-2 font-medium">5.0</span>
                                    <span class="w-8 h-8 bg-brand-orange rounded-full flex items-center justify-center ml-3">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Chef Section -->
    <section class="py-20 bg-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold text-gray-700 mb-6">
                    Become a true <span class="text-brand-orange">chef</span><br>
                    with our recipes.
                </h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Join our culinary journey and discover the secrets behind our most beloved dishes with step-by-step guidance from our expert chefs.
                </p>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Cooking Process -->
                <div class="bg-gray-50 rounded-3xl p-8 relative overflow-hidden">
                    <div class="absolute top-4 left-4 bg-gray-800 text-white px-3 py-1 rounded-full text-sm font-medium">
                        STEP 1
                    </div>
                    <img src="https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=200&fit=crop&crop=center" 
                         alt="Cooking Process" 
                         class="w-full h-48 object-cover rounded-2xl mt-8">
                    <div class="mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Preparation</h3>
                        <p class="text-gray-600 text-sm">Learn the fundamentals of ingredient preparation and mise en place.</p>
                    </div>
                </div>

                <!-- Chef Quote -->
                <div class="bg-brand-orange rounded-3xl p-8 text-white relative">
                    <div class="absolute top-4 right-4 text-6xl opacity-20">"</div>
                    <div class="space-y-4 relative z-10">
                        <h3 class="text-2xl font-bold leading-tight">
                            "Cooking has<br>
                            never been<br>
                            this easy!"
                        </h3>
                        <div class="space-y-3 mt-8">
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="text-sm">Professional techniques</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="text-sm">Step-by-step guidance</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-orange" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <span class="text-sm">Expert chef support</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chef Image -->
                <div class="relative">
                    <div class="bg-gray-100 rounded-3xl p-6 h-full flex items-end relative overflow-hidden">
                        <div class="absolute top-4 right-4 bg-white px-3 py-1 rounded-full text-sm font-medium text-gray-700">
                            Chef Master
                        </div>
                        <img src="https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&h=400&fit=crop&crop=center" 
                             alt="Professional Chef" 
                             class="w-full h-80 object-cover rounded-2xl">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Items -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <div class="inline-flex items-center bg-orange-100 rounded-full px-4 py-2 text-sm font-medium text-brand-orange mb-4">
                    <span>Our Specialties</span>
                </div>
                <h2 class="text-5xl font-bold text-gray-800 mb-6">Featured <span class="text-brand-orange">Dishes</span></h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Discover our chef's carefully curated selection of signature dishes, crafted with passion and the finest ingredients.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_items as $item): ?>
                <div class="bg-white rounded-3xl overflow-hidden card-shadow hover-lift dish-card">
                    <div class="relative">
                        <img src="<?= $item['image'] ?: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400&h=250&fit=crop&crop=center' ?>" 
                             alt="<?= htmlspecialchars($item['name']) ?>" 
                             class="w-full h-56 object-cover">
                        <div class="absolute top-4 left-4">
                            <span class="bg-brand-orange text-white text-xs px-3 py-1 rounded-full font-medium"><?= htmlspecialchars($item['category_name']) ?></span>
                        </div>
                        <?php if (isLoggedIn()): ?>
                        <div class="absolute top-4 right-4">
                            <button class="w-10 h-10 bg-white/80 backdrop-blur-sm rounded-full flex items-center justify-center hover:bg-white transition-all duration-300">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-gray-600 mb-4 text-sm leading-relaxed"><?= htmlspecialchars($item['description']) ?></p>
                        <div class="flex justify-between items-center">
                            <div class="text-2xl font-bold text-brand-orange">$<?= number_format($item['price'], 2) ?></div>
                            <?php if (isLoggedIn()): ?>
                                <form method="POST" action="add_to_cart.php" class="inline">
                                    <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="bg-brand-orange text-white px-6 py-2.5 rounded-full font-semibold hover:bg-red-500 transform hover:scale-105 transition-all duration-300 text-sm">
                                        Add to Cart
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="py-20 bg-brand-cream relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div class="space-y-8">
                    <div class="space-y-4">
                        <div class="inline-flex items-center bg-white/80 backdrop-blur-sm rounded-full px-4 py-2 text-sm font-medium text-brand-orange border border-orange-200">
                            <span>About Us</span>
                        </div>
                        <h2 class="text-5xl font-bold text-gray-800">
                            About <span class="text-brand-orange">Cafe For You</span>
                        </h2>
                        <p class="text-xl text-gray-600 leading-relaxed">
                            For over 20 years, Cafe For You has been serving the finest cuisine with a commitment to quality, 
                            freshness, and exceptional service. Our talented chefs create memorable dining experiences using only the 
                            finest ingredients.
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Whether you're celebrating a special occasion or enjoying a casual meal with family and friends, 
                            we provide an atmosphere that's both elegant and welcoming.
                        </p>
                    </div>
                    
                    <a href="about.php" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-4 rounded-full font-semibold hover:shadow-xl transform hover:scale-105 transition-all duration-300 inline-block">
                        Learn More
                    </a>
                </div>

                <!-- Right Content - Restaurant Image -->
                <div class="relative">
                    <div class="bg-white rounded-3xl p-8 card-shadow hover-lift">
                        <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=500&h=400&fit=crop&crop=center" 
                             alt="Cafe For You Interior" 
                             class="w-full h-96 object-cover rounded-2xl">
                    </div>
                </div>
            </div>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
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
        // Enhanced slideshow functionality with large circle and fast response
        document.addEventListener('DOMContentLoaded', function() {
            const SLIDE_DELAY = 2000;      // Fast auto-advance (2 seconds)
            const INITIAL_DELAY = 100;     // Very quick start (100ms for first image)

            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide-image');
            const indicators = document.querySelectorAll('.slide-indicator');
            const ratingCardImage = document.getElementById('rating-card-image');
            const slideImages = [
                'https://i.pinimg.com/736x/ab/e6/57/abe65721a6d06545c99230151aab0177.jpg',
                'https://images.unsplash.com/photo-1551782450-17144efb9c50?w=500&h=500&fit=crop&crop=center',
                'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=500&h=500&fit=crop&crop=center',
                'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=500&h=500&fit=crop&crop=center'
            ];

            function showSlide(index) {
                slides.forEach((slide, i) => {
                    if (i === index) {
                        slide.style.opacity = '1';
                        slide.style.zIndex = '20';
                        slide.style.display = 'block';
                    } else {
                        slide.style.opacity = '0';
                        slide.style.zIndex = '1';
                    }
                });
                
                indicators.forEach((indicator, i) => {
                    if (i === index) {
                        indicator.classList.remove('w-3', 'bg-gray-300');
                        indicator.classList.add('w-8', 'bg-brand-orange');
                    } else {
                        indicator.classList.remove('w-8', 'bg-brand-orange');
                        indicator.classList.add('w-3', 'bg-gray-300');
                    }
                });
                
                if (ratingCardImage) {
                    ratingCardImage.src = slideImages[index];
                }
                
                currentSlide = index;
            }

            function nextSlide() {
                const nextIndex = (currentSlide + 1) % slides.length;
                showSlide(nextIndex);
            }

            // Ensure slides exist and initialize immediately
            if (slides.length > 0) {
                // Force first image to be visible immediately
                slides[0].style.opacity = '1';
                slides[0].style.zIndex = '20';
                slides[0].style.display = 'block';
                
                // Initialize slideshow
                showSlide(0);

                // Set up click handlers for immediate response
                indicators.forEach((indicator, index) => {
                    indicator.addEventListener('click', () => {
                        showSlide(index);
                        // Reset timer on manual interaction
                        clearInterval(autoAdvanceTimer);
                        autoAdvanceTimer = setInterval(nextSlide, SLIDE_DELAY);
                    });
                });

                // Start auto-advance quickly
                let autoAdvanceTimer;
                setTimeout(() => {
                    autoAdvanceTimer = setInterval(nextSlide, SLIDE_DELAY);
                }, INITIAL_DELAY);
            }
        });

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

            document.querySelectorAll('.hover-lift, .dish-card').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });
    </script>
</body>
</html>