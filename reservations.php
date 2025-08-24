<?php
// reservations.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $date = sanitize($_POST['date']);
    $time = sanitize($_POST['time']);
    $guests = (int)$_POST['guests'];
    $message = sanitize($_POST['message']);
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    
    $errors = [];
    
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($date)) $errors[] = 'Date is required';
    if (empty($time)) $errors[] = 'Time is required';
    if ($guests < 1 || $guests > 20) $errors[] = 'Number of guests must be between 1 and 20';
    
    // Check if date is not in the past
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Reservation date cannot be in the past';
    }
    
    if (empty($errors)) {
        $query = "INSERT INTO reservations (user_id, name, email, phone, date, time, guests, message) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$user_id, $name, $email, $phone, $date, $time, $guests, $message])) {
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

// Get user info if logged in
$user = null;
if (isLoggedIn()) {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - Delicious Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-orange-600">Delicious</h1>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="index.php" class="text-gray-700 hover:text-orange-600 transition">Home</a>
                    <a href="menu.php" class="text-gray-700 hover:text-orange-600 transition">Menu</a>
                    <a href="reservations.php" class="text-orange-600 font-semibold">Reservations</a>
                    <a href="contact.php" class="text-gray-700 hover:text-orange-600 transition">Contact</a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-orange-600 transition">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-orange-600 transition">Orders</a>
                        <?php if (isAdmin()): ?>
                            <a href="admin/dashboard.php" class="text-gray-700 hover:text-orange-600 transition">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-700 hover:text-orange-600 transition">Login</a>
                        <a href="register.php" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="bg-gradient-to-r from-orange-500 to-red-600 text-white py-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h1 class="text-4xl font-bold mb-4">Make a Reservation</h1>
            <p class="text-xl">Reserve your table for an unforgettable dining experience</p>
        </div>
    </section>

    <!-- Reservation Form -->
    <section class="py-16">
        <div class="max-w-4xl mx-auto px-4">
            <?php displayMessage(); ?>
            
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Form -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Reservation Details</h2>
                    
                    <form method="POST" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                                <input type="text" name="name" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? $user['full_name'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                                <input type="tel" name="phone" required 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Number of Guests *</label>
                                <select name="guests" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                        <option value="<?= $i ?>" <?= (isset($_POST['guests']) && $_POST['guests'] == $i) ? 'selected' : '' ?>>
                                            <?= $i ?> <?= $i == 1 ? 'Guest' : 'Guests' ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Date *</label>
                                <input type="date" name="date" required 
                                       value="<?= htmlspecialchars($_POST['date'] ?? '') ?>"
                                       min="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Time *</label>
                                <select name="time" required 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">Select Time</option>
                                    <?php
                                    $times = ['11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00'];
                                    foreach ($times as $time):
                                    ?>
                                        <option value="<?= $time ?>" <?= (isset($_POST['time']) && $_POST['time'] == $time) ? 'selected' : '' ?>>
                                            <?= date('g:i A', strtotime($time)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Special Requests</label>
                            <textarea name="message" rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                                      placeholder="Any special requests or dietary requirements..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="w-full bg-orange-600 text-white py-3 px-6 rounded-md font-semibold hover:bg-orange-700 transition">
                            Submit Reservation
                        </button>
                    </form>
                </div>
                
                <!-- Restaurant Info -->
                <div class="space-y-8">
                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Restaurant Hours</h3>
                        <div class="space-y-2 text-gray-600">
                            <div class="flex justify-between">
                                <span>Monday - Thursday:</span>
                                <span>11:00 AM - 10:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Friday - Saturday:</span>
                                <span>11:00 AM - 11:00 PM</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Sunday:</span>
                                <span>12:00 PM - 9:00 PM</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Contact Information</h3>
                        <div class="space-y-3 text-gray-600">
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                </svg>
                                <span>(555) 123-4567</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                <span>reservations@delicious.com</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                </svg>
                                <span>123 Restaurant Street<br>City, State 12345</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 rounded-lg p-6">
                        <h4 class="font-semibold text-orange-800 mb-2">Reservation Policy</h4>
                        <ul class="text-sm text-orange-700 space-y-1">
                            <li>• Reservations are confirmed within 24 hours</li>
                            <li>• Please arrive within 15 minutes of your reservation time</li>
                            <li>• Cancellations must be made 2 hours in advance</li>
                            <li>• Large parties (8+) may require a deposit</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p>&copy; 2025 Delicious Restaurant. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>