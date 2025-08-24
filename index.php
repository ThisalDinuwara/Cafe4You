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
    <title>Delicious Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
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
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-orange-600 transition">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-orange-600 transition">Orders</a>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="text-gray-700 hover:text-orange-600 transition">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-orange-600 transition">Login</a>
                        <a href="register.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-orange-500 to-red-600 text-white">
        <div class="max-w-7xl mx-auto px-4 py-20">
            <div class="text-center">
                <h1 class="text-5xl font-bold mb-6">Welcome to Delicious Restaurant</h1>
                <p class="text-xl mb-8">Experience the finest dining with our exquisite menu and exceptional service</p>
                <div class="space-x-4">
                    <a href="menu.php" class="bg-white text-orange-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">View Menu</a>
                    <a href="reservations.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-orange-600 transition">Make Reservation</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Items -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-12">Featured Dishes</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_items as $item): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                    <img src="<?= $item['image'] ?: 'https://via.placeholder.com/400x300' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-48 object-cover">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($item['name']) ?></h3>
                        <p class="text-gray-600 mb-4"><?= htmlspecialchars($item['description']) ?></p>
                        <div class="flex justify-between items-center">
                            <span class="text-2xl font-bold text-orange-600">$<?= number_format($item['price'], 2) ?></span>
                            <?php if (isLoggedIn()): ?>
                                <form method="POST" action="add_to_cart.php" class="inline">
                                    <input type="hidden" name="menu_item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">
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
    <section class="bg-gray-100 py-16">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold text-gray-800 mb-6">About Delicious Restaurant</h2>
                    <p class="text-gray-600 mb-4">
                        For over 20 years, Delicious Restaurant has been serving the finest cuisine with a commitment to quality, 
                        freshness, and exceptional service. Our talented chefs create memorable dining experiences using only the 
                        finest ingredients.
                    </p>
                    <p class="text-gray-600 mb-6">
                        Whether you're celebrating a special occasion or enjoying a casual meal with family and friends, 
                        we provide an atmosphere that's both elegant and welcoming.
                    </p>
                    <a href="about.php" class="bg-orange-600 text-white px-6 py-3 rounded hover:bg-orange-700 transition">Learn More</a>
                </div>
                <div>
                    <img src="https://via.placeholder.com/500x400" alt="Restaurant Interior" class="rounded-lg shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Delicious Restaurant</h3>
                    <p class="text-gray-400">Experience fine dining at its best with our exquisite menu and exceptional service.</p>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="menu.php" class="hover:text-white transition">Menu</a></li>
                        <li><a href="reservations.php" class="hover:text-white transition">Reservations</a></li>
                        <li><a href="contact.php" class="hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li>123 Restaurant Street</li>
                        <li>City, State 12345</li>
                        <li>(555) 123-4567</li>
                        <li>info@delicious.com</li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold mb-4">Hours</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li>Monday - Thursday: 11am - 10pm</li>
                        <li>Friday - Saturday: 11am - 11pm</li>
                        <li>Sunday: 12pm - 9pm</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 Delicious Restaurant. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>