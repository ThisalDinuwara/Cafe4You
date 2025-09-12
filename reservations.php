<?php
// reservations.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle status update
    if (isset($_POST['action']) && $_POST['action'] === 'cancel_reservation') {
        $reservation_id = (int)$_POST['reservation_id'];
        $user_id = $_SESSION['user_id'];
        
        // Verify the reservation belongs to the current user
        $verify_query = "SELECT id FROM reservations WHERE id = ? AND user_id = ? AND status != 'cancelled'";
        $verify_stmt = $db->prepare($verify_query);
        $verify_stmt->execute([$reservation_id, $user_id]);
        
        if ($verify_stmt->fetch()) {
            $cancel_query = "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ?";
            $cancel_stmt = $db->prepare($cancel_query);
            if ($cancel_stmt->execute([$reservation_id, $user_id])) {
                showMessage('Reservation cancelled successfully.');
            } else {
                showMessage('Failed to cancel reservation. Please try again.', 'error');
            }
        } else {
            showMessage('Invalid reservation or already cancelled.', 'error');
        }
    } else {
        // Handle new reservation
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone']);
        $date = sanitize($_POST['date']);
        $time = sanitize($_POST['time']);
        $guests = (int)$_POST['guests'];
        $message = sanitize($_POST['message']);
        $table_number = isset($_POST['table_number']) ? (int)$_POST['table_number'] : 0;

        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        
        $errors = [];
        
        // Basic validation
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (empty($phone)) $errors[] = 'Phone is required';
        if (empty($date)) $errors[] = 'Date is required';
        if (empty($time)) $errors[] = 'Time is required';
        if ($table_number < 1 || $table_number > 15) $errors[] = 'Please select a valid table (1-15)';
        if ($guests < 1 || $guests > 20) $errors[] = 'Number of guests must be between 1 and 20';
        
        // Check if date is not in the past
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Reservation date cannot be in the past';
        }
        
        // Check table availability BEFORE attempting to insert
        if (empty($errors)) {
            $checkSql = "SELECT COUNT(*) FROM reservations 
                         WHERE date = ? AND time = ? AND table_number = ? AND status != 'cancelled'";
            $checkStmt = $db->prepare($checkSql);
            $checkStmt->execute([$date, $time, $table_number]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $errors[] = "Sorry, Table {$table_number} is already booked at {$time} on {$date}. Please select a different table or time.";
            }
        }
        
        // Only proceed with insertion if no errors
        if (empty($errors)) {
            $query = "INSERT INTO reservations (user_id, name, email, phone, date, time, table_number, guests, message, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$user_id, $name, $email, $phone, $date, $time, $table_number, $guests, $message])) {
                showMessage('Reservation request submitted successfully! We will contact you shortly to confirm.');
                // Clear form data
                $_POST = [];
            } else {
                $errors[] = 'Failed to submit reservation. Please try again.';
            }
        }
        
        if (!empty($errors)) {
            showMessage(implode('<br>', $errors), 'error');
        }
    }
}

