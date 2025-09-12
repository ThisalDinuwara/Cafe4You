<?php
// admin/reservations.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $new_status = sanitize($_POST['status']);
    
    $update_query = "UPDATE reservations SET status = ? WHERE id = ?";
    $update_stmt = $db->prepare($update_query);
    if ($update_stmt->execute([$new_status, $reservation_id])) {
        showMessage('Reservation status updated successfully!');
    } else {
        showMessage('Failed to update reservation status', 'error');
    }
}

// Get all reservations
$reservations_query = "SELECT r.*, u.full_name as user_name, u.email as user_email 
                      FROM reservations r 
                      LEFT JOIN users u ON r.user_id = u.id 
                      ORDER BY r.date DESC, r.time DESC";
$reservations_stmt = $db->prepare($reservations_query);
$reservations_stmt->execute();
$reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate reservation statistics
$stats = [];
$stats['total_reservations'] = count($reservations);
$stats['total_guests'] = array_sum(array_column($reservations, 'guests'));
$stats['avg_party_size'] = $stats['total_reservations'] > 0 ? $stats['total_guests'] / $stats['total_reservations'] : 0;

// Status breakdown
$status_counts = [];
foreach ($reservations as $reservation) {
    $status = $reservation['status'];
    $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
}

// Today's reservations
$today_reservations = array_filter($reservations, function($reservation) {
    return date('Y-m-d', strtotime($reservation['date'])) === date('Y-m-d');
});
$stats['today_reservations'] = count($today_reservations);
$stats['today_guests'] = array_sum(array_column($today_reservations, 'guests'));

// Upcoming reservations (next 7 days)
$upcoming_reservations = array_filter($reservations, function($reservation) {
    $reservation_date = strtotime($reservation['date']);
    $today = strtotime('today');
    $next_week = strtotime('+7 days', $today);
    return $reservation_date >= $today && $reservation_date <= $next_week && $reservation['status'] !== 'cancelled';
});
$stats['upcoming_reservations'] = count($upcoming_reservations);

