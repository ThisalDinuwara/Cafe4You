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
                        'brand-orange': '#FF6B35',
                        'brand-cream': '#FFF8F0'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-brand-orange rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <h1 class="text-2xl font-bold text-brand-orange">Cafe For You - Admin</h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition">View Site</a>
                    <a href="../logout.php" class="bg-brand-orange text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg min-h-screen">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📊 Dashboard
                    </a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        🛒 Orders
                    </a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        🍽️ Menu Management
                    </a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📂 Categories
                    </a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-brand-orange font-medium">
                        📅 Reservations
                    </a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        👥 Users
                    </a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                        📧 Messages
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Header Section -->
            <div class="mb-8">
                <h1 class="text-4xl font-bold text-gray-800">📅 Reservation Management</h1>
                <p class="text-gray-600 mt-2">Manage table reservations and track customer bookings</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid md:grid-cols-4 gap-6 mb-8">
                <!-- Total Reservations Card -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm">Total Reservations</p>
                            <p class="text-3xl font-bold"><?= $stats['total_reservations'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 3H18V1H16V3H8V1H6V3H5C3.89 3 3.01 3.9 3.01 5L3 19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V8H19V19ZM7 10H12V15H7V10Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Guests Card -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm">Total Guests</p>
                            <p class="text-3xl font-bold"><?= $stats['total_guests'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Today's Reservations Card -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm">Today's Bookings</p>
                            <p class="text-3xl font-bold"><?= $stats['today_reservations'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L13.09 8.26L22 9L17 14L18.18 21L12 17.77L5.82 21L7 14L2 9L8.91 8.26L12 2Z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Average Party Size Card -->
                <div class="bg-gradient-to-r from-brand-orange to-yellow-500 rounded-xl shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm">Avg Party Size</p>
                            <p class="text-3xl font-bold"><?= number_format($stats['avg_party_size'], 1) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16 4C18.2 4 20 5.8 20 8C20 10.2 18.2 12 16 12C13.8 12 12 10.2 12 8C12 5.8 13.8 4 16 4ZM8 4C10.2 4 12 5.8 12 8C12 10.2 10.2 12 8 12C5.8 12 4 10.2 4 8C4 5.8 5.8 4 8 4ZM8 13C10.67 13 16 14.34 16 17V20H0V17C0 14.34 5.33 13 8 13ZM16 13C18.67 13 24 14.34 24 17V20H18V17C18 15.36 17.36 14.14 16.43 13.27C17.6 13.1 18.96 13 20.5 13C21.54 13 22.8 13.16 24 13.45V17H22V20H24V17C24 14.34 21.33 13 18.5 13H16Z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Overview -->
            <div class="grid md:grid-cols-4 gap-4 mb-8">
                <?php
                $status_colors = [
                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                    'confirmed' => 'bg-green-100 text-green-800 border-green-300',
                    'cancelled' => 'bg-red-100 text-red-800 border-red-300',
                    'completed' => 'bg-gray-100 text-gray-800 border-gray-300'
                ];
                
                foreach (['pending', 'confirmed', 'cancelled', 'completed'] as $status):
                    $count = $status_counts[$status] ?? 0;
                ?>
                    <div class="bg-white rounded-lg shadow-md p-4 border-l-4 <?= $status_colors[$status] ?>">
                        <h3 class="text-sm font-medium text-gray-500 capitalize"><?= $status ?></h3>
                        <p class="text-2xl font-bold text-gray-900"><?= $count ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php displayMessage(); ?>

            <!-- Reservations Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100">
                <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-brand-orange to-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-white">📅 Table Reservations</h3>
                            <p class="text-orange-100 text-sm">Manage customer table bookings (<?= count($reservations) ?> reservations)</p>
                        </div>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                            <span class="text-white font-bold text-lg"><?= count($reservations) ?></span>
                            <span class="text-orange-100 text-sm block">Total Bookings</span>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Reservation ID</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Party Size</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($reservations as $reservation): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center mr-3">
                                                <span class="text-white font-bold text-sm">#<?= $reservation['id'] ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <span class="text-blue-600 font-bold text-sm"><?= strtoupper(substr($reservation['name'], 0, 2)) ?></span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($reservation['name']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($reservation['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?= date('M j, Y', strtotime($reservation['date'])) ?></div>
                                        <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($reservation['time'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-sm font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                            <?= $reservation['guests'] ?> guests
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($reservation['phone']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($reservation['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                            <select name="status" onchange="confirmStatusChange(this)" 
                                                    class="text-sm border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-orange focus:border-transparent p-2 font-semibold <?php
                                                    echo match($reservation['status']) {
                                                        'pending' => 'bg-yellow-50 text-yellow-800',
                                                        'confirmed' => 'bg-green-50 text-green-800',
                                                        'cancelled' => 'bg-red-50 text-red-800',
                                                        'completed' => 'bg-gray-50 text-gray-800',
                                                        default => 'bg-gray-50 text-gray-800'
                                                    };
                                                    ?>">
                                                <option value="pending" <?= $reservation['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                                <option value="confirmed" <?= $reservation['status'] === 'confirmed' ? 'selected' : '' ?>>✅ Confirmed</option>
                                                <option value="cancelled" <?= $reservation['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                                <option value="completed" <?= $reservation['status'] === 'completed' ? 'selected' : '' ?>>✨ Completed</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="reservations.php?view=<?= $reservation['id'] ?>" 
                                           class="bg-brand-orange text-white px-4 py-2 rounded-lg font-semibold hover:bg-orange-600 transition-all duration-300 inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($reservations)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No Reservations Yet</h3>
                    <p class="text-gray-500">Customer reservations will appear here once they start booking tables.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Enhanced Reservation Details Modal -->
    <?php if ($reservation_details): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm overflow-y-auto h-full w-full z-50 flex items-center justify-center" id="reservation-modal">
            <div class="relative mx-4 p-0 w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl rounded-2xl bg-white">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-brand-orange to-red-500 px-8 py-6 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-2xl font-bold text-white">📅 Reservation Details</h3>
                            <p class="text-orange-100">Reservation #<?= $reservation_details['id'] ?></p>
                        </div>
                        <a href="reservations.php" class="text-white hover:text-orange-200 transition-colors">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-8">
                    <!-- Customer Information -->
                    <div class="mb-8">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-orange" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 1H5C3.9 1 3 1.9 3 3V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V9M19 21H5V3H13V9H19Z"/>
                            </svg>
                            Customer Information
                        </h4>
                        <div class="bg-gray-50 rounded-xl p-6 space-y-3">
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Name:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($reservation_details['name']) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Email:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($reservation_details['email']) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Phone:</span>
                                <span class="text-gray-900"><?= htmlspecialchars($reservation_details['phone']) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Date:</span>
                                <span class="text-gray-900"><?= date('M j, Y', strtotime($reservation_details['date'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Time:</span>
                                <span class="text-gray-900"><?= date('g:i A', strtotime($reservation_details['time'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Guests:</span>
                                <span class="text-gray-900"><?= $reservation_details['guests'] ?> people</span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Status:</span>
                                <span class="px-3 py-1 rounded-full text-sm font-bold <?php
                                    echo match($reservation_details['status']) {
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'confirmed' => 'bg-green-100 text-green-800',
                                        'cancelled' => 'bg-red-100 text-red-800',
                                        'completed' => 'bg-gray-100 text-gray-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                    ?>">
                                    <?= ucfirst($reservation_details['status']) ?>
                                </span>
                            </div>
                            <div class="flex items-center">
                                <span class="font-semibold text-gray-700 w-24">Created:</span>
                                <span class="text-gray-900"><?= date('M j, Y g:i A', strtotime($reservation_details['created_at'])) ?></span>
                            </div>
                            <?php if ($reservation_details['message']): ?>
                            <div class="flex">
                                <span class="font-semibold text-gray-700 w-24">Notes:</span>
                                <span class="text-gray-900 flex-1"><?= htmlspecialchars($reservation_details['message']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-4 mt-8 pt-6 border-t border-gray-200">
                        <a href="reservations.php" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold hover:bg-gray-300 transition-all duration-300 text-center">
                            Close Details
                        </a>
                        <button onclick="window.print()" class="flex-1 bg-gradient-to-r from-brand-orange to-red-500 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300">
                            Print Reservation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
        
        // Auto-hide success messages
        setTimeout(function() {
            const successMessages = document.querySelectorAll('.bg-green-500');
            successMessages.forEach(function(message) {
                message.style.transition = 'opacity 0.5s ease-out';
                message.style.opacity = '0';
                setTimeout(function() {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            });
        }, 4000);
        
        // Add hover effects to table rows
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(4px)';
                    this.style.boxShadow = '4px 0 8px rgba(255, 107, 53, 0.1)';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                    this.style.boxShadow = 'none';
                });
            });
            
            // Add gradient cards hover effects
            const gradientCards = document.querySelectorAll('.bg-gradient-to-r');
            gradientCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'transform 0.2s ease-in-out';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Add click-to-copy functionality for reservation IDs
        function copyReservationId(reservationId) {
            navigator.clipboard.writeText('Reservation #' + reservationId).then(function() {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                toast.textContent = 'Reservation ID copied to clipboard!';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 2000);
            });
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('reservation-modal');
            if (modal && event.target === modal) {
                window.location.href = 'reservations.php';
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('reservation-modal');
                if (modal) {
                    window.location.href = 'reservations.php';
                }
            }
        });
        
        // Add real-time clock for current time display
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            const dateString = now.toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
            
            // Update any clock elements if they exist
            const clockElements = document.querySelectorAll('.current-time');
            clockElements.forEach(element => {
                element.textContent = timeString;
            });
            
            const dateElements = document.querySelectorAll('.current-date');
            dateElements.forEach(element => {
                element.textContent = dateString;
            });
        }
        
        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call
        
        // Enhanced table interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add status change animations
            const statusSelects = document.querySelectorAll('select[name="status"]');
            statusSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
            
            // Add loading state for form submissions
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = form.querySelector('select');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.6';
                    }
                });
            });
        });
        
        // Add notification system for status updates
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 
                type === 'error' ? 'bg-red-500 text-white' : 
                'bg-blue-500 text-white'
            }`;
            notification.textContent = message;
            
            // Add to DOM
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.style.transform = 'translateX(400px)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }
        
        // Add reservation count updates
        function updateReservationCounts() {
            // This could be enhanced with AJAX calls to get real-time updates
            // For now, it's a placeholder for future enhancement
        }
    </script>

    <style>
        /* Additional custom styles for enhanced UX */
        .table-row-hover {
            transition: all 0.2s ease-in-out;
        }
        
        .table-row-hover:hover {
            transform: translateX(4px);
            box-shadow: 4px 0 8px rgba(255, 107, 53, 0.1);
        }
        
        .status-badge {
            transition: all 0.2s ease-in-out;
        }
        
        .status-badge:hover {
            transform: scale(1.05);
        }
        
        .gradient-card {
            transition: transform 0.2s ease-in-out;
        }
        
        .gradient-card:hover {
            transform: translateY(-2px);
        }
        
        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            #reservation-modal, #reservation-modal * {
                visibility: visible;
            }
            
            #reservation-modal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Enhanced modal animations */
        #reservation-modal {
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Custom scrollbar for modal */
        #reservation-modal::-webkit-scrollbar {
            width: 8px;
        }
        
        #reservation-modal::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        #reservation-modal::-webkit-scrollbar-thumb {
            background: #FF6B35;
            border-radius: 10px;
        }
        
        #reservation-modal::-webkit-scrollbar-thumb:hover {
            background: #e55a2b;
        }
    </style>
</body>
</html>