// Get user info if logged in
$user = null;
$user_reservations = [];
if (isLoggedIn()) {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's reservations
    $reservations_query = "SELECT * FROM reservations WHERE user_id = ? ORDER BY date DESC, time DESC";
    $reservations_stmt = $db->prepare($reservations_query);
    $reservations_stmt->execute([$_SESSION['user_id']]);
    $user_reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'confirmed':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'cancelled':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'completed':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

function getStatusIcon($status) {
    switch (strtolower($status)) {
        case 'confirmed':
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        case 'pending':
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        case 'cancelled':
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
        case 'completed':
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        default:
            return '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    }
}

// Function to get available tables for a specific date and time
function getAvailableTables($db, $date, $time, $excludeReservationId = null) {
    $sql = "SELECT table_number FROM reservations 
            WHERE date = ? AND time = ? AND status != 'cancelled'";
    $params = [$date, $time];
    
    if ($excludeReservationId) {
        $sql .= " AND id != ?";
        $params[] = $excludeReservationId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookedTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $allTables = range(1, 15);
    $availableTables = array_diff($allTables, $bookedTables);
    
    return $availableTables;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - Cafe For You</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-yellow': '#FCD34D',
                        'brand-amber': '#F59E0B',
                        'brand-cream': '#FFF8F0',
                        'brand-brown': '#8B4513',
                        'brand-gray': '#F5F5F5'
                    },
                    fontFamily: {
                        'display': ['Georgia', 'serif'],
                        'body': ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        .card-shadow {
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
        }
        
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(252, 211, 77, 0.2);
            border-color: #FCD34D;
            transform: translateY(-2px);
        }
        
        .hero-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(252, 211, 77, 0.15) 1px, transparent 0);
            background-size: 20px 20px;
        }
        
        .floating-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hero-bg {
            background-image: url('images/The20deco20de81cor20CIRQA20.webp');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        
        @media (max-width: 768px) {
            .hero-bg {
                background-attachment: scroll;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .table-occupied {
            background-color: #fee2e2 !important;
            color: #dc2626 !important;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .table-available {
            background-color: #f0fdf4 !important;
            color: #166534 !important;
        }
    </style>
</head>
<body class="bg-brand-cream font-body">
    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md shadow-lg sticky top-0 z-50 border-b border-yellow-100">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-xl">C</span>
                    </div>
                    <h1 class="text-2xl font-bold bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">Cafe For You</h1>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Home</a>
                    <a href="menu.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Menu</a>
                    <a href="reservations.php" class="text-brand-yellow font-semibold relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-0.5 after:bg-brand-yellow">Reservations</a>
                    <a href="contact.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Contact</a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Orders</a>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Login</a>
                        <a href="register.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-6 py-2.5 rounded-full font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">Register</a>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <button class="md:hidden p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="relative hero-bg text-white py-20 overflow-hidden">
        <div class="hero-pattern absolute inset-0 opacity-20"></div>
        <div class="absolute top-10 left-10 w-6 h-6 bg-white/20 rounded-full animate-bounce"></div>
        <div class="absolute top-32 right-20 w-4 h-4 bg-yellow-400/30 rounded-full"></div>
        <div class="absolute bottom-20 left-1/4 w-3 h-3 bg-white/30 rounded-full"></div>
        
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div class="space-y-6">
                    <div class="inline-flex items-center bg-white/20 backdrop-blur-sm rounded-full px-6 py-2 text-sm font-medium mb-4">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                        </svg>
                        <span>Book Your Table</span>
                    </div>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-4">Reserve Your <span class="text-yellow-300">Perfect</span> Table</h1>
                    <p class="text-xl text-white/90 leading-relaxed">
                        Secure your spot for an unforgettable dining experience. We'll make sure everything is perfect for your visit.
                    </p>
                    
                    <!-- Features -->
                    <div class="grid grid-cols-2 gap-4 pt-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Instant Confirmation</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Flexible Timing</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Group Friendly</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Special Occasions</span>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Restaurant Interior Image -->
                <div class="relative">
                    <div class="bg-white/10 backdrop-blur-sm rounded-3xl p-8 border border-white/20">
                        <img src="images/for small squre.jpeg" 
                             alt="Restaurant Interior" 
                             class="w-full h-80 object-cover rounded-2xl">
                    </div>
                    
                    <!-- Floating availability card -->
                    <div class="absolute -bottom-6 -left-6 bg-white rounded-2xl p-4 shadow-xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Available Today</div>
                                <div class="text-xs text-gray-600">7:00 PM - 9:30 PM</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table capacity indicator -->
                    <div class="absolute -top-4 -right-4 bg-white rounded-2xl p-3 shadow-xl">
                        <div class="text-center">
                            <div class="text-lg font-bold text-brand-yellow">15</div>
                            <div class="text-xs text-gray-600">Tables</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- My Reservations Section (Only show if user is logged in) -->
    <?php if (isLoggedIn() && !empty($user_reservations)): ?>
    <section class="py-16 bg-gradient-to-br from-yellow-50 to-amber-50">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-800 mb-4">My Reservations</h2>
                <p class="text-gray-600">Track and manage your reservation status</p>
            </div>

            <div class="grid gap-6">
                <?php foreach ($user_reservations as $reservation): ?>
                <div class="bg-white rounded-2xl card-shadow hover-lift fade-in">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                            <!-- Reservation Info -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-4 mb-4">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-2xl font-bold text-gray-800">#<?= $reservation['id'] ?></span>
                                        <span class="text-sm text-gray-600">Table <?= $reservation['table_number'] ?></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="<?= getStatusColor($reservation['status']) ?> px-3 py-1 rounded-full text-xs font-semibold border flex items-center space-x-1">
                                            <?= getStatusIcon($reservation['status']) ?>
                                            <span><?= ucfirst($reservation['status']) ?></span>
                                        </span>
                                    </div>
                                </div>

                                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-600">Date</div>
                                            <div class="font-semibold"><?= date('M j, Y', strtotime($reservation['date'])) ?></div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-600">Time</div>
                                            <div class="font-semibold"><?= date('g:i A', strtotime($reservation['time'])) ?></div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-600">Guests</div>
                                            <div class="font-semibold"><?= $reservation['guests'] ?> <?= $reservation['guests'] == 1 ? 'Guest' : 'Guests' ?></div>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-600">Created</div>
                                            <div class="font-semibold"><?= date('M j', strtotime($reservation['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($reservation['message'])): ?>
                                <div class="mt-4 p-3 bg-gray-50 rounded-xl">
                                    <div class="text-sm font-medium text-gray-600 mb-1">Special Requests:</div>
                                    <div class="text-gray-800"><?= htmlspecialchars($reservation['message']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="flex flex-col space-y-2 lg:ml-6">
                                <?php if (in_array(strtolower($reservation['status']), ['pending', 'confirmed'])): ?>
                                    <?php 
                                    $reservationDateTime = strtotime($reservation['date'] . ' ' . $reservation['time']);
                                    $canCancel = $reservationDateTime > (time() + 2 * 3600); // Can cancel if more than 2 hours away
                                    ?>
                                    
                                    <?php if ($canCancel): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this reservation?');">
                                        <input type="hidden" name="action" value="cancel_reservation">
                                        <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                        <button type="submit" class="w-full lg:w-auto px-4 py-2 border border-red-300 text-red-600 rounded-xl hover:bg-red-50 transition-colors duration-300 text-sm font-medium">
                                            Cancel Reservation
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div class="text-xs text-gray-500 text-center lg:text-left">
                                        Cannot cancel<br>(less than 2hrs away)
                                    </div>
                                    <?php endif; ?>
                                    
                                    <a href="contact.php" class="w-full lg:w-auto px-4 py-2 bg-brand-yellow text-white rounded-xl hover:bg-brand-amber transition-colors duration-300 text-sm font-medium text-center">
                                        Modify Request
                                    </a>
                                <?php elseif (strtolower($reservation['status']) === 'cancelled'): ?>
                                    <div class="text-xs text-gray-500 text-center lg:text-left">
                                        Reservation Cancelled
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-gray-500 text-center lg:text-left">
                                        Reservation Completed
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Reservation Form -->
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-6">
            <?php displayMessage(); ?>
            
            <div class="grid lg:grid-cols-3 gap-12">
                <!-- Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl card-shadow p-8 hover-lift">
                        <div class="mb-8">
                            <h2 class="text-3xl font-bold text-gray-800 mb-2">Make a New Reservation</h2>
                            <p class="text-gray-600">Fill out the form below and we'll confirm your reservation within 24 hours.</p>
                        </div>
                        
                        <form method="POST" class="space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Full Name *</label>
                                    <input type="text" name="name" required 
                                           value="<?= htmlspecialchars($_POST['name'] ?? $user['full_name'] ?? '') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Email Address *</label>
                                    <input type="email" name="email" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50">
                                </div>
                            </div>
                            
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Phone Number *</label>
                                    <input type="tel" name="phone" required 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Number of Guests *</label>
                                    <select name="guests" required 
                                            class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50">
                                        <?php for ($i = 1; $i <= 20; $i++): ?>
                                            <option value="<?= $i ?>" <?= (isset($_POST['guests']) && $_POST['guests'] == $i) ? 'selected' : '' ?>>
                                                <?= $i ?> <?= $i == 1 ? 'Guest' : 'Guests' ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Preferred Date *</label>
                                    <input type="date" name="date" required
                                           value="<?= htmlspecialchars($_POST['date'] ?? '') ?>"
                                           min="<?= date('Y-m-d') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                           onchange="checkTableAvailability()">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Preferred Time *</label>
                                    <input type="time" name="time" required
                                           value="<?= htmlspecialchars($_POST['time'] ?? '') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                           onchange="checkTableAvailability()">
                                </div>
                                
                                <!-- Select Table -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select Table *</label>
                                    <select name="table_number" required id="table_select"
                                            class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50">
                                        <option value="">Choose a table...</option>
                                        <?php for ($i = 1; $i <= 15; $i++): ?>
                                            <option value="<?= $i ?>"
                                              <?= (isset($_POST['table_number']) && (int)$_POST['table_number'] === $i) ? 'selected' : '' ?>>
                                              Table <?= $i ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <div id="availability_message" class="mt-2 text-sm"></div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Special Requests</label>
                                <textarea name="message" rows="4" 
                                          class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                          placeholder="Any special requests, dietary requirements, or celebrations we should know about..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="w-full bg-gradient-to-r from-brand-yellow to-brand-amber text-white py-4 px-8 rounded-2xl font-semibold hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 text-lg">
                                <span class="flex items-center justify-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                    </svg>
                                    <span>Reserve Your Table</span>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Restaurant Info -->
                <div class="space-y-6">
                    <!-- Opening Hours -->
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Opening Hours</h3>
                        </div>
                        <div class="space-y-3 text-gray-600">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="font-medium">Monday - Thursday:</span>
                                <span class="text-brand-yellow font-semibold">11:00 AM - 10:00 PM</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <span class="font-medium">Friday - Saturday:</span>
                                <span class="text-brand-yellow font-semibold">11:00 AM - 11:00 PM</span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="font-medium">Sunday:</span>
                                <span class="text-brand-yellow font-semibold">12:00 PM - 9:00 PM</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Contact Info</h3>
                        </div>
                        <div class="space-y-4 text-gray-600">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-brand-yellow/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div>123 Restaurant Street</div>
                                    <div>City, State 12345</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reservation Policy -->
                    <div class="bg-gradient-to-br from-yellow-50 to-amber-50 rounded-3xl p-6 border border-yellow-100">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/20 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-gray-800">Reservation Policy</h4>
                        </div>
                        <ul class="text-sm text-gray-700 space-y-3">
                            <li class="flex items-start space-x-3">
                                <div class="w-1.5 h-1.5 bg-brand-yellow rounded-full mt-2"></div>
                                <span>Reservations are confirmed within 24 hours via email or phone</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <div class="w-1.5 h-1.5 bg-brand-yellow rounded-full mt-2"></div>
                                <span>Please arrive within 15 minutes of your reservation time</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <div class="w-1.5 h-1.5 bg-brand-yellow rounded-full mt-2"></div>
                                <span>Cancellations must be made at least 2 hours in advance</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <div class="w-1.5 h-1.5 bg-brand-yellow rounded-full mt-2"></div>
                                <span>Large parties (8+ guests) may require a deposit</span>
                            </li>
                            <li class="flex items-start space-x-3">
                                <div class="w-1.5 h-1.5 bg-brand-yellow rounded-full mt-2"></div>
                                <span>Each table can only be booked once per time slot</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Special Features -->
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Special Services</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl">
                                <span class="text-2xl">🎂</span>
                                <div>
                                    <div class="font-medium text-gray-800">Birthday Celebrations</div>
                                    <div class="text-sm text-gray-600">Complimentary dessert & decoration</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl">
                                <span class="text-2xl">💍</span>
                                <div>
                                    <div class="font-medium text-gray-800">Romantic Dining</div>
                                    <div class="text-sm text-gray-600">Private seating & candlelit ambiance</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl">
                                <span class="text-2xl">🏢</span>
                                <div>
                                    <div class="font-medium text-gray-800">Business Meetings</div>
                                    <div class="text-sm text-gray-600">Quiet areas & WiFi available</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-20 bg-brand-cream">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <div class="space-y-8">
                <h2 class="text-4xl font-bold text-gray-800">Need Help with Your <span class="text-brand-yellow">Reservation?</span></h2>
                <p class="text-xl text-gray-600">
                    Our friendly staff is here to help you plan the perfect dining experience. Call us directly for immediate assistance.
                </p>
                
                <div class="flex flex-wrap justify-center gap-6">
                    <a href="tel:+15551234567" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-8 py-4 rounded-full font-semibold hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span>Call Now</span>
                    </a>
                    <a href="mailto:reservations@cafeforyou.com" class="border-2 border-brand-yellow text-brand-yellow px-8 py-4 rounded-full font-semibold hover:bg-brand-yellow hover:text-white transition-all duration-300 flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span>Send Email</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-16">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div class="space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center">
                            <span class="text-white font-bold text-xl">C</span>
                        </div>
                        <h3 class="text-2xl font-bold">Cafe For You</h3>
                    </div>
                    <p class="text-gray-400 leading-relaxed">Experience fine dining at its best with our exquisite menu and exceptional service crafted with passion.</p>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Quick Links</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="menu.php" class="hover:text-white transition-colors duration-300 hover:text-brand-yellow">Menu</a></li>
                        <li><a href="reservations.php" class="hover:text-white transition-colors duration-300 hover:text-brand-yellow">Reservations</a></li>
                        <li><a href="contact.php" class="hover:text-white transition-colors duration-300 hover:text-brand-yellow">Contact</a></li>
                    </ul>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Contact Info</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>123 Restaurant Street</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <span class="ml-8">City, State 12345</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>