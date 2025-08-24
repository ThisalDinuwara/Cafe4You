<?php
// includes/functions.php - Fixed version
function sanitize($data) {
    // Check if data is null or empty
    if ($data === null || $data === '') {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function showMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function displayMessage() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        $bgColor = $type === 'success' ? 'bg-green-500' : 'bg-red-500';
        echo "<div class='$bgColor text-white p-4 rounded mb-4'>{$_SESSION['message']}</div>";
        unset($_SESSION['message'], $_SESSION['message_type']);
    }
}

// Helper function to safely get POST data
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// Helper function to safely get array values
function getValue($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>