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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Admin Navigation -->
    <nav class="bg-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-orange-400">Admin Panel</h1>
                </div>
                
                <div class="flex items-center space-x-6">
                    <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition">View Site</a>
                    <a href="../logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg min-h-screen">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Dashboard</a>
                    <a href="orders.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Orders</a>
                    <a href="menu.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Menu Management</a>
                    <a href="categories.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Categories</a>
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-orange-600 font-medium">Reservations</a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Users</a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Contact Messages</a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Reservation Management</h1>
                <p class="text-gray-600 mt-2">Manage table reservations and bookings</p>
            </div>

            <?php displayMessage(); ?>

            <!-- Reservations Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reservations as $reservation): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($reservation['name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($reservation['email']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= date('M j, Y', strtotime($reservation['date'])) ?></div>
                                    <div class="text-sm text-gray-500"><?= date('g:i A', strtotime($reservation['time'])) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $reservation['guests'] ?> guests
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($reservation['phone']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" 
                                                class="text-sm border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500">
                                            <option value="pending" <?= $reservation['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= $reservation['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="cancelled" <?= $reservation['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="showDetails(<?= htmlspecialchars(json_encode($reservation)) ?>)" 
                                            class="text-orange-600 hover:text-orange-900">View Details</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Reservation Details Modal -->
    <div id="reservationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Reservation Details</h3>
                    <button onclick="hideModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="modalContent" class="space-y-3">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function showDetails(reservation) {
            const modal = document.getElementById('reservationModal');
            const content = document.getElementById('modalContent');
            
            const statusColors = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'confirmed': 'bg-green-100 text-green-800',
                'cancelled': 'bg-red-100 text-red-800'
            };
            
            content.innerHTML = `
                <p><strong>Name:</strong> ${reservation.name}</p>
                <p><strong>Email:</strong> ${reservation.email}</p>
                <p><strong>Phone:</strong> ${reservation.phone}</p>
                <p><strong>Date:</strong> ${new Date(reservation.date).toLocaleDateString()}</p>
                <p><strong>Time:</strong> ${new Date('2000-01-01 ' + reservation.time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                <p><strong>Guests:</strong> ${reservation.guests}</p>
                <p><strong>Status:</strong> <span class="px-2 py-1 text-xs font-semibold rounded-full ${statusColors[reservation.status]}">${reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1)}</span></p>
                <p><strong>Created:</strong> ${new Date(reservation.created_at).toLocaleString()}</p>
                ${reservation.message ? `<p><strong>Special Requests:</strong> ${reservation.message}</p>` : ''}
            `;
            
            modal.classList.remove('hidden');
        }
        
        function hideModal() {
            document.getElementById('reservationModal').classList.add('hidden');
        }
    </script>
</body>
</html>