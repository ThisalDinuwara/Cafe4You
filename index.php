<?php
// index.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

/**
 * Pull featured menu items + their latest promo (if any).
 * We fetch the latest promo per item by joining on MAX(id) for that item.
 */
$query = "
    SELECT
        mi.id,
        mi.name,
        mi.description,
        mi.image,
        mi.price              AS base_price,
        mi.status,
        mi.created_at,
        c.name                AS category_name,
        p.id                  AS promo_id,
        p.promo_price,
        p.discount_percent,
        p.label,
        p.starts_at,
        p.ends_at,
        p.active
    FROM menu_items mi
    JOIN categories c ON c.id = mi.category_id
    LEFT JOIN (
        SELECT mp1.*
        FROM menu_promotions mp1
        JOIN (
            SELECT menu_item_id, MAX(id) AS max_id
            FROM menu_promotions
            GROUP BY menu_item_id
        ) t ON t.menu_item_id = mp1.menu_item_id AND t.max_id = mp1.id
    ) p ON p.menu_item_id = mi.id
    WHERE mi.status = 'available'
    ORDER BY mi.created_at DESC
    LIMIT 6
";
$stmt = $db->prepare($query);
$stmt->execute();
$featured_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Decide if promo is live and compute final price.
 * Returns [is_live(bool), final_price(float|null), badge('LIVE'|'SCHEDULED'|null)]
 */
