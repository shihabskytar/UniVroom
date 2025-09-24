<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    redirect('marketplace/');
}

// Get order details
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect('marketplace/');
}

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.title, p.images 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clear cart from localStorage will be done via JavaScript
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
        }
        
        .confirmation-header {
            background: linear-gradient(135deg, var(--success-color), #45a049);
            color: white;
            padding: 60px 0;
            text-align: center;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin: -50px auto 30px;
            max-width: 800px;
            position: relative;
            z-index: 10;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
            background-color: #f8f9fa;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
        }
        
        .summary-row.total {
            border-top: 2px solid #eee;
            padding-top: 10px;
            font-weight: bold;
            font-size: 1.1em;
        }
    </style>
</head>
<body class="bg-light">
    <div class="confirmation-header">
        <div class="container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="display-5 fw-bold mb-3">Order Confirmed!</h1>
            <p class="lead">Thank you for your purchase</p>
        </div>
    </div>

    <div class="container">
        <div class="order-card">
            <div class="text-center mb-4">
                <h3>Order #<?php echo $order['id']; ?></h3>
                <p class="text-muted">Placed on <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <h5 class="mb-3">Order Items</h5>
                    
                    <?php foreach ($order_items as $item): ?>
                        <div class="order-item">
                            <?php 
                            $images = json_decode($item['images'], true);
                            $first_image = !empty($images) ? $images[0] : null;
                            ?>
                            <img src="<?php echo $first_image ? 'uploads/' . $first_image : 'assets/images/placeholder-product.jpg'; ?>" alt="Product" class="item-image">
                            
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                <div class="text-muted">
                                    Quantity: <?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['price']); ?>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <strong><?php echo formatCurrency($item['total']); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="col-lg-4">
                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-3">Order Summary</h6>
                        
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><?php echo formatCurrency($order['total_amount']); ?></span>
                        </div>
                        
                        <?php if ($order['discount_amount'] > 0): ?>
                            <div class="summary-row text-success">
                                <span>Discount<?php echo $order['coupon_code'] ? ' (' . $order['coupon_code'] . ')' : ''; ?></span>
                                <span>-<?php echo formatCurrency($order['discount_amount']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Delivery</span>
                            <span class="text-success">Free</span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?php echo formatCurrency($order['final_amount']); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Delivery Information</h6>
                        <p class="text-muted mb-1">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                        </p>
                        <p class="text-muted mb-1">
                            <i class="fas fa-credit-card me-2"></i>
                            <?php echo ucfirst($order['payment_method']); ?>
                        </p>
                        <p class="text-muted">
                            <i class="fas fa-truck me-2"></i>
                            Status: <span class="badge bg-info"><?php echo ucfirst($order['status']); ?></span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 pt-4 border-top">
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>View All Orders
                    </a>
                    <a href="marketplace/" class="btn btn-outline-primary">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
                
                <div class="mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>What's next?</strong> You will receive updates about your order status. 
                        For COD orders, please have the exact amount ready upon delivery.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        // Clear cart from localStorage
        localStorage.removeItem('cart');
        
        // Update cart count if element exists
        const cartCountEl = document.getElementById('cartCount');
        if (cartCountEl) {
            cartCountEl.textContent = '0';
            cartCountEl.style.display = 'none';
        }
    </script>
</body>
</html>
