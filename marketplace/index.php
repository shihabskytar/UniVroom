<?php
require_once '../config/config.php';

$db = getDB();

// Get search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$category = sanitize($_GET['category'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'latest');
$page = max(1, intval($_GET['page'] ?? 1));

// Build query
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category;
}

$where_clause = implode(' AND ', $where_conditions);

// Sorting
$order_by = match($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'popular' => 'p.views DESC',
    'rating' => 'p.rating DESC',
    default => 'p.created_at DESC'
};

// Get total count
$count_sql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_products = $stmt->fetchColumn();

// Calculate pagination
$total_pages = ceil($total_products / ITEMS_PER_PAGE);
$offset = ($page - 1) * ITEMS_PER_PAGE;

// Get products
$sql = "
    SELECT p.*, c.name as category_name,
           JSON_UNQUOTE(JSON_EXTRACT(p.images, '$[0]')) as first_image
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE $where_clause 
    ORDER BY $order_by 
    LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $db->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products (highest rated)
$stmt = $db->query("SELECT p.*, c.name as category_name, JSON_UNQUOTE(JSON_EXTRACT(p.images, '$[0]')) as first_image FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = 'active' AND p.rating > 0 ORDER BY p.rating DESC, p.views DESC LIMIT 6");
$featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - <?php echo SITE_NAME; ?></title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --secondary-color: #424242;
            --success-color: #4caf50;
            --warning-color: #ff9800;
        }
        
        .marketplace-hero {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 60px 0;
        }
        
        .search-bar {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .product-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            background-color: #f8f9fa;
            position: relative;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .price-tag {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--success-color);
        }
        
        .rating-stars {
            color: var(--warning-color);
        }
        
        .filter-sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .category-filter {
            list-style: none;
            padding: 0;
        }
        
        .category-filter li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .category-filter li:last-child {
            border-bottom: none;
        }
        
        .category-filter a {
            text-decoration: none;
            color: var(--secondary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-filter a:hover, .category-filter a.active {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .nav-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .nav-item.active, .nav-item:hover {
            color: var(--primary-color);
        }
        
        .featured-section {
            background: #f8f9fa;
            padding: 60px 0;
        }
        
        @media (max-width: 768px) {
            .marketplace-hero {
                padding: 40px 0;
            }
            
            .product-card {
                margin-bottom: 20px;
            }
            
            .filter-sidebar {
                margin-bottom: 20px;
            }
            
            body {
                padding-bottom: 70px;
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
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Marketplace</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">
                                <i class="fas fa-shopping-cart me-1"></i>Cart
                                <span class="badge bg-danger ms-1" id="cartCount">0</span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-mdb-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="../orders.php">My Orders</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2" href="../auth/register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="marketplace-hero">
        <div class="container text-center">
            <h1 class="display-5 fw-bold mb-4">Student Marketplace</h1>
            <p class="lead mb-4">Buy and sell with fellow students</p>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" action="">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <?php if ($category): ?>
                        <input type="hidden" name="category" value="<?php echo $category; ?>">
                    <?php endif; ?>
                    <?php if ($sort !== 'latest'): ?>
                        <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <?php if (!$search && !$category && $page == 1 && !empty($featured_products)): ?>
    <section class="featured-section">
        <div class="container">
            <h3 class="text-center mb-5">Featured Products</h3>
            <div class="row">
                <?php foreach ($featured_products as $product): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-4">
                    <div class="card product-card">
                        <div class="product-image" style="background-image: url('<?php echo $product['first_image'] ? '../uploads/' . $product['first_image'] : '../assets/images/placeholder-product.jpg'; ?>');">
                            <?php if ($product['rating'] > 0): ?>
                                <div class="product-badge">
                                    <i class="fas fa-star"></i> <?php echo number_format($product['rating'], 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars(substr($product['title'], 0, 50)); ?><?php echo strlen($product['title']) > 50 ? '...' : ''; ?></h6>
                            <div class="price-tag"><?php echo formatCurrency($product['price']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 col-md-4">
                <div class="filter-sidebar">
                    <h5 class="mb-3">Filters</h5>
                    
                    <!-- Categories -->
                    <h6 class="mb-2">Categories</h6>
                    <ul class="category-filter">
                        <li>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => '', 'page' => 1])); ?>" class="<?php echo !$category ? 'active' : ''; ?>">
                                All Categories
                                <span class="badge bg-secondary"><?php echo $total_products; ?></span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => $cat['id'], 'page' => 1])); ?>" class="<?php echo $category == $cat['id'] ? 'active' : ''; ?>">
                                <i class="<?php echo $cat['icon']; ?> me-2"></i><?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <!-- Sort Options -->
                    <h6 class="mb-2 mt-4">Sort By</h6>
                    <select class="form-select" onchange="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>&sort=' + this.value">
                        <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest</option>
                        <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                    </select>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9 col-md-8">
                <!-- Results Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0">
                        <?php if ($search): ?>
                            Search results for "<?php echo htmlspecialchars($search); ?>"
                        <?php elseif ($category): ?>
                            <?php 
                            $cat_name = array_filter($categories, fn($c) => $c['id'] == $category);
                            echo $cat_name ? htmlspecialchars(reset($cat_name)['name']) : 'Category';
                            ?>
                        <?php else: ?>
                            All Products
                        <?php endif; ?>
                        <small class="text-muted">(<?php echo $total_products; ?> items)</small>
                    </h5>
                </div>
                
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No products found</h5>
                        <p class="text-muted">Try adjusting your search or filters</p>
                        <a href="index.php" class="btn btn-primary">View All Products</a>
                    </div>
                <?php else: ?>
                    <!-- Products Grid -->
                    <div class="row">
                        <?php foreach ($products as $product): ?>
                        <div class="col-lg-4 col-md-6 col-sm-6 mb-4">
                            <div class="card product-card">
                                <a href="product.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                    <div class="product-image" style="background-image: url('<?php echo $product['first_image'] ? '../uploads/' . $product['first_image'] : '../assets/images/placeholder-product.jpg'; ?>');">
                                        <?php if ($product['rating'] > 0): ?>
                                            <div class="product-badge">
                                                <i class="fas fa-star"></i> <?php echo number_format($product['rating'], 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-title text-dark"><?php echo htmlspecialchars($product['title']); ?></h6>
                                        <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?><?php echo strlen($product['description']) > 80 ? '...' : ''; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="price-tag"><?php echo formatCurrency($product['price']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-eye me-1"></i><?php echo $product['views']; ?>
                                            </small>
                                        </div>
                                        <?php if ($product['category_name']): ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['category_name']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <div class="card-footer bg-transparent border-0 pt-0">
                                    <button class="btn btn-primary btn-sm w-100" onclick="addToCart(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-cart-plus me-1"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Products pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="bottom-nav d-md-none">
        <div class="d-flex">
            <a href="../index.php" class="nav-item">
                <i class="fas fa-home d-block"></i>
                <small>Home</small>
            </a>
            <a href="../rides.php" class="nav-item">
                <i class="fas fa-car d-block"></i>
                <small>Rides</small>
            </a>
            <a href="index.php" class="nav-item active">
                <i class="fas fa-shopping-bag d-block"></i>
                <small>Shop</small>
            </a>
            <a href="<?php echo isLoggedIn() ? '../profile.php' : '../auth/login.php'; ?>" class="nav-item">
                <i class="fas fa-user d-block"></i>
                <small>Profile</small>
            </a>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        // Add to cart functionality
        function addToCart(productId) {
            <?php if (!isLoggedIn()): ?>
                window.location.href = '../auth/login.php?redirect=marketplace/';
                return;
            <?php endif; ?>
            
            // Get existing cart from localStorage
            let cart = JSON.parse(localStorage.getItem('cart') || '[]');
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.id === productId);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({ id: productId, quantity: 1 });
            }
            
            // Save to localStorage
            localStorage.setItem('cart', JSON.stringify(cart));
            
            // Update cart count
            updateCartCount();
            
            // Show success message
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed';
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>Product added to cart!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-mdb-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            
            const bsToast = new mdb.Toast(toast);
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.mdb.toast', () => {
                toast.remove();
            });
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
        
        // Initialize cart count on page load
        updateCartCount();
        
        // Search on enter key
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
