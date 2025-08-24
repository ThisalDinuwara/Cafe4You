<?php
// add_to_cart.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_item_id = (int)$_POST['menu_item_id'];
    $quantity = (int)($_POST['quantity'] ?? 1);
    $user_id = $_SESSION['user_id'];
    
    if ($menu_item_id <= 0 || $quantity <= 0) {
        showMessage('Invalid item or quantity', 'error');
        redirect('menu.php');
    }
    
    // Check if item exists and is available
    $item_query = "SELECT id, name FROM menu_items WHERE id = ? AND status = 'available'";
    $item_stmt = $db->prepare($item_query);
    $item_stmt->execute([$menu_item_id]);
    $item = $item_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        showMessage('Item not found or unavailable', 'error');
        redirect('menu.php');
    }
    
    // Check if item already in cart
    $check_query = "SELECT id, quantity FROM cart WHERE user_id = ? AND menu_item_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$user_id, $menu_item_id]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update quantity
        $update_query = "UPDATE cart SET quantity = quantity + ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([$quantity, $existing['id']]);
    } else {
        // Add new item
        $insert_query = "INSERT INTO cart (user_id, menu_item_id, quantity) VALUES (?, ?, ?)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->execute([$user_id, $menu_item_id, $quantity]);
    }
    
    showMessage($item['name'] . ' added to cart successfully!');
    redirect('cart.php');
} else {
    redirect('menu.php');
}
?>