<?php
// register.php - Fixed version
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use safe method to get POST values
    $username = sanitize(getPostValue('username'));
    $email = sanitize(getPostValue('email'));
    $full_name = sanitize(getPostValue('full_name'));
    $phone = sanitize(getPostValue('phone'));
    $address = sanitize(getPostValue('address'));
    $password = getPostValue('password');
    $confirm_password = getPostValue('confirm_password');
    
    $errors = [];
    
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($password)) $errors[] = 'Password is required';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    
    if (empty($errors)) {
        // Check if username or email exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->fetch()) {
            $errors[] = 'Username or email already exists';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO users (username, email, full_name, phone, address, password) VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $db->prepare($insert_query);
            
            if ($insert_stmt->execute([$username, $email, $full_name, $phone, $address, $hashed_password])) {
                showMessage('Account created successfully! Please login.');
                redirect('login.php');
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
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
    <title>Register - Cafe For You</title>
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
        
        .hero-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(252, 211, 77, 0.15) 1px, transparent 0);
            background-size: 20px 20px;
        }
        
        .floating-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(252, 211, 77, 0.2);
            border-color: #FCD34D;
        }
        
        .register-gradient {
            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
        }
    </style>
</head>
<body class="bg-brand-cream font-body">
    <!-- Background decorative elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="hero-pattern absolute inset-0 opacity-30"></div>
        <div class="absolute top-20 left-20 w-6 h-6 bg-brand-yellow/20 rounded-full animate-bounce"></div>
        <div class="absolute top-40 right-32 w-8 h-8 bg-yellow-400/30 rounded-full"></div>
        <div class="absolute bottom-32 left-16 w-4 h-4 bg-brand-amber/20 rounded-full"></div>
        <div class="absolute top-60 left-1/4 w-3 h-3 bg-yellow-300/25 rounded-full"></div>
        <div class="absolute bottom-20 right-1/4 w-5 h-5 bg-brand-yellow/15 rounded-full"></div>
        <div class="absolute top-32 right-16 w-5 h-5 bg-yellow-200/20 rounded-full"></div>
    </div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-lg w-full space-y-8">
            <!-- Logo Section -->
            <div class="text-center">
                <div class="flex items-center justify-center space-x-4 mb-8">
                    <div class="w-16 h-16 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-2xl">C</span>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">Cafe For You</h1>
                        <p class="text-sm text-gray-600">Join our community!</p>
                    </div>
                </div>
                <h2 class="text-4xl font-bold text-gray-900 mb-2">Create your account</h2>
                <p class="text-gray-600">
                    Already have an account?
                    <a href="login.php" class="font-semibold text-brand-yellow hover:text-brand-amber transition-colors duration-300">Sign in here</a>
                </p>
            </div>
            
            <!-- Registration Form Card -->
            <div class="floating-card rounded-3xl shadow-2xl p-8 border border-yellow-100">
                <?php displayMessage(); ?>
                
                <form class="space-y-6" method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Username -->
                        <div class="md:col-span-2">
                            <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <input id="username" name="username" type="text" required 
                                       value="<?= htmlspecialchars(getPostValue('username')) ?>"
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Choose a username">
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div class="md:col-span-2">
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                    </svg>
                                </div>
                                <input id="email" name="email" type="email" required 
                                       value="<?= htmlspecialchars(getPostValue('email')) ?>"
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Enter your email">
                            </div>
                        </div>
                        
                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label for="full_name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <input id="full_name" name="full_name" type="text" required 
                                       value="<?= htmlspecialchars(getPostValue('full_name')) ?>"
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Enter your full name">
                            </div>
                        </div>
                        
                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                </div>
                                <input id="phone" name="phone" type="tel" 
                                       value="<?= htmlspecialchars(getPostValue('phone')) ?>"
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Phone number">
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div>
                            <label for="address" class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <div class="relative">
                                <div class="absolute top-3 left-0 pl-4 flex items-start pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                                <textarea id="address" name="address" rows="3"
                                          class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium resize-none" 
                                          placeholder="Your address"><?= htmlspecialchars(getPostValue('address')) ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input id="password" name="password" type="password" required 
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Create password">
                            </div>
                        </div>
                        
                        <!-- Confirm Password -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <input id="confirm_password" name="confirm_password" type="password" required 
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Confirm password">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4">
                        <button type="submit" class="w-full register-gradient text-white py-4 px-6 rounded-2xl font-bold text-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                            </svg>
                            <span>Create Account</span>
                        </button>
                        
                        <div class="text-center">
                            <a href="index.php" class="text-brand-yellow hover:text-brand-amber font-semibold transition-colors duration-300 flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                <span>Back to Home</span>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Features -->
            <div class="grid grid-cols-3 gap-4 mt-12">
                <div class="text-center">
                    <div class="w-12 h-12 bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3 border border-yellow-100">
                        <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Safe & Secure</h3>
                    <p class="text-xs text-gray-600 mt-1">Your data is protected</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3 border border-yellow-100">
                        <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Quick Setup</h3>
                    <p class="text-xs text-gray-600 mt-1">Easy registration</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3 border border-yellow-100">
                        <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Join Community</h3>
                    <p class="text-xs text-gray-600 mt-1">Be part of our family</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus effects to inputs
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    const svg = this.closest('div').querySelector('svg');
                    if (svg) {
                        svg.classList.remove('text-gray-400');
                        svg.classList.add('text-brand-yellow');
                    }
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        const svg = this.closest('div').querySelector('svg');
                        if (svg) {
                            svg.classList.remove('text-brand-yellow');
                            svg.classList.add('text-gray-400');
                        }
                    }
                });
            });

            // Add floating animation to decorative elements
            const floatingElements = document.querySelectorAll('.absolute');
            floatingElements.forEach((element, index) => {
                if (element.classList.contains('rounded-full')) {
                    element.style.animation = `float ${3 + index}s ease-in-out infinite`;
                    element.style.animationDelay = `${index * 0.5}s`;
                }
            });

            // Password confirmation validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePasswords() {
                if (password.value && confirmPassword.value) {
                    if (password.value === confirmPassword.value) {
                        confirmPassword.classList.remove('border-red-300');
                        confirmPassword.classList.add('border-green-300');
                    } else {
                        confirmPassword.classList.remove('border-green-300');
                        confirmPassword.classList.add('border-red-300');
                    }
                }
            }
            
            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });

        // Add floating keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
                0%, 100% { transform: translateY(0px) rotate(0deg); }
                33% { transform: translateY(-10px) rotate(2deg); }
                66% { transform: translateY(5px) rotate(-2deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>