<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ride_id = intval($input['ride_id'] ?? 0);
$message = sanitize($input['message'] ?? '');

if (!$ride_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Verify user is part of this ride
    $stmt = $db->prepare("
        SELECT r.user_id, r.rider_id, riders.user_id as rider_user_id
        FROM rides r 
        LEFT JOIN riders ON r.rider_id = riders.id 
        WHERE r.id = ? AND (r.user_id = ? OR riders.user_id = ?)
    ");
    $stmt->execute([$ride_id, $user_id, $user_id]);
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ride) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Determine receiver
    $receiver_id = ($ride['user_id'] == $user_id) ? $ride['rider_user_id'] : $ride['user_id'];
    
    if (!$receiver_id) {
        echo json_encode(['success' => false, 'message' => 'No receiver found']);
        exit;
    }
    
    // Insert message
    $stmt = $db->prepare("INSERT INTO messages (ride_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$ride_id, $user_id, $receiver_id, $message]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
