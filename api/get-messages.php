<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$ride_id = intval($_GET['ride_id'] ?? 0);
$last_check = intval($_GET['last_check'] ?? 0);

if (!$ride_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ride ID']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Verify user is part of this ride
    $stmt = $db->prepare("
        SELECT r.user_id, riders.user_id as rider_user_id
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
    
    // Get new messages since last check
    $since_time = date('Y-m-d H:i:s', $last_check / 1000);
    
    $stmt = $db->prepare("
        SELECT m.*, u.name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.ride_id = ? AND m.created_at > ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$ride_id, $since_time]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark new messages as read
    if (!empty($messages)) {
        $stmt = $db->prepare("UPDATE messages SET is_read = TRUE WHERE ride_id = ? AND receiver_id = ? AND created_at > ?");
        $stmt->execute([$ride_id, $user_id, $since_time]);
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