// Get reservation details if requested
$reservation_details = null;
if (isset($_GET['view'])) {
    $reservation_id = (int)$_GET['view'];
    
    $details_query = "SELECT r.*, u.full_name as user_name, u.email as user_email 
                     FROM reservations r 
                     LEFT JOIN users u ON r.user_id = u.id 
                     WHERE r.id = ?";
    $details_stmt = $db->prepare($details_query);
    $details_stmt->execute([$reservation_id]);
    $reservation_details = $details_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Cafe For You Admin</title>
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

        .reservation-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 199, 40, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .reservation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 199, 40, 0.2), transparent);
            transition: left 0.5s;
        }

        .reservation-card:hover::before {
            left: 100%;
        }

        .reservation-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(255, 184, 0, 0.3);
            border-color: rgba(255, 199, 40, 0.6);
        }

        .status-pending {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            color: #92400E;
            border: 2px solid #F59E0B;
        }

        .status-confirmed {
            background: linear-gradient(135deg, #DBEAFE, #BFDBFE);
            color: #1E40AF;
            border: 2px solid #3B82F6;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #FEE2E2, #FECACA);
            color: #991B1B;
            border: 2px solid #EF4444;
        }

        .status-completed {
            background: linear-gradient(135deg, #D1FAE5, #A7F3D0);
            color: #065F46;
            border: 2px solid #10B981;
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
            .reservation-card:hover {
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
                        <p class="text-warm-gray font-medium text-lg">Reservation Management</p>
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
                        <a href="reservations.php" class="nav-item active flex items-center px-6 py-4 transition-all duration-300 font-semibold text-lg rounded-2xl">
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
                    <div class="flex items-center">
                        <div class="relative">
                            <div class="w-20 h-20 icon-container rounded-3xl flex items-center justify-center mr-6 animate-glow">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="animate-slide-up">
                            <h1 class="text-4xl font-black golden-text mb-2">Reservation Management</h1>
                            <p class="text-warm-gray text-xl font-medium">Manage table reservations and track customer bookings</p>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12 animate-slide-up">
                    <!-- Total Reservations Card -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Reservations</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['total_reservations'] ?></div>
                        <div class="text-white/80 text-base font-medium">All table bookings</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Total Guests Card -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Total Guests</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['total_guests'] ?></div>
                        <div class="text-white/80 text-base font-medium">Expected diners</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Today's Reservations Card -->
                    <div class="gradient-card from-green-500 to-green-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Today's Bookings</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= $stats['today_reservations'] ?></div>
                        <div class="text-white/80 text-base font-medium">Reservations today</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>

                    <!-- Average Party Size Card -->
                    <div class="gradient-card from-golden-500 to-golden-600 rounded-3xl p-8 text-white hover-lift">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-white/90 text-lg font-bold">Avg Party Size</h3>
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="text-4xl font-black mb-3"><?= number_format($stats['avg_party_size'], 1) ?></div>
                        <div class="text-white/80 text-base font-medium">People per table</div>
                        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
                            <div class="h-full bg-white/60 rounded-full shimmer-bg"></div>
                        </div>
                    </div>
                </div>

                <!-- Status Overview -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
                    <?php
                    $status_config = [
                        'pending' => ['icon' => '⏳', 'color' => 'from-yellow-400 to-yellow-600'],
                        'confirmed' => ['icon' => '✅', 'color' => 'from-blue-400 to-blue-600'],
                        'cancelled' => ['icon' => '❌', 'color' => 'from-red-400 to-red-600'],
                        'completed' => ['icon' => '✨', 'color' => 'from-green-400 to-green-600']
                    ];

                    foreach ($status_config as $status => $config):
                        $count = $status_counts[$status] ?? 0;
                    ?>
                        <div class="glass-card rounded-2xl p-6 hover-lift border-l-4 border-golden-400">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-3xl"><?= $config['icon'] ?></span>
                                <div class="w-12 h-12 bg-gradient-to-br <?= $config['color'] ?> rounded-xl flex items-center justify-center shadow-lg">
                                    <span class="text-white font-bold text-lg"><?= $count ?></span>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-cafe-brown capitalize"><?= $status ?></h3>
                            <p class="text-sm text-warm-gray font-medium">Total <?= $status ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reservations Grid -->
                <div class="glass-card rounded-3xl shadow-2xl overflow-hidden">
                    <div class="bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-white font-black text-2xl mb-2">Table Reservations</h3>
                                    <p class="text-white/80 text-lg font-medium">Manage customer table bookings</p>
                                </div>
                            </div>
                            <div class="text-white/90 text-lg">
                                <span class="bg-white/20 px-6 py-3 rounded-2xl font-black backdrop-blur-sm">
                                    <?= count($reservations) ?> Total Bookings
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-10">
                        <?php if (empty($reservations)): ?>
                        <div class="text-center py-24">
                            <div class="w-40 h-40 mx-auto mb-10 bg-golden-200/20 rounded-full flex items-center justify-center">
                                <svg class="w-20 h-20 text-golden-400/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-cafe-brown mb-4">No Reservations Yet</h3>
                            <p class="text-warm-gray mb-8 text-xl font-medium">Customer reservations will appear here once they start booking tables.</p>
                        </div>
                        <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <?php foreach ($reservations as $reservation): ?>
                                <div class="reservation-card rounded-3xl shadow-2xl overflow-hidden hover-lift">
                                    <div class="bg-gradient-to-br from-golden-50 to-golden-100 p-6 border-b-2 border-golden-200">
                                        <div class="flex items-center justify-between mb-4">
                                            <!-- Reservation ID -->
                                            <div class="w-16 h-16 bg-gradient-to-br from-golden-500 to-golden-600 rounded-2xl flex items-center justify-center shadow-xl">
                                                <span class="text-white font-black text-lg">#<?= $reservation['id'] ?></span>
                                            </div>

                                            <!-- Status Badge -->
                                            <div class="status-<?= $reservation['status'] ?> px-4 py-2 rounded-2xl text-sm font-black shadow-lg">
                                                <?= ucfirst($reservation['status']) ?>
                                            </div>
                                        </div>

                                        <!-- Customer Info -->
                                        <div class="flex items-center space-x-4">
                                            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                                                <span class="text-white font-bold text-lg">
                                                    <?= strtoupper(substr($reservation['name'], 0, 2)) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-black text-cafe-brown"><?= htmlspecialchars($reservation['name']) ?></h4>
                                                <p class="text-base text-golden-600 font-medium"><?= htmlspecialchars($reservation['email']) ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-6">
                                        <!-- Reservation Details -->
                                        <div class="space-y-4 mb-6">
                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Date & Time</span>
                                                <div class="text-right">
                                                    <div class="text-sm font-bold text-cafe-brown"><?= date('M j, Y', strtotime($reservation['date'])) ?></div>
                                                    <div class="text-xs text-warm-gray"><?= date('g:i A', strtotime($reservation['time'])) ?></div>
                                                </div>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Party Size</span>
                                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">
                                                    <?= $reservation['guests'] ?> guests
                                                </span>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-warm-gray font-medium">Phone</span>
                                                <span class="text-sm font-medium text-cafe-brown"><?= htmlspecialchars($reservation['phone']) ?></span>
                                            </div>
                                        </div>

                                        <!-- Status Update & Actions -->
                                        <div class="space-y-4 pt-6 border-t-2 border-golden-200/50">
                                            <form method="POST" class="space-y-3">
                                                <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                                <label class="block text-sm font-bold text-cafe-brown">Update Status</label>
                                                <select name="status" onchange="confirmStatusChange(this)" 
                                                        class="w-full px-4 py-3 border-2 border-golden-200/50 rounded-xl focus:outline-none focus:ring-4 focus:ring-golden-400/20 focus:border-golden-400 bg-white/80 backdrop-blur-sm transition-all duration-300 font-bold">
                                                    <option value="pending" <?= $reservation['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                                    <option value="confirmed" <?= $reservation['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                                                    <option value="cancelled" <?= $reservation['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                                    <option value="completed" <?= $reservation['status'] === 'completed' ? 'selected' : '' ?>>✨ Completed</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>

                                            <a href="reservations.php?view=<?= $reservation['id'] ?>" 
                                               class="w-full bg-gradient-to-r from-golden-500 to-golden-600 text-white px-6 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg text-center block">
                                                View Full Details
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

    <!-- Enhanced Reservation Details Modal -->
    <?php if ($reservation_details): ?>
        <div class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" id="reservation-modal">
            <div class="modal-content rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <!-- Modal Header -->
                <div class="sticky top-0 bg-gradient-to-r from-golden-500 to-golden-600 px-10 py-8 rounded-t-3xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mr-6 backdrop-blur-sm">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black text-white mb-2">Reservation Details</h3>
                                <p class="text-white/80 text-lg font-medium">Reservation #<?= $reservation_details['id'] ?> - <?= ucfirst($reservation_details['status']) ?></p>
                            </div>
                        </div>
                        <a href="reservations.php" class="text-white/80 hover:text-white p-2 hover:bg-white/10 rounded-xl transition-all duration-300" title="Close">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-10">
                    <!-- Customer Information -->
                    <div class="mb-10">
                        <h4 class="text-2xl font-black text-cafe-brown mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-3 text-golden-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Customer Information
                        </h4>
                        <div class="bg-gradient-to-br from-golden-50 to-golden-100 rounded-3xl p-8 border-2 border-golden-200/50">
                            <div class="grid md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Name:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= htmlspecialchars($reservation_details['name']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Email:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= htmlspecialchars($reservation_details['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Phone:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= htmlspecialchars($reservation_details['phone']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Guests:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= $reservation_details['guests'] ?> people</span>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Date:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= date('M j, Y', strtotime($reservation_details['date'])) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Time:</span>
                                        <span class="text-lg font-black text-cafe-brown"><?= date('g:i A', strtotime($reservation_details['time'])) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Status:</span>
                                        <span class="status-<?= $reservation_details['status'] ?> px-4 py-2 rounded-2xl text-lg font-black">
                                            <?= ucfirst($reservation_details['status']) ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-lg font-bold text-warm-gray">Created:</span>
                                        <span class="text-lg font-medium text-cafe-brown"><?= date('M j, Y g:i A', strtotime($reservation_details['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($reservation_details['message']): ?>
                            <div class="mt-6 pt-6 border-t-2 border-golden-200/50">
                                <div class="flex justify-between items-start">
                                    <span class="text-lg font-bold text-warm-gray">Special Notes:</span>
                                    <span class="text-lg font-medium text-cafe-brown text-right max-w-md"><?= htmlspecialchars($reservation_details['message']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-6 pt-8 border-t-2 border-golden-200/50">
                        <a href="reservations.php" 
                           class="flex-1 bg-warm-gray/20 text-warm-gray px-8 py-4 rounded-2xl font-black text-lg hover:bg-warm-gray/30 transition-all duration-300 transform hover:scale-105 text-center flex items-center justify-center space-x-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            <span>Back to Reservations</span>
                        </a>
                        <button onclick="window.print()" 
                                class="flex-1 bg-gradient-to-r from-golden-500 to-golden-600 text-white px-8 py-4 rounded-2xl font-black text-lg hover:shadow-2xl transition-all duration-300 transform hover:scale-105 shimmer-bg flex items-center justify-center space-x-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            <span>Print Reservation</span>
                        </button>
                    </div>
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
        function confirmStatusChange(selectElement) {
            const form = selectElement.form;
            const newStatus = selectElement.value;
            const reservationNumber = form.querySelector('input[name="reservation_id"]').value;

            if (confirm(`Are you sure you want to change reservation #${reservationNumber} status to "${newStatus}"?`)) {
                form.submit();
            } else {
                // Reset to previous value if cancelled
                selectElement.selectedIndex = 0;
            }
        }

        // Enhanced modal handling
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('reservation-modal')) {
                    window.location.href = 'reservations.php';
                }
            });

            // Close modal on background click
            const modal = document.getElementById('reservation-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        window.location.href = 'reservations.php';
                    }
                });
            }

            // Enhanced hover effects for reservation cards
            const reservationCards = document.querySelectorAll('.reservation-card');
            reservationCards.forEach(card => {
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
        ;
        document.head.appendChild(style);
    </script>
</body>
</html>