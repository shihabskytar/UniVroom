<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isRider()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Check if rider is online
    $stmt = $db->prepare("SELECT is_online FROM riders WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $rider = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rider || !$rider['is_online']) {
        echo json_encode(['success' => true, 'new_requests' => 0]);
        exit;
    }
    
    // Count new ride requests (last 30 seconds)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM rides 
        WHERE status = 'requested' 
        AND rider_id IS NULL 
        AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'new_requests' => $result['count']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
