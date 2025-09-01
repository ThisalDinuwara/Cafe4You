<?php
// config/email_config.php - Email Configuration File

class EmailConfig {
    // EMAIL SETTINGS - UPDATE THESE WITH YOUR DETAILS
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'your-email@gmail.com';        // YOUR GMAIL ADDRESS
    const SMTP_PASSWORD = 'your-16-char-app-password';   // YOUR GMAIL APP PASSWORD
    const SMTP_ENCRYPTION = 'tls';
    
    // SENDER INFORMATION
    const FROM_EMAIL = 'your-email@gmail.com';
    const FROM_NAME = 'Cafe For You';
    const REPLY_TO_EMAIL = 'support@cafeforyou.com';
    
    // COMPANY INFORMATION FOR EMAIL TEMPLATE
    const COMPANY_NAME = 'Cafe For You';
    const COMPANY_ADDRESS = '123 Coffee Street, Food City, FC 12345';
    const COMPANY_PHONE = '+1 (234) 567-8900';
    const COMPANY_EMAIL = 'support@cafeforyou.com';
    const COMPANY_WEBSITE = 'https://cafeforyou.com';
    
    // EMAIL TEMPLATE SETTINGS
    const PRIMARY_COLOR = '#FF6B35';
    const SECONDARY_COLOR = '#F7931E';
    const SUCCESS_COLOR = '#4CAF50';
    
    // DELIVERY SETTINGS
    const ESTIMATED_DELIVERY_TIME = '25-35 minutes';
    const AUTO_SEND_ON_CONFIRMED = true;
    
    /**
     * Get all email configuration as array
     */
    public static function getConfig() {
        return [
            'smtp' => [
                'host' => self::SMTP_HOST,
                'port' => self::SMTP_PORT,
                'username' => self::SMTP_USERNAME,
                'password' => self::SMTP_PASSWORD,
                'encryption' => self::SMTP_ENCRYPTION
            ],
            'sender' => [
                'email' => self::FROM_EMAIL,
                'name' => self::FROM_NAME,
                'reply_to' => self::REPLY_TO_EMAIL
            ],
            'company' => [
                'name' => self::COMPANY_NAME,
                'address' => self::COMPANY_ADDRESS,
                'phone' => self::COMPANY_PHONE,
                'email' => self::COMPANY_EMAIL,
                'website' => self::COMPANY_WEBSITE
            ],
            'template' => [
                'primary_color' => self::PRIMARY_COLOR,
                'secondary_color' => self::SECONDARY_COLOR,
                'success_color' => self::SUCCESS_COLOR
            ],
            'settings' => [
                'delivery_time' => self::ESTIMATED_DELIVERY_TIME,
                'auto_send_confirmed' => self::AUTO_SEND_ON_CONFIRMED
            ]
        ];
    }
    
    /**
     * Check if email configuration is properly set
     */
    public static function isConfigured() {
        return (
            self::SMTP_USERNAME !== 'your-email@gmail.com' &&
            self::SMTP_PASSWORD !== 'your-16-char-app-password' &&
            !empty(self::SMTP_USERNAME) &&
            !empty(self::SMTP_PASSWORD)
        );
    }
}

// Alternative SMTP Configurations for Different Email Providers
class AlternativeSMTPConfigs {
    
    // GMAIL CONFIGURATION
    public static function gmail() {
        return [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls'
        ];
    }
    
    // YAHOO CONFIGURATION
    public static function yahoo() {
        return [
            'host' => 'smtp.mail.yahoo.com',
            'port' => 587,
            'encryption' => 'tls'
        ];
    }
    
    // OUTLOOK/HOTMAIL CONFIGURATION
    public static function outlook() {
        return [
            'host' => 'smtp-mail.outlook.com',
            'port' => 587,
            'encryption' => 'tls'
        ];
    }
    
    // ZOHO CONFIGURATION
    public static function zoho() {
        return [
            'host' => 'smtp.zoho.com',
            'port' => 587,
            'encryption' => 'tls'
        ];
    }
    
    // SENDGRID CONFIGURATION (Recommended for production)
    public static function sendgrid() {
        return [
            'host' => 'smtp.sendgrid.net',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'apikey',
            'password' => 'YOUR_SENDGRID_API_KEY'
        ];
    }
    
    // MAILGUN CONFIGURATION (Recommended for production)
    public static function mailgun() {
        return [
            'host' => 'smtp.mailgun.org',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'postmaster@your-domain.com',
            'password' => 'YOUR_MAILGUN_SMTP_PASSWORD'
        ];
    }
}

?>

