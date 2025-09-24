<?php
require_once '../config/config.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('admin/login.php');
}

$db = getDB();

// Get dashboard statistics
$stats = [];

// Total users
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$stats['users'] = $stmt->fetchColumn();

// Total riders
$stmt = $db->query("SELECT COUNT(*) as count FROM riders");
$stats['riders'] = $stmt->fetchColumn();

// Total rides
$stmt = $db->query("SELECT COUNT(*) as count FROM rides");
$stats['rides'] = $stmt->fetchColumn();

// Total products
$stmt = $db->query("SELECT COUNT(*) as count FROM products");
$stats['products'] = $stmt->fetchColumn();

// Total orders
$stmt = $db->query("SELECT COUNT(*) as count FROM orders");
$stats['orders'] = $stmt->fetchColumn();

// Revenue
$stmt = $db->query("SELECT SUM(final_amount) as revenue FROM orders WHERE status != 'cancelled'");
$stats['revenue'] = $stmt->fetchColumn() ?: 0;

// Recent activities
$stmt = $db->query("
    SELECT 'ride' as type, id, created_at, status, 
           CONCAT('Ride #', id, ' - ', status) as description
    FROM rides 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'order' as type, id, created_at, status,
           CONCAT('Order #', id, ' - ', status) as description
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC 
    LIMIT 10
");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending riders
$stmt = $db->query("
    SELECT r.*, u.name, u.email 
    FROM riders r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.status = 'pending' 
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$pending_riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --sidebar-width: 250px;
        }
        
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #2c3e50;
            color: white;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .admin-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #34495e;
            border-bottom: 1px solid #3a5169;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li a {
            display: block;
            padding: 15px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background: var(--primary-color);
            color: white;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .activity-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </h5>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="index.php" class="active">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a></li>
            <li><a href="users.php">
                <i class="fas fa-users me-2"></i>Users
            </a></li>
            <li><a href="riders.php">
                <i class="fas fa-car me-2"></i>Riders
            </a></li>
            <li><a href="rides.php">
                <i class="fas fa-route me-2"></i>Rides
            </a></li>
            <li><a href="products.php">
                <i class="fas fa-box me-2"></i>Products
            </a></li>
            <li><a href="orders.php">
                <i class="fas fa-shopping-cart me-2"></i>Orders
            </a></li>
            <li><a href="coupons.php">
                <i class="fas fa-tags me-2"></i>Coupons
            </a></li>
            <li><a href="announcements.php">
                <i class="fas fa-bullhorn me-2"></i>Announcements
            </a></li>
            <li><a href="settings.php">
                <i class="fas fa-cog me-2"></i>Settings
            </a></li>
            <li><a href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="admin-content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Dashboard</h2>
                <div>
                    <span class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4><?php echo number_format($stats['users']); ?></h4>
                        <p class="text-muted mb-0">Total Users</p>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="fas fa-car"></i>
                        </div>
                        <h4><?php echo number_format($stats['riders']); ?></h4>
                        <p class="text-muted mb-0">Total Riders</p>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-info">
                            <i class="fas fa-route"></i>
                        </div>
                        <h4><?php echo number_format($stats['rides']); ?></h4>
                        <p class="text-muted mb-0">Total Rides</p>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-warning">
                            <i class="fas fa-box"></i>
                        </div>
                        <h4><?php echo number_format($stats['products']); ?></h4>
                        <p class="text-muted mb-0">Products</p>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-purple">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h4><?php echo number_format($stats['orders']); ?></h4>
                        <p class="text-muted mb-0">Orders</p>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h4><?php echo formatCurrency($stats['revenue']); ?></h4>
                        <p class="text-muted mb-0">Revenue</p>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Activities -->
                <div class="col-lg-8">
                    <div class="activity-card">
                        <h5 class="mb-4">Recent Activities</h5>
                        
                        <?php if (empty($recent_activities)): ?>
                            <p class="text-muted text-center py-4">No recent activities</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-<?php echo $activity['type'] == 'ride' ? 'car' : 'shopping-cart'; ?> me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo timeAgo($activity['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Pending Riders -->
                <div class="col-lg-4">
                    <div class="activity-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Pending Riders</h5>
                            <a href="riders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        
                        <?php if (empty($pending_riders)): ?>
                            <p class="text-muted text-center py-4">No pending riders</p>
                        <?php else: ?>
                            <?php foreach ($pending_riders as $rider): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($rider['name']); ?></div>
                                        <small class="text-muted"><?php echo ucfirst($rider['vehicle_type']); ?> - <?php echo htmlspecialchars($rider['plate_no']); ?></small>
                                    </div>
                                    <div>
                                        <a href="riders.php?action=approve&id=<?php echo $rider['id']; ?>" class="btn btn-sm btn-success me-1" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="riders.php?action=reject&id=<?php echo $rider['id']; ?>" class="btn btn-sm btn-danger" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
</body>
</html>
