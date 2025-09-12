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
                        'golden': {
                            50: '#FFFDF7',
                            100: '#FFF9E6',
                            200: '#FFF0B8',
                            300: '#FFE388',
                            400: '#FFD558',
                            500: '#FFC728',
                            600: '#F5B800',
                            700: '#CC9900',
                            800: '#A37A00',
                            900: '#7A5B00'
                        },
                        'amber-glow': '#FFB800',
                        'honey': '#FFCC33',
                        'cafe-brown': '#8B4513',
                        'warm-gray': '#78716C'
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-soft': 'pulse-soft 2s ease-in-out infinite',
                        'slide-up': 'slide-up 0.8s ease-out',
                        'fade-in': 'fade-in 0.6s ease-out',
                        'bounce-gentle': 'bounce-gentle 2s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                        'shimmer': 'shimmer 2.5s linear infinite'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        'pulse-soft': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.8 }
                        },
                        'slide-up': {
                            '0%': { transform: 'translateY(30px)', opacity: 0 },
                            '100%': { transform: 'translateY(0)', opacity: 1 }
                        },
                        'fade-in': {
                            '0%': { opacity: 0 },
                            '100%': { opacity: 1 }
                        },
                        'bounce-gentle': {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-5px)' }
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 20px rgba(255, 199, 40, 0.3)' },
                            '100%': { boxShadow: '0 0 30px rgba(255, 199, 40, 0.6)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-1000px 0' },
                            '100%': { backgroundPosition: '1000px 0' }
                        }
                    },
                    backdropBlur: {
                        xs: '2px'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        
        body { 
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #FFFDF7 0%, #FFF9E6 25%, #FFF0B8 50%, #FFE388 75%, #FFD558 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }

        .glass-morphism {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 199, 40, 0.18);
            box-shadow: 0 8px 32px rgba(255, 199, 40, 0.37);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 199, 40, 0.25);
            box-shadow: 0 12px 40px rgba(255, 184, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(255, 184, 0, 0.25);
        }

        .gradient-card {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .gradient-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                transparent 30%, 
                rgba(255, 255, 255, 0.1) 50%, 
                transparent 70%);
            transform: rotate(45deg);
            transition: all 0.6s ease;
            opacity: 0;
        }

        .gradient-card:hover::before {
            opacity: 1;
            transform: rotate(45deg) translate(50%, 50%);
        }

        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }

        .nav-item {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 16px;
            margin: 6px 12px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 199, 40, 0.2), transparent);
            transition: left 0.5s;
        }

        .nav-item:hover::before {
            left: 100%;
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, #FFC728, #FFB800, #F5B800);
            color: #8B4513;
            box-shadow: 0 8px 32px rgba(255, 199, 40, 0.4);
            font-weight: 600;
            transform: translateX(8px);
        }

        .nav-item.active svg {
            color: #8B4513;
            filter: drop-shadow(0 2px 4px rgba(139, 69, 19, 0.2));
        }

        .nav-item:not(.active):hover {
            background: rgba(255, 199, 40, 0.15);
            color: #F5B800;
            transform: translateX(8px) translateY(-2px);
            box-shadow: 0 4px 20px rgba(255, 199, 40, 0.3);
        }

        .stat-number {
            background: linear-gradient(135deg, #F5B800 0%, #FFC728 50%, #FFB800 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 8px rgba(255, 199, 40, 0.3);
        }

        .icon-container {
            background: linear-gradient(135deg, #FFC728, #FFB800);
            box-shadow: 0 8px 32px rgba(255, 199, 40, 0.4);
            position: relative;
            overflow: hidden;
        }

        .icon-container::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.3) 0%, transparent 70%);
            animation: glow 3s ease-in-out infinite;
        }

        .hover-lift {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        .hover-lift:hover {
            transform: translateY(-10px) rotateX(5deg);
            box-shadow: 0 25px 80px rgba(255, 184, 0, 0.3);
        }

        .category-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 199, 40, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 199, 40, 0.2), transparent);
            transition: left 0.5s;
        }

        .category-card:hover::before {
            left: 100%;
        }

        .category-card:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 25px 80px rgba(255, 184, 0, 0.3);
            border-color: rgba(255, 199, 40, 0.6);
        }

        .upload-zone {
            background: linear-gradient(135deg, rgba(255, 199, 40, 0.1) 0%, rgba(255, 184, 0, 0.15) 100%);
            border: 2px dashed rgba(255, 199, 40, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .upload-zone:hover {
            border-color: rgba(255, 199, 40, 0.8);
            background: linear-gradient(135deg, rgba(255, 199, 40, 0.15) 0%, rgba(255, 184, 0, 0.25) 100%);
            transform: scale(1.02);
        }

        .upload-zone::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 199, 40, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s ease;
            opacity: 0;
        }

        .upload-zone:hover::before {
            opacity: 1;
            transform: rotate(45deg) translate(30%, 30%);
        }

        .section-header {
            background: linear-gradient(135deg, rgba(255, 199, 40, 0.1), rgba(255, 184, 0, 0.05));
            border-left: 6px solid #FFC728;
            backdrop-filter: blur(10px);
        }

        .form-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 199, 40, 0.2);
            box-shadow: 0 20px 60px rgba(255, 184, 0, 0.15);
        }

        .golden-text {
            background: linear-gradient(135deg, #FFB800 0%, #FFC728 25%, #FFD700 50%, #FFC728 75%, #FFB800 100%);
            background-size: 200% auto;
            color: transparent;
            -webkit-background-clip: text;
            background-clip: text;
            animation: shimmer 3s linear infinite;
        }

        .shimmer-bg {
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.4),
                transparent
            );
            background-size: 200% 100%;
            animation: shimmer 2.5s infinite;
        }

        .interactive-element {
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .interactive-element:hover {
            transform: scale(1.05);
        }

        .interactive-element:active {
            transform: scale(0.98);
        }

        .status-badge {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .status-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .status-badge:hover::before {
            left: 100%;
        }

        .notification-badge {
            animation: bounce-gentle 2s ease-in-out infinite;
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        @media (max-width: 768px) {
            .glass-card:hover {
                transform: none;
            }
            .category-card:hover {
                transform: translateY(-6px);
            }
        }
    </style>
</head>
<body class="font-sans">
    <!-- Floating Background Elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-32 h-32 bg-golden-200 rounded-full opacity-20 floating-animation"></div>
        <div class="absolute top-3/4 right-1/4 w-24 h-24 bg-golden-300 rounded-full opacity-15 floating-animation" style="animation-delay: -2s;"></div>
        <div class="absolute top-1/2 left-3/4 w-40 h-40 bg-golden-100 rounded-full opacity-10 floating-animation" style="animation-delay: -4s;"></div>
    </div>

    <!-- Top Navigation -->
    <nav class="glass-morphism shadow-2xl border-b-2 border-golden-400/30 relative z-10">
        <div class="max-w-7xl mx-auto px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-6">
                    <div class="relative">
                        <div class="w-16 h-16 icon-container rounded-2xl flex items-center justify-center shadow-2xl animate-glow">
                            <span class="text-white font-black text-2xl">C</span>
                        </div>
                        <div class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full pulse-ring"></div>
                    </div>
                    <div class="animate-slide-up">
                        <h1 class="text-3xl font-black golden-text">Cafe For You</h1>
                        <p class="text-warm-gray font-medium text-lg">Category Management</p>
                    </div>
                </div>

                <div class="flex items-center space-x-8">
                    <div class="flex items-center space-x-4 glass-card px-6 py-3 rounded-2xl hover:shadow-xl transition-all duration-300">
                        <div class="relative">
                            <div class="w-12 h-12 bg-gradient-to-br from-golden-400 to-golden-600 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="text-white font-bold text-lg">
                                    <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
                                </span>
                            </div>
                            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-white"></div>
                        </div>
                        <div>
                            <p class="text-warm-gray font-semibold">Welcome back,</p>
                            <p class="font-bold text-cafe-brown"><?= htmlspecialchars($_SESSION['full_name']) ?></p>
                        </div>
                    </div>
                    <a href="../index.php" class="text-warm-gray hover:text-golden-600 transition-colors duration-300 font-semibold text-lg hover:scale-105 transform">View Site</a>
                    <a href="../logout.php" class="bg-gradient-to-r from-golden-500 to-golden-600 text-white px-8 py-3 rounded-2xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300 font-bold text-lg shimmer-bg">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen relative z-10">
        <!-- Sidebar -->
        <aside class="w-72 glass-morphism shadow-2xl relative">
            <div class="sticky top-0">
                <nav class="mt-8 pb-8">
                    <div class="px-6 space-y-3">
                        <a href="dashboard.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                </svg>
                            </div>
                            Dashboard
                        </a>
                        <a href="orders.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            Orders
                        </a>
                        <a href="menu.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            Menu Management
                        </a>
                        <a href="categories.php" class="nav-item active flex items-center px-6 py-4 transition-all duration-300 font-semibold text-lg rounded-2xl">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                            </div>
                            Categories
                        </a>
                        <a href="reservations.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                            Reservations
                        </a>
                        <a href="users.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                            Users
                        </a>
                        <a href="messages.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            Contact Messages
                        </a>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <div class="p-10">
                <!-- Header Section -->
                <div class="section-header rounded-3xl p-8 mb-10 animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="relative">
                                <div class="w-20 h-20 icon-container rounded-3xl flex items-center justify-center mr-6 animate-glow">
                                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="animate-slide-up">
                                <h1 class="text-4xl font-black golden-text mb-2">Category Management</h1>
                                <p class="text-warm-gray text-xl font-medium">Organize and manage your menu categories</p>
                            </div>
                        </div>
                        <button onclick="showAddForm()" class="bg-gradient-to-r from-golden-500 to-golden-600 text-white px-8 py-4 rounded-2xl font-bold text-lg hover:shadow-2xl transform hover:scale-105 transition-all duration-300 shimmer-bg">
                            <svg class="w-6 h-6 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Category
                        </button>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12 animate-slide-up">
                    <!-- Total Categories Card -->
                    <div class="gradient-card from-emerald-500 to-emerald-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Categories</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['total_categories'] ?></div>
                        <div class="text-white/80 text-base font-medium">All menu categories</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Active Categories Card -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Active Categories</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['active_categories'] ?></div>
                        <div class="text-white/80 text-base font-medium">Currently available</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Total Menu Items Card -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Menu Items</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['total_items'] ?></div>
                        <div class="text-white/80 text-base font-medium">Across all categories</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Average Items Per Category Card -->
                    <div class="gradient-card from-golden-500 to-golden-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Avg Items/Category</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= number_format($stats['avg_items_per_category'], 1) ?></div>
                        <div class="text-white/80 text-base font-medium">Items per category</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Form -->
                <div id="categoryForm" class="<?= $edit_category ? '' : 'hidden' ?> mb-12">
                    <div class="form-card rounded-3xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-8 py-8">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-white font-black text-2xl mb-2">
                                            <?= $edit_category ? 'Edit Category' : 'Add New Category' ?>
                                        </h3>
                                        <p class="text-white/80 text-lg font-medium">
                                            <?= $edit_category ? 'Update category information and settings' : 'Create a new menu category' ?>
                                        </p>
                                    </div>
                                </div>
                                <button onclick="hideForm()" class="text-white/70 hover:text-white transition-colors duration-300 p-2 hover:bg-white/10 rounded-xl">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="p-10">
                            <?php if ($edit_category): ?>
                                <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                                <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_category['image'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <div class="grid md:grid-cols-2 gap-10 mb-10">
                                <div>
                                    <label class="block text-lg font-bold text-cafe-brown mb-4">Category Name *</label>
                                    <input type="text" name="name" required 
                                           value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>"
                                           class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium"
                                           placeholder="Enter category name">
                                </div>
                                
                                <?php if ($edit_category): ?>
                                <div>
                                    <label class="block text-lg font-bold text-cafe-brown mb-4">Status</label>
                                    <select name="status" 
                                            class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium">
                                        <option value="active" <?= ($edit_category['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= ($edit_category['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-10">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">Description</label>
                                <textarea name="description" rows="5" 
                                          class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium resize-none"
                                          placeholder="Describe this category..."><?= htmlspecialchars($edit_category['description'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Current Image Preview -->
                            <?php if ($edit_category && $edit_category['image']): ?>
                            <div class="mb-10">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">Current Image</label>
                                <div class="relative inline-block">
                                    <img src="../<?= htmlspecialchars($edit_category['image']) ?>" alt="Current category image" 
                                         class="w-48 h-48 object-cover rounded-2xl border-4 border-golden-400/30 shadow-2xl">
                                    <div class="absolute -top-3 -right-3 w-10 h-10 bg-emerald-500 rounded-full flex items-center justify-center shadow-xl">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 12L11 14L15 10M21 12C21 16.97 16.97 21 12 21C7.03 21 3 16.97 3 12C3 7.03 7.03 3 12 3C16.97 3 21 7.03 21 12Z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Image Upload -->
                            <div class="mb-10">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">Upload New Image</label>
                                <div class="upload-zone mt-1 flex justify-center px-10 pt-16 pb-16 rounded-3xl cursor-pointer hover-lift" onclick="document.getElementById('image_file').click()">
                                    <div class="space-y-6 text-center">
                                        <svg class="mx-auto h-24 w-24 text-golden-500" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="text-golden-600">
                                            <label for="image_file" class="relative cursor-pointer font-black text-xl hover:text-golden-700 transition-colors duration-300">
                                                <span>Click to upload a file</span>
                                                <input id="image_file" name="image_file" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                            </label>
                                            <p class="text-golden-500 text-lg font-medium mt-2">or drag and drop</p>
                                        </div>
                                        <p class="text-lg text-warm-gray font-medium">PNG, JPG, GIF, WEBP up to 5MB</p>
                                    </div>
                                </div>
                                
                                <!-- Image Preview -->
                                <div id="imagePreview" class="mt-8 hidden">
                                    <div class="relative inline-block">
                                        <img id="previewImg" src="" alt="Preview" class="w-48 h-48 object-cover rounded-2xl border-4 border-blue-400 shadow-2xl">
                                        <div class="absolute -top-3 -right-3 w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center shadow-xl">
                                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M19 7L18.3 7.7L16.6 6L17.3 5.3C17.7 4.9 18.3 4.9 18.7 5.3L19 5.6C19.4 6 19.4 6.6 19 7M15.9 6.7L9 13.6V16H11.4L18.3 9.1L15.9 6.7Z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OR Image URL -->
                            <div class="mb-12">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">OR Image URL</label>
                                <input type="url" name="image_url" 
                                       value="<?= htmlspecialchars($edit_category['image'] ?? '') ?>"
                                       class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium"
                                       placeholder="https://example.com/image.jpg">
                                <p class="mt-4 text-lg text-warm-gray font-medium">Note: Uploading a file will override the URL</p>
                            </div>
                            
                            <div class="flex space-x-6 pt-10 border-t-2 border-golden-200/30">
                                <button type="submit" name="<?= $edit_category ? 'update_category' : 'add_category' ?>" 
                                        class="flex-1 bg-gradient-to-r from-golden-500 to-golden-600 text-white px-10 py-5 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg">
                                    <svg class="w-6 h-6 inline mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <?= $edit_category ? 'Update Category' : 'Create Category' ?>
                                </button>
                                <button type="button" onclick="hideForm()" 
                                        class="flex-1 bg-warm-gray/20 text-warm-gray px-10 py-5 rounded-2xl font-black text-lg hover:bg-warm-gray/30 transition-all duration-300 transform hover:scale-105">
                                    <svg class="w-6 h-6 inline mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Categories Grid -->
                <div class="glass-card rounded-3xl shadow-2xl overflow-hidden">
                    <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-2xl mb-2">Menu Categories</h3>
                                    <p class="text-white/80 text-lg font-medium">Manage your restaurant's menu categories</p>
                                </div>
                            </div>
                            <div class="text-white/90 text-lg">
                                <span class="bg-white/20 px-6 py-3 rounded-2xl font-black backdrop-blur-sm">
                                    <?= count($categories) ?> Total Categories
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-10">
                        <?php if (empty($categories)): ?>
                        <div class="text-center py-24">
                            <div class="w-40 h-40 mx-auto mb-10 bg-golden-200/20 rounded-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-golden-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-cafe-brown mb-4">No Categories Yet</h3>
                            <p class="text-warm-gray mb-8 text-xl font-medium">Start organizing your menu by creating your first category.</p>
                            <button onclick="showAddForm()" class="bg-gradient-to-r from-golden-500 to-golden-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg">
                                Create First Category
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-10">
                            <?php foreach ($categories as $category): ?>
                                <div class="category-card rounded-3xl shadow-2xl overflow-hidden border hover-lift">
                                    <?php 
                                    $imageSrc = $category['image'] ? '../' . $category['image'] : 'https://via.placeholder.com/400x280/FFC728/FFFFFF?text=No+Image';
                                    ?>
                                    <div class="relative">
                                        <img src="<?= $imageSrc ?>" 
                                             alt="<?= htmlspecialchars($category['name']) ?>" 
                                             class="w-full h-64 object-cover"
                                             onerror="this.src='https://via.placeholder.com/400x280/FFC728/FFFFFF?text=No+Image'">
                                        
                                        <!-- Status Badge -->
                                        <div class="absolute top-5 right-5">
                                            <span class="status-badge px-5 py-3 text-sm font-black rounded-2xl shadow-2xl
                                                <?= $category['status'] === 'active' ? 'text-emerald-700' : 'text-red-700' ?>">
                                                <span class="inline-block w-3 h-3 rounded-full mr-3 <?= $category['status'] === 'active' ? 'bg-emerald-500' : 'bg-red-500' ?>"></span>
                                                <?= $category['status'] === 'active' ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>

                                        <!-- Item Count Badge -->
                                        <div class="absolute top-5 left-5">
                                            <div class="status-badge px-4 py-3 rounded-2xl shadow-2xl">
                                                <div class="flex items-center text-blue-700">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                                    </svg>
                                                    <span class="text-sm font-black"><?= $category['item_count'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-8">
                                        <div class="mb-6">
                                            <h3 class="text-2xl font-black text-cafe-brown mb-3"><?= htmlspecialchars($category['name']) ?></h3>
                                            <?php if ($category['description']): ?>
                                            <p class="text-warm-gray text-lg line-clamp-3 font-medium"><?= htmlspecialchars($category['description']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Only Edit button (full width). Delete/disabled button removed -->
                                        <div class="flex">
                                            <a href="categories.php?edit=<?= $category['id'] ?>" 
                                               class="w-full bg-gradient-to-r from-golden-500 to-golden-600 text-white px-6 py-4 rounded-2xl font-black hover:shadow-2xl transition-all duration-300 text-center inline-flex items-center justify-center transform hover:scale-105 shimmer-bg">
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-3xl p-8 flex items-center space-x-4">
            <div class="w-8 h-8 border-4 border-golden-400 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-cafe-brown font-semibold">Loading...</span>
        </div>
    </div>

    <script>
        function showAddForm() {
            document.getElementById('categoryForm').classList.remove('hidden');
            document.querySelector('input[name="name"]').focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
        
        // Enhanced drag and drop functionality
        const dropArea = document.querySelector('.upload-zone');
        const fileInput = document.getElementById('image_file');
        
        if (dropArea && fileInput) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
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
                dropArea.style.borderColor = 'rgba(255, 199, 40, 0.8)';
                dropArea.style.background = 'linear-gradient(135deg, rgba(255, 199, 40, 0.2) 0%, rgba(255, 184, 0, 0.3) 100%)';
                dropArea.style.transform = 'scale(1.05)';
            }
            
            function unhighlight(e) {
                dropArea.style.borderColor = '';
                dropArea.style.background = '';
                dropArea.style.transform = '';
            }
            
            dropArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImage(fileInput);
                }
            }
        }
        
        // Enhanced interactions and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Close form on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const form = document.getElementById('categoryForm');
                    if (form && !form.classList.contains('hidden')) {
                        hideForm();
                    }
                }
            });

            // Enhanced hover effects for cards
            const cards = document.querySelectorAll('.hover-lift');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-12px) scale(1.03)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });

            // Form input focus effects
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.transform = 'scale(1.02)';
                    this.style.boxShadow = '0 0 30px rgba(255, 199, 40, 0.3)';
                });
                input.addEventListener('blur', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                });
            });

            // Success message animation
            const messages = document.querySelectorAll('.alert, .success, .error');
            messages.forEach(message => {
                message.style.animation = 'slideInFromTop 0.5s ease-out';
                setTimeout(() => {
                    if (message.parentElement) {
                        message.style.animation = 'fadeOut 0.5s ease-out';
                        setTimeout(() => {
                            if (message.parentElement) {
                                message.remove();
                            }
                        }, 500);
                    }
                }, 5000);
            });
        });

        // Enhanced button interactions
        const buttons = document.querySelectorAll('button, .btn');
        buttons.forEach(button => {
            button.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.95)';
            });
            
            button.addEventListener('mouseup', function() {
                this.style.transform = '';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = '';
            });
        });

        // Smooth scroll for form opening
        if (window.location.hash === '#add' || <?= $edit_category ? 'true' : 'false' ?>) {
            setTimeout(() => {
                if (!document.getElementById('categoryForm').classList.contains('hidden')) {
                    document.getElementById('categoryForm').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            }, 100);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInFromTop {
                0% {
                    transform: translateY(-100px);
                    opacity: 0;
                }
                100% {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            @keyframes fadeOut {
                0% {
                    opacity: 1;
                    transform: translateY(0);
                }
                100% {
                    opacity: 0;
                    transform: translateY(-20px);
                }
            }
            
            .line-clamp-3 {
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .pulse-ring {
                animation: pulse-ring 2s cubic-