<!-- SETUP INSTRUCTIONS -->
<?php if (!EmailConfig::isConfigured()): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Setup Required</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-bold text-red-800">Email Configuration Required</h3>
                    <p class="text-red-600">Please configure your email settings before using the automatic email feature.</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">📧 Email Setup Instructions</h1>
            
            <!-- Gmail Setup -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-blue-600 mb-4">🔧 Gmail Setup (Recommended)</h2>
                <div class="bg-blue-50 rounded-lg p-6 mb-4">
                    <ol class="space-y-3 text-gray-700">
                        <li class="flex items-start">
                            <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5">1</span>
                            <div>
                                <strong>Enable 2-Factor Authentication</strong><br>
                                Go to your Google Account settings and enable 2-Step Verification
                            </div>
                        </li>
                        <li class="flex items-start">
                            <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5">2</span>
                            <div>
                                <strong>Generate App Password</strong><br>
                                Go to Security → 2-Step Verification → App passwords<br>
                                Select "Mail" and your device, then copy the 16-character password
                            </div>
                        </li>
                        <li class="flex items-start">
                            <span class="bg-blue-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5">3</span>
                            <div>
                                <strong>Update Configuration</strong><br>
                                Edit <code class="bg-gray-200 px-2 py-1 rounded">config/email_config.php</code> with your details
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
            
            <!-- Configuration Code -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-green-600 mb-4">⚙️ Update Your Configuration</h2>
                <div class="bg-gray-900 text-green-400 p-6 rounded-lg font-mono text-sm overflow-x-auto">
<pre>// Update these lines in config/email_config.php:

const SMTP_USERNAME = '<span class="text-yellow-400">your-actual-email@gmail.com</span>';
const SMTP_PASSWORD = '<span class="text-yellow-400">your-16-character-app-password</span>';
const FROM_EMAIL = '<span class="text-yellow-400">your-actual-email@gmail.com</span>';
const FROM_NAME = '<span class="text-yellow-400">Your Cafe Name</span>';

// Company Information:
const COMPANY_NAME = '<span class="text-yellow-400">Your Cafe Name</span>';
const COMPANY_ADDRESS = '<span class="text-yellow-400">Your Address</span>';
const COMPANY_PHONE = '<span class="text-yellow-400">Your Phone Number</span>';
const COMPANY_EMAIL = '<span class="text-yellow-400">support@yourcafe.com</span>';</pre>
                </div>
            </div>
            
            <!-- Testing -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-purple-600 mb-4">🧪 Test Your Configuration</h2>
                <div class="bg-purple-50 rounded-lg p-6">
                    <p class="text-gray-700 mb-4">After updating your configuration:</p>
                    <ol class="space-y-2 text-gray-700 mb-4">
                        <li>1. Go to your admin orders page</li>
                        <li>2. Click the "Test Email" button (bottom-right)</li>
                        <li>3. Check your inbox for the test email</li>
                        <li>4. If it fails, check spam folder or review settings</li>
                    </ol>
                    <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-4">
                        <p class="text-yellow-800 text-sm">
                            <strong>💡 Tip:</strong> If Gmail isn't working, try using a professional email service like SendGrid or Mailgun for production use.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Alternative Providers -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-orange-600 mb-4">🌐 Other Email Providers</h2>
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-orange-50 rounded-lg p-4">
                        <h3 class="font-bold text-orange-800 mb-2">Yahoo Mail</h3>
                        <code class="text-sm">Host: smtp.mail.yahoo.com<br>Port: 587</code>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4">
                        <h3 class="font-bold text-orange-800 mb-2">Outlook/Hotmail</h3>
                        <code class="text-sm">Host: smtp-mail.outlook.com<br>Port: 587</code>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4">
                        <h3 class="font-bold text-orange-800 mb-2">SendGrid (Pro)</h3>
                        <code class="text-sm">Host: smtp.sendgrid.net<br>Port: 587</code>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4">
                        <h3 class="font-bold text-orange-800 mb-2">Mailgun (Pro)</h3>
                        <code class="text-sm">Host: smtp.mailgun.org<br>Port: 587</code>
                    </div>
                </div>
            </div>
            
            <!-- How It Works -->
            <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">🚀 How The Email System Works</h2>
                <div class="grid md:grid-cols-3 gap-6 text-sm">
                    <div class="text-center">
                        <div class="bg-yellow-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-2xl">⏳</span>
                        </div>
                        <h3 class="font-bold mb-2">1. Order Placed</h3>
                        <p class="text-gray-600">Customer places order<br>Status: "Pending"<br>No email sent yet</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-2xl">✅</span>
                        </div>
                        <h3 class="font-bold mb-2">2. Admin Confirms</h3>
                        <p class="text-gray-600">Admin changes status to "Confirmed"<br>Email sends automatically<br>Customer notified</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                            <span class="text-2xl">🍽️</span>
                        </div>
                        <h3 class="font-bold mb-2">3. Order Prepared</h3>
                        <p class="text-gray-600">Kitchen prepares order<br>Additional status updates<br>Customer receives meal</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php endif; ?>