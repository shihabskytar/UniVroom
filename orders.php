<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user's orders
$stmt = $db->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           GROUP_CONCAT(p.title SEPARATOR ', ') as product_titles
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 15px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="marketplace/">
                    <i class="fas fa-shopping-bag me-1"></i>Marketplace
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Home
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-shopping-bag me-2"></i>My Orders</h2>
            <a href="marketplace/" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Shop More
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-4"></i>
                <h4>No orders yet</h4>
                <p class="text-muted mb-4">You haven't placed any orders yet</p>
                <a href="marketplace/" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">Order #<?php echo $order['id']; ?></h5>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                                </div>
                                <div>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'processing' => 'primary',
                                        'shipped' => 'info',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $status_color = $status_colors[$order['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Items:</strong>
                                <div class="text-muted">
                                    <?php echo htmlspecialchars(substr($order['product_titles'], 0, 100)); ?>
                                    <?php echo strlen($order['product_titles']) > 100 ? '...' : ''; ?>
                                    (<?php echo $order['item_count']; ?> item<?php echo $order['item_count'] > 1 ? 's' : ''; ?>)
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <strong>Payment:</strong>
                                <span class="text-muted"><?php echo ucfirst($order['payment_method']); ?></span>
                                <span class="badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : 'warning'; ?> ms-2">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($order['shipping_address']): ?>
                                <div class="text-muted">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars(substr($order['shipping_address'], 0, 50)); ?>...
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <div class="mb-3">
                                <div class="h5 text-success"><?php echo formatCurrency($order['final_amount']); ?></div>
                                <?php if ($order['discount_amount'] > 0): ?>
                                    <small class="text-muted">
                                        <del><?php echo formatCurrency($order['total_amount']); ?></del>
                                        <span class="text-success ms-1">-<?php echo formatCurrency($order['discount_amount']); ?></span>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex flex-column gap-2">
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                
                                <?php if ($order['status'] == 'delivered'): ?>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="reorderItems(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-redo me-1"></i>Reorder
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($order['status'], ['pending', 'confirmed'])): ?>
                                    <button class="btn btn-outline-danger btn-sm" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                fetch('api/cancel-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to cancel order: ' + data.message);
                    }
                });
            }
        }
        
        function reorderItems(orderId) {
            fetch('api/reorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Items added to cart!');
                    window.location.href = 'marketplace/cart.php';
                } else {
                    alert('Failed to reorder: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>
