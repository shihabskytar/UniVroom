<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['ids']) || !is_array($input['ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$ids = array_filter($input['ids'], 'is_numeric');

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid IDs provided']);
    exit;
}

try {
    $db = getDB();
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name,
               JSON_UNQUOTE(JSON_EXTRACT(p.images, '$[0]')) as first_image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id IN ($placeholders) AND p.status = 'active'
    ");
    
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'products' => $products]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
