<?php
require_once '../config/config.php';

$db = getDB();
$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    redirect('marketplace/');
}

// Get product details
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = ? AND p.status = 'active'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    redirect('marketplace/');
}

// Update view count
$stmt = $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$stmt->execute([$product_id]);

// Get product images
$images = json_decode($product['images'], true) ?: [];

// Get product reviews
$stmt = $db->prepare("
    SELECT pr.*, u.name as user_name 
    FROM product_reviews pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.product_id = ? 
    ORDER BY pr.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related products
$stmt = $db->prepare("
    SELECT p.*, JSON_UNQUOTE(JSON_EXTRACT(p.images, '$[0]')) as first_image
    FROM products p 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
    ORDER BY p.views DESC 
    LIMIT 4
");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isLoggedIn()) {
    $rating = intval($_POST['rating']);
    $review_text = sanitize($_POST['review']);
    
    if ($rating >= 1 && $rating <= 5) {
        try {
            $stmt = $db->prepare("INSERT INTO product_reviews (product_id, user_id, rating, review, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$product_id, $_SESSION['user_id'], $rating, $review_text]);
            
            // Update product rating
            $stmt = $db->prepare("
                UPDATE products 
                SET rating = (
                    SELECT AVG(rating) 
                    FROM product_reviews 
                    WHERE product_id = ?
                ) 
                WHERE id = ?
            ");
            $stmt->execute([$product_id, $product_id]);
            
            redirect("marketplace/product.php?id=$product_id#reviews");
        } catch (Exception $e) {
            $error = 'Failed to submit review';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
            --warning-color: #ff9800;
        }
        
        .product-gallery {
            position: sticky;
            top: 20px;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 15px;
            background-color: #f8f9fa;
        }
        
        .thumbnail-images {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            overflow-x: auto;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }
        
        .thumbnail.active {
            border-color: var(--primary-color);
        }
        
        .product-info {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .price-section {
            background: linear-gradient(135deg, var(--success-color), #45a049);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .stars {
            color: var(--warning-color);
        }
        
        .review-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .review-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 8px;
        }
        
        .related-products {
            margin-top: 50px;
        }
        
        .product-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 20px 0;
        }
        
        .breadcrumb-item a {
            text-decoration: none;
            color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .product-info {
                padding: 20px;
            }
            
            .main-image {
                height: 300px;
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
                <?php if (isLoggedIn()): ?>
                    <a class="nav-link" href="cart.php">
                        <i class="fas fa-shopping-cart me-1"></i>Cart
                        <span class="badge bg-danger ms-1" id="cartCount">0</span>
                    </a>
                <?php endif; ?>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Marketplace
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Marketplace</a></li>
                <?php if ($product['category_name']): ?>
                    <li class="breadcrumb-item"><a href="index.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['title']); ?></li>
            </ol>
        </nav>

        <div class="row">
            <!-- Product Gallery -->
            <div class="col-lg-6">
                <div class="product-gallery">
                    <?php if (!empty($images)): ?>
                        <img src="../uploads/<?php echo $images[0]; ?>" alt="Product Image" class="main-image" id="mainImage">
                        
                        <?php if (count($images) > 1): ?>
                            <div class="thumbnail-images">
                                <?php foreach ($images as $index => $image): ?>
                                    <img src="../uploads/<?php echo $image; ?>" alt="Thumbnail" class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeMainImage(this, <?php echo $index; ?>)">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <img src="../assets/images/placeholder-product.jpg" alt="No Image" class="main-image">
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info">
                    <h1 class="h3 mb-3"><?php echo htmlspecialchars($product['title']); ?></h1>
                    
                    <!-- Rating -->
                    <?php if ($product['rating'] > 0): ?>
                        <div class="rating-display">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $product['rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <span><?php echo number_format($product['rating'], 1); ?> (<?php echo count($reviews); ?> reviews)</span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Price -->
                    <div class="price-section text-center">
                        <div class="h2 mb-2"><?php echo formatCurrency($product['price']); ?></div>
                        <small>Free delivery on campus</small>
                    </div>
                    
                    <!-- Stock Status -->
                    <div class="mb-3">
                        <?php if ($product['stock'] > 0): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check me-1"></i>In Stock (<?php echo $product['stock']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-times me-1"></i>Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <h5>Description</h5>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    
                    <!-- Category -->
                    <?php if ($product['category_name']): ?>
                        <div class="mb-3">
                            <strong>Category:</strong> 
                            <a href="index.php?category=<?php echo $product['category_id']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Add to Cart -->
                    <?php if ($product['stock'] > 0): ?>
                        <div class="quantity-selector">
                            <label class="form-label mb-0">Quantity:</label>
                            <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
                            <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-primary btn-lg" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                                <button class="btn btn-success btn-lg" onclick="buyNow(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-bolt me-2"></i>Buy Now
                                </button>
                            <?php else: ?>
                                <a href="../auth/login.php?redirect=marketplace/product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Purchase
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Stats -->
                    <div class="row text-center mt-4">
                        <div class="col-4">
                            <div class="h6 text-primary"><?php echo $product['views']; ?></div>
                            <small class="text-muted">Views</small>
                        </div>
                        <div class="col-4">
                            <div class="h6 text-success"><?php echo $product['sales_count']; ?></div>
                            <small class="text-muted">Sold</small>
                        </div>
                        <div class="col-4">
                            <div class="h6 text-warning"><?php echo count($reviews); ?></div>
                            <small class="text-muted">Reviews</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reviews Section -->
        <div class="row mt-5" id="reviews">
            <div class="col-12">
                <div class="product-info">
                    <h4 class="mb-4">Customer Reviews</h4>
                    
                    <!-- Write Review -->
                    <?php if (isLoggedIn()): ?>
                        <div class="mb-4">
                            <h5>Write a Review</h5>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Rating</label>
                                    <div class="rating-input">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                                            <label for="star<?php echo $i; ?>" class="star-label">
                                                <i class="fas fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Review</label>
                                    <textarea class="form-control" name="review" rows="4" placeholder="Share your experience with this product..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-star me-2"></i>Submit Review
                                </button>
                            </form>
                        </div>
                        <hr>
                    <?php endif; ?>
                    
                    <!-- Reviews List -->
                    <?php if (empty($reviews)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <h5>No reviews yet</h5>
                            <p class="text-muted">Be the first to review this product!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['user_name']); ?></strong>
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($review['created_at']); ?></small>
                                </div>
                                <?php if ($review['review']): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="related-products">
                <h4 class="mb-4">Related Products</h4>
                <div class="row">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card product-card">
                                <a href="product.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                    <div style="height: 200px; background-image: url('<?php echo $related['first_image'] ? '../uploads/' . $related['first_image'] : '../assets/images/placeholder-product.jpg'; ?>'); background-size: cover; background-position: center;"></div>
                                    <div class="card-body">
                                        <h6 class="card-title text-dark"><?php echo htmlspecialchars($related['title']); ?></h6>
                                        <div class="text-success fw-bold"><?php echo formatCurrency($related['price']); ?></div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <style>
        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }
        
        .rating-input input[type="radio"] {
            display: none;
        }
        
        .star-label {
            color: #ddd;
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .rating-input input[type="radio"]:checked ~ .star-label,
        .rating-input .star-label:hover,
        .rating-input .star-label:hover ~ .star-label {
            color: var(--warning-color);
        }
    </style>
    
    <script>
        // Change main image
        function changeMainImage(thumbnail, index) {
            document.getElementById('mainImage').src = thumbnail.src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            thumbnail.classList.add('active');
        }
        
        // Quantity controls
        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            const newValue = Math.max(1, Math.min(maxValue, currentValue + change));
            quantityInput.value = newValue;
        }
        
        // Add to cart
        function addToCart(productId) {
            const quantity = parseInt(document.getElementById('quantity').value);
            
            // Get existing cart from localStorage
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.id === productId);
            
            if (existingItem) {
                existingItem.quantity += quantity;
            } else {
                cart.push({ id: productId, quantity: quantity });
            }
            
            // Save to localStorage
            localStorage.setItem('cart', JSON.stringify(cart));
            
            // Update cart count
            updateCartCount();
            
            // Show success message
            showToast('Product added to cart!', 'success');
        }
        
        // Buy now
        function buyNow(productId) {
            addToCart(productId);
            window.location.href = 'cart.php';
        }
        
        // Update cart count
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart') || '[]');
            const count = cart.reduce((total, item) => total + item.quantity, 0);
            const cartCountEl = document.getElementById('cartCount');
            if (cartCountEl) {
                cartCountEl.textContent = count;
                cartCountEl.style.display = count > 0 ? 'inline' : 'none';
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            
            const bsToast = new mdb.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.mdb.toast', () => {
                toast.remove();
            });
        }
        
        // Initialize cart count on page load
        updateCartCount();
        
        // Quantity input validation
        document.getElementById('quantity').addEventListener('input', function() {
            const value = parseInt(this.value);
            const max = parseInt(this.max);
            
            if (value < 1) this.value = 1;
            if (value > max) this.value = max;
        });
    </script>
</body>
</html>
