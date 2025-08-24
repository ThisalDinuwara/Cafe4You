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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin</title>
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
                    <a href="reservations.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Reservations</a>
                    <a href="users.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-50 transition">Users</a>
                    <a href="messages.php" class="block px-4 py-2 text-gray-700 bg-orange-50 border-r-4 border-orange-600 font-medium">Contact Messages</a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Contact Messages</h1>
                <p class="text-gray-600 mt-2">Manage customer inquiries and messages</p>
            </div>

            <?php displayMessage(); ?>

            <!-- Messages Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($messages as $message): ?>
                            <tr class="<?= $message['status'] === 'unread' ? 'bg-blue-50' : '' ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($message['name']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($message['email']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($message['subject']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars(substr($message['message'], 0, 60)) ?>...</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php
                                        $status_colors = [
                                            'unread' => 'bg-blue-100 text-blue-800',
                                            'read' => 'bg-yellow-100 text-yellow-800',
                                            'replied' => 'bg-green-100 text-green-800'
                                        ];
                                        echo $status_colors[$message['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>">
                                        <?= ucfirst($message['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="messages.php?view=<?= $message['id'] ?>" class="text-orange-600 hover:text-orange-900">View</a>
                                    
                                    <?php if ($message['status'] !== 'replied'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                            <button type="submit" name="mark_replied" class="text-green-600 hover:text-green-900">Mark Replied</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this message?')">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <button type="submit" name="delete_message" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Message Details Modal -->
    <?php if ($message_details): ?>
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="message-modal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Message Details</h3>
                        <a href="messages.php" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </a>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="grid md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-600">From:</p>
                                <p class="font-medium"><?= htmlspecialchars($message_details['name']) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($message_details['email']) ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Date:</p>
                                <p class="font-medium"><?= date('M j, Y g:i A', strtotime($message_details['created_at'])) ?></p>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full mt-1
                                    <?php
                                    $status_colors = [
                                        'unread' => 'bg-blue-100 text-blue-800',
                                        'read' => 'bg-yellow-100 text-yellow-800',
                                        'replied' => 'bg-green-100 text-green-800'
                                    ];
                                    echo $status_colors[$message_details['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?= ucfirst($message_details['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Subject:</p>
                            <p class="font-medium"><?= htmlspecialchars($message_details['subject']) ?></p>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <p class="text-sm text-gray-600 mb-2">Message:</p>
                        <div class="bg-white border rounded-lg p-4">
                            <p class="whitespace-pre-wrap"><?= nl2br(htmlspecialchars($message_details['message'])) ?></p>
                        </div>
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="mailto:<?= urlencode($message_details['email']) ?>?subject=Re: <?= urlencode($message_details['subject']) ?>" 
                           class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">
                            Reply via Email
                        </a>
                        
                        <?php if ($message_details['status'] !== 'replied'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="message_id" value="<?= $message_details['id'] ?>">
                                <button type="submit" name="mark_replied" 
                                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                                    Mark as Replied
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this message?')">
                            <input type="hidden" name="message_id" value="<?= $message_details['id'] ?>">
                            <button type="submit" name="delete_message" 
                                    class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition">
                                Delete Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>