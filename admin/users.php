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
$total_orders = array_sum(array_column($users, 'total_orders'));
$total_reservations = array_sum(array_column($users, 'total_reservations'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Cafe For You Admin</title>
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

        .section-header {
            background: linear-gradient(135deg, rgba(255, 199, 40, 0.1), rgba(255, 184, 0, 0.05));
            border-left: 6px solid #FFC728;
            backdrop-filter: blur(10px);
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

        .user-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 199, 40, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 199, 40, 0.2), transparent);
            transition: left 0.5s;
        }

        .user-card:hover::before {
            left: 100%;
        }

        .user-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(255, 184, 0, 0.3);
            border-color: rgba(255, 199, 40, 0.6);
        }

        .role-admin {
            background: linear-gradient(135deg, #A855F7, #9333EA);
            color: white;
            border: 2px solid #8B5CF6;
        }

        .role-user {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border: 2px solid #06D6A0;
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

        .modal-backdrop {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 199, 40, 0.2);
            box-shadow: 0 30px 80px rgba(255, 184, 0, 0.3);
        }

        @media (max-width: 768px) {
            .glass-card:hover {
                transform: none;
            }
            .user-card:hover {
                transform: translateY(-4px);
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
                        <p class="text-warm-gray font-medium text-lg">User Management</p>
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
                        <a href="users.php" class="nav-item active flex items-center px-6 py-4 transition-all duration-300 font-semibold text-lg rounded-2xl">
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
                    <div class="flex items-center">
                        <div class="relative">
                            <div class="w-20 h-20 icon-container rounded-3xl flex items-center justify-center mr-6 animate-glow">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="animate-slide-up">
                            <h1 class="text-4xl font-black golden-text mb-2">User Management</h1>
                            <p class="text-warm-gray text-xl font-medium">Manage customer accounts and user permissions</p>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12 animate-slide-up">
                    <!-- Total Users Card -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Users</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $total_users ?></div>
                        <div class="text-white/80 text-base font-medium">Registered accounts</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Regular Users Card -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Customer Users</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $user_count ?></div>
                        <div class="text-white/80 text-base font-medium">Customer accounts</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Admin Users Card -->
                    <div class="gradient-card from-green-500 to-green-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Admin Users</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $admin_count ?></div>
                        <div class="text-white/80 text-base font-medium">System administrators</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Total Revenue Card -->
                    <div class="gradient-card from-golden-500 to-golden-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Revenue</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3">Rs<?= number_format($total_spent, 0) ?></div>
                        <div class="text-white/80 text-base font-medium">From all users</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Overview -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6 mb-12">
                    <!-- Total Orders -->
                    <div class="glass-card rounded-2xl p-6 hover-lift border-l-4 border-golden-400">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-3xl">🛍️</span>
                            <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="text-white font-bold text-lg"><?= $total_orders ?></span>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-cafe-brown">Total Orders</h3>
                        <p class="text-sm text-warm-gray font-medium">All user orders</p>
                    </div>

                    <!-- Total Reservations -->
                    <div class="glass-card rounded-2xl p-6 hover-lift border-l-4 border-golden-400">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-3xl">📅</span>
                            <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-teal-600 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="text-white font-bold text-lg"><?= $total_reservations ?></span>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-cafe-brown">Reservations</h3>
                        <p class="text-sm text-warm-gray font-medium">Table bookings</p>
                    </div>

                    <!-- Average Revenue -->
                    <div class="glass-card rounded-2xl p-6 hover-lift border-l-4 border-golden-400">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-3xl">💰</span>
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                                <span class="text-white font-bold text-sm">Rs<?= $total_users > 0 ? number_format($total_spent / $total_users, 0) : 0 ?></span>
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-cafe-brown">Avg Per User</h3>
                        <p class="text-sm text-warm-gray font-medium">Revenue per customer</p>
                    </div>
                </div>

                <!-- Users Grid -->
                <div class="glass-card rounded-3xl shadow-2xl overflow-hidden">
                    <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-2xl mb-2">User Accounts</h3>
                                    <p class="text-white/80 text-lg font-medium">Manage customer accounts and permissions</p>
                                </div>
                            </div>
                            <div class="text-white/90 text-lg">
                                <span class="bg-white/20 px-6 py-3 rounded-2xl font-black backdrop-blur-sm">
                                    <?= count($users) ?> Total Users
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-10">
                        <?php if (empty($users)): ?>
                        <div class="text-center py-24">
                            <div class="w-40 h-40 mx-auto mb-10 bg-golden-200/20 rounded-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-golden-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-cafe-brown mb-4">No Users Yet</h3>
                            <p class="text-warm-gray mb-8 text-xl font-medium">User accounts will appear here once customers register.</p>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <?php $counter = 1; foreach ($users as $user): ?>
                                <div class="user-card rounded-3xl shadow-2xl overflow-hidden hover-lift">
                                    <div class="bg-gradient-to-br from-golden-50 to-golden-100 p-6 border-b-2 border-golden-200">
                                        <div class="flex items-center justify-between mb-4">
                                            <!-- User ID -->
                                            <div class="w-16 h-16 bg-gradient-to-br from-golden-500 to-golden-600 rounded-2xl flex items-center justify-center shadow-xl">
                                                <span class="text-white font-black text-lg">#<?= $counter++ ?></span>
                                            </div>

                                            <!-- Role Badge -->
                                            <div class="role-<?= $user['role'] ?> px-4 py-2 rounded-2xl text-sm font-black shadow-lg flex items-center space-x-2">
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span>👑</span><span>Admin</span>
                                                <?php else: ?>
                                                    <span>👤</span><span>Customer</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Customer Info -->
                                        <div class="flex items-center space-x-4">
                                            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                                                <span class="text-white font-bold text-lg">
                                                    <?= strtoupper(substr($user['full_name'], 0, 2)) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-black text-cafe-brown"><?= htmlspecialchars($user['full_name']) ?></h4>
                                                <p class="text-base text-golden-600 font-medium"><?= htmlspecialchars($user['email']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-6">
                                        <!-- User Stats -->
                                        <div class="space-y-4 mb-6">
                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Total Orders</span>
                                                <div class="text-right">
                                                    <div class="text-sm font-bold text-cafe-brown"><?= $user['total_orders'] ?: 0 ?> orders</div>
                                                    <div class="text-xs text-warm-gray">Rs<?= number_format($user['total_spent'] ?: 0, 0) ?> spent</div>
                                                </div>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Reservations</span>
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">
                                                    <?= $user['total_reservations'] ?: 0 ?> bookings
                                                </span>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Member Since</span>
                                                <span class="text-sm font-medium text-cafe-brown"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="space-y-3 pt-6 border-t-2 border-golden-200/50">
                                            <a href="users.php?view=<?= (int)$user['id'] ?>" 
                                               class="w-full bg-gradient-to-r from-golden-500 to-golden-600 text-white px-6 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg text-center block">
                                                👁 View Details
                                            </a>

                                            <?php if (canDeleteUserRow($user)): ?>
                                                <form method="post" action="users.php" class="w-full" 
                                                      onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <button type="submit" 
                                                            class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105">
                                                        🗑 Delete User
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button disabled
                                                        class="w-full bg-gray-300 text-gray-500 px-6 py-4 rounded-2xl font-black text-lg cursor-not-allowed" 
                                                        title="Delete not allowed">
                                                    🗑 Delete Restricted
                                                </button>
                                            <?php endif; ?>
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

    <!-- Enhanced User Details/Edit Modal -->
    <?php if ($user_details): ?>
        <div class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" id="user-modal">
            <div class="modal-content rounded-3xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="sticky top-0 bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8 rounded-t-3xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-white mb-2">
                                    <?= $edit_mode ? 'Edit User' : 'User Details' ?>
                                </h3>
                                <p class="text-white/80 text-lg font-medium"><?= htmlspecialchars($user_details['full_name']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <?php if (!$edit_mode): ?>
                                <a href="users.php?edit=<?= (int)$user_details['id'] ?>" 
                                   class="bg-white/20 hover:bg-white/30 text-white px-6 py-3 rounded-2xl text-lg font-black transition-all duration-300 flex items-center space-x-2">
                                    <span>✏</span><span>Edit</span>
                                </a>
                                <?php if (canDeleteUserRow($user_details)): ?>
                                    <form method="post" action="users.php" class="inline" 
                                          onsubmit="return confirm('Delete this user permanently? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int)$user_details['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <button type="submit" 
                                                class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-2xl text-lg font-black transition-all duration-300 flex items-center space-x-2">
                                            <span>🗑</span><span>Delete</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="users.php" class="text-white/80 hover:text-white p-2 hover:bg-white/10 rounded-xl transition-all duration-300" title="Close">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-10">
                    <?php if ($edit_mode): ?>
                        <!-- Edit Form -->
                        <form method="post" action="users.php" class="space-y-8">
                            <input type="hidden" name="action" value="update_user">
                            <input type="hidden" name="user_id" value="<?= (int)$user_details['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <div class="bg-gradient-to-br from-golden-50 to-golden-100 rounded-3xl p-8 border-2 border-golden-200/50">
                                <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Personal Information
                                </h4>

                                <div class="grid md:grid-cols-2 gap-8">
                                    <div>
                                        <label class="block text-lg font-bold text-cafe-brown mb-3">Full Name</label>
                                        <input type="text" name="full_name" value="<?= htmlspecialchars($user_details['full_name']) ?>"
                                               class="w-full px-6 py-4 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold text-lg">
                                    </div>
                                    <div>
                                        <label class="block text-lg font-bold text-cafe-brown mb-3">Username</label>
                                        <input type="text" name="username" value="<?= htmlspecialchars($user_details['username'] ?? '') ?>"
                                               class="w-full px-6 py-4 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold text-lg">
                                    </div>
                                    <div>
                                        <label class="block text-lg font-bold text-cafe-brown mb-3">Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($user_details['email']) ?>"
                                               class="w-full px-6 py-4 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold text-lg">
                                    </div>
                                    <div>
                                        <label class="block text-lg font-bold text-cafe-brown mb-3">Phone</label>
                                        <input type="text" name="phone" value="<?= htmlspecialchars($user_details['phone'] ?? '') ?>"
                                               class="w-full px-6 py-4 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold text-lg">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-lg font-bold text-cafe-brown mb-3">Address</label>
                                        <textarea name="address" rows="4"
                                                  class="w-full px-6 py-4 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold text-lg resize-none"><?= htmlspecialchars($user_details['address'] ?? '') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-lg font-bold text-cafe-brown mb-3">Role</label>
                                        <?php $allow_role_edit = canEditRoleOf($user_details); ?>
                                        <select name="role"
                                                class="w-full px-6 py-4 border-2 border-golden-200/50 rounded-2xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold text-lg"
                                                <?= $allow_role_edit ? '' : 'disabled' ?>>
                                            <?php
                                                $role_val = strtolower($user_details['role']);
                                                $roles = ['user' => '👤 User', 'admin' => '👑 Admin'];
                                                foreach ($roles as $val => $label) {
                                                    $sel = ($role_val === $val) ? 'selected' : '';
                                                    echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                                }
                                            ?>
                                        </select>
                                        <?php if (!$allow_role_edit): ?>
                                            <p class="text-sm text-warm-gray mt-2 font-medium">You cannot change your own role.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-6 pt-8 border-t-2 border-golden-200/50">
                                <a href="users.php?view=<?= (int)$user_details['id'] ?>" 
                                   class="px-8 py-4 bg-warm-gray/20 text-warm-gray rounded-2xl font-black text-lg hover:bg-warm-gray/30 transition-all duration-300 transform hover:scale-105">
                                    Cancel
                                </a>
                                <button type="submit" 
                                        class="px-8 py-4 bg-gradient-to-r from-golden-500 to-golden-600 text-white rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg flex items-center space-x-3">
                                    <span>💾</span><span>Save Changes</span>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- View Mode -->
                        <div class="grid lg:grid-cols-2 gap-10 mb-10">
                            <!-- User Information -->
                            <div class="bg-gradient-to-br from-golden-50 to-golden-100 rounded-3xl p-8 border-2 border-golden-200/50">
                                <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Personal Information
                                </h4>
                                <div class="space-y-6">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-bold text-warm-gray">Username:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= htmlspecialchars($user_details['username'] ?? 'N/A') ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-bold text-warm-gray">Email:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= htmlspecialchars($user_details['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-bold text-warm-gray">Phone:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= htmlspecialchars($user_details['phone'] ?: 'Not provided') ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-bold text-warm-gray">Role:</span>
                                        <span class="role-<?= $user_details['role'] ?> px-4 py-2 rounded-2xl text-lg font-black flex items-center space-x-2">
                                            <?php if ($user_details['role'] === 'admin'): ?>
                                                <span>👑</span><span>Admin</span>
                                            <?php else: ?>
                                                <span>👤</span><span>Customer</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-bold text-warm-gray">Joined:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= date('M j, Y g:i A', strtotime($user_details['created_at'])) ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($user_details['address'])): ?>
                                    <div class="mt-8 pt-8 border-t-2 border-golden-200/50">
                                        <span class="text-lg font-bold text-warm-gray">Address:</span>
                                        <p class="text-lg font-medium text-cafe-brown mt-2"><?= nl2br(htmlspecialchars($user_details['address'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Statistics -->
                            <div class="space-y-6">
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-3xl p-8 border-2 border-blue-200/50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="text-xl font-black text-blue-800">Total Orders</h5>
                                            <p class="text-4xl font-black text-blue-600"><?= count($user_orders ?? []) ?></p>
                                        </div>
                                        <div class="w-16 h-16 bg-blue-500 rounded-2xl flex items-center justify-center shadow-xl">
                                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-3xl p-8 border-2 border-green-200/50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="text-xl font-black text-green-800">Reservations</h5>
                                            <p class="text-4xl font-black text-green-600"><?= count($user_reservations ?? []) ?></p>
                                        </div>
                                        <div class="w-16 h-16 bg-green-500 rounded-2xl flex items-center justify-center shadow-xl">
                                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-br from-golden-50 to-golden-100 rounded-3xl p-8 border-2 border-golden-200/50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h5 class="text-xl font-black text-golden-800">Total Spent</h5>
                                            <p class="text-4xl font-black text-golden-600">Rs<?= number_format((float)($user_details['total_spent'] ?? 0), 0) ?></p>
                                        </div>
                                        <div class="w-16 h-16 bg-golden-500 rounded-2xl flex items-center justify-center shadow-xl">
                                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid lg:grid-cols-2 gap-10">
                            <!-- Recent Orders -->
                            <div>
                                <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                    </svg>
                                    Recent Orders
                                </h4>
                                <?php if (empty($user_orders)): ?>
                                    <div class="text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100 rounded-3xl">
                                        <div class="w-24 h-24 mx-auto mb-6 bg-gray-200 rounded-full flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                            </svg>
                                        </div>
                                        <p class="text-gray-500 text-lg font-medium">No orders found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($user_orders as $order): ?>
                                            <div class="bg-white border-2 border-golden-200/50 rounded-2xl p-6 hover:shadow-xl transition-all duration-300">
                                                <div class="flex justify-between items-start mb-3">
                                                    <span class="text-lg font-black text-cafe-brown">Order #<?= (int)$order['id'] ?></span>
                                                    <span class="text-xl font-black text-golden-600">Rs<?= number_format((float)$order['total_amount'], 2) ?></span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm text-warm-gray font-medium"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                                                    <span class="px-3 py-1 text-sm rounded-2xl font-black
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
                                <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                                    <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                    </svg>
                                    Recent Reservations
                                </h4>
                                <?php if (empty($user_reservations)): ?>
                                    <div class="text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100 rounded-3xl">
                                        <div class="w-24 h-24 mx-auto mb-6 bg-gray-200 rounded-full flex items-center justify-center">
                                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                            </svg>
                                        </div>
                                        <p class="text-gray-500 text-lg font-medium">No reservations found</p>
                                    </div>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($user_reservations as $reservation): ?>
                                            <div class="bg-white border-2 border-golden-200/50 rounded-2xl p-6 hover:shadow-xl transition-all duration-300">
                                                <div class="flex justify-between items-start mb-3">
                                                    <span class="text-lg font-black text-cafe-brown"><?= date('M j, Y', strtotime($reservation['date'])) ?></span>
                                                    <span class="text-xl font-black text-golden-600"><?= (int)$reservation['guests'] ?> guests</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm text-warm-gray font-medium"><?= date('g:i A', strtotime($reservation['time'])) ?></span>
                                                    <span class="px-3 py-1 text-sm rounded-2xl font-black
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

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-3xl p-8 flex items-center space-x-4">
            <div class="w-8 h-8 border-4 border-golden-400 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-cafe-brown font-semibold">Loading...</span>
        </div>
    </div>

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

            // Enhanced hover effects for user cards
            const userCards = document.querySelectorAll('.user-card');
            userCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-12px) scale(1.03)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
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
        const buttons = document.querySelectorAll('button, .btn, a[class*="bg-"]');
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
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>