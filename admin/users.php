<?php
// admin/users.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

// Ensure a session exists for CSRF + user info (usually started in auth.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -----------------------------------------
   CSRF token (generate once per session)
----------------------------------------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$database = new Database();
$db = $database->getConnection();

/* -----------------------------------------
   Helpers
----------------------------------------- */
function canDeleteUserRow(array $userRow): bool {
    $role = strtolower($userRow['role'] ?? '');
    if ($role === 'admin') return false;
    $current_id = $_SESSION['user_id'] ?? null;
    if ($current_id && (int)$current_id === (int)($userRow['id'] ?? 0)) return false;
    return true;
}

function canEditRoleOf(array $target): bool {
    $current_id = $_SESSION['user_id'] ?? null;
    if ($current_id && (int)$current_id === (int)($target['id'] ?? 0)) {
        // Do not allow changing your own role
        return false;
    }
    return true;
}

function sanitize_text($v) { return trim((string)$v); }
function sanitize_email($v) { return trim((string)$v); }

/* -----------------------------------------
   Handle DELETE (POST)
----------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $posted_token   = $_POST['csrf_token'] ?? '';
    $delete_user_id = (int)($_POST['user_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        if (function_exists('setMessage')) setMessage('Invalid request. Please try again.', 'error');
        header('Location: users.php?msg=csrf');
        exit;
    }

    $current_admin_id = $_SESSION['user_id'] ?? null;
    if ($current_admin_id && (int)$current_admin_id === $delete_user_id) {
        if (function_exists('setMessage')) setMessage('You cannot delete your own account.', 'error');
        header('Location: users.php?msg=cannot_delete_self');
        exit;
    }

    $check_stmt = $db->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
    $check_stmt->execute([$delete_user_id]);
    $target_user = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_user) {
        if (function_exists('setMessage')) setMessage('User not found.', 'error');
        header('Location: users.php?msg=not_found');
        exit;
    }

    if (strtolower($target_user['role']) === 'admin') {
        if (function_exists('setMessage')) setMessage('You cannot delete an admin user.', 'error');
        header('Location: users.php?msg=cannot_delete_admin');
        exit;
    }

    try {
        $db->beginTransaction();

        $del_res = $db->prepare("DELETE FROM reservations WHERE user_id = ?");
        $del_res->execute([$delete_user_id]);

        $del_orders = $db->prepare("DELETE FROM orders WHERE user_id = ?");
        $del_orders->execute([$delete_user_id]);

        $del_user = $db->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
        $del_user->execute([$delete_user_id]);

        $db->commit();

        if (function_exists('setMessage')) setMessage('User deleted successfully.', 'success');
        header('Location: users.php?deleted=1');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        if (function_exists('setMessage')) setMessage('Delete failed: ' . $e->getMessage(), 'error');
        header('Location: users.php?msg=delete_failed');
        exit;
    }
}

/* -----------------------------------------
   Handle UPDATE (POST)
----------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        if (function_exists('setMessage')) setMessage('Invalid request. Please try again.', 'error');
        header('Location: users.php?msg=csrf');
        exit;
    }

    $user_id   = (int)($_POST['user_id'] ?? 0);
    $full_name = sanitize_text($_POST['full_name'] ?? '');
    $username  = sanitize_text($_POST['username'] ?? '');
    $email     = sanitize_email($_POST['email'] ?? '');
    $phone     = sanitize_text($_POST['phone'] ?? '');
    $address   = sanitize_text($_POST['address'] ?? '');
    $role_in   = sanitize_text($_POST['role'] ?? 'user');

    // Load target user
    $user_stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $user_stmt->execute([$user_id]);
    $target = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        if (function_exists('setMessage')) setMessage('User not found.', 'error');
        header('Location: users.php?msg=not_found');
        exit;
    }

    // Basic validation
    $errors = [];
    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    // Duplicate email check
    $dup_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $dup_stmt->execute([$email, $user_id]);
    if ($dup_stmt->fetch()) $errors[] = 'Email is already in use by another account.';

    // Role editing permissions
    $final_role = $target['role']; // default keep existing
    if (canEditRoleOf($target)) {
        $role_in = strtolower($role_in);
        if (!in_array($role_in, ['admin', 'user'], true)) {
            $errors[] = 'Invalid role.';
        } else {
            $final_role = $role_in;
        }
    }

    if (!empty($errors)) {
        if (function_exists('setMessage')) setMessage(implode(' ', $errors), 'error');
        header('Location: users.php?edit=' . $user_id);
        exit;
    }

    // Update
    $upd = $db->prepare("
        UPDATE users
           SET full_name = ?, username = ?, email = ?, phone = ?, address = ?, role = ?
         WHERE id = ?
         LIMIT 1
    ");
    $ok = $upd->execute([
        $full_name,
        $username,
        $email,
        $phone,
        $address,
        $final_role,
        $user_id
    ]);

    if ($ok) {
        if (function_exists('setMessage')) setMessage('User updated successfully.', 'success');
        header('Location: users.php?view=' . $user_id);
        exit;
    } else {
        if (function_exists('setMessage')) setMessage('Update failed. Please try again.', 'error');
        header('Location: users.php?edit=' . $user_id);
        exit;
    }
}

/* -----------------------------------------
   Get all users with stats
----------------------------------------- */
$users_query = "SELECT u.*, 
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_amount) as total_spent,
                COUNT(DISTINCT r.id) as total_reservations
                FROM users u 
                LEFT JOIN orders o ON u.id = o.user_id 
                LEFT JOIN reservations r ON u.id = r.user_id 
                GROUP BY u.id 
                ORDER BY u.created_at DESC";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------------------
   Load user for view/edit modal
