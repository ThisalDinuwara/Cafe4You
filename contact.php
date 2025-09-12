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
    <title>Contact - Cafe For You</title>
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

        .hero-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.3)), url('images/1727692351-a-group-of-friends-sit-at-a-table-laid-with-sharable-dishes-and-cocktails.avif');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }
        
        @media (max-width: 768px) {
            .hero-bg {
                background-attachment: scroll;
            }
        }
        
        .floating-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .contact-icon {
            background: linear-gradient(135deg, #FCD34D, #F59E0B);
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
                    <a href="reservations.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Reservations</a>
                    <a href="contact.php" class="text-brand-yellow font-semibold relative after:absolute after:bottom-0 after:left-0 after:w-full after:h-0.5 after:bg-brand-yellow">Contact</a>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="cart.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Cart</a>
                        <a href="orders.php" class="text-gray-700 hover:text-brand-yellow transition-all duration-300 font-medium relative after:absolute after:bottom-0 after:left-0 after:w-0 after:h-0.5 after:bg-brand-yellow after:transition-all after:duration-300 hover:after:w-full">Orders</a>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <span>Get In Touch</span>
                    </div>
                    <h1 class="text-5xl lg:text-6xl font-bold mb-4">Let's Start a <span class="text-yellow-300">Conversation</span></h1>
                    <p class="text-xl text-white/90 leading-relaxed">
                        We'd love to hear from you! Whether you have questions, feedback, or just want to say hello, we're here to help.
                    </p>
                    
                    <!-- Features -->
                    <div class="grid grid-cols-2 gap-4 pt-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Quick Response</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Friendly Staff</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">24/7 Support</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <span class="text-sm">Multiple Channels</span>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Contact Image -->
                <div class="relative">
                    <div class="bg-white/10 backdrop-blur-sm rounded-3xl p-8 border border-white/20">
                        <img src="images/happy-waitress-and-crew-of-professional-cooks-posing-at-restaurant-JW1R62.jpg" 
                             alt="Friendly Restaurant Staff" 
                             class="w-full h-80 object-cover rounded-2xl">
                    </div>
                    
                    <!-- Floating response card -->
                    <div class="absolute -bottom-6 -left-6 bg-white rounded-2xl p-4 shadow-xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Quick Response</div>
                                <div class="text-xs text-gray-600">Usually within 2 hours</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Service rating indicator -->
                    <div class="absolute -top-4 -right-4 bg-white rounded-2xl p-3 shadow-xl">
                        <div class="text-center">
                            <div class="text-lg font-bold text-brand-yellow">5.0★</div>
                            <div class="text-xs text-gray-600">Service</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-20 bg-white">
        <div class="max-w-6xl mx-auto px-6">
            <?php displayMessage(); ?>
            
            <div class="grid lg:grid-cols-3 gap-12">
                <!-- Contact Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl card-shadow p-8 hover-lift">
                        <div class="mb-8">
                            <h2 class="text-3xl font-bold text-gray-800 mb-2">Send us a Message</h2>
                            <p class="text-gray-600">Fill out the form below and we'll get back to you as soon as possible.</p>
                        </div>
                        
                        <form method="POST" class="space-y-6">
                            <div class="grid md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Full Name *</label>
                                    <input type="text" name="name" required 
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                           placeholder="Your full name">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-3">Email Address *</label>
                                    <input type="email" name="email" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                           class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                           placeholder="your@email.com">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Subject *</label>
                                <input type="text" name="subject" required 
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                       class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                       placeholder="What is this message about?">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Message *</label>
                                <textarea name="message" rows="6" required 
                                          class="form-input w-full px-4 py-3 border border-gray-200 rounded-2xl focus:outline-none bg-gray-50"
                                          placeholder="Tell us more about your message..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="w-full bg-gradient-to-r from-brand-yellow to-brand-amber text-white py-4 px-8 rounded-2xl font-semibold hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 text-lg">
                                <span class="flex items-center justify-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                    <span>Send Message</span>
                                </span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div class="space-y-6">
                    <!-- Get in Touch -->
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-6">
                            <div class="w-10 h-10 contact-icon rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Visit Us</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-xl">
                                <div class="w-8 h-8 bg-brand-yellow/10 rounded-full flex items-center justify-center mt-1">
                                    <svg class="w-4 h-4 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 text-sm">Address</h4>
                                    <p class="text-gray-600 text-sm">123 Restaurant Street<br>City, State 12345</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-xl">
                                <div class="w-8 h-8 bg-brand-yellow/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 text-sm">Phone</h4>
                                    <a href="tel:+15551234567" class="text-brand-yellow font-medium text-sm hover:underline">(555) 123-4567</a>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-xl">
                                <div class="w-8 h-8 bg-brand-yellow/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 text-sm">Email</h4>
                                    <a href="mailto:info@cafeforyou.com" class="text-brand-yellow font-medium text-sm hover:underline">info@cafeforyou.com</a>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-xl">
                                <div class="w-8 h-8 bg-brand-yellow/10 rounded-full flex items-center justify-center mt-1">
                                    <svg class="w-4 h-4 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 text-sm">Hours</h4>
                                    <div class="text-gray-600 text-sm">
                                        <p>Mon-Thu: 11am-10pm</p>
                                        <p>Fri-Sat: 11am-11pm</p>
                                        <p>Sunday: 12pm-9pm</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Response -->
                    <div class="bg-gradient-to-br from-yellow-50 to-amber-50 rounded-3xl p-6 border border-yellow-100">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/20 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <h4 class="font-bold text-gray-800">Quick Response</h4>
                        </div>
                        <p class="text-sm text-gray-700 mb-4">
                            We typically respond to messages within 2-4 hours during business hours. For urgent matters or same-day reservations, please call us directly.
                        </p>
                        <div class="flex flex-col space-y-3">
                            <a href="tel:+15551234567" class="bg-brand-yellow text-white px-4 py-3 rounded-xl text-sm font-semibold hover:bg-brand-amber transition-all duration-300 text-center flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                <span>Call Now</span>
                            </a>
                            <a href="reservations.php" class="bg-white text-brand-yellow border-2 border-brand-yellow px-4 py-3 rounded-xl text-sm font-semibold hover:bg-yellow-50 transition-all duration-300 text-center flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                                <span>Make Reservation</span>
                            </a>
                        </div>
                    </div>

                    <!-- FAQ -->
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800">Common Questions</h3>
                        </div>
                        <div class="space-y-3">
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <div class="font-medium text-gray-800 text-sm mb-1">Do you take walk-ins?</div>
                                <div class="text-xs text-gray-600">Yes! But we recommend reservations for guaranteed seating.</div>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <div class="font-medium text-gray-800 text-sm mb-1">Private event space?</div>
                                <div class="text-xs text-gray-600">We offer private dining rooms for groups of 15+.</div>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <div class="font-medium text-gray-800 text-sm mb-1">Dietary restrictions?</div>
                                <div class="text-xs text-gray-600">We accommodate most dietary needs with advance notice.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="py-20 bg-brand-cream">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Find <span class="text-brand-yellow">Our Location</span></h2>
                <p class="text-xl text-gray-600">We're conveniently located in the heart of the city</p>
            </div>
            
            <div class="grid lg:grid-cols-3 gap-8">
                <!-- Map Placeholder -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl card-shadow overflow-hidden hover-lift">
                        <div class="h-96 relative">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3022.9663095343008!2d-74.00425878459418!3d40.74844097932681!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c259a9b3117469%3A0xd134e199a405a163!2sEmpire%20State%20Building!5e0!3m2!1sen!2sus!4v1609459728023!5m2!1sen!2sus"
                                width="100%" 
                                height="100%" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                class="rounded-3xl">
                            </iframe>
                            
                            <!-- Map overlay with contact info -->
                            <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-sm rounded-xl p-4 shadow-lg max-w-xs">
                                <div class="flex items-center space-x-3 mb-2">
                                    <div class="w-8 h-8 bg-brand-yellow rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="font-bold text-gray-800 text-sm">Cafe For You</h4>
                                </div>
                                <p class="text-xs text-gray-600 leading-relaxed">123 Restaurant Street<br>City, State 12345</p>
                                <div class="flex items-center space-x-2 mt-2">
                                    <span class="text-xs text-brand-yellow font-semibold">Open Now</span>
                                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                </div>
                            </div>

                            <!-- Directions button -->
                            <div class="absolute bottom-4 right-4">
                                <a href="https://www.google.com/maps/dir/?api=1&destination=Empire+State+Building,New+York,NY" 
                                   target="_blank" 
                                   class="bg-brand-yellow text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-brand-amber transition-all duration-300 shadow-lg flex items-center space-x-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                    <span>Directions</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Location Details -->
                <div class="space-y-6">
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800">Directions</h3>
                        </div>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 bg-brand-yellow rounded-full"></span>
                                <span>5 min walk from Central Station</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 bg-brand-yellow rounded-full"></span>
                                <span>Street parking available</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="w-2 h-2 bg-brand-yellow rounded-full"></span>
                                <span>Valet service on weekends</span>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="https://maps.google.com" target="_blank" class="bg-brand-yellow text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-brand-amber transition-all duration-300 inline-flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                <span>Get Directions</span>
                            </a>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-3xl card-shadow p-6 hover-lift">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-brand-yellow/10 rounded-2xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800">Parking</h3>
                        </div>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Street Parking</span>
                                <span class="text-green-600 font-semibold">Free</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Garage (2 blocks)</span>
                                <span class="text-gray-800 font-semibold">$5/hr</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Valet (Weekends)</span>
                                <span class="text-gray-800 font-semibold">$15</span>
                            </div>
                        </div>
                    </div>
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
                            </svg>
                            <span>(555) 123-4567</span>
                        </li>
                        <li class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span>info@cafeforyou.com</span>
                        </li>
                    </ul>
                </div>
                
                <div class="space-y-4">
                    <h4 class="text-lg font-semibold">Hours</h4>
                    <ul class="space-y-2 text-gray-400 text-sm">
                        <li class="flex justify-between">
                            <span>Monday - Thursday:</span>
                            <span class="text-white">11am - 10pm</span>
                        </li>
                        <li class="flex justify-between">
                            <span>Friday - Saturday:</span>
                            <span class="text-white">11am - 11pm</span>
                        </li>
                        <li class="flex justify-between">
                            <span>Sunday:</span>
                            <span class="text-white">12pm - 9pm</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                    <div class="text-gray-400 text-center md:text-left">
                        <p>&copy; 2025 Cafe For You. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect for navbar
        window.addEventListener('scroll', function() {
            const nav = document.querySelector('nav');
            if (window.scrollY > 100) {
                nav.classList.add('bg-white/95');
            } else {
                nav.classList.remove('bg-white/95');
            }
        });

        // Intersection Observer for animations
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe elements for animation
            document.querySelectorAll('.hover-lift').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(el);
            });
        });

        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                    
                    // Simple validation feedback
                    if (this.hasAttribute('required') && !this.value.trim()) {
                        this.classList.add('border-red-300');
                    } else {
                        this.classList.remove('border-red-300');
                        this.classList.add('border-green-300');
                    }
                });
            });

            // Real-time validation
            const emailInput = document.querySelector('input[type="email"]');
            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (emailRegex.test(this.value)) {
                        this.classList.remove('border-red-300');
                        this.classList.add('border-green-300');
                    } else if (this.value) {
                        this.classList.remove('border-green-300');
                        this.classList.add('border-red-300');
                    }
                });
            }
        });
    </script>
</body>
</html>