function evaluate_promo(?array $row): array {
    $base = isset($row['base_price']) ? (float)$row['base_price'] : 0.0;

    // No promo at all
    if (empty($row['promo_id'])) {
        return [false, null, null];
    }

    $active = (int)($row['active'] ?? 0) === 1;
    $now = new DateTime('now');
    $starts_ok = empty($row['starts_at']) || (new DateTime($row['starts_at'])) <= $now;
    $ends_ok   = empty($row['ends_at'])   || (new DateTime($row['ends_at']))   >= $now;

    // Compute potential final
    $final = null;
    if ($row['promo_price'] !== null && $row['promo_price'] !== '') {
        $final = (float)$row['promo_price'];
    } elseif ($row['discount_percent'] !== null && $row['discount_percent'] !== '') {
        $pct = max(0.0, min(95.0, (float)$row['discount_percent']));
        $final = max(0.0, $base * (1 - $pct / 100.0));
    }

    // Promo considered LIVE only if: active + within window + actually cheaper
    if ($active && $starts_ok && $ends_ok && $final !== null && $final < $base) {
        return [true, $final, 'LIVE'];
    }

    // If we have a promo row but it's not live yet or already ended, mark SCHEDULED
    return [false, null, 'SCHEDULED'];
}
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
                        'brand-yellow': '#FCD34D',
                        'brand-amber':  '#F59E0B',
                        'brand-cream':  '#FFF8F0',
                        'brand-brown':  '#8B4513',
                        'brand-gray':   '#F5F5F5'
                    },
                    fontFamily: {
                        'display': ['Georgia', 'serif'],
                        'body':    ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        .hero-gradient { background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%); }
        .card-shadow { box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .hover-lift { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-lift:hover { transform: translateY(-8px); }

        .floating-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dish-card { position: relative; overflow: hidden; }
        .dish-card::before {
            content: '';
            position: absolute; top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        .dish-card:hover::before { left: 100%; }

        .blob { position: absolute; border-radius: 50%; filter: blur(40px); opacity: 0.7; animation: float 6s ease-in-out infinite; }
        .blob-1 { top: 20%; left: 10%; width: 300px; height: 300px; background: linear-gradient(45deg, #FCD34D, #F59E0B); animation-delay: 0s; }
        .blob-2 { top: 60%; right: 10%; width: 200px; height: 200px; background: linear-gradient(45deg, #F59E0B, #FCD34D); animation-delay: 2s; }
        @keyframes float { 0%,100% {transform: translateY(0) rotate(0)} 33% {transform: translateY(-20px) rotate(5deg)} 66% {transform: translateY(10px) rotate(-5deg)} }

        .slideshow-container { position: relative; }
        .slide-image { display: block; width: 100%; height: 100%; }
        .slide-image:first-child { position: relative !important; z-index: 10 !important; }

        /* Fixed Add to Cart Button Styles */
        .add-to-cart-btn {
            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(252, 211, 77, 0.3);
            font-size: 14px;
            min-width: 120px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .add-to-cart-btn:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(252, 211, 77, 0.4);
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        }

        .add-to-cart-btn:active {
            transform: translateY(0) scale(0.98);
        }

        /* Consistent card layout */
        .menu-item-card {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .menu-item-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .menu-item-footer {
            margin-top: auto;
            padding-top: 16px;
        }

        .price-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .price-display {
            flex: 1;
            min-width: 120px;
        }

        .button-wrapper {
            flex-shrink: 0;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .price-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .add-to-cart-btn {
                width: 100%;
                margin-top: 8px;
            }
        }
    </style>
</head>
<body class="bg-brand-cream font-body">
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-yellow-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-xl">C</span>
                    </div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">Cafe For You</h1>
                </div>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Home</a>
                    <a href="menu.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Menu</a>
                    <a href="reservations.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Reservations</a>
                    <a href="contact.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Contact</a>

                    <?php if (isLoggedIn()): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Orders</a>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Login</a>
                        <a href="register.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Register</a>
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
        <!-- Decorative dots -->
        <div class="absolute top-20 left-20 w-4 h-4 bg-brand-yellow rounded-full animate-bounce"></div>
        <div class="absolute top-40 right-32 w-6 h-6 bg-yellow-400 rounded-full"></div>
        <div class="absolute bottom-32 left-16 w-3 h-3 bg-amber-400 rounded-full"></div>
        <div class="absolute top-60 left-1/3 w-2 h-2 bg-yellow-300 rounded-full"></div>

        <div class="max-w-7xl mx-auto px-6 py-20 relative z-10">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Left Content -->
                <div class="space-y-8">
                    <div class="space-y-6">
                        <h1 class="text-6xl lg:text-7xl font-bold leading-tight">
                            <span class="text-gray-800">WELCOME</span><br>
                            <span class="text-gray-800">TO </span>
                            <span class="bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">CAFE FOR YOU</span>
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
                        <a href="reservations.php" class="border-2 border-yellow-200 text-gray-700 px-8 py-4 rounded-full font-semibold hover:border-gray-800 hover:text-gray-800 transition-all duration-300 bg-white">
                            Get Directions
                        </a>
                    </div>

                    <!-- Info Cards -->
                    <div class="grid grid-cols-3 gap-6 pt-8">
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-800 text-sm mb-1">Fast Delivery</h3>
                            <p class="text-xs text-gray-600">Experience lightning-fast delivery that brings restaurant-quality meals to your door.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-semibold text-gray-800 text-sm mb-1">Fresh Recipe</h3>
                            <p class="text-xs text-gray-600">Our expert chefs use only the freshest ingredients, sourced locally when possible.</p>
                        </div>
                        <div class="text-center">
                            <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <div class="relative">
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

                        <!-- Simple utensil decorations + emojis -->
                        <div class="absolute -top-10 -right-12 w-20 h-20 bg-white rounded-full shadow-xl flex items-center justify-center animate-bounce" style="animation-duration:3s;"><span class="text-3xl">🍅</span></div>
                        <div class="absolute -bottom-6 -left-12 w-16 h-16 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 5s ease-in-out infinite;"><span class="text-2xl">🥗</span></div>
                        <div class="absolute top-10 -left-16 w-18 h-18 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 6s ease-in-out infinite; animation-delay: 1.5s;"><span class="text-2xl">🧄</span></div>
                        <div class="absolute top-20 right-8 w-14 h-14 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 4s ease-in-out infinite; animation-delay: 3s;"><span class="text-xl">🌶️</span></div>
                        <div class="absolute bottom-16 right-16 w-12 h-12 bg-white rounded-full shadow-xl flex items-center justify-center" style="animation: float 4.5s ease-in-out infinite; animation-delay: 2s;"><span class="text-lg">🥑</span></div>
                    </div>

                    <!-- Slide indicators -->
                    <div class="flex justify-center space-x-3 mt-8" id="slide-indicators">
                        <button class="slide-indicator w-8 h-3 rounded-full bg-brand-yellow transition-all duration-300 shadow-md" data-slide="0"></button>
                        <button class="slide-indicator w-3 h-3 rounded-full bg-gray-300 transition-all duration-300 shadow-md hover:bg-gray-400" data-slide="1"></button>
                        <button class="slide-indicator w-3 h-3 rounded-full bg-gray-300 transition-all duration-300 shadow-md hover:bg-gray-400" data-slide="2"></button>
                        <button class="slide-indicator w-3 h-3 rounded-full bg-gray-300 transition-all duration-300 shadow-md hover:bg-gray-400" data-slide="3"></button>
                    </div>

                    <!-- Rating Card -->
                    <div class="absolute -bottom-12 -right-8 bg-white rounded-3xl p-5 shadow-2xl border border-gray-100">
                        <div class="flex items-center space-x-4">
                            <img id="rating-card-image" src="https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=80&h=80&fit=crop&crop=center" alt="Current Dish" class="w-16 h-16 object-cover rounded-2xl">
                            <div>
                                <h4 class="font-semibold text-base text-gray-800">Culinary Excellence</h4>
                                <p class="text-sm text-gray-600">and Gourmet</p>
                                <div class="flex items-center mt-2">
                                    <div class="flex text-yellow-400 text-sm">★★★★★</div>
                                    <span class="text-sm text-gray-600 ml-2 font-medium">5.0</span>
                                    <span class="w-8 h-8 bg-brand-yellow rounded-full flex items-center justify-center ml-3">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
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
                    Become a true <span class="text-brand-yellow">chef</span><br>
                    with our recipes.
                </h2>
                <p class="text-gray-600 max-w-2xl mx-auto">
                    Join our culinary journey and discover the secrets behind our most beloved dishes with step-by-step guidance from our expert chefs.
                </p>
            </div>
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="bg-gray-50 rounded-3xl p-8 relative overflow-hidden">
                    <div class="absolute top-4 left-4 bg-gray-800 text-white px-3 py-1 rounded-full text-sm font-medium">STEP 1</div>
                    <img src="https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=300&h=200&fit=crop&crop=center" alt="Cooking Process" class="w-full h-48 object-cover rounded-2xl mt-8">
                    <div class="mt-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">Preparation</h3>
                        <p class="text-gray-600 text-sm">Learn the fundamentals of ingredient preparation and mise en place.</p>
                    </div>
                </div>
                <div class="bg-brand-yellow rounded-3xl p-8 text-gray-900 relative">
                    <div class="absolute top-4 right-4 text-6xl opacity-20">"</div>
                    <div class="space-y-4 relative z-10">
                        <h3 class="text-2xl font-bold leading-tight">"Cooking has<br>never been<br>this easy!"</h3>
                        <div class="space-y-3 mt-8">
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-sm">Professional techniques</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-sm">Step-by-step guidance</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-sm">Expert chef support</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div class="bg-gray-100 rounded-3xl p-6 h-full flex items-end relative overflow-hidden">
                        <div class="absolute top-4 right-4 bg-white px-3 py-1 rounded-full text-sm font-medium text-gray-700">Chef Master</div>
                        <img src="https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&h=400&fit=crop&crop=center" alt="Professional Chef" class="w-full h-80 object-cover rounded-2xl">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Items with Fixed Add to Cart Buttons -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <div class="inline-flex items-center bg-yellow-100 rounded-full px-4 py-2 text-sm font-medium text-brand-amber mb-4">
                    <span>Our Specialties</span>
                </div>
                <h2 class="text-5xl font-bold text-gray-800 mb-6">Featured <span class="text-brand-yellow">Dishes</span></h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Discover our chef's carefully curated selection of signature dishes, crafted with passion and the finest ingredients.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_items as $item): 
                    // Build a uniform array to feed evaluate_promo()
                    $row = [
                        'base_price'       => $item['base_price'],
                        'promo_id'         => $item['promo_id'] ?? null,
                        'promo_price'      => $item['promo_price'] ?? null,
                        'discount_percent' => $item['discount_percent'] ?? null,
                        'starts_at'        => $item['starts_at'] ?? null,
                        'ends_at'          => $item['ends_at'] ?? null,
                        'active'           => $item['active'] ?? 0,
                    ];
                    [$isLive, $finalPrice, $badge] = evaluate_promo($row);

                    $imgSrc = $item['image'] ?: 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400&h=250&fit=crop&crop=center';
                ?>
                <div class="bg-white rounded-3xl overflow-hidden card-shadow hover-lift dish-card menu-item-card">
                    <div class="relative">
                        <img src="<?= htmlspecialchars($imgSrc) ?>"
                             alt="<?= htmlspecialchars($item['name']) ?>"
                             class="w-full h-56 object-cover"
                             onerror="this.src='https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400&h=250&fit=crop&crop=center'">
                        <div class="absolute top-4 left-4 flex gap-2">
                            <span class="bg-brand-yellow text-gray-900 text-xs px-3 py-1 rounded-full font-medium border border-yellow-300">
                                <?= htmlspecialchars($item['category_name']) ?>
                            </span>

                            <?php if ($badge === 'LIVE'): ?>
                                <span class="bg-emerald-500 text-white text-xs px-3 py-1 rounded-full font-semibold animate-pulse">
                                    🔥 LIVE PROMO
                                </span>
                            <?php elseif ($badge === 'SCHEDULED'): ?>
                                <span class="bg-yellow-500 text-white text-xs px-3 py-1 rounded-full font-semibold">
                                    ⏰ SCHEDULED
                                </span>
                            <?php endif; ?>
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

                    <div class="p-6 menu-item-content">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-gray-600 mb-4 text-sm leading-relaxed flex-grow"><?= htmlspecialchars($item['description']) ?></p>

                        <div class="menu-item-footer">
                            <div class="price-section">
                                <div class="price-display">
                                    <?php if ($isLive && $finalPrice !== null): ?>
                                        <div class="space-y-1">
                                            <div class="text-sm text-gray-500 line-through">
                                                Rs.<?= number_format((float)$item['base_price'], 2) ?>
                                            </div>
                                            <div class="text-2xl font-bold text-emerald-600 flex items-center gap-2">
                                                Rs.<?= number_format($finalPrice, 2) ?>
                                                <span class="text-xs bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full">
                                                    <?= round((((float)$item['base_price'] - $finalPrice) / (float)$item['base_price']) * 100) ?>% OFF
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-2xl font-bold text-brand-amber">
                                            Rs.<?= number_format((float)$item['base_price'], 2) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (isLoggedIn()): ?>
                                    <div class="button-wrapper">
                                        <form method="POST" action="add_to_cart.php" class="inline">
                                            <input type="hidden" name="menu_item_id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="add-to-cart-btn">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m0 0h8.5"></path>
                                                </svg>
                                                Add to Cart
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="button-wrapper">
                                        <a href="login.php" class="add-to-cart-btn">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                            </svg>
                                            Login to Order
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
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
                <div class="space-y-8">
                    <div class="space-y-4">
                        <div class="inline-flex items-center bg-white/80 backdrop-blur-sm rounded-full px-4 py-2 text-sm font-medium text-brand-amber border border-yellow-200">
                            <span>About Us</span>
                        </div>
                        <h2 class="text-5xl font-bold text-gray-800">About <span class="text-brand-yellow">Cafe For You</span></h2>
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
                    <a href="about.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-8 py-4 rounded-full font-semibold hover:shadow-xl transform hover:scale-105 transition-all duration-300 inline-block">
                        Learn More
                    </a>
                </div>
                <div class="relative">
                    <div class="bg-white rounded-3xl p-8 card-shadow hover-lift">
                        <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=500&h=400&fit=crop&crop=center" alt="Cafe For You Interior" class="w-full h-96 object-cover rounded-2xl">
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
                        <div class="w-12 h-12 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center">
                            <span class="text-white font-bold text-xl">C</span>
                        </div>
                        <h3 class="text-2xl font-bold">Cafe For You</h3>
                    </div>
                    <p class="text-gray-400 leading-relaxed">Experience fine dining at its best with our exquisite menu and exceptional service crafted with passion.</p>
                </div>
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="menu.php" class="hover:text-white transition-colors duration-300 hover:text-brand-yellow">Menu</a></li>
                        <li><a href="reservations.php" class="hover:text-white transition-colors duration-300 hover:text-brand-yellow">Reservations</a></li>
                        <li><a href="contact.php" class="hover:text-white transition-colors duration-300 hover:text-brand-yellow">Contact</a></li>
                    </ul>
                </div>
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Contact Info</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <span>123 Restaurant Street</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span class="ml-8">City, State 12345</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            <span>(555) 123-4567</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            <span>info@cafeforyou.com</span>
                        </li>
                    </ul>
                </div>
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Hours</h4>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li class="flex justify-between"><span>Monday - Thursday:</span><span class="text-white">11am - 10pm</span></li>
                        <li class="flex justify-between"><span>Friday - Saturday:</span><span class="text-white">11am - 11pm</span></li>
                        <li class="flex justify-between"><span>Sunday:</span><span class="text-white">12pm - 9pm</span></li>
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
        // Slideshow logic
        document.addEventListener('DOMContentLoaded', function() {
            const SLIDE_DELAY = 2000, INITIAL_DELAY = 100;
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
            
            function showSlide(index){
                slides.forEach((slide,i)=>{ 
                    slide.style.opacity = (i===index)?'1':'0'; 
                    slide.style.zIndex=(i===index)?'20':'1'; 
                    if(i!==0) slide.style.display='block'; 
                });
                indicators.forEach((ind,i)=>{ 
                    if(i===index){ 
                        ind.classList.remove('w-3','bg-gray-300'); 
                        ind.classList.add('w-8','bg-brand-yellow'); 
                    } else { 
                        ind.classList.remove('w-8','bg-brand-yellow'); 
                        ind.classList.add('w-3','bg-gray-300'); 
                    }
                });
                if(ratingCardImage) ratingCardImage.src = slideImages[index];
                currentSlide = index;
            }
            
            function nextSlide(){ 
                showSlide((currentSlide+1)%slides.length); 
            }
            
            if(slides.length>0){ 
                slides[0].style.opacity='1'; 
                slides[0].style.zIndex='20'; 
                slides[0].style.display='block'; 
                showSlide(0);
                indicators.forEach((ind,idx)=>{ 
                    ind.addEventListener('click',()=>{ 
                        showSlide(idx); 
                        clearInterval(t); 
                        t=setInterval(nextSlide,SLIDE_DELAY); 
                    }); 
                });
                let t; 
                setTimeout(()=>{ 
                    t=setInterval(nextSlide,SLIDE_DELAY); 
                }, INITIAL_DELAY);
            }
        });

        // Smooth scroll for anchors
        document.querySelectorAll('a[href^="#"]').forEach(a=>{
            a.addEventListener('click',e=>{
                e.preventDefault();
                const t=document.querySelector(a.getAttribute('href'));
                if(t) t.scrollIntoView({behavior:'smooth',block:'start'});
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll',()=>{
            const nav=document.querySelector('nav');
            if(window.scrollY>100) nav.classList.add('bg-white/95');
            else nav.classList.remove('bg-white/95');
        });

        // Reveal on scroll
        document.addEventListener('DOMContentLoaded',()=>{
            const obs=new IntersectionObserver((entries)=>{ 
                entries.forEach(entry=>{ 
                    if(entry.isIntersecting){ 
                        entry.target.style.opacity='1'; 
                        entry.target.style.transform='translateY(0)'; 
                    } 
                }); 
            }, {threshold:0.1, rootMargin:'0px 0px -50px 0px'});
            
            document.querySelectorAll('.hover-lift, .dish-card').forEach(el=>{ 
                el.style.opacity='0'; 
                el.style.transform='translateY(20px)'; 
                el.style.transition='opacity .6s ease, transform .6s ease'; 
                obs.observe(el); 
            });
        });

        // Add to cart animation
        document.addEventListener('DOMContentLoaded', function() {
            const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
            
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Create a ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });
    </script>

    <style>
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }

        @keyframes ripple-animation {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
</body>
</html>