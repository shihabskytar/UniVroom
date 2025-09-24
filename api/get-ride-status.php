<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$ride_id = intval($_GET['ride_id'] ?? 0);

if (!$ride_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ride ID']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Get ride status
    $stmt = $db->prepare("
        SELECT r.status, r.updated_at
        FROM rides r 
        LEFT JOIN riders ON r.rider_id = riders.id 
        WHERE r.id = ? AND (r.user_id = ? OR riders.user_id = ?)
    ");
    $stmt->execute([$ride_id, $user_id, $user_id]);
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ride) {
        echo json_encode(['success' => false, 'message' => 'Ride not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'status' => $ride['status'],
        'updated_at' => $ride['updated_at']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
