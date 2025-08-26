<?php
// admin/menu.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Function to handle image upload
function uploadMenuImage($file) {
    $uploadDir = '../uploads/menu/';
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Check if file was uploaded
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large (max 5MB)'];
    }
    
    // Get file extension
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    // Check file type
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, JPEG, PNG, GIF, WEBP allowed'];
    }
    
    // Generate unique filename
    $filename = 'menu_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => 'uploads/menu/' . $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Function to delete image file
function deleteMenuImage($imagePath) {
    if ($imagePath && file_exists('../' . $imagePath)) {
        unlink('../' . $imagePath);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = (float)$_POST['price'];
        $category_id = (int)$_POST['category_id'];
        $image = sanitize($_POST['image_url']); // URL field
        
        // Handle file upload
        if (!empty($_FILES['image_file']['name'])) {
            $uploadResult = uploadMenuImage($_FILES['image_file']);
            if ($uploadResult['success']) {
                $image = $uploadResult['filename'];
            } else {
                showMessage($uploadResult['message'], 'error');
                $image = '';
            }
        }
        
        if (!empty($name) && !empty($category_id) && $price > 0) {
            $query = "INSERT INTO menu_items (name, description, price, category_id, image) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$name, $description, $price, $category_id, $image])) {
                showMessage('Menu item added successfully!');
            } else {
                showMessage('Failed to add menu item', 'error');
            }
        } else {
            showMessage('Please fill in all required fields', 'error');
        }
        
    } elseif (isset($_POST['update_item'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $price = (float)$_POST['price'];
        $category_id = (int)$_POST['category_id'];
        $status = sanitize($_POST['status']);
        $image = sanitize($_POST['image_url']); // URL field
        $current_image = sanitize($_POST['current_image']);
        
        // Handle file upload
        if (!empty($_FILES['image_file']['name'])) {
            $uploadResult = uploadMenuImage($_FILES['image_file']);
            if ($uploadResult['success']) {
                // Delete old image if it's a local file
                if ($current_image && strpos($current_image, 'uploads/') === 0) {
                    deleteMenuImage($current_image);
                }
                $image = $uploadResult['filename'];
            } else {
                showMessage($uploadResult['message'], 'error');
                $image = $current_image; // Keep current image if upload fails
            }
        } elseif (empty($image)) {
            $image = $current_image; // Keep current image if no new image provided
        } elseif ($image !== $current_image) {
            // New URL provided, delete old local file if exists
            if ($current_image && strpos($current_image, 'uploads/') === 0) {
                deleteMenuImage($current_image);
            }
        }
        
        $query = "UPDATE menu_items SET name = ?, description = ?, price = ?, category_id = ?, image = ?, status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$name, $description, $price, $category_id, $image, $status, $id])) {
            showMessage('Menu item updated successfully!');
        } else {
            showMessage('Failed to update menu item', 'error');
        }
        
    } elseif (isset($_POST['delete_item'])) {
        $id = (int)$_POST['id'];
        
        // Get item info to delete image
        $item_query = "SELECT image FROM menu_items WHERE id = ?";
        $item_stmt = $db->prepare($item_query);
        $item_stmt->execute([$id]);
        $item = $item_stmt->fetch(PDO::FETCH_ASSOC);
        
        $query = "DELETE FROM menu_items WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$id])) {
            // Delete associated image file
            if ($item && $item['image'] && strpos($item['image'], 'uploads/') === 0) {
                deleteMenuImage($item['image']);
            }
            showMessage('Menu item deleted successfully!');
        } else {
            showMessage('Failed to delete menu item', 'error');
        }
    }
}

// Get categories for dropdown
$cat_query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all menu items with statistics
$menu_query = "SELECT mi.*, c.name as category_name FROM menu_items mi 
               LEFT JOIN categories c ON mi.category_id = c.id 
               ORDER BY c.name, mi.name";
$menu_stmt = $db->prepare($menu_query);
$menu_stmt->execute();
$menu_items = $menu_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get menu statistics
$stats = [];
$stats['total_items'] = count($menu_items);
$stats['available_items'] = count(array_filter($menu_items, function($item) { return $item['status'] === 'available'; }));
$stats['unavailable_items'] = $stats['total_items'] - $stats['available_items'];

// Average price
$total_price = array_sum(array_column($menu_items, 'price'));
$stats['avg_price'] = $stats['total_items'] > 0 ? $total_price / $stats['total_items'] : 0;

