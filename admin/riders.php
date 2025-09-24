<?php
require_once '../config/config.php';

if (!isAdmin()) {
    redirect('admin/login.php');
}

$db = getDB();
$message = '';

// Handle rider actions
if ($_GET['action'] ?? '' && $_GET['id'] ?? '') {
    $action = $_GET['action'];
    $rider_id = intval($_GET['id']);
    
    try {
        if ($action == 'approve') {
            $stmt = $db->prepare("UPDATE riders SET status = 'approved' WHERE id = ?");
            $stmt->execute([$rider_id]);
            $message = 'Rider approved successfully';
        } elseif ($action == 'reject') {
            $stmt = $db->prepare("UPDATE riders SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$rider_id]);
            $message = 'Rider rejected';
        } elseif ($action == 'suspend') {
            $stmt = $db->prepare("UPDATE riders SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$rider_id]);
            $message = 'Rider suspended';
        }
    } catch (Exception $e) {
        $message = 'Action failed: ' . $e->getMessage();
    }
}

// Get all riders
$stmt = $db->query("
    SELECT r.*, u.name, u.email, u.phone, u.created_at as user_created
    FROM riders r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.created_at DESC
");
$riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Riders - Admin</title>
    
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
        
        .rider-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
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
            <li><a href="index.php">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a></li>
            <li><a href="users.php">
                <i class="fas fa-users me-2"></i>Users
            </a></li>
            <li><a href="riders.php" class="active">
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
                <h2>Manage Riders</h2>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Riders List -->
            <div class="row">
                <?php foreach ($riders as $rider): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="rider-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($rider['name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($rider['email']); ?></small>
                                </div>
                                <span class="badge bg-<?php 
                                    echo match($rider['status']) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'suspended' => 'secondary',
                                        default => 'secondary'
                                    };
                                ?> status-badge">
                                    <?php echo ucfirst($rider['status']); ?>
                                </span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Vehicle</small>
                                        <div><?php echo ucfirst($rider['vehicle_type']); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Plate No</small>
                                        <div><?php echo htmlspecialchars($rider['plate_no']); ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($rider['brand'] && $rider['model']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Model</small>
                                        <div><?php echo htmlspecialchars($rider['brand'] . ' ' . $rider['model']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <div class="row">
                                    <div class="col-4">
                                        <small class="text-muted">Trips</small>
                                        <div class="fw-bold"><?php echo $rider['total_trips']; ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Rating</small>
                                        <div class="fw-bold"><?php echo number_format($rider['rating'], 1); ?></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Status</small>
                                        <div class="fw-bold">
                                            <?php if ($rider['is_online']): ?>
                                                <span class="text-success">Online</span>
                                            <?php else: ?>
                                                <span class="text-muted">Offline</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-muted mb-3">
                                <small>Joined: <?php echo date('M j, Y', strtotime($rider['created_at'])); ?></small>
                            </div>
                            
                            <!-- Actions -->
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if ($rider['status'] == 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $rider['id']; ?>" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Approve this rider?')">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </a>
                                    <a href="?action=reject&id=<?php echo $rider['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Reject this rider?')">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </a>
                                <?php elseif ($rider['status'] == 'approved'): ?>
                                    <a href="?action=suspend&id=<?php echo $rider['id']; ?>" 
                                       class="btn btn-warning btn-sm"
                                       onclick="return confirm('Suspend this rider?')">
                                        <i class="fas fa-ban me-1"></i>Suspend
                                    </a>
                                <?php elseif ($rider['status'] == 'suspended'): ?>
                                    <a href="?action=approve&id=<?php echo $rider['id']; ?>" 
                                       class="btn btn-success btn-sm"
                                       onclick="return confirm('Reactivate this rider?')">
                                        <i class="fas fa-check me-1"></i>Reactivate
                                    </a>
                                <?php endif; ?>
                                
                                <a href="tel:<?php echo $rider['phone']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <a href="mailto:<?php echo $rider['email']; ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($riders)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-car fa-4x text-muted mb-3"></i>
                    <h4>No riders found</h4>
                    <p class="text-muted">No riders have registered yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
</body>
</html>
