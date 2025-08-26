<?php
// admin/categories.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Function to handle image upload
function uploadImage($file) {
    $uploadDir = '../uploads/categories/';
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
    $filename = 'category_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => 'uploads/categories/' . $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Function to delete image file
function deleteImage($imagePath) {
    if ($imagePath && file_exists('../' . $imagePath)) {
        unlink('../' . $imagePath);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $image = sanitize($_POST['image_url']); // URL field
        
        // Handle file upload
        if (!empty($_FILES['image_file']['name'])) {
            $uploadResult = uploadImage($_FILES['image_file']);
            if ($uploadResult['success']) {
                $image = $uploadResult['filename'];
            } else {
                showMessage($uploadResult['message'], 'error');
                $image = '';
            }
        }
        
        if (!empty($name)) {
            $query = "INSERT INTO categories (name, description, image) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$name, $description, $image])) {
                showMessage('Category added successfully!');
            } else {
                showMessage('Failed to add category', 'error');
            }
        } else {
            showMessage('Category name is required', 'error');
        }
        
    } elseif (isset($_POST['update_category'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $status = sanitize($_POST['status']);
        $image = sanitize($_POST['image_url']); // URL field
        $current_image = sanitize($_POST['current_image']);
        
        // Handle file upload
        if (!empty($_FILES['image_file']['name'])) {
            $uploadResult = uploadImage($_FILES['image_file']);
            if ($uploadResult['success']) {
                // Delete old image if it's a local file
                if ($current_image && strpos($current_image, 'uploads/') === 0) {
                    deleteImage($current_image);
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
                deleteImage($current_image);
            }
        }
        
        $query = "UPDATE categories SET name = ?, description = ?, image = ?, status = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$name, $description, $image, $status, $id])) {
            showMessage('Category updated successfully!');
        } else {
            showMessage('Failed to update category', 'error');
        }
        
    } elseif (isset($_POST['delete_category'])) {
        $id = (int)$_POST['id'];
        
        // Get category info to delete image
        $cat_query = "SELECT image FROM categories WHERE id = ?";
        $cat_stmt = $db->prepare($cat_query);
        $cat_stmt->execute([$id]);
        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if category has menu items
        $check_query = "SELECT COUNT(*) FROM menu_items WHERE category_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$id]);
        $item_count = $check_stmt->fetchColumn();
        
        if ($item_count > 0) {
            showMessage('Cannot delete category with existing menu items', 'error');
        } else {
            $query = "DELETE FROM categories WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$id])) {
                // Delete associated image file
                if ($category && $category['image'] && strpos($category['image'], 'uploads/') === 0) {
                    deleteImage($category['image']);
                }
                showMessage('Category deleted successfully!');
            } else {
                showMessage('Failed to delete category', 'error');
            }
        }
    }
}

// Get all categories with statistics
$categories_query = "SELECT c.*, COUNT(mi.id) as item_count 
                    FROM categories c 
                    LEFT JOIN menu_items mi ON c.id = mi.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate category statistics
$stats = [];
$stats['total_categories'] = count($categories);
$stats['active_categories'] = count(array_filter($categories, fn($cat) => $cat['status'] === 'active'));
$stats['inactive_categories'] = $stats['total_categories'] - $stats['active_categories'];
$stats['total_items'] = array_sum(array_column($categories, 'item_count'));
$stats['avg_items_per_category'] = $stats['total_categories'] > 0 ? $stats['total_items'] / $stats['total_categories'] : 0;

// Get category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM categories WHERE id = ?";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->execute([$edit_id]);
    $edit_category = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Cafe For You Admin</title>
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
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        🍽️ Menu Management
                    </a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-brand-orange font-medium">
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
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold text-gray-800">📂 Category Management</h1>
                    <p class="text-gray-600 mt-2">Organize and manage your menu categories</p>
                </div>
                <button onclick="showAddForm()" class="bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add New Category
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="grid md:grid-cols-4 gap-6 mb-8">
                <!-- Total Categories Card -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Categories</p>
                            <p class="text-3xl font-bold"><?= $stats['total_categories'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L2 7L12 12L22 7L12 2ZM2 17L12 22L22 17M2 12L12 17L22 12"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Active Categories Card -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Active Categories</p>
                            <p class="text-3xl font-bold"><?= $stats['active_categories'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 12L11 14L15 10M21 12C21 16.97 16.97 21 12 21C7.03 21 3 16.97 3 12C3 7.03 7.03 3 12 3C16.97 3 21 7.03 21 12Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Menu Items Card -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Total Menu Items</p>
                            <p class="text-3xl font-bold"><?= $stats['total_items'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.1 13.34L2 14L3.74 9.19L9.84 8.5L8.1 13.34ZM14.5 11L16.69 7.93L22.78 9.4L21.05 14.21L14.5 11ZM12.9 5.24C13.75 2.5 16.38.61 19.5 1.5C19.83 1.61 20.17 1.75 20.5 1.9L21.58 4.32L19.93 5.56L18.22 4.32C17.76 4.05 17.20 3.95 16.69 4.05C15.47 4.31 14.85 5.57 15.1 6.8C15.2 7.08 15.37 7.32 15.6 7.5L17.31 8.74L14.89 10.41L12.9 5.24ZM9.29 4.75L5.69 3.42L6.73 1L10.33 2.33L9.29 4.75ZM5.1 6.8L1.5 5.47L2.54 3.05L6.14 4.38L5.1 6.8ZM12.5 18.16L14.1 16.63L16.45 19.27L15.38 21.7L12.5 18.16ZM8.9 17.8L6.55 15.16L5.48 17.59L8.36 21.13L10.81 18.78L8.9 17.8Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Average Items Per Category Card -->
                <div class="bg-gradient-to-r from-brand-orange to-yellow-500 rounded-xl shadow-lg p-6 text-white transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Avg Items/Category</p>
                            <p class="text-3xl font-bold"><?= number_format($stats['avg_items_per_category'], 1) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16 6L18.29 8.29L13.41 13.17L9.41 9.17L2 16.59L3.41 18L9.41 12L13.41 16L19.71 9.71L22 12V6H16Z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <?php displayMessage(); ?>

            <!-- Add/Edit Form -->
            <div id="categoryForm" class="<?= $edit_category ? '' : 'hidden' ?> mb-8">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                    <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-brand-orange to-red-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-white">
                                    <?= $edit_category ? '✏️ Edit Category' : '➕ Add New Category' ?>
                                </h3>
                                <p class="text-orange-100 text-sm">
                                    <?= $edit_category ? 'Update category information and settings' : 'Create a new menu category' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="p-8">
                        <?php if ($edit_category): ?>
                            <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                            <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_category['image'] ?? '') ?>">
                        <?php endif; ?>
                        
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-3">Category Name *</label>
                                <input type="text" name="name" required 
                                       value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300"
                                       placeholder="Enter category name">
                            </div>
                            
                            <?php if ($edit_category): ?>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-3">Status</label>
                                <select name="status" 
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300">
                                    <option value="active" <?= ($edit_category['status'] === 'active') ? 'selected' : '' ?>>🟢 Active</option>
                                    <option value="inactive" <?= ($edit_category['status'] === 'inactive') ? 'selected' : '' ?>>🔴 Inactive</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Description</label>
                            <textarea name="description" rows="4" 
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300"
                                      placeholder="Describe this category..."><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Current Image Preview -->
                        <?php if ($edit_category && $edit_category['image']): ?>
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Current Image</label>
                            <div class="relative inline-block">
                                <img src="../<?= htmlspecialchars($edit_category['image']) ?>" alt="Current category image" 
                                     class="w-32 h-32 object-cover rounded-xl border-4 border-gray-200 shadow-lg">
                                <div class="absolute -top-2 -right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 12L11 14L15 10M21 12C21 16.97 16.97 21 12 21C7.03 21 3 16.97 3 12C3 7.03 7.03 3 12 3C16.97 3 21 7.03 21 12Z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Image Upload -->
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 mb-3">Upload New Image</label>
                            <div class="mt-1 flex justify-center px-6 pt-8 pb-8 border-2 border-brand-orange border-dashed rounded-xl bg-orange-50 hover:bg-orange-100 transition-all duration-300">
                                <div class="space-y-2 text-center">
                                    <svg class="mx-auto h-16 w-16 text-brand-orange" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="text-brand-orange">
                                        <label for="image_file" class="relative cursor-pointer bg-transparent rounded-md font-bold text-brand-orange hover:text-orange-700 focus-within:outline-none transition-colors duration-300">
                                            <span class="text-lg">📤 Upload a file</span>
                                            <input id="image_file" name="image_file" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                        </label>
                                        <p class="text-brand-orange">or drag and drop</p>
                                    </div>
                                    <p class="text-sm text-gray-500">PNG, JPG, GIF, WEBP up to 5MB</p>
                                </div>
                            </div>
                            
                            <!-- Image Preview -->
                            <div id="imagePreview" class="mt-4 hidden">
                                <div class="relative inline-block">
                                    <img id="previewImg" src="" alt="Preview" class="w-32 h-32 object-cover rounded-xl border-4 border-brand-orange shadow-lg">
                                    <div class="absolute -top-2 -right-2 w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M19 7L18.3 7.7L16.6 6L17.3 5.3C17.7 4.9 18.3 4.9 18.7 5.3L19 5.6C19.4 6 19.4 6.6 19 7M15.9 6.7L9 13.6V16H11.4L18.3 9.1L15.9 6.7Z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- OR Image URL -->
                        <div class="mb-8">
                            <label class="block text-sm font-bold text-gray-700 mb-3">OR Image URL</label>
                            <input type="url" name="image_url" 
                                   value="<?= htmlspecialchars($edit_category['image'] ?? '') ?>"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-orange focus:border-transparent transition-all duration-300"
                                   placeholder="https://example.com/image.jpg">
                            <p class="mt-2 text-sm text-gray-500">💡 Note: Uploading a file will override the URL</p>
                        </div>
                        
                        <div class="flex space-x-4 pt-6 border-t border-gray-200">
                            <button type="submit" name="<?= $edit_category ? 'update_category' : 'add_category' ?>" 
                                    class="flex-1 bg-gradient-to-r from-brand-orange to-red-500 text-white px-8 py-4 rounded-xl font-bold hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <?= $edit_category ? 'Update Category' : 'Create Category' ?>
                            </button>
                            <button type="button" onclick="hideForm()" 
                                    class="flex-1 bg-gray-200 text-gray-700 px-8 py-4 rounded-xl font-bold hover:bg-gray-300 transition-all duration-300">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Categories Grid -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-brand-orange to-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white">📂 Menu Categories</h3>
                            <p class="text-orange-100 text-sm">Manage your restaurant's menu categories (<?= count($categories) ?> categories)</p>
                        </div>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-white font-bold text-lg"><?= count($categories) ?></span>
                            <span class="text-orange-100 text-sm block">Total Categories</span>
                        </div>
                    </div>
                </div>

                <div class="p-8">
                    <?php if (empty($categories)): ?>
                    <div class="text-center py-16">
                        <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-700 mb-2">No Categories Yet</h3>
                        <p class="text-gray-500 mb-4">Start organizing your menu by creating your first category.</p>
                        <button onclick="showAddForm()" class="bg-brand-orange text-white px-6 py-3 rounded-lg font-semibold hover:bg-orange-700 transition">
                            Create First Category
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($categories as $category): ?>
                            <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                                <?php 
                                $imageSrc = $category['image'] ? '../' . $category['image'] : 'https://via.placeholder.com/300x200/FF6B35/FFFFFF?text=No+Image';
                                ?>
                                <div class="relative">
                                    <img src="<?= $imageSrc ?>" 
                                         alt="<?= htmlspecialchars($category['name']) ?>" 
                                         class="w-full h-48 object-cover"
                                         onerror="this.src='https://via.placeholder.com/300x200/FF6B35/FFFFFF?text=No+Image'">
                                    
                                    <!-- Status Badge -->
                                    <div class="absolute top-4 right-4">
                                        <span class="px-3 py-1 text-xs font-bold rounded-full shadow-lg
                                            <?= $category['status'] === 'active' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>">
                                            <?= $category['status'] === 'active' ? '✓ Active' : '✗ Inactive' ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($category['name']) ?></h3>
                                        <div class="flex items-center space-x-1 bg-blue-50 px-3 py-1 rounded-full">
                                            <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8.1 13.34L2 14L3.74 9.19L9.84 8.5L8.1 13.34ZM14.5 11L16.69 7.93L22.78 9.4L21.05 14.21L14.5 11Z"/>
                                            </svg>
                                            <span class="text-blue-800 text-sm font-semibold"><?= $category['item_count'] ?> items</span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($category['description']): ?>
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($category['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="flex space-x-3">
                                        <a href="categories.php?edit=<?= $category['id'] ?>" 
                                           class="flex-1 bg-brand-orange text-white px-4 py-2 rounded-lg font-semibold hover:bg-orange-600 transition-all duration-300 text-center inline-flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </a>
                                        
                                        <?php if ($category['item_count'] == 0): ?>
                                            <form method="POST" class="flex-1" onsubmit="return confirmDelete('<?= htmlspecialchars($category['name']) ?>')">
                                                <input type="hidden" name="id" value="<?= $category['id'] ?>">
                                                <button type="submit" name="delete_category" 
                                                        class="w-full bg-red-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-600 transition-all duration-300 inline-flex items-center justify-center">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="flex-1 bg-gray-100 text-gray-500 px-4 py-2 rounded-lg font-semibold text-center cursor-not-allowed">
                                                Cannot Delete
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function showAddForm() {
            document.getElementById('categoryForm').classList.remove('hidden');
            document.querySelector('input[name="name"]').focus();
        }
        
        function hideForm() {
            document.getElementById('categoryForm').classList.add('hidden');
            window.location.href = 'categories.php';
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
        
        function confirmDelete(categoryName) {
            return confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`);
        }
        
        // Drag and drop functionality
        const dropArea = document.querySelector('.border-dashed');
        const fileInput = document.getElementById('image_file');
        
        if (dropArea && fileInput) {
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
                dropArea.classList.add('border-brand-orange', 'bg-orange-100');
            }
            
            function unhighlight(e) {
                dropArea.classList.remove('border-brand-orange', 'bg-orange-100');
            }
            
            dropArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                previewImage(fileInput);
            }
        }
        
        // Auto-hide success messages
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
        
        // Add hover effects to category cards
        document.addEventListener('DOMContentLoaded', function() {
            const categoryCards = document.querySelectorAll('.grid .bg-white');
            categoryCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderColor = '#FF6B35';
                    this.style.borderWidth = '2px';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.borderColor = '#E5E7EB';
                    this.style.borderWidth = '1px';
                });
            });
            
            // Add gradient cards hover effects
            const gradientCards = document.querySelectorAll('.bg-gradient-to-r');
            gradientCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.transition = 'transform 0.3s ease-in-out';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const form = document.getElementById('categoryForm');
                if (form && !form.classList.contains('hidden')) {
                    hideForm();
                }
            }
            
            if (e.key === 'n' && e.ctrlKey) {
                e.preventDefault();
                showAddForm();
            }
        });
        
        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const nameInput = this.querySelector('input[name="name"]');
                if (!nameInput.value.trim()) {
                    e.preventDefault();
                    alert('Category name is required!');
                    nameInput.focus();
                }
            });
        }
    </script>
</body>
</html>