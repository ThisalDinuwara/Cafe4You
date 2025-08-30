<?php
// admin/messages.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $message_id = (int)$_POST['message_id'];
        
        $update_query = "UPDATE contact_messages SET status = 'read' WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        if ($update_stmt->execute([$message_id])) {
            showMessage('Message marked as read!');
        }
    } elseif (isset($_POST['mark_replied'])) {
        $message_id = (int)$_POST['message_id'];
        
        $update_query = "UPDATE contact_messages SET status = 'replied' WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        if ($update_stmt->execute([$message_id])) {
            showMessage('Message marked as replied!');
        }
    } elseif (isset($_POST['delete_message'])) {
        $message_id = (int)$_POST['message_id'];
        
        $delete_query = "DELETE FROM contact_messages WHERE id = ?";
        $delete_stmt = $db->prepare($delete_query);
        if ($delete_stmt->execute([$message_id])) {
            showMessage('Message deleted successfully!');
        }
    }
}

// Get all messages
$messages_query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
$messages_stmt = $db->prepare($messages_query);
$messages_stmt->execute();
$messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get message details if requested
$message_details = null;
if (isset($_GET['view'])) {
    $message_id = (int)$_GET['view'];
    
    $details_query = "SELECT * FROM contact_messages WHERE id = ?";
    $details_stmt = $db->prepare($details_query);
    $details_stmt->execute([$message_id]);
    $message_details = $details_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mark as read if it was unread
    if ($message_details && $message_details['status'] === 'unread') {
        $read_query = "UPDATE contact_messages SET status = 'read' WHERE id = ?";
        $read_stmt = $db->prepare($read_query);
        $read_stmt->execute([$message_id]);
        $message_details['status'] = 'read';
    }
}

