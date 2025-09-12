<?php
// admin/menu.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

/* ---------- helpers for datetime-local <-> MySQL DATETIME ---------- */
function to_mysql_dt(?string $local){
   if(!$local){ return null; }
   $local = trim($local);
   if($local === '') return null;

   if (strpos($local, 'T') !== false) {
      $local = str_replace('T', ' ', $local);
      if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $local)) {
         $local .= ':00';
      }
   }
   return $local;
}

function to_input_dt(?string $mysql){
   if(!$mysql){ return ''; }
   $ts = strtotime($mysql);
   if($ts === false){ return ''; }
   return date('Y-m-d\TH:i', $ts);
}

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
        
        // Delete associated promotions first
        $delete_promos = $db->prepare("DELETE FROM menu_promotions WHERE menu_item_id = ?");
        $delete_promos->execute([$id]);
        
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

    /* =========================================
       PROMOTION: Create/Update (Enhanced for Discounts)
    ========================================= */
    } elseif(isset($_POST['save_promo'])){
        $promo_id         = isset($_POST['promo_id']) ? (int)$_POST['promo_id'] : 0;
        $menu_item_id     = isset($_POST['menu_item_id']) ? (int)$_POST['menu_item_id'] : 0;
        $promo_price      = (isset($_POST['promo_price']) && $_POST['promo_price'] !== '') ? (float)$_POST['promo_price'] : null;
        $discount_percent = (isset($_POST['discount_percent']) && $_POST['discount_percent'] !== '') ? (float)$_POST['discount_percent'] : null;

        $label     = sanitize($_POST['label'] ?? '');
        if ($label === '') $label = 'Limited Offer';
        $starts_at = to_mysql_dt($_POST['starts_at'] ?? null);
        $ends_at   = to_mysql_dt($_POST['ends_at'] ?? null);
        $active    = isset($_POST['active']) ? 1 : 0;

        $errs = [];
        if($menu_item_id <= 0){ $errs[] = 'Choose a valid menu item.'; }
        if($promo_price === null && $discount_percent === null){ $errs[] = 'Set either Promo Price or Discount %.'; }
        if($promo_price !== null && $promo_price < 0){ $errs[] = 'Promo price must be >= 0.'; }
        if($discount_percent !== null && ($discount_percent < 0 || $discount_percent > 95)){ $errs[] = 'Discount % must be between 0 and 95.'; }

        // Enhanced: If both are provided, prioritize discount percentage and clear promo price
        if($promo_price !== null && $discount_percent !== null) {
            showMessage('Using discount percentage. Promo price has been cleared to avoid conflicts.', 'warning');
            $promo_price = null;
        }

        if(empty($errs)){
            if($promo_id > 0){
                $sql = "UPDATE menu_promotions
                        SET menu_item_id=?, promo_price=?, discount_percent=?, label=?, starts_at=?, ends_at=?, active=?
                        WHERE id=?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$menu_item_id, $promo_price, $discount_percent, $label, $starts_at, $ends_at, $active, $promo_id]);
                $promo_type = $discount_percent !== null ? 'discount' : 'fixed price';
                showMessage("Promotion updated successfully! Using {$promo_type} pricing.");
            }else{
                $chk = $db->prepare("SELECT id FROM menu_promotions WHERE menu_item_id=? ORDER BY id DESC LIMIT 1");
                $chk->execute([$menu_item_id]);
                if($chk->rowCount()){
                    $row = $chk->fetch(PDO::FETCH_ASSOC);
                    $sql = "UPDATE menu_promotions
                            SET promo_price=?, discount_percent=?, label=?, starts_at=?, ends_at=?, active=?
                            WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$promo_price, $discount_percent, $label, $starts_at, $ends_at, $active, (int)$row['id']]);
                    $promo_type = $discount_percent !== null ? 'discount' : 'fixed price';
                    showMessage("Promotion updated successfully! Using {$promo_type} pricing.");
                }else{
                    $sql = "INSERT INTO menu_promotions (menu_item_id, promo_price, discount_percent, label, starts_at, ends_at, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$menu_item_id, $promo_price, $discount_percent, $label, $starts_at, $ends_at, $active]);
                    $promo_type = $discount_percent !== null ? 'discount' : 'fixed price';
                    showMessage("Promotion created successfully! Using {$promo_type} pricing.");
                }
            }
        }else{
            showMessage(implode(' ', $errs), 'error');
        }

        header('location:menu.php#m'.$menu_item_id);
        exit;

    /* =========================================
       PROMOTION: Delete (CRUD)
    ========================================= */
    } elseif(isset($_GET['delete_promo'])){
        $del_mid     = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;
        $del_promoid = (int)$_GET['delete_promo'];
        $stmt = $db->prepare("DELETE FROM menu_promotions WHERE id = ?");
        $stmt->execute([$del_promoid]);
        showMessage('Promotion deleted successfully!');
        header('location:menu.php#m'.$del_mid);
        exit;
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

        .menu-item-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 199, 40, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: linear-gradient(90deg, transparent, rgba(255, 199, 40, 0.2), transparent);
            transition: left 0.5s;
        }

        .menu-item-card:hover::before {
            left: 100%;
        }

        .menu-item-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(255, 184, 0, 0.3);
            border-color: rgba(255, 199, 40, 0.6);
        }

        .line-clamp-2{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}

        /* Enhanced discount field styling */
        .discount-field {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.05));
            border: 2px solid rgba(34, 197, 94, 0.3);
        }

        .discount-field:focus {
            border-color: rgba(34, 197, 94, 0.6);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .promo-price-field {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
            border: 2px solid rgba(59, 130, 246, 0.3);
        }

        .promo-price-field:focus {
            border-color: rgba(59, 130, 246, 0.6);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        @media (max-width: 768px) {
            .glass-card:hover {
                transform: none;
            }
            .menu-item-card:hover {
                transform: translateY(-4px);
            }
        }

        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.33);
                opacity: 1;
            }
            80%, 100% {
                transform: scale(2.33);
                opacity: 0;
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
                        <p class="text-warm-gray font-medium text-lg">Menu Management</p>
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
                        <a href="menu.php" class="nav-item active flex items-center px-6 py-4 transition-all duration-300 font-semibold text-lg rounded-2xl">
                            <div class="w-8 h-8 mr-4 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            Menu Management
                        </a>
                        <a href="categories.php" class="nav-item flex items-center px-6 py-4 text-warm-gray hover:text-golden-600 transition-all duration-300 font-medium text-lg">
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="animate-slide-up">
                                <h1 class="text-4xl font-black golden-text mb-2">Menu Management</h1>
                                <p class="text-warm-gray text-xl font-medium">Create and manage delicious menu items with discount promotions</p>
                            </div>
                        </div>
                        <button onclick="showAddForm()" class="bg-gradient-to-r from-golden-500 to-golden-600 text-white px-8 py-4 rounded-2xl font-bold text-lg hover:shadow-2xl transform hover:scale-105 transition-all duration-300 shimmer-bg">
                            <svg class="w-6 h-6 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add New Item
                        </button>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12 animate-slide-up">
                    <!-- Total Items Card -->
                    <div class="gradient-card from-emerald-500 to-emerald-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Items</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['total_items'] ?></div>
                        <div class="text-white/80 text-base font-medium">All menu items</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Available Items Card -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Available Items</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['available_items'] ?></div>
                        <div class="text-white/80 text-base font-medium">Ready to order</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Unavailable Items Card -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Unavailable</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['unavailable_items'] ?></div>
                        <div class="text-white/80 text-base font-medium">Currently unavailable</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Average Price Card -->
                    <div class="gradient-card from-golden-500 to-golden-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Average Price</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3">Rs<?= number_format($stats['avg_price'], 2) ?></div>
                        <div class="text-white/80 text-base font-medium">Per menu item</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Form -->
                <div id="itemForm" class="<?= $edit_item ? '' : 'hidden' ?> mb-12">
                    <div class="form-card rounded-3xl shadow-2xl overflow-hidden">
                        <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-8 py-8">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-white font-black text-2xl mb-2">
                                            <?= $edit_item ? 'Edit Menu Item' : 'Add New Menu Item' ?>
                                        </h3>
                                        <p class="text-white/80 text-lg font-medium">
                                            <?= $edit_item ? 'Update menu item information and settings' : 'Create a new delicious menu item' ?>
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
                            <?php if ($edit_item): ?>
                                <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
                                <input type="hidden" name="current_image" value="<?= htmlspecialchars($edit_item['image'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <div class="grid md:grid-cols-2 gap-10 mb-10">
                                <div>
                                    <label class="block text-lg font-bold text-cafe-brown mb-4">Item Name *</label>
                                    <input type="text" name="name" required 
                                           value="<?= htmlspecialchars($edit_item['name'] ?? '') ?>"
                                           class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium"
                                           placeholder="Enter delicious dish name...">
                                </div>
                                
                                <div>
                                    <label class="block text-lg font-bold text-cafe-brown mb-4">Category *</label>
                                    <select name="category_id" required 
                                            class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium">
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
                            
                            <div class="grid md:grid-cols-2 gap-10 mb-10">
                                <div>
                                    <label class="block text-lg font-bold text-cafe-brown mb-4">Price *</label>
                                    <div class="relative">
                                        <span class="absolute left-6 top-1/2 transform -translate-y-1/2 text-golden-600 font-black text-xl">Rs</span>
                                        <input type="number" name="price" step="0.01" min="0" required 
                                               value="<?= $edit_item['price'] ?? '' ?>"
                                               class="w-full pl-16 pr-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium">
                                    </div>
                                </div>
                                
                                <?php if ($edit_item): ?>
                                <div>
                                    <label class="block text-lg font-bold text-cafe-brown mb-4">Status</label>
                                    <select name="status" 
                                            class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium">
                                        <option value="available" <?= ($edit_item['status'] === 'available') ? 'selected' : '' ?>>Available</option>
                                        <option value="unavailable" <?= ($edit_item['status'] === 'unavailable') ? 'selected' : '' ?>>Unavailable</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-10">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">Description</label>
                                <textarea name="description" rows="5" 
                                          class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium resize-none"
                                          placeholder="Describe your delicious menu item in detail..."><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Current Image Preview -->
                            <?php if ($edit_item && $edit_item['image']): ?>
                            <div class="mb-10">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">Current Image</label>
                                <div class="relative inline-block">
                                    <img src="../<?= htmlspecialchars($edit_item['image']) ?>" alt="Current menu item image" 
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
                                <label class="block text-lg font-bold text-cafe-brown mb-4">Upload Food Image</label>
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
                                        <p class="text-base text-golden-600 font-black">High-quality food photos work best!</p>
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
                                        <button type="button" onclick="removePreview()" class="absolute top-3 left-3 bg-red-500 text-white px-3 py-1 rounded-lg text-sm font-bold hover:bg-red-600 transition-colors">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- OR Image URL -->
                            <div class="mb-12">
                                <label class="block text-lg font-bold text-cafe-brown mb-4">OR Image URL</label>
                                <input type="url" name="image_url" 
                                       value="<?= htmlspecialchars($edit_item['image'] ?? '') ?>"
                                       class="w-full px-6 py-5 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 text-lg font-medium"
                                       placeholder="https://example.com/delicious-food-image.jpg">
                                <p class="mt-4 text-lg text-warm-gray font-medium">Note: Uploading a file will override the URL</p>
                            </div>
                            
                            <div class="flex space-x-6 pt-10 border-t-2 border-golden-200/30">
                                <button type="submit" name="<?= $edit_item ? 'update_item' : 'add_item' ?>" 
                                        class="flex-1 bg-gradient-to-r from-golden-500 to-golden-600 text-white px-10 py-5 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg">
                                    <svg class="w-6 h-6 inline mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <?= $edit_item ? 'Update Menu Item' : 'Add Menu Item' ?>
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

                <!-- Menu Items Grid -->
                <div class="glass-card rounded-3xl shadow-2xl overflow-hidden">
                    <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-2xl mb-2">Menu Items</h3>
                                    <p class="text-white/80 text-lg font-medium">Manage your delicious offerings with discount promotions</p>
                                </div>
                            </div>
                            <div class="text-white/90 text-lg">
                                <span class="bg-white/20 px-6 py-3 rounded-2xl font-black backdrop-blur-sm">
                                    <?= count($menu_items) ?> Total Items
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-10">
                        <?php if (empty($menu_items)): ?>
                        <div class="text-center py-24">
                            <div class="w-40 h-40 mx-auto mb-10 bg-golden-200/20 rounded-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-golden-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-cafe-brown mb-4">No Menu Items Yet</h3>
                            <p class="text-warm-gray mb-8 text-xl font-medium">Start building your delicious menu by adding your first item.</p>
                            <button onclick="showAddForm()" class="bg-gradient-to-r from-golden-500 to-golden-600 text-white px-10 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg">
                                Add Your First Menu Item
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-10">
                            <?php foreach ($menu_items as $item): 
                                $mid = (int)$item['id'];

                                // Load latest promo (if any)
                                $promo = null;
                                $getPromo = $db->prepare("SELECT * FROM menu_promotions WHERE menu_item_id = ? ORDER BY id DESC LIMIT 1");
                                $getPromo->execute([$mid]);
                                if($getPromo->rowCount()){
                                    $promo = $getPromo->fetch(PDO::FETCH_ASSOC);
                                }

                                // Final price preview (if live)
                                $now   = date('Y-m-d H:i:s');
                                $base  = (float)$item['price'];
                                $final = null; $isLive = false;

                                if($promo){
                                    $inWindow = (empty($promo['starts_at']) || $promo['starts_at'] <= $now)
                                             && (empty($promo['ends_at'])   || $promo['ends_at']   >= $now);
                                    if((int)$promo['active'] === 1 && $inWindow){
                                        if($promo['promo_price'] !== null && $promo['promo_price'] !== ''){
                                            $final = (float)$promo['promo_price'];
                                        }elseif($promo['discount_percent'] !== null && $promo['discount_percent'] !== ''){
                                            $final = max(0, $base * (1 - ((float)$promo['discount_percent']/100)));
                                        }
                                        if($final !== null && $final < $base){ $isLive = true; }
                                    }
                                }

                                $imageSrc = $item['image'] ? '../' . $item['image'] : 'https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400&h=280&fit=crop&crop=center';
                            ?>

                                <span id="m<?= $mid; ?>" class="relative -top-20 block"></span>

                                <div class="menu-item-card rounded-3xl shadow-2xl overflow-hidden hover-lift">
                                    <!-- Image Section - Fixed Height -->
                                    <div class="relative h-64 flex-shrink-0">
                                        <img src="<?= $imageSrc ?>" 
                                             alt="<?= htmlspecialchars($item['name']) ?>" 
                                             class="w-full h-full object-cover"
                                             onerror="this.src='https://images.unsplash.com/photo-1565299624946-b28f40a0ca4b?w=400&h=280&fit=crop&crop=center'">
                                        
                                        <!-- Status Badge -->
                                        <div class="absolute top-5 right-5">
                                            <span class="px-4 py-2 text-sm font-black rounded-2xl backdrop-blur-sm shadow-xl
                                                <?= $item['status'] === 'available' ? 'bg-emerald-500/90 text-white' : 'bg-red-500/90 text-white' ?>">
                                                <?= $item['status'] === 'available' ? '✓ Available' : '✗ Unavailable' ?>
                                            </span>
                                        </div>

                                        <!-- Category Badge -->
                                        <div class="absolute top-5 left-5">
                                            <span class="bg-blue-500/90 text-white px-4 py-2 rounded-2xl text-sm font-black backdrop-blur-sm shadow-xl">
                                                <?= htmlspecialchars($item['category_name'] ?? 'No Category') ?>
                                            </span>
                                        </div>

                                        <!-- Price Badge -->
                                        <div class="absolute bottom-5 right-5">
                                            <div class="bg-golden-500/95 text-white px-5 py-3 rounded-2xl backdrop-blur-sm shadow-xl">
                                                <?php if($isLive): ?>
                                                    <div class="text-center">
                                                        <div class="text-sm line-through opacity-70">Rs<?= number_format($base,2); ?></div>
                                                        <div class="text-lg font-black">Rs<?= number_format($final,2); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-lg font-black">Rs<?= number_format($base, 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Promotion Status -->
                                        <?php if($isLive): ?>
                                            <div class="absolute bottom-5 left-5">
                                                <div class="bg-emerald-500/95 text-white px-4 py-2 rounded-2xl backdrop-blur-sm shadow-xl">
                                                    <div class="text-xs font-black flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M13 1L15.5 7.5L22 9L15.5 10.5L13 17L10.5 10.5L4 9L10.5 7.5L13 1Z"/>
                                                        </svg>
                                                        LIVE DISCOUNT
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif($promo): ?>
                                            <div class="absolute bottom-5 left-5">
                                                <div class="bg-yellow-500/95 text-white px-4 py-2 rounded-2xl backdrop-blur-sm shadow-xl">
                                                    <div class="text-xs font-black flex items-center gap-1">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M12 2C6.48 2 2 6.48 2 12S6.48 22 12 22 22 17.52 22 12 17.52 2 12 2M13 17H11V15H13V17M13 13H11V7H13V13Z"/>
                                                        </svg>
                                                        SCHEDULED
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Content Section - Flexible -->
                                    <div class="p-8 flex-grow flex flex-col">
                                        <!-- Title and Description - Fixed Height Container -->
                                        <div class="mb-6 flex-grow">
                                            <h3 class="text-2xl font-black text-cafe-brown mb-3 min-h-[3rem] line-clamp-2"><?= htmlspecialchars($item['name']) ?></h3>
                                            <div class="min-h-[4.5rem]">
                                                <?php if ($item['description']): ?>
                                                <p class="text-warm-gray text-lg line-clamp-3 font-medium"><?= htmlspecialchars($item['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Action Buttons - Fixed at Bottom -->
                                        <div class="flex space-x-4 mb-6 mt-auto">
                                            <a href="menu.php?edit=<?= $item['id'] ?>" 
                                               class="flex-1 bg-gradient-to-r from-golden-500 to-golden-600 text-white px-6 py-4 rounded-2xl font-black hover:shadow-2xl transition-all duration-300 text-center inline-flex items-center justify-center transform hover:scale-105 shimmer-bg">
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            <form method="POST" class="flex-1" onsubmit="return confirm('Are you sure you want to delete this menu item?')">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" name="delete_item" 
                                                        class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-4 rounded-2xl font-black hover:shadow-2xl transition-all duration-300 inline-flex items-center justify-center transform hover:scale-105">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Enhanced Promotion Editor - Fixed Height -->
                                    <div class="p-6 bg-gradient-to-br from-gray-50 to-gray-100 border-t mt-auto">
                                        <div class="mb-4 flex items-center justify-between">
                                            <h4 class="text-lg font-bold text-cafe-brown flex items-center gap-2">
                                                <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"/>
                                                </svg>
                                                Discount Promotions
                                            </h4>
                                            <?php if($isLive): ?>
                                                <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-sm font-bold flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M9 12L11 14L15 10"/>
                                                    </svg>
                                                    LIVE NOW
                                                </span>
                                            <?php elseif($promo): ?>
                                                <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-bold">
                                                    ⏰ SCHEDULED
                                                </span>
                                            <?php else: ?>
                                                <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-bold">
                                                    No Promotion
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <form method="POST" action="" class="space-y-4" onsubmit="return handlePromotionSubmit(this)">
                                            <input type="hidden" name="menu_item_id" value="<?= $mid; ?>">
                                            <input type="hidden" name="promo_id" value="<?= $promo ? (int)$promo['id'] : 0; ?>">

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-bold text-cafe-brown mb-2 flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M7 4V2C7 1.45 7.45 1 8 1H16C16.55 1 17 1.45 17 2V4H20C20.55 4 21 4.45 21 5S20.55 6 20 6H19V19C19 20.1 18.1 21 17 21H7C5.9 21 5 20.1 5 19V6H4C3.45 6 3 5.55 3 5S3.45 4 4 4H7M9 3V4H15V3H9M7 6V19H17V6H7Z"/>
                                                        </svg>
                                                        Fixed Price (Rs) - Optional
                                                    </label>
                                                    <input type="number" step="0.01" name="promo_price" id="promo_price_<?= $mid ?>"
                                                           value="<?= $promo && $promo['promo_price'] !== null ? htmlspecialchars($promo['promo_price']) : ''; ?>"
                                                           placeholder="e.g. 1299.00"
                                                           class="promo-price-field w-full border-2 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 transition-all duration-300"
                                                           onchange="clearDiscountField(<?= $mid ?>)" />
                                                    <p class="text-xs text-blue-600 mt-1 font-medium">Fixed promotional price override</p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-bold text-cafe-brown mb-2 flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M12 2L13.09 8.26L22 9L13.09 9.74L12 16L10.91 9.74L2 9L10.91 8.26L12 2Z"/>
                                                        </svg>
                                                        Discount % - Recommended
                                                    </label>
                                                    <input type="number" step="0.01" name="discount_percent" id="discount_percent_<?= $mid ?>"
                                                           value="<?= $promo && $promo['discount_percent'] !== null ? htmlspecialchars($promo['discount_percent']) : ''; ?>"
                                                           placeholder="e.g. 20"
                                                           min="0" max="95"
                                                           class="discount-field w-full border-2 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 transition-all duration-300"
                                                           onchange="clearPromoField(<?= $mid ?>)" />
                                                    <p class="text-xs text-emerald-600 mt-1 font-medium">Percentage discount off original price</p>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-bold text-cafe-brown mb-2">Promotion Label</label>
                                                    <input type="text" name="label"
                                                           value="<?= $promo ? htmlspecialchars($promo['label']) : 'Special Discount'; ?>"
                                                           maxlength="60"
                                                           class="w-full border-2 border-golden-200/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80" />
                                                </div>
                                                <div class="flex items-center gap-3 pt-8">
                                                    <input type="checkbox" id="active_<?= $mid; ?>" name="active"
                                                           class="w-5 h-5 text-emerald-600 rounded focus:ring-emerald-500"
                                                           <?= $promo ? ((int)$promo['active']===1 ? 'checked' : '') : 'checked'; ?> />
                                                    <label for="active_<?= $mid; ?>" class="text-sm font-bold text-cafe-brown">Active Promotion</label>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-bold text-cafe-brown mb-2">Starts At</label>
                                                    <input type="datetime-local" name="starts_at"
                                                           value="<?= $promo ? htmlspecialchars(to_input_dt($promo['starts_at'] ?? null)) : ''; ?>"
                                                           class="w-full border-2 border-golden-200/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-bold text-cafe-brown mb-2">Ends At</label>
                                                    <input type="datetime-local" name="ends_at"
                                                           value="<?= $promo ? htmlspecialchars(to_input_dt($promo['ends_at'] ?? null)) : ''; ?>"
                                                           class="w-full border-2 border-golden-200/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80" />
                                                </div>
                                            </div>

                                            <div class="flex items-center flex-wrap gap-3 pt-4">
                                                <button type="submit" name="save_promo"
                                                        class="bg-gradient-to-r from-emerald-500 to-emerald-600 text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-300 transform hover:scale-105 flex items-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                    Save Discount
                                                </button>

                                                <?php if($promo): ?>
                                                    <a href="menu.php?delete_promo=<?= (int)$promo['id']; ?>&mid=<?= $mid; ?>"
                                                       onclick="return confirm('Delete this promotion?');"
                                                       class="bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl font-bold hover:shadow-lg transition-all duration-300 transform hover:scale-105 flex items-center gap-2">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Delete
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Enhanced Live Preview -->
                                            <?php if($promo): ?>
                                                <div class="mt-4 p-4 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                                                    <h5 class="text-sm font-bold text-blue-800 mb-2 flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2M21 9V7L15 1L13.5 2.5L16.17 5.23L10.5 10.9L16.1 16.5L21 11.9V9.5L18.5 12C17.1 13.4 14.9 13.4 13.5 12C12.1 10.6 12.1 8.4 13.5 7L16 9.5H21Z"/>
                                                        </svg>
                                                        Promotion Preview
                                                    </h5>
                                                    <div class="text-sm text-blue-700 space-y-2">
                                                        <div class="flex justify-between">
                                                            <strong>Label:</strong> 
                                                            <span><?= htmlspecialchars($promo['label']); ?></span>
                                                        </div>
                                                        <?php if($promo['promo_price'] !== null): ?>
                                                            <div class="flex justify-between">
                                                                <strong>Fixed Price:</strong> 
                                                                <span class="text-blue-600 font-bold">Rs<?= number_format((float)$promo['promo_price'], 2); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if($promo['discount_percent'] !== null): ?>
                                                            <div class="flex justify-between">
                                                                <strong>Discount:</strong> 
                                                                <span class="text-emerald-600 font-bold"><?= htmlspecialchars($promo['discount_percent']); ?>% OFF</span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if($final !== null && $final !== $base): ?>
                                                            <div class="mt-3 p-3 bg-white rounded-lg border-l-4 border-emerald-500">
                                                                <div class="font-bold text-gray-800">Customer Pays:</div>
                                                                <div class="flex items-center gap-3 mt-1">
                                                                    <span class="line-through text-gray-500 text-lg">Rs<?= number_format($base, 2); ?></span>
                                                                    <span class="text-2xl font-black text-emerald-600">Rs<?= number_format($final, 2); ?></span>
                                                                    <span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full text-xs font-bold">
                                                                        <?= round((($base-$final)/$base)*100); ?>% SAVINGS
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="mt-3 pt-2 border-t border-blue-200">
                                                            <div class="flex justify-between items-center">
                                                                <span class="text-xs font-medium">Status:</span>
                                                                <span class="text-xs">
                                                                    <?= $isLive ? '<span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded-full font-bold">🟢 LIVE NOW</span>' : '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full font-bold">⏸ Not Live</span>'; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </form>
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
            document.getElementById('itemForm').classList.remove('hidden');
            document.getElementById('itemForm').scrollIntoView({ behavior: 'smooth' });
            document.querySelector('input[name="name"]').focus();
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

        // Enhanced promotion form handling
        function clearDiscountField(itemId) {
            const promoPrice = document.getElementById(`promo_price_${itemId}`);
            const discountPercent = document.getElementById(`discount_percent_${itemId}`);
            
            if (promoPrice.value !== '') {
                discountPercent.value = '';
                discountPercent.style.opacity = '0.5';
                discountPercent.disabled = true;
            } else {
                discountPercent.style.opacity = '1';
                discountPercent.disabled = false;
            }
        }

        function clearPromoField(itemId) {
            const promoPrice = document.getElementById(`promo_price_${itemId}`);
            const discountPercent = document.getElementById(`discount_percent_${itemId}`);
            
            if (discountPercent.value !== '') {
                promoPrice.value = '';
                promoPrice.style.opacity = '0.5';
                promoPrice.disabled = true;
            } else {
                promoPrice.style.opacity = '1';
                promoPrice.disabled = false;
            }
        }

        function handlePromotionSubmit(form) {
            const formData = new FormData(form);
            const promoPrice = formData.get('promo_price');
            const discountPercent = formData.get('discount_percent');

            if (!promoPrice && !discountPercent) {
                alert('Please enter either a fixed promo price or a discount percentage.');
                return false;
            }

            if (promoPrice && discountPercent) {
                if (confirm('Both promo price and discount percentage are set. The system will use the discount percentage and ignore the promo price. Continue?')) {
                    // Clear the promo price field
                    form.querySelector('input[name="promo_price"]').value = '';
                    return true;
                }
                return false;
            }

            return true;
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
            // Initialize promotion field interactions
            const promoForms = document.querySelectorAll('form[method="POST"]');
            promoForms.forEach(form => {
                const menuItemId = form.querySelector('input[name="menu_item_id"]')?.value;
                if (menuItemId) {
                    const promoPrice = document.getElementById(`promo_price_${menuItemId}`);
                    const discountPercent = document.getElementById(`discount_percent_${menuItemId}`);
                    
                    if (promoPrice && discountPercent) {
                        // Initial state setup
                        if (promoPrice.value !== '') {
                            discountPercent.style.opacity = '0.5';
                            discountPercent.disabled = true;
                        } else if (discountPercent.value !== '') {
                            promoPrice.style.opacity = '0.5';
                            promoPrice.disabled = true;
                        }
                    }
                }
            });

            // Close form on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const form = document.getElementById('itemForm');
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
        if (<?= $edit_item ? 'true' : 'false' ?>) {
            setTimeout(() => {
                if (!document.getElementById('itemForm').classList.contains('hidden')) {
                    document.getElementById('itemForm').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start' 
                    });
                }
            }, 100);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = 
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

            .discount-promotion-highlight {
                background: linear-gradient(135deg, rgba(34, 197, 94, 0.1), rgba(22, 163, 74, 0.05));
                border: 2px solid rgba(34, 197, 94, 0.3);
                box-shadow: 0 0 20px rgba(34, 197, 94, 0.2);
            }

            .promotion-badge-animate {
                animation: pulse-glow 2s ease-in-out infinite;
            }

            @keyframes pulse-glow {
                0%, 100% {
                    transform: scale(1);
                    box-shadow: 0 0 10px rgba(34, 197, 94, 0.5);
                }
                50% {
                    transform: scale(1.05);
                    box-shadow: 0 0 20px rgba(34, 197, 94, 0.8);
                }
            }
        ;
        document.head.appendChild(style);
    </script>
</body>
</html>