// Get item for editing
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM menu_items WHERE id = ?";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->execute([$edit_id]);
    $edit_item = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - Cafe For You Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-orange': '#FF6B35',
                        'brand-cream': '#FFF8F0'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-brand-orange rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <h1 class="text-2xl font-bold text-brand-orange">Cafe For You - Admin</h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition">View Site</a>
                    <a href="../logout.php" class="bg-brand-orange text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg min-h-screen">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📊 Dashboard
                    </a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        🛒 Orders
                    </a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-brand-orange font-medium">
                        🍽️ Menu Management
                    </a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📂 Categories
                    </a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📅 Reservations
                    </a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        👥 Users
                    </a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📧 Messages
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800">🍽️ Menu Management</h1>
                        <p class="text-gray-600 mt-2">Create and manage delicious menu items for your restaurant</p>
                    </div>
                    <button onclick="showAddForm()" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-3 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add New Item
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="grid md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 text-sm">Total Items</p>
                                <p class="text-3xl font-bold"><?= $stats['total_items'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 12L11 14L15 10M21 12A9 9 0 11 12 3A9 9 0 01 21 12Z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm">Available Items</p>
                                <p class="text-3xl font-bold"><?= $stats['available_items'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 12L11 14L15 10M21 12A9 9 0 11 12 3A9 9 0 01 21 12Z"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-red-100 text-sm">Unavailable</p>
                                <p class="text-3xl font-bold"><?= $stats['unavailable_items'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 18L18 6M6 6L18 18"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-brand-orange to-yellow-500 rounded-xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 text-sm">Average Price</p>
                                <p class="text-3xl font-bold">$<?= number_format($stats['avg_price'], 2) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9M19 21H5V3H13V9H19Z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Add/Edit Form -->
            <div id="itemForm" class="<?= $edit_item ? '' : 'hidden' ?> mb-8 bg-white rounded-xl shadow-lg p-8 border border-gray-100">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-brand-orange rounded-xl flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?= $edit_item ? '✏️ Edit Menu Item' : '➕ Add New Menu Item' ?>
                    </h2>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_item): ?>
                        <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
                        <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_item['image'] ?? '') ?>">
                    <?php endif; ?>
                    
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Item Name *</label>
                            <input type="text" name="name" required 
                                   value="<?= htmlspecialchars($edit_item['name'] ?? '') ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300"
                                   placeholder="Enter delicious dish name...">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Category *</label>
                            <select name="category_id" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= ($edit_item && $edit_item['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Price *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 font-bold text-lg">$</span>
                                <input type="number" name="price" step="0.01" min="0" required 
                                       value="<?= $edit_item['price'] ?? '' ?>"
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300">
                            </div>
                        </div>
                        
                        <?php if ($edit_item): ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Status</label>
                            <select name="status" 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300">
                                <option value="available" <?= ($edit_item['status'] === 'available') ? 'selected' : '' ?>>✅ Available</option>
                                <option value="unavailable" <?= ($edit_item['status'] === 'unavailable') ? 'selected' : '' ?>>❌ Unavailable</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Description</label>
                        <textarea name="description" rows="4" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300 resize-none"
                                  placeholder="Describe your delicious menu item in detail..."><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Current Image Preview -->
                    <?php if ($edit_item && $edit_item['image']): ?>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Current Image</label>
                        <div class="relative inline-block">
                            <img src="../<?= htmlspecialchars($edit_item['image']) ?>" alt="Current menu item image" 
                                 class="w-32 h-32 object-cover rounded-xl border-4 border-brand-orange shadow-lg">
                            <div class="absolute -top-2 -right-2 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 12L11 14L15 10M21 12A9 9 0 11 12 3A9 9 0 01 21 12Z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Image Upload -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">📸 Upload Food Image</label>
                        <div class="mt-1 flex justify-center px-6 pt-8 pb-8 border-2 border-dashed border-brand-orange rounded-xl hover:border-orange-600 transition-all duration-300 bg-orange-50">
                            <div class="space-y-2 text-center">
                                <svg class="mx-auto h-16 w-16 text-brand-orange" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="image_file" class="relative cursor-pointer bg-white rounded-lg font-semibold text-brand-orange hover:text-orange-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-brand-orange px-3 py-1">
                                        <span>📤 Upload food photo</span>
                                        <input id="image_file" name="image_file" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF, WEBP up to 5MB</p>
                                <p class="text-xs text-brand-orange font-semibold">🌟 High-quality food photos work best!</p>
                            </div>
                        </div>
                        
                        <!-- Image Preview -->
                        <div id="imagePreview" class="mt-4 hidden">
                            <div class="flex items-center space-x-6 p-4 bg-green-50 rounded-xl border border-green-200">
                                <img id="previewImg" src="" alt="Preview" class="w-24 h-24 object-cover rounded-xl border-2 border-green-300">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-green-800">✅ Image Preview</p>
                                    <p class="text-xs text-green-600 mt-1">This is how your food photo will appear</p>
                                    <button type="button" onclick="removePreview()" class="mt-3 text-xs text-red-600 hover:text-red-800 font-medium bg-red-100 px-3 py-1 rounded-full transition-colors">
                                        🗑️ Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- OR Image URL -->
                    <div class="mb-8">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">🔗 OR Image URL</label>
                        <input type="url" name="image_url" 
                               value="<?= htmlspecialchars($edit_item['image'] ?? '') ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300"
                               placeholder="https://example.com/delicious-food-image.jpg">
                        <p class="mt-2 text-xs text-gray-500">💡 Note: Uploading a file will override the URL</p>
                    </div>
                    
                    <div class="flex space-x-4 pt-6 border-t border-gray-200">
                        <button type="submit" name="<?= $edit_item ? 'update_item' : 'add_item' ?>" 
                                class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg transform hover:scale-105 transition-all duration-300 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <?= $edit_item ? '✅ Update Menu Item' : '➕ Add Menu Item' ?>
                        </button>
                        <button type="button" onclick="hideForm()" 
                                class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-bold hover:bg-gray-300 transition-all duration-300 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            ❌ Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Menu Items Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-brand-orange to-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white">🍽️ Menu Items</h3>
                            <p class="text-orange-100 text-sm">Manage your delicious offerings (<?= count($menu_items) ?> items)</p>
                        </div>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-white font-bold text-lg"><?= count($menu_items) ?></span>
                            <span class="text-orange-100 text-sm">Total Items</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Food Item</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($menu_items as $item): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php 
                                            $imageSrc = $item['image'] ? '../' . $item['image'] : 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=80&h=80&fit=crop&crop=center';
                                            ?>
                                            <div class="relative">
                                                <img class="h-16 w-16 rounded-xl object-cover border-2 border-brand-orange shadow-md" 
                                                     src="<?= $imageSrc ?>" 
                                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                                     onerror="this.src='https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=80&h=80&fit=crop&crop=center'">
                                                <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-brand-orange rounded-full flex items-center justify-center">
                                                    <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M12 2L15.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z"/>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></div>
                                                <div class="text-sm text-gray-500 max-w-xs truncate"><?= htmlspecialchars($item['description']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                            <?= htmlspecialchars($item['category_name'] ?? 'No Category') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-lg font-bold text-green-600">
                                        $<?= number_format($item['price'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-xs font-bold rounded-full border-2 
                                            <?= $item['status'] === 'available' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300' ?>">
                                            <?= $item['status'] === 'available' ? 'Available' : 'Unavailable' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="menu.php?edit=<?= $item['id'] ?>" 
                                               class="bg-brand-orange text-white px-4 py-2 rounded-lg font-semibold hover:bg-orange-600 transition-all duration-300 flex items-center text-xs">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this delicious menu item?')">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" name="delete_item" 
                                                        class="bg-red-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-600 transition-all duration-300 flex items-center text-xs">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($menu_items)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No Menu Items Yet</h3>
                    <p class="text-gray-500 mb-4">Start building your delicious menu by adding your first item.</p>
                    <button onclick="showAddForm()" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-6 py-3 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                        Add Your First Menu Item
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function showAddForm() {
            document.getElementById('itemForm').classList.remove('hidden');
            document.getElementById('itemForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function hideForm() {
            document.getElementById('itemForm').classList.add('hidden');
            window.location.href = 'menu.php';
        }
        
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removePreview() {
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('image_file').value = '';
        }
        
        // Enhanced drag and drop functionality
        const dropArea = document.querySelector('.border-dashed');
        const fileInput = document.getElementById('image_file');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropArea.classList.add('border-orange-600', 'bg-orange-100');
            dropArea.classList.remove('border-brand-orange', 'bg-orange-50');
        }
        
        function unhighlight(e) {
            dropArea.classList.remove('border-orange-600', 'bg-orange-100');
            dropArea.classList.add('border-brand-orange', 'bg-orange-50');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            previewImage(fileInput);
        }
        
        // Auto-hide success messages with fade effect
        setTimeout(function() {
            const successMessages = document.querySelectorAll('.bg-green-500');
            successMessages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(function() {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            });
        }, 4000);
        
        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(4px)';
                    this.style.boxShadow = '4px 0 8px rgba(255, 107, 53, 0.1)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.boxShadow = 'none';
                });
            });
        });
    </script>
</body>
</html>