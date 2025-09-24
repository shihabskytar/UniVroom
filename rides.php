<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user's rides
$stmt = $db->prepare("
    SELECT r.*, 
           rider_user.name as rider_name, rider_user.phone as rider_phone,
           riders.vehicle_type, riders.plate_no, riders.rating as rider_rating
    FROM rides r
    LEFT JOIN riders ON r.rider_id = riders.id
    LEFT JOIN users rider_user ON riders.user_id = rider_user.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$rides = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rides - <?php echo SITE_NAME; ?></title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
        }
        
        .ride-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .ride-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 15px;
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
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Home
                </a>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-car me-2"></i>My Rides</h2>
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Book New Ride
            </a>
        </div>

        <?php if (empty($rides)): ?>
            <div class="empty-state">
                <i class="fas fa-car fa-4x text-muted mb-4"></i>
                <h4>No rides yet</h4>
                <p class="text-muted mb-4">You haven't booked any rides yet</p>
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-car me-2"></i>Book Your First Ride
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($rides as $ride): ?>
                <div class="ride-card">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">Ride #<?php echo $ride['id']; ?></h5>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($ride['created_at'])); ?></small>
                                </div>
                                <div>
                                    <?php
                                    $status_colors = [
                                        'requested' => 'warning',
                                        'accepted' => 'info',
                                        'picked_up' => 'primary',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $status_color = $status_colors[$ride['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                        <?php echo ucwords(str_replace('_', ' ', $ride['status'])); ?>
                                    </span>
                                </div>
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
                            
                            <?php if ($ride['rider_name']): ?>
                                <div class="mt-3 p-2 bg-light rounded">
                                    <small class="text-muted">Rider:</small>
                                    <strong><?php echo htmlspecialchars($ride['rider_name']); ?></strong>
                                    <?php if ($ride['vehicle_type']): ?>
                                        <span class="ms-2">
                                            <i class="fas fa-car me-1"></i><?php echo ucfirst($ride['vehicle_type']); ?>
                                            <?php if ($ride['plate_no']): ?>
                                                - <?php echo htmlspecialchars($ride['plate_no']); ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <div class="mb-2">
                                <div class="h5 text-success"><?php echo formatCurrency($ride['final_fare']); ?></div>
                                <small class="text-muted"><?php echo number_format($ride['distance'], 1); ?> km</small>
                            </div>
                            
                            <div class="d-flex flex-column gap-2">
                                <a href="ride-status.php?id=<?php echo $ride['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                
                                <?php if ($ride['status'] == 'completed'): ?>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="rateRide(<?php echo $ride['id']; ?>)">
                                        <i class="fas fa-star me-1"></i>Rate Ride
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
        function rateRide(rideId) {
            // Simple rating implementation
            const rating = prompt('Rate this ride (1-5 stars):');
            if (rating && rating >= 1 && rating <= 5) {
                fetch('api/rate-ride.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ride_id: rideId,
                        rating: parseInt(rating)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thank you for your rating!');
                    } else {
                        alert('Failed to submit rating');
                    }
                });
            }
        }
    </script>
</body>
</html>