// Calculate stats
$total_messages = count($messages);
$unread_count = count(array_filter($messages, fn($m) => $m['status'] === 'unread'));
$read_count = count(array_filter($messages, fn($m) => $m['status'] === 'read'));
$replied_count = count(array_filter($messages, fn($m) => $m['status'] === 'replied'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand-yellow': '#FCD34D',
                        'brand-amber': '#F59E0B',
                        'brand-cream': '#FFF8F0',
                        'sidebar-bg': '#2C3E50',
                        'sidebar-hover': '#34495E'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .card-shadow {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .gradient-card {
            background: linear-gradient(135deg, var(--tw-gradient-stops));
        }
        
        .nav-item {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #FCD34D;
            border-radius: 0 4px 4px 0;
        }
        
        .nav-item.active {
            background: rgba(252, 211, 77, 0.1);
            color: #FCD34D;
            border-right: 3px solid #FCD34D;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <!-- Top Navigation -->
    <nav class="bg-sidebar-bg text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="w-10 h-10 bg-gradient-to-br from-brand-yellow to-brand-amber rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <h1 class="text-xl font-bold">Cafe For You - Admin</h1>
                </div>

                <div class="flex items-center space-x-6">
                    <span class="text-gray-300">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    <a href="../index.php" class="text-gray-300 hover:text-white transition-colors duration-300">View Site</a>
                    <a href="../logout.php" class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-4 py-2 rounded-lg hover:shadow-lg transform hover:scale-105 transition-all duration-300 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg">
            <nav class="mt-8">
                <div class="px-4 space-y-2">
                    <a href="dashboard.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                        </svg>
                        Dashboard
                    </a>
                    <a href="orders.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        Orders
                    </a>
                    <a href="menu.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Menu Management
                    </a>
                    <a href="categories.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Categories
                    </a>
                    <a href="reservations.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 011 1v9a1 1 0 01-1 1H5a1 1 0 01-1-1V8a1 1 0 011-1h3z"></path>
                        </svg>
                        Reservations
                    </a>
                    <a href="users.php" class="nav-item flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 transition-all duration-300 rounded-lg mx-2">
                        <svg class="w-5 h-5 mr-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        Users
                    </a>
                    <a href="messages.php" class="nav-item active flex items-center px-4 py-3 text-brand-yellow bg-yellow-50 transition-all duration-300 rounded-lg mx-2 font-medium">
                        <svg class="w-5 h-5 mr-3 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Contact Messages
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden">
            <div class="p-8">
                <!-- Header Section -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-purple-600 rounded-2xl flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">Contact Messages</h1>
                            <p class="text-gray-600 mt-1">Manage customer inquiries and messages</p>
                        </div>
                    </div>
                </div>

                <?php displayMessage(); ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Messages -->
                    <div class="gradient-card from-purple-500 to-purple-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Total Messages</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $total_messages ?></div>
                        <div class="text-white/70 text-sm">All inquiries</div>
                    </div>

                    <!-- Unread Messages -->
                    <div class="gradient-card from-blue-500 to-blue-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Unread</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $unread_count ?></div>
                        <div class="text-white/70 text-sm">Need attention</div>
                    </div>

                    <!-- Read Messages -->
                    <div class="gradient-card from-yellow-500 to-yellow-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Read</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $read_count ?></div>
                        <div class="text-white/70 text-sm">Viewed messages</div>
                    </div>

                    <!-- Replied Messages -->
                    <div class="gradient-card from-green-500 to-green-600 rounded-2xl p-6 text-white hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-white/80 text-sm font-medium">Replied</h3>
                            <svg class="w-8 h-8 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="text-3xl font-bold mb-2"><?= $replied_count ?></div>
                        <div class="text-white/70 text-sm">Completed</div>
                    </div>
                </div>

                <!-- Messages Table -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-yellow to-brand-amber px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-6 h-6 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <h3 class="text-white font-semibold text-lg">Customer Messages</h3>
                            </div>
                            <div class="text-white/90 text-sm">
                                <span class="bg-white/20 px-3 py-1 rounded-full">
                                    <?= $total_messages ?> Total Messages
                                </span>
                            </div>
                        </div>
                        <p class="text-white/80 text-sm mt-1">Manage customer inquiries and support messages (<?= $total_messages ?> messages)</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">MESSAGE ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">CUSTOMER</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">SUBJECT</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">DATE & TIME</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">STATUS</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php $counter = 1; foreach ($messages as $message): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200 <?= $message['status'] === 'unread' ? 'bg-blue-50' : '' ?>">
                                        <!-- Message ID -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="w-10 h-10 bg-gradient-to-br from-purple-400 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                                                #<?= $counter++ ?>
                                            </div>
                                        </td>

                                        <!-- Customer Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center mr-3">
                                                    <span class="text-white font-medium text-sm">
                                                        <?= strtoupper(substr($message['name'], 0, 2)) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($message['name']) ?></div>
                                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($message['email']) ?></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Subject & Preview -->
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($message['subject']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars(substr($message['message'], 0, 60)) ?>...</div>
                                        </td>

                                        <!-- Date & Time -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-gray-900"><?= date('M j, Y', strtotime($message['created_at'])) ?></div>
                                            <div class="text-xs text-gray-500"><?= date('g:i A', strtotime($message['created_at'])) ?></div>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                <?php
                                                $status_colors = [
                                                    'unread' => 'bg-blue-100 text-blue-800',
                                                    'read' => 'bg-yellow-100 text-yellow-800',
                                                    'replied' => 'bg-green-100 text-green-800'
                                                ];
                                                echo $status_colors[$message['status']] ?? 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?php
                                                $status_icons = [
                                                    'unread' => '✉️ Unread',
                                                    'read' => '👁️ Read',
                                                    'replied' => '✅ Replied'
                                                ];
                                                echo $status_icons[$message['status']] ?? ucfirst($message['status']);
                                                ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <a href="messages.php?view=<?= $message['id'] ?>" 
                                                   class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                                    👁 View Details
                                                </a>

                                                <?php if ($message['status'] !== 'replied'): ?>
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                                        <button type="submit" name="mark_replied" 
                                                                class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-300">
                                                            ✅ Mark Replied
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" class="inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete this message?')">
                                                    <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                                    <button type="submit" name="delete_message" 
                                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-300">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Message Details Modal -->
    <?php if ($message_details): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center p-4" id="message-modal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 bg-gradient-to-r from-brand-yellow to-brand-amber px-6 py-4 rounded-t-2xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white">Message Details</h3>
                                <p class="text-white/80 text-sm">From <?= htmlspecialchars($message_details['name']) ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="mailto:<?= urlencode($message_details['email']) ?>?subject=Re: <?= urlencode($message_details['subject']) ?>" 
                               class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300">
                                📧 Reply via Email
                            </a>
                            <a href="messages.php" class="text-white/80 hover:text-white" title="Close">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <!-- Message Header Info -->
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-6 mb-6">
                        <div class="grid md:grid-cols-2 gap-6 mb-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-600 mb-2">From</h4>
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-medium text-sm">
                                            <?= strtoupper(substr($message_details['name'], 0, 2)) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($message_details['name']) ?></p>
                                        <p class="text-sm text-gray-600"><?= htmlspecialchars($message_details['email']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-600 mb-2">Date & Status</h4>
                                <p class="font-semibold text-gray-900"><?= date('M j, Y g:i A', strtotime($message_details['created_at'])) ?></p>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium mt-2
                                    <?php
                                    $status_colors = [
                                        'unread' => 'bg-blue-100 text-blue-800',
                                        'read' => 'bg-yellow-100 text-yellow-800',
                                        'replied' => 'bg-green-100 text-green-800'
                                    ];
                                    echo $status_colors[$message_details['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?php
                                    $status_icons = [
                                        'unread' => 'Unread',
                                        'read' => 'Read',
                                        'replied' => 'Replied'
                                    ];
                                    echo $status_icons[$message_details['status']] ?? ucfirst($message_details['status']);
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-semibold text-gray-600 mb-2">Subject</h4>
                            <p class="font-semibold text-lg text-gray-900"><?= htmlspecialchars($message_details['subject']) ?></p>
                        </div>
                    </div>
                    
                    <!-- Message Content -->
                    <div class="mb-8">
                        <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-yellow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            Message Content
                        </h4>
                        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                            <div class="prose max-w-none">
                                <p class="whitespace-pre-wrap text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($message_details['message'])) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-4 pt-4 border-t border-gray-200">
                        <a href="mailto:<?= urlencode($message_details['email']) ?>?subject=Re: <?= urlencode($message_details['subject']) ?>" 
                           class="bg-gradient-to-r from-brand-yellow to-brand-amber text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all duration-300 transform hover:scale-105 flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span>Reply via Email</span>
                        </a>
                        
                        <?php if ($message_details['status'] !== 'replied'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="message_id" value="<?= $message_details['id'] ?>">
                                <button type="submit" name="mark_replied" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Mark as Replied</span>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" class="inline" 
                              onsubmit="return confirm('Are you sure you want to delete this message? This action cannot be undone.')">
                            <input type="hidden" name="message_id" value="<?= $message_details['id'] ?>">
                            <button type="submit" name="delete_message" 
                                    class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 flex items-center space-x-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                <span>Delete Message</span>
                            </button>
                        </form>
                        
                        <a href="messages.php" 
                           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold transition-all duration-300 flex items-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            <span>Back to Messages</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Enhanced modal handling
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('message-modal')) {
                    window.location.href = 'messages.php';
                }
            });

            // Close modal on background click
            const modal = document.getElementById('message-modal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        window.location.href = 'messages.php';
                    }
                });
            }
        });
    </script>
</body>
</html>