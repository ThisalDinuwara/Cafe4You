<?php
// login.php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        showMessage('Please fill in all fields', 'error');
    } else {
        $query = "SELECT id, username, password, full_name, role FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('index.php');
            }
        } else {
            showMessage('Invalid username or password', 'error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cafe For You</title>
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
        
        .login-gradient {
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
    </div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo Section -->
            <div class="text-center">
                <div class="flex items-center justify-center space-x-4 mb-8">
                    <div class="w-16 h-16 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-2xl">C</span>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-brand-yellow to-brand-amber bg-clip-text text-transparent">Cafe For You</h1>
                        <p class="text-sm text-gray-600">Welcome back!</p>
                    </div>
                </div>
                <h2 class="text-4xl font-bold text-gray-900 mb-2">Sign in to your account</h2>
                <p class="text-gray-600">
                    Don't have an account?
                    <a href="register.php" class="font-semibold text-brand-yellow hover:text-brand-amber transition-colors duration-300">Create one here</a>
                </p>
            </div>
            
            <!-- Login Form Card -->
            <div class="floating-card rounded-3xl shadow-2xl p-8 border border-yellow-100">
                <?php displayMessage(); ?>
                
                <form class="space-y-6" method="POST">
                    <div class="space-y-5">
                        <div>
                            <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username or Email</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <input id="username" name="username" type="text" required 
                                       class="input-focus block w-full pl-12 pr-4 py-3 border border-gray-200 rounded-2xl bg-white placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-0 transition-all duration-300 text-sm font-medium" 
                                       placeholder="Enter your username or email">
                            </div>
                        </div>
                        
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
                                       placeholder="Enter your password">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <button type="submit" class="w-full login-gradient text-white py-4 px-6 rounded-2xl font-bold text-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            <span>Sign In</span>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Secure Login</h3>
                    <p class="text-xs text-gray-600 mt-1">Your data is protected</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3 border border-yellow-100">
                        <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Quick Access</h3>
                    <p class="text-xs text-gray-600 mt-1">Fast and easy login</p>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg flex items-center justify-center mx-auto mb-3 border border-yellow-100">
                        <svg class="w-6 h-6 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 text-sm">Great Experience</h3>
                    <p class="text-xs text-gray-600 mt-1">Enjoy our services</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus effects to inputs
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.closest('div').querySelector('svg').classList.add('text-brand-yellow');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.closest('div').querySelector('svg').classList.remove('text-brand-yellow');
                        this.closest('div').querySelector('svg').classList.add('text-gray-400');
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