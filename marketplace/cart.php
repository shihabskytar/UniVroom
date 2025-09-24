<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php?redirect=marketplace/cart.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_cart') {
            // This will be handled by JavaScript
        } elseif ($action == 'apply_coupon') {
            $coupon_code = sanitize($_POST['coupon_code']);
            
            if ($coupon_code) {
                $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND active = TRUE AND (expires_at IS NULL OR expires_at > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit) AND (applies_to = 'products' OR applies_to = 'both')");
                $stmt->execute([$coupon_code]);
                $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($coupon) {
                    $_SESSION['cart_coupon'] = $coupon;
                    $success = 'Coupon applied successfully!';
                } else {
                    $error = 'Invalid or expired coupon code';
                }
            }
        } elseif ($action == 'remove_coupon') {
            unset($_SESSION['cart_coupon']);
            $success = 'Coupon removed';
        } elseif ($action == 'checkout') {
            $cart_items = json_decode($_POST['cart_items'], true);
            $shipping_address = sanitize($_POST['shipping_address']);
            $payment_method = sanitize($_POST['payment_method']);
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($cart_items) || empty($shipping_address)) {
                $error = 'Please fill in all required fields';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Calculate totals
                    $subtotal = 0;
                    $valid_items = [];
                    
                    foreach ($cart_items as $item) {
                        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active' AND stock >= ?");
                        $stmt->execute([$item['id'], $item['quantity']]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($product) {
                            $item_total = $product['price'] * $item['quantity'];
                            $subtotal += $item_total;
                            $valid_items[] = [
                                'product' => $product,
                                'quantity' => $item['quantity'],
                                'total' => $item_total
                            ];
                        }
                    }
                    
                    if (empty($valid_items)) {
                        throw new Exception('No valid items in cart');
                    }
                    
                    // Apply coupon discount
                    $discount_amount = 0;
                    $coupon_code = null;
                    
                    if (isset($_SESSION['cart_coupon'])) {
                        $coupon = $_SESSION['cart_coupon'];
                        
                        if ($subtotal >= $coupon['minimum_amount']) {
                            if ($coupon['discount_type'] == 'percentage') {
                                $discount_amount = ($subtotal * $coupon['discount_value']) / 100;
                                if ($coupon['maximum_discount']) {
                                    $discount_amount = min($discount_amount, $coupon['maximum_discount']);
                                }
                            } else {
                                $discount_amount = $coupon['discount_value'];
                            }
                            $coupon_code = $coupon['code'];
                        }
                    }
                    
                    $final_amount = max($subtotal - $discount_amount, 0);
                    
                    // Create order
                    $stmt = $db->prepare("INSERT INTO orders (user_id, total_amount, discount_amount, final_amount, coupon_code, payment_method, shipping_address, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                    $stmt->execute([$user_id, $subtotal, $discount_amount, $final_amount, $coupon_code, $payment_method, $shipping_address, $notes]);
                    
                    $order_id = $db->lastInsertId();
                    
                    // Create order items and update stock
                    foreach ($valid_items as $item) {
                        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$order_id, $item['product']['id'], $item['quantity'], $item['product']['price'], $item['total']]);
                        
                        // Update product stock and sales count
                        $stmt = $db->prepare("UPDATE products SET stock = stock - ?, sales_count = sales_count + ? WHERE id = ?");
                        $stmt->execute([$item['quantity'], $item['quantity'], $item['product']['id']]);
                    }
                    
                    // Update coupon usage
                    if ($coupon_code) {
                        $stmt = $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
                        $stmt->execute([$coupon_code]);
                        unset($_SESSION['cart_coupon']);
                    }
                    
                    $db->commit();
                    
                    redirect("../order-confirmation.php?id=$order_id");
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Checkout failed: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - <?php echo SITE_NAME; ?></title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
            --danger-color: #f44336;
        }
        
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .cart-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            background-color: #f8f9fa;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
        }
        
        .cart-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
        }
        
        .summary-row.total {
            border-top: 2px solid #eee;
            padding-top: 15px;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .coupon-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }
        
        .checkout-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .cart-container {
                padding: 10px;
            }
            
            .cart-item {
                padding: 15px;
            }
            
            .item-image {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Continue Shopping
                </a>
            </div>
        </div>
    </nav>

    <div class="cart-container">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</h2>
                
                <!-- Cart Items Container -->
                <div id="cartItems">
                    <!-- Items will be loaded by JavaScript -->
                </div>
                
                <!-- Empty Cart Message -->
                <div id="emptyCart" class="empty-cart" style="display: none;">
                    <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                    <h4>Your cart is empty</h4>
                    <p class="text-muted mb-4">Looks like you haven't added any items to your cart yet</p>
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                    </a>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h4 class="mb-4">Order Summary</h4>
                    
                    <div class="summary-row">
                        <span>Subtotal (<span id="itemCount">0</span> items)</span>
                        <span id="subtotal">৳0.00</span>
                    </div>
                    
                    <div class="summary-row" id="discountRow" style="display: none;">
                        <span class="text-success">Discount</span>
                        <span class="text-success" id="discountAmount">-৳0.00</span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Delivery</span>
                        <span class="text-success">Free</span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="totalAmount">৳0.00</span>
                    </div>
                    
                    <!-- Coupon Section -->
                    <div class="coupon-section">
                        <h6 class="mb-3">Have a coupon?</h6>
                        <?php if (isset($_SESSION['cart_coupon'])): ?>
                            <div class="alert alert-success d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-tag me-2"></i>
                                    <strong><?php echo $_SESSION['cart_coupon']['code']; ?></strong> applied
                                </span>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="remove_coupon">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="apply_coupon">
                                <input type="text" class="form-control" name="coupon_code" placeholder="Enter coupon code">
                                <button type="submit" class="btn btn-outline-primary">Apply</button>
                            </form>
                            <small class="text-muted">Try: STUDENT10, NEWUSER</small>
                        <?php endif; ?>
                    </div>
                    
                    <button class="btn btn-success btn-lg w-100" id="checkoutBtn" onclick="showCheckoutForm()" disabled>
                        <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Checkout Form -->
        <div class="checkout-form" id="checkoutForm" style="display: none;">
            <h4 class="mb-4"><i class="fas fa-credit-card me-2"></i>Checkout Details</h4>
            
            <form method="POST" action="" id="orderForm">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="cart_items" id="cartItemsData">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Shipping Address *</label>
                            <textarea class="form-control" name="shipping_address" rows="3" placeholder="Enter your full address for delivery" required></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Payment Method *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" checked>
                                <label class="form-check-label" for="cod">
                                    <i class="fas fa-money-bill-wave me-2"></i>Cash on Delivery
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="bkash" value="bkash">
                                <label class="form-check-label" for="bkash">
                                    <i class="fas fa-mobile-alt me-2"></i>bKash
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Order Notes (Optional)</label>
                    <textarea class="form-control" name="notes" rows="2" placeholder="Any special instructions..."></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" onclick="hideCheckoutForm()">
                        <i class="fas fa-arrow-left me-2"></i>Back to Cart
                    </button>
                    <button type="submit" class="btn btn-success flex-grow-1">
                        <i class="fas fa-check me-2"></i>Place Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        let cartItems = [];
        let products = {};
        
        // Load cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
        });
        
        // Load cart from localStorage and fetch product details
        async function loadCart() {
            cartItems = JSON.parse(localStorage.getItem('cart') || '[]');
            
            if (cartItems.length === 0) {
                showEmptyCart();
                return;
            }
            
            // Fetch product details
            const productIds = cartItems.map(item => item.id);
            
            try {
                const response = await fetch('../api/get-products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids: productIds })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    products = data.products.reduce((acc, product) => {
                        acc[product.id] = product;
                        return acc;
                    }, {});
                    
                    renderCart();
                    updateSummary();
                } else {
                    showEmptyCart();
                }
            } catch (error) {
                console.error('Error loading cart:', error);
                showEmptyCart();
            }
        }
        
        // Render cart items
        function renderCart() {
            const container = document.getElementById('cartItems');
            container.innerHTML = '';
            
            cartItems.forEach((item, index) => {
                const product = products[item.id];
                if (!product) return;
                
                const itemTotal = product.price * item.quantity;
                const imageUrl = product.first_image ? `../uploads/${product.first_image}` : '../assets/images/placeholder-product.jpg';
                
                const itemHtml = `
                    <div class="cart-item">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <img src="${imageUrl}" alt="${product.title}" class="item-image">
                            </div>
                            <div class="col-md-4">
                                <h6 class="mb-1">${product.title}</h6>
                                <small class="text-muted">${product.category_name || ''}</small>
                                <div class="text-success fw-bold mt-1">৳${parseFloat(product.price).toFixed(2)}</div>
                            </div>
                            <div class="col-md-3">
                                <div class="quantity-controls">
                                    <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
                                    <input type="number" class="quantity-input" value="${item.quantity}" min="1" max="${product.stock}" onchange="setQuantity(${index}, this.value)">
                                    <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
                                </div>
                                <small class="text-muted d-block mt-1">${product.stock} available</small>
                            </div>
                            <div class="col-md-2 text-center">
                                <div class="fw-bold">৳${itemTotal.toFixed(2)}</div>
                            </div>
                            <div class="col-md-1 text-center">
                                <button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})" title="Remove item">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                container.innerHTML += itemHtml;
            });
            
            document.getElementById('emptyCart').style.display = 'none';
            document.getElementById('checkoutBtn').disabled = false;
        }
        
        // Show empty cart
        function showEmptyCart() {
            document.getElementById('cartItems').innerHTML = '';
            document.getElementById('emptyCart').style.display = 'block';
            document.getElementById('checkoutBtn').disabled = true;
            updateSummary();
        }
        
        // Update quantity
        function updateQuantity(index, change) {
            const item = cartItems[index];
            const product = products[item.id];
            const newQuantity = Math.max(1, Math.min(product.stock, item.quantity + change));
            
            cartItems[index].quantity = newQuantity;
            saveCart();
            renderCart();
            updateSummary();
        }
        
        // Set quantity directly
        function setQuantity(index, quantity) {
            const item = cartItems[index];
            const product = products[item.id];
            const newQuantity = Math.max(1, Math.min(product.stock, parseInt(quantity) || 1));
            
            cartItems[index].quantity = newQuantity;
            saveCart();
            renderCart();
            updateSummary();
        }
        
        // Remove item
        function removeItem(index) {
            if (confirm('Remove this item from cart?')) {
                cartItems.splice(index, 1);
                saveCart();
                
                if (cartItems.length === 0) {
                    showEmptyCart();
                } else {
                    renderCart();
                    updateSummary();
                }
            }
        }
        
        // Save cart to localStorage
        function saveCart() {
            localStorage.setItem('cart', JSON.stringify(cartItems));
        }
        
        // Update summary
        function updateSummary() {
            const itemCount = cartItems.reduce((total, item) => total + item.quantity, 0);
            const subtotal = cartItems.reduce((total, item) => {
                const product = products[item.id];
                return product ? total + (product.price * item.quantity) : total;
            }, 0);
            
            document.getElementById('itemCount').textContent = itemCount;
            document.getElementById('subtotal').textContent = `৳${subtotal.toFixed(2)}`;
            
            // Apply coupon discount if exists
            let discount = 0;
            <?php if (isset($_SESSION['cart_coupon'])): ?>
                const coupon = <?php echo json_encode($_SESSION['cart_coupon']); ?>;
                if (subtotal >= coupon.minimum_amount) {
                    if (coupon.discount_type === 'percentage') {
                        discount = (subtotal * coupon.discount_value) / 100;
                        if (coupon.maximum_discount) {
                            discount = Math.min(discount, coupon.maximum_discount);
                        }
                    } else {
                        discount = coupon.discount_value;
                    }
                }
                
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountAmount').textContent = `-৳${discount.toFixed(2)}`;
            <?php else: ?>
                document.getElementById('discountRow').style.display = 'none';
            <?php endif; ?>
            
            const total = Math.max(subtotal - discount, 0);
            document.getElementById('totalAmount').textContent = `৳${total.toFixed(2)}`;
        }
        
        // Show checkout form
        function showCheckoutForm() {
            document.getElementById('cartItemsData').value = JSON.stringify(cartItems);
            document.getElementById('checkoutForm').style.display = 'block';
            document.getElementById('checkoutForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Hide checkout form
        function hideCheckoutForm() {
            document.getElementById('checkoutForm').style.display = 'none';
        }
        
        // Form validation
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            const shippingAddress = document.querySelector('textarea[name="shipping_address"]').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!shippingAddress) {
                e.preventDefault();
                alert('Please enter your shipping address');
                return;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return;
            }
            
            if (cartItems.length === 0) {
                e.preventDefault();
                alert('Your cart is empty');
                return;
            }
        });
    </script>
</body>
</html>
