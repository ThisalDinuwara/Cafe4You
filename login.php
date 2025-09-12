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
                        'brand-amber':  '#F59E0B',
                        'brand-cream':  '#FFF8F0',
                        'brand-brown':  '#8B4513',
                        'brand-gray':   '#F5F5F5'
                    },
                    fontFamily: {
                        'display': ['Georgia', 'serif'],
                        'body':    ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
        }
        
        .card-shadow {
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 48px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="bg-brand-cream font-body min-h-screen">
    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-20 w-4 h-4 bg-brand-yellow rounded-full animate-bounce"></div>
        <div class="absolute top-40 right-32 w-6 h-6 bg-yellow-400 rounded-full opacity-60"></div>
        <div class="absolute bottom-32 left-16 w-3 h-3 bg-brand-amber rounded-full"></div>
        <div class="absolute top-60 left-1/3 w-2 h-2 bg-yellow-300 rounded-full"></div>
    </div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="max-w-md w-full">
            <!-- Logo and Header -->
            <div class="text-center mb-8">
                <div class="flex items-center justify-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-2xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-2xl">C</span>
                    </div>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
                <p class="text-gray-600">Sign in to your Cafe For You account</p>
            </div>

            <!-- Login Form -->
            <div class="bg-white rounded-2xl card-shadow hover-lift p-8">
                <?php displayMessage(); ?>
                
                <form method="POST" class="space-y-6">
                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username or Email</label>
                            <input id="username" name="username" type="text" required 
                                   class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-500" 
                                   placeholder="Enter your username or email">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                            <input id="password" name="password" type="password" required 
                                   class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-yellow focus:border-transparent transition-all duration-300 text-gray-900 placeholder-gray-500" 
                                   placeholder="Enter your password">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-brand-yellow to-brand-amber text-white py-3 px-4 rounded-xl font-semibold hover:shadow-lg transform hover:scale-105 transition-all duration-300">
                            <span class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                </svg>
                                Sign in
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Divider -->
                <div class="my-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-4 bg-white text-gray-500">New to Cafe For You?</span>
                        </div>
                    </div>
                </div>

                <!-- Register Link -->
                <div class="text-center">
                    <a href="register.php" 
                       class="inline-flex items-center text-brand-amber hover:text-brand-yellow font-semibold transition-colors duration-300">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        Create a new account
                    </a>
                </div>
            </div>

            <!-- Back to Home -->
            <div class="text-center mt-6">
                <a href="index.php" 
                   class="inline-flex items-center text-gray-600 hover:text-gray-800 font-medium transition-colors duration-300">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-6 text-gray-500 text-sm">
        <p>&copy; 2025 Cafe For You. All rights reserved.</p>
    </div>

    <script>
        // Enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus animations
            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('transform', 'scale-105');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('transform', 'scale-105');
                });
            });

            // Form validation feedback
            const form = document.querySelector('form');
            const submitButton = form.querySelector('button[type="submit"]');
            
            form.addEventListener('submit', function(e) {
                submitButton.innerHTML = '<span class="flex items-center justify-center"><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Signing in...</span>';
                submitButton.disabled = true;
            });

            // Auto-hide messages after 5 seconds
            const messages = document.querySelectorAll('.bg-red-500, .bg-green-500');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease-out';
                    message.style.opacity = '0';
                    setTimeout(() => {
                        if (message.parentNode) {
                            message.parentNode.removeChild(message);
                        }
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>