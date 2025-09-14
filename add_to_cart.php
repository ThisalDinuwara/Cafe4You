<?php
// add_to_cart.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

function isAjax(): bool {
    return (
        (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
}

function jsonRespond(bool $ok, string $message, array $extra = []) {
    header('Content-Type: application/json');
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge([
        'ok'      => $ok,
        'message' => $message,
    ], $extra));
    exit;
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_item_id = (int)($_POST['menu_item_id'] ?? 0);
    $quantity     = (int)($_POST['quantity'] ?? 1);
    $user_id      = $_SESSION['user_id'];

    if ($menu_item_id <= 0 || $quantity <= 0) {
        if (isAjax()) jsonRespond(false, 'Invalid item or quantity');
        showMessage('Invalid item or quantity', 'error');
        redirect($_SERVER['HTTP_REFERER'] ?? 'menu.php');
    }

    // Ensure item exists and available
    $item_stmt = $db->prepare("SELECT id, name FROM menu_items WHERE id = ? AND status = 'available'");
    $item_stmt->execute([$menu_item_id]);
    $item = $item_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        if (isAjax()) jsonRespond(false, 'Item not found or unavailable');
        showMessage('Item not found or unavailable', 'error');
        redirect($_SERVER['HTTP_REFERER'] ?? 'menu.php');
    }

    // Check if item already in cart
    $check_stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND menu_item_id = ?");
    $check_stmt->execute([$user_id, $menu_item_id]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $update_stmt = $db->prepare("UPDATE cart SET quantity = quantity + ? WHERE id = ?");
        $update_stmt->execute([$quantity, $existing['id']]);
    } else {
        $insert_stmt = $db->prepare("INSERT INTO cart (user_id, menu_item_id, quantity) VALUES (?, ?, ?)");
        $insert_stmt->execute([$user_id, $menu_item_id, $quantity]);
    }

    // Updated cart count
    $count_stmt = $db->prepare("SELECT COALESCE(SUM(quantity),0) AS items FROM cart WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $cart_items = (int)$count_stmt->fetchColumn();

    $msg = $item['name'] . ' added to cart successfully!';

    if (isAjax()) {
        jsonRespond(true, $msg, [
            'cart_count' => $cart_items,
            'item_id'    => (int)$item['id'],
            'added_qty'  => $quantity,
        ]);
    }

    // Non-AJAX fallback
    showMessage($msg);
    redirect($_SERVER['HTTP_REFERER'] ?? 'menu.php');
} else {
    redirect('menu.php');
}
