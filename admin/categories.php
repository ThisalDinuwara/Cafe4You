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

// Get all categories
$categories_query = "SELECT c.*, COUNT(mi.id) as item_count 
                    FROM categories c 
                    LEFT JOIN menu_items mi ON c.id = mi.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Category Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-orange-400">Admin Panel</h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition">View Site</a>
                    <a href="../logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg min-h-screen">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Dashboard</a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Orders</a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Menu Management</a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-orange-600 font-medium">Categories</a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Reservations</a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Users</a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Contact Messages</a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Category Management</h1>
                    <p class="text-gray-600 mt-2">Manage menu categories</p>
                </div>
                <button onclick="showAddForm()" class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 transition">
                    Add New Category
                </button>
            </div>

            <?php displayMessage(); ?>

            <!-- Add/Edit Form -->
            <div id="categoryForm" class="<?= $edit_category ? '' : 'hidden' ?> mb-8 bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <?= $edit_category ? 'Edit Category' : 'Add New Category' ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                        <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_category['image'] ?? '') ?>">
                    <?php endif; ?>
                    
                    <div class="grid md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input type="text" name="name" required 
                                   value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        
                        <?php if ($edit_category): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                                <option value="active" <?= ($edit_category['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_category['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Current Image Preview -->
                    <?php if ($edit_category && $edit_category['image']): ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Image</label>
                        <img src="../<?= htmlspecialchars($edit_category['image']) ?>" alt="Current category image" 
                             class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                    </div>
                    <?php endif; ?>
                    
                    <!-- Image Upload -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Upload New Image</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="image_file" class="relative cursor-pointer bg-white rounded-md font-medium text-orange-600 hover:text-orange-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-orange-500">
                                        <span>Upload a file</span>
                                        <input id="image_file" name="image_file" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF, WEBP up to 5MB</p>
                            </div>
                        </div>
                        
                        <!-- Image Preview -->
                        <div id="imagePreview" class="mt-4 hidden">
                            <img id="previewImg" src="" alt="Preview" class="w-32 h-32 object-cover rounded-lg border border-gray-300">
                        </div>
                    </div>
                    
                    <!-- OR Image URL -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">OR Image URL</label>
                        <input type="url" name="image_url" 
                               value="<?= htmlspecialchars($edit_category['image'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                               placeholder="https://example.com/image.jpg">
                        <p class="mt-1 text-xs text-gray-500">Note: Uploading a file will override the URL</p>
                    </div>
                    
                    <div class="flex space-x-4">
                        <button type="submit" name="<?= $edit_category ? 'update_category' : 'add_category' ?>" 
                                class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 transition">
                            <?= $edit_category ? 'Update Category' : 'Add Category' ?>
                        </button>
                        <button type="button" onclick="hideForm()" 
                                class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Categories Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($categories as $category): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <?php 
                        $imageSrc = $category['image'] ? '../' . $category['image'] : 'https://via.placeholder.com/300x200';
                        ?>
                        <img src="<?= $imageSrc ?>" 
                             alt="<?= htmlspecialchars($category['name']) ?>" 
                             class="w-full h-48 object-cover"
                             onerror="this.src='https://via.placeholder.com/300x200'">
                        
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($category['name']) ?></h3>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?= $category['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ucfirst($category['status']) ?>
                                </span>
                            </div>
                            
                            <p class="text-gray-600 text-sm mb-4"><?= htmlspecialchars($category['description']) ?></p>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500"><?= $category['item_count'] ?> items</span>
                                <div class="space-x-2">
                                    <a href="categories.php?edit=<?= $category['id'] ?>" 
                                       class="text-orange-600 hover:text-orange-900 text-sm">Edit</a>
                                    
                                    <?php if ($category['item_count'] == 0): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this category?')">
                                            <input type="hidden" name="id" value="<?= $category['id'] ?>">
                                            <button type="submit" name="delete_category" class="text-red-600 hover:text-red-900 text-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script>
        function showAddForm() {
            document.getElementById('categoryForm').classList.remove('hidden');
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
        
        // Drag and drop functionality
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
            dropArea.classList.add('border-orange-500', 'bg-orange-50');
        }
        
        function unhighlight(e) {
            dropArea.classList.remove('border-orange-500', 'bg-orange-50');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            previewImage(fileInput);
        }
    </script>
</body>
</html>