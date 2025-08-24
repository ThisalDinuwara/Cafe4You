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
    <title>Menu - Delicious Restaurant</title>
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
                    <a href="menu.php" class="text-orange-600 font-semibold">Menu</a>
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

    <!-- Page Header -->
    <section class="bg-gradient-to-r from-orange-500 to-red-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl font-bold mb-4">Our Menu</h1>
            <p class="text-xl">Discover our delicious selection of dishes</p>
        </div>
    </section>

    <!-- Category Filter -->
    <section class="bg-white py-8 shadow">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-wrap justify-center gap-4">
                <a href="menu.php" class="px-6 py-2 rounded-full <?= empty($selected_category) ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-orange-100' ?> transition">
                    All Items
                </a>
                <?php foreach ($categories as $category): ?>
                    <a href="menu.php?category=<?= $category['id'] ?>" 
                       class="px-6 py-2 rounded-full <?= $selected_category == $category['id'] ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-orange-100' ?> transition">
                        <?= htmlspecialchars($category['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Menu Items -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4">
            <?php displayMessage(); ?>
            
            <?php if (empty($menu_items)): ?>
                <div class="text-center py-16">
                    <h3 class="text-2xl text-gray-600">No items found</h3>
                </div>
            <?php else: ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($menu_items as $item): ?>
                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                        <img src="<?= $item['image'] ?: 'https://via.placeholder.com/400x300' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-48 object-cover">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h3>
                                <span class="text-sm text-orange-600 font-medium"><?= htmlspecialchars($item['category_name']) ?></span>
                            </div>
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
                                <?php else: ?>
                                    <a href="login.php" class="bg-gray-400 text-white px-4 py-2 rounded">Login to Order</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; 2025 Delicious Restaurant. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>