----------------------------------------- */
$user_details = null;
$user_orders = [];
$user_reservations = [];
$edit_mode = false;

if (isset($_GET['view']) || isset($_GET['edit'])) {
    $user_id = (int)($_GET['view'] ?? $_GET['edit']);
    $edit_mode = isset($_GET['edit']);

    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_details) {
        $orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
        $orders_stmt = $db->prepare($orders_query);
        $orders_stmt->execute([$user_id]);
        $user_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

        $reservations_query = "SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
        $reservations_stmt = $db->prepare($reservations_query);
        $reservations_stmt->execute([$user_id]);
        $user_reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Calculate stats
$total_users = count($users);
$admin_count = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$user_count = $total_users - $admin_count;
$total_spent = array_sum(array_column($users, 'total_spent'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-yellow': '#FCD34D',
                        'brand-amber': '#F59E0B',
                        'brand-cream': '#FFF8F0',
                        'sidebar-bg': '#2C3E50',
                        'sidebar-hover': '#34495E'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .card-shadow {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .gradient-card {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
        }
        
        .nav-item {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #FCD34D;
            border-radius: 0 4px 4px 0;
        }
        
        .nav-item.active {
            background: rgba(252, 211, 77, 0.1);
            color: #FCD34D;
            border-right: 3px solid #FCD34D;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Top Navigation -->
    <nav class="bg-sidebar-bg text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <h1 class="text-xl font-bold">Cafe For You - Admin</h1>
                </div>

                <div class="flex items-center space-x-6">
                    <span class="text-gray-300">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition-colors duration-300">View Site</a>
                    <a href="../logout.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-4 py-2 rounded-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="orders.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        Orders
                    </a>
                    <a href="menu.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Menu Management
                    </a>
                    <a href="categories.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Categories
                    </a>
                    <a href="reservations.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                        </svg>
                        Reservations
                    </a>
                    <a href="users.php" class="nav-item active flex items-center px-4 py-3 text-brand-yellow bg-yellow-50 transition-all duration-300 rounded-lg mx-2 font-medium">
                        <svg class="w-5 h-5 mr-3 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        Users
                    </a>
                    <a href="messages.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Contact Messages
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <div class="p-8">
                <!-- Header Section -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-purple-600 rounded-2xl flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
                            <p class="text-gray-600 mt-1">Manage customer accounts and user permissions</p>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Users -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Total Users</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $total_users ?></div>
                        <div class="text-white/70 text-sm">Registered accounts</div>
                    </div>

                    <!-- Regular Users -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Regular Users</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $user_count ?></div>
                        <div class="text-white/70 text-sm">Customer accounts</div>
                    </div>

                    <!-- Admins -->
                    <div class="gradient-card from-green-500 to-green-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Admin Users</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $admin_count ?></div>
                        <div class="text-white/70 text-sm">System administrators</div>
                    </div>

                    <!-- Total Revenue -->
                    <div class="gradient-card from-brand-yellow to-brand-amber rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Total Revenue</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2">$<?= number_format($total_spent, 0) ?></div>
                        <div class="text-white/70 text-sm">From all users</div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-yellow to-brand-amber px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                <h3 class="text-white font-semibold text-lg">User Accounts</h3>
                            </div>
                            <div class="text-white/90 text-sm">
                                <span class="bg-white/20 px-3 py-1 rounded-full">
                                    <?= $total_users ?> Total Users
                                </span>
                            </div>
                        </div>
                        <p class="text-white/80 text-sm mt-1">Manage customer accounts and permissions (<?= $total_users ?> users)</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">USER ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">CUSTOMER</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ROLE</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ORDERS</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">TOTAL SPENT</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">STATUS</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php $counter = 1; foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <!-- User ID -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                                                #<?= $counter++ ?>
                                            </div>
                                        </td>

                                        <!-- Customer Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-white font-medium text-sm">
                                                        <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Role -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' ?>">
                                                <?= $user['role'] === 'admin' ? '👑 Admin' : '👤 Customer' ?>
                                            </span>
                                        </td>

                                        <!-- Orders -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-gray-900"><?= $user['total_orders'] ?: 0 ?> orders</div>
                                            <div class="text-xs text-gray-500"><?= $user['total_reservations'] ?: 0 ?> reservations</div>
                                        </td>

                                        <!-- Total Spent -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-bold text-gray-900">$<?= number_format($user['total_spent'] ?: 0, 2) ?></div>
                                            <div class="text-xs text-gray-500">lifetime value</div>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✓ Active
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <a href="users.php?view=<?= (int)$user['id'] ?>" 
                                                   class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                                    👁 View Details
                                                </a>

                                                <?php if (canDeleteUserRow($user)): ?>
                                                    <form method="post" action="users.php" class="inline" 
                                                          onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                        <button type="submit" 
                                                                class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-300">
                                                            🗑 Delete
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="bg-gray-300 text-gray-500 px-3 py-1.5 rounded-lg text-xs cursor-not-allowed" title="Delete not allowed">
                                                        🗑 Delete
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User Modal (View or Edit) -->
    <?php if ($user_details): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" id="user-modal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-gradient-to-r from-brand-yellow to-brand-amber px-6 py-4 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">
                                    <?= $edit_mode ? 'Edit User' : 'User Details' ?>
                                </h3>
                                <p class="text-white/80 text-sm"><?= htmlspecialchars($user_details['full_name']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php if (!$edit_mode): ?>
                                <a href="users.php?edit=<?= (int)$user_details['id'] ?>" 
                                   class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300">
                                    ✏️ Edit
                                </a>
                                <?php if (canDeleteUserRow($user_details)): ?>
                                    <form method="post" action="users.php" class="inline" 
                                          onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$user_details['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" 
                                                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="users.php" class="text-white/80 hover:text-white" title="Close">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <?php if ($edit_mode): ?>
                        <!-- Edit Form -->
                        <form method="post" action="users.php" class="space-y-6">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="user_id" value="<?= (int)$user_details['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                                    <input type="text" name="full_name" value="<?= htmlspecialchars($user_details['full_name']) ?>"
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                                    <input type="text" name="username" value="<?= htmlspecialchars($user_details['username'] ?? '') ?>"
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($user_details['email']) ?>"
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Phone</label>
                                    <input type="text" name="phone" value="<?= htmlspecialchars($user_details['phone'] ?? '') ?>"
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                                    <textarea name="address" rows="3"
                                              class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent"><?= htmlspecialchars($user_details['address'] ?? '') ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                                    <?php $allow_role_edit = canEditRoleOf($user_details); ?>
                                    <select name="role"
                                            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent"
                                            <?= $allow_role_edit ? '' : 'disabled' ?>>
                                        <?php
                                            $role_val = strtolower($user_details['role']);
                                            $roles = ['user' => 'User', 'admin' => 'Admin'];
                                            foreach ($roles as $val => $label) {
                                                $sel = ($role_val === $val) ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                    <?php if (!$allow_role_edit): ?>
                                        <p class="text-xs text-gray-500 mt-1">You cannot change your own role.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                                <a href="users.php?view=<?= (int)$user_details['id'] ?>" 
                                   class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-all duration-300">
                                    Cancel
                                </a>
                                <button type="submit" 
                                        class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                    💾 Save Changes
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- View Mode -->
                        <div class="grid md:grid-cols-2 gap-8 mb-8">
                            <!-- User Information -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-6">
                                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Personal Information
                                </h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium text-gray-600">Username:</span>
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($user_details['username'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium text-gray-600">Email:</span>
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($user_details['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium text-gray-600">Phone:</span>
                                        <span class="text-sm text-gray-900"><?= htmlspecialchars($user_details['phone'] ?: 'Not provided') ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium text-gray-600">Role:</span>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?= $user_details['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' ?>">
                                            <?= $user_details['role'] === 'admin' ? '👑 Admin' : '👤 Customer' ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm font-medium text-gray-600">Joined:</span>
                                        <span class="text-sm text-gray-900"><?= date('M j, Y g:i A', strtotime($user_details['created_at'])) ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($user_details['address'])): ?>
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <span class="text-sm font-medium text-gray-600">Address:</span>
                                        <p class="text-sm text-gray-900 mt-1"><?= nl2br(htmlspecialchars($user_details['address'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Statistics -->
                            <div class="space-y-4">
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="font-semibold text-blue-800">Total Orders</h5>
                                            <p class="text-3xl font-bold text-blue-600"><?= count($user_orders ?? []) ?></p>
                                        </div>
                                        <svg class="w-12 h-12 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl p-6">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="font-semibold text-green-800">Reservations</h5>
                                            <p class="text-3xl font-bold text-green-600"><?= count($user_reservations ?? []) ?></p>
                                        </div>
                                        <svg class="w-12 h-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid md:grid-cols-2 gap-8">
                            <!-- Recent Orders -->
                            <div>
                                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                    </svg>
                                    Recent Orders
                                </h4>
                                <?php if (empty($user_orders)): ?>
                                    <div class="text-center py-8 bg-gray-50 rounded-xl">
                                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                        <p class="text-gray-500 text-sm">No orders found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-3">
                                        <?php foreach ($user_orders as $order): ?>
                                            <div class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all duration-300">
                                                <div class="flex justify-between items-start mb-2">
                                                    <span class="font-semibold text-gray-900">Order #<?= (int)$order['id'] ?></span>
                                                    <span class="font-bold text-brand-yellow">$<?= number_format((float)$order['total_amount'], 2) ?></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                                                    <span class="px-2 py-1 text-xs rounded-full font-medium
                                                        <?php
                                                        $status_colors = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'confirmed' => 'bg-blue-100 text-blue-800',
                                                            'preparing' => 'bg-purple-100 text-purple-800',
                                                            'ready' => 'bg-green-100 text-green-800',
                                                            'delivered' => 'bg-gray-100 text-gray-800',
                                                            'cancelled' => 'bg-red-100 text-red-800'
                                                        ];
                                                        echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                                        ?>">
                                                        <?= ucfirst($order['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Recent Reservations -->
                            <div>
                                <h4 class="font-bold text-gray-800 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                    </svg>
                                    Recent Reservations
                                </h4>
                                <?php if (empty($user_reservations)): ?>
                                    <div class="text-center py-8 bg-gray-50 rounded-xl">
                                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                        </svg>
                                        <p class="text-gray-500 text-sm">No reservations found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-3">
                                        <?php foreach ($user_reservations as $reservation): ?>
                                            <div class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-md transition-all duration-300">
                                                <div class="flex justify-between items-start mb-2">
                                                    <span class="font-semibold text-gray-900"><?= date('M j, Y', strtotime($reservation['date'])) ?></span>
                                                    <span class="font-bold text-brand-yellow"><?= (int)$reservation['guests'] ?> guests</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500"><?= date('g:i A', strtotime($reservation['time'])) ?></span>
                                                    <span class="px-2 py-1 text-xs rounded-full font-medium
                                                        <?php
                                                        $status_colors = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'confirmed' => 'bg-green-100 text-green-800',
                                                            'cancelled' => 'bg-red-100 text-red-800'
                                                        ];
                                                        echo $status_colors[$reservation['status']] ?? 'bg-gray-100 text-gray-800';
                                                        ?>">
                                                        <?= ucfirst($reservation['status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Enhanced modal handling
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('user-modal')) {
                    window.location.href = 'users.php';
                }
            });

            // Close modal on background click
            const modal = document.getElementById('user-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        window.location.href = 'users.php';
                    }
                });
            }
        });
    </script>
</body>
</html>