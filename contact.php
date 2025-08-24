<?php
// contact.php
require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    $errors = [];
    
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($subject)) $errors[] = 'Subject is required';
    if (empty($message)) $errors[] = 'Message is required';
    
    if (empty($errors)) {
        $query = "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$name, $email, $subject, $message])) {
            showMessage('Thank you for your message! We will get back to you soon.');
            $_POST = []; // Clear form
        } else {
            $errors[] = 'Failed to send message. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        showMessage(implode('<br>', $errors), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Delicious Restaurant</title>
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
                    <a href="reservations.php" class="text-gray-700 hover:text-orange-600 transition">Reservations</a>
                    <a href="contact.php" class="text-orange-600 font-semibold">Contact</a>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-orange-600 transition">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-orange-600 transition">Orders</a>
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
            <h1 class="text-4xl font-bold mb-4">Contact Us</h1>
            <p class="text-xl">We'd love to hear from you</p>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4">
            <?php displayMessage(); ?>
            
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Contact Form -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Send us a Message</h2>
                    
                    <form method="POST" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                                <input type="text" name="name" required 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" required 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                            <input type="text" name="subject" required 
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                                   placeholder="What is this message about?">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                            <textarea name="message" rows="6" required 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-orange-500 focus:border-orange-500"
                                      placeholder="Your message..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="w-full bg-orange-600 text-white py-3 px-6 rounded-md font-semibold hover:bg-orange-700 transition">
                            Send Message
                        </button>
                    </form>
                </div>
                
                <!-- Contact Info -->
                <div class="space-y-8">
                    <div class="bg-white rounded-lg shadow-lg p-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Get in Touch</h3>
                        
                        <div class="space-y-6">
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Address</h4>
                                    <p class="text-gray-600">123 Restaurant Street<br>City, State 12345</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Phone</h4>
                                    <p class="text-gray-600">(555) 123-4567</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Email</h4>
                                    <p class="text-gray-600">info@delicious.com</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4">
                                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Hours</h4>
                                    <div class="text-gray-600">
                                        <p>Mon-Thu: 11am-10pm</p>
                                        <p>Fri-Sat: 11am-11pm</p>
                                        <p>Sunday: 12pm-9pm</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-orange-50 rounded-lg p-6">
                        <h4 class="font-semibold text-orange-800 mb-3">Quick Response</h4>
                        <p class="text-sm text-orange-700 mb-3">
                            We typically respond to messages within 24 hours. For urgent matters or same-day reservations, 
                            please call us directly.
                        </p>
                        <div class="flex space-x-4">
                            <a href="tel:5551234567" class="bg-orange-600 text-white px-4 py-2 rounded text-sm hover:bg-orange-700 transition">
                                Call Now
                            </a>
                            <a href="reservations.php" class="bg-white text-orange-600 border border-orange-600 px-4 py-2 rounded text-sm hover:bg-orange-50 transition">
                                Make Reservation
                            </a>
                        </div>
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