<?php
// check_availability.php
require_once 'config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

try {
    // Get date and time from POST data
    $date = isset($_POST['date']) ? trim($_POST['date']) : '';
    $time = isset($_POST['time']) ? trim($_POST['time']) : '';
    
    // Validate inputs
    if (empty($date) || empty($time)) {
        echo json_encode(['error' => 'Date and time are required']);
        exit;
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }
    
    // Validate time format
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        echo json_encode(['error' => 'Invalid time format']);
        exit;
    }
    
    // Check if date is not in the past
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        echo json_encode(['error' => 'Cannot check availability for past dates']);
        exit;
    }
    
    // Query to get occupied tables for the specific date and time
    $query = "SELECT DISTINCT table_number FROM reservations 
              WHERE date = ? AND time = ? AND status != 'cancelled' 
              ORDER BY table_number";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$date, $time]);
    
    $occupiedTables = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $occupiedTables[] = (int)$row['table_number'];
    }
    
    // Calculate available tables
    $allTables = range(1, 15);
    $availableTables = array_diff($allTables, $occupiedTables);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'date' => $date,
        'time' => $time,
        'occupied_tables' => $occupiedTables,
        'available_tables' => array_values($availableTables),
        'total_available' => count($availableTables),
        'total_occupied' => count($occupiedTables)
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in check_availability.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in check_availability.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while checking availability']);
}
?>