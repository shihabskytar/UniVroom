<?php
require_once '../config/config.php';

if (!isLoggedIn() || !isRider()) {
    redirect('auth/login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get rider info
$stmt = $db->prepare("
    SELECT r.*, u.name, u.email, u.phone 
    FROM riders r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.user_id = ?
");
$stmt->execute([$user_id]);
$rider = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rider || $rider['status'] !== 'approved') {
    redirect('index.php');
}

// Handle online/offline toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    $new_status = $rider['is_online'] ? 0 : 1;
    $stmt = $db->prepare("UPDATE riders SET is_online = ?, last_location_update = NOW() WHERE user_id = ?");
    $stmt->execute([$new_status, $user_id]);
    
    $rider['is_online'] = $new_status;
}

// Get pending ride requests
$stmt = $db->prepare("
    SELECT r.*, u.name as passenger_name, u.phone as passenger_phone
    FROM rides r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'requested'
    AND r.rider_id IS NULL
    ORDER BY r.created_at ASC
    LIMIT 10
");
$stmt->execute();
$pending_rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rider's active rides
$stmt = $db->prepare("
    SELECT r.*, u.name as passenger_name, u.phone as passenger_phone
    FROM rides r
    JOIN users u ON r.user_id = u.id
    WHERE r.rider_id = (SELECT id FROM riders WHERE user_id = ?)
    AND r.status IN ('accepted', 'picked_up')
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$active_rides = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rider stats
$stmt = $db->prepare("SELECT * FROM riders WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Dashboard - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
            --warning-color: #ff9800;
        }
        
        .rider-header {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 30px 0;
        }
        
        .status-toggle {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: -30px auto 30px;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .ride-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .online-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .online {
            background: var(--success-color);
        }
        
        .offline {
            background: #dc3545;
        }
        
        .location-item {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        
        .location-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: white;
            font-size: 12px;
        }
        
        .pickup-icon {
            background: var(--success-color);
        }
        
        .dropoff-icon {
            background: var(--primary-color);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-home me-1"></i>Home
                </a>
            </div>
        </div>
    </nav>

    <div class="rider-header">
        <div class="container text-center">
            <h2><i class="fas fa-car me-2"></i>Rider Dashboard</h2>
            <p class="mb-0">Welcome back, <?php echo htmlspecialchars($rider['name']); ?>!</p>
        </div>
    </div>

    <div class="container">
        <!-- Status Toggle -->
        <div class="status-toggle">
            <h5 class="mb-3">
                <span class="online-indicator <?php echo $rider['is_online'] ? 'online' : 'offline'; ?>"></span>
                You are <?php echo $rider['is_online'] ? 'ONLINE' : 'OFFLINE'; ?>
            </h5>
            
            <form method="POST" class="d-inline">
                <button type="submit" name="toggle_status" class="btn btn-<?php echo $rider['is_online'] ? 'danger' : 'success'; ?> btn-lg">
                    <i class="fas fa-power-off me-2"></i>
                    Go <?php echo $rider['is_online'] ? 'Offline' : 'Online'; ?>
                </button>
            </form>
            
            <div class="mt-3">
                <small class="text-muted">
                    <?php echo $rider['is_online'] ? 'You will receive ride requests' : 'You will not receive ride requests'; ?>
                </small>
            </div>
        </div>

        <div class="row">
            <!-- Stats -->
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <i class="fas fa-route fa-2x text-primary mb-2"></i>
                    <h4><?php echo $stats['total_trips']; ?></h4>
                    <small class="text-muted">Total Trips</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <i class="fas fa-star fa-2x text-warning mb-2"></i>
                    <h4><?php echo number_format($stats['rating'], 1); ?></h4>
                    <small class="text-muted">Rating</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave fa-2x text-success mb-2"></i>
                    <h4><?php echo formatCurrency($stats['total_earnings']); ?></h4>
                    <small class="text-muted">Total Earnings</small>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <i class="fas fa-car fa-2x text-info mb-2"></i>
                    <h4><?php echo ucfirst($stats['vehicle_type']); ?></h4>
                    <small class="text-muted"><?php echo htmlspecialchars($stats['plate_no']); ?></small>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Active Rides -->
            <div class="col-lg-6">
                <h4 class="mb-3">
                    <i class="fas fa-car-side me-2"></i>Active Rides
                    <?php if (!empty($active_rides)): ?>
                        <span class="badge bg-primary"><?php echo count($active_rides); ?></span>
                    <?php endif; ?>
                </h4>
                
                <?php if (empty($active_rides)): ?>
                    <div class="ride-card text-center">
                        <i class="fas fa-car fa-3x text-muted mb-3"></i>
                        <h5>No active rides</h5>
                        <p class="text-muted">Your accepted rides will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_rides as $ride): ?>
                        <div class="ride-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1">Ride #<?php echo $ride['id']; ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($ride['passenger_name']); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $ride['status'] == 'accepted' ? 'warning' : 'primary'; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $ride['status'])); ?>
                                </span>
                            </div>
                            
                            <div class="location-item">
                                <div class="location-icon pickup-icon">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Pickup</small>
                                    <div><?php echo htmlspecialchars($ride['pickup_address']); ?></div>
                                </div>
                            </div>
                            
                            <div class="location-item">
                                <div class="location-icon dropoff-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Drop-off</small>
                                    <div><?php echo htmlspecialchars($ride['dropoff_address']); ?></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="text-success fw-bold">
                                    <?php echo formatCurrency($ride['final_fare']); ?>
                                </div>
                                <a href="../ride-status.php?id=<?php echo $ride['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pending Requests -->
            <div class="col-lg-6">
                <h4 class="mb-3">
                    <i class="fas fa-clock me-2"></i>Ride Requests
                    <?php if (!empty($pending_rides)): ?>
                        <span class="badge bg-warning"><?php echo count($pending_rides); ?></span>
                    <?php endif; ?>
                </h4>
                
                <?php if (empty($pending_rides)): ?>
                    <div class="ride-card text-center">
                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                        <h5>No pending requests</h5>
                        <p class="text-muted">
                            <?php echo $rider['is_online'] ? 'New ride requests will appear here' : 'Go online to receive ride requests'; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_rides as $ride): ?>
                        <div class="ride-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1">Ride #<?php echo $ride['id']; ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($ride['passenger_name']); ?></small>
                                </div>
                                <small class="text-muted"><?php echo timeAgo($ride['created_at']); ?></small>
                            </div>
                            
                            <div class="location-item">
                                <div class="location-icon pickup-icon">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">From</small>
                                    <div><?php echo htmlspecialchars($ride['pickup_address']); ?></div>
                                </div>
                            </div>
                            
                            <div class="location-item">
                                <div class="location-icon dropoff-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div>
                                    <small class="text-muted">To</small>
                                    <div><?php echo htmlspecialchars($ride['dropoff_address']); ?></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <div class="text-success fw-bold"><?php echo formatCurrency($ride['final_fare']); ?></div>
                                    <small class="text-muted"><?php echo number_format($ride['distance'], 1); ?> km</small>
                                </div>
                                <div>
                                    <a href="../ride-status.php?id=<?php echo $ride['id']; ?>" class="btn btn-success btn-sm me-1">
                                        <i class="fas fa-check me-1"></i>Accept
                                    </a>
                                    <a href="tel:<?php echo $ride['passenger_phone']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        // Auto-refresh for new ride requests
        setInterval(function() {
            if (<?php echo $rider['is_online'] ? 'true' : 'false'; ?>) {
                fetch('../api/get-ride-requests.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.new_requests > 0) {
                            // Show notification or reload page
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error checking requests:', error));
            }
        }, 15000); // Check every 15 seconds
    </script>
</body>
</html>
