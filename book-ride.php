<?php
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php?redirect=book-ride.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get ride details from URL parameters
$pickup_lat = $_GET['pickup_lat'] ?? '';
$pickup_lng = $_GET['pickup_lng'] ?? '';
$pickup_address = $_GET['pickup_address'] ?? '';
$dropoff_lat = $_GET['dropoff_lat'] ?? '';
$dropoff_lng = $_GET['dropoff_lng'] ?? '';
$dropoff_address = $_GET['dropoff_address'] ?? '';

$error = '';
$success = '';

// Calculate distance and fare
$distance = 0;
$fare = 0;
$duration = 0;

if ($pickup_lat && $pickup_lng && $dropoff_lat && $dropoff_lng) {
    // Calculate distance using Haversine formula as backup
    $earth_radius = 6371; // km
    $lat_diff = deg2rad($dropoff_lat - $pickup_lat);
    $lng_diff = deg2rad($dropoff_lng - $pickup_lng);
    $a = sin($lat_diff/2) * sin($lat_diff/2) + cos(deg2rad($pickup_lat)) * cos(deg2rad($dropoff_lat)) * sin($lng_diff/2) * sin($lng_diff/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    $fare = max($distance * BASE_FARE_PER_KM, 20); // Minimum 20 BDT
    $duration = $distance * 3; // Rough estimate: 3 minutes per km
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $coupon_code = sanitize($_POST['coupon_code'] ?? '');
    $payment_method = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    $final_fare = $fare;
    $discount_amount = 0;
    
    // Apply coupon if provided
    if ($coupon_code) {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND active = TRUE AND (expires_at IS NULL OR expires_at > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit) AND (applies_to = 'rides' OR applies_to = 'both')");
        $stmt->execute([$coupon_code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coupon) {
            if ($fare >= $coupon['minimum_amount']) {
                if ($coupon['discount_type'] == 'percentage') {
                    $discount_amount = ($fare * $coupon['discount_value']) / 100;
                    if ($coupon['maximum_discount']) {
                        $discount_amount = min($discount_amount, $coupon['maximum_discount']);
                    }
                } else {
                    $discount_amount = $coupon['discount_value'];
                }
                $final_fare = max($fare - $discount_amount, 20); // Minimum fare still applies
            } else {
                $error = "Minimum order amount for this coupon is " . formatCurrency($coupon['minimum_amount']);
            }
        } else {
            $error = 'Invalid or expired coupon code';
        }
    }
    
    if (!$error) {
        try {
            $db->beginTransaction();
            
            // Create ride request
            $stmt = $db->prepare("INSERT INTO rides (user_id, pickup_address, pickup_lat, pickup_lng, dropoff_address, dropoff_lat, dropoff_lng, distance, duration, fare, discount_amount, final_fare, coupon_code, payment_method, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'requested', NOW())");
            $stmt->execute([
                $user_id, $pickup_address, $pickup_lat, $pickup_lng, 
                $dropoff_address, $dropoff_lat, $dropoff_lng, 
                $distance, $duration, $fare, $discount_amount, $final_fare, 
                $coupon_code ?: null, $payment_method, $notes
            ]);
            
            $ride_id = $db->lastInsertId();
            
            // Update coupon usage if applied
            if ($coupon_code && isset($coupon)) {
                $stmt = $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                $stmt->execute([$coupon['id']]);
            }
            
            // Create notification for available riders
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, data, created_at)
                SELECT r.user_id, 'New Ride Request', 'A new ride request is available in your area', 'ride', JSON_OBJECT('ride_id', ?, 'pickup', ?, 'dropoff', ?), NOW()
                FROM riders r 
                WHERE r.status = 'approved' AND r.is_online = TRUE
            ");
            $stmt->execute([$ride_id, $pickup_address, $dropoff_address]);
            
            $db->commit();
            
            redirect("ride-status.php?id=$ride_id");
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Failed to book ride: ' . $e->getMessage();
        }
    }
}

// Get available riders nearby (within 10km radius)
$available_riders = [];
if ($pickup_lat && $pickup_lng) {
    $stmt = $db->prepare("
        SELECT r.*, u.name, u.phone, 
               (6371 * acos(cos(radians(?)) * cos(radians(r.current_lat)) * cos(radians(r.current_lng) - radians(?)) + sin(radians(?)) * sin(radians(r.current_lat)))) AS distance
        FROM riders r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'approved' AND r.is_online = TRUE
        AND r.current_lat IS NOT NULL AND r.current_lng IS NOT NULL
        HAVING distance <= 10
        ORDER BY distance ASC
        LIMIT 10
    ");
    $stmt->execute([$pickup_lat, $pickup_lng, $pickup_lat]);
    $available_riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Ride - <?php echo SITE_NAME; ?></title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Mapbox CSS -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #4caf50;
            --warning-color: #ff9800;
        }
        
        .booking-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .ride-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .location-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .location-item:last-child {
            border-bottom: none;
        }
        
        .location-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 16px;
        }
        
        .pickup-icon {
            background: var(--success-color);
        }
        
        .dropoff-icon {
            background: var(--primary-color);
        }
        
        .fare-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .fare-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .fare-row.total {
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .map-container {
            height: 300px;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .rider-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .rider-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 10px rgba(25, 118, 210, 0.1);
        }
        
        .coupon-input {
            position: relative;
        }
        
        .apply-coupon-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        @media (max-width: 768px) {
            .booking-container {
                padding: 10px;
            }
            
            .ride-summary {
                padding: 20px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Home
                </a>
            </div>
        </div>
    </nav>

    <div class="booking-container">
        <div class="row">
            <div class="col-lg-8">
                <!-- Ride Summary -->
                <div class="ride-summary">
                    <h4 class="mb-4"><i class="fas fa-route me-2"></i>Ride Summary</h4>
                    
                    <div class="location-item">
                        <div class="location-icon pickup-icon">
                            <i class="fas fa-circle"></i>
                        </div>
                        <div>
                            <strong>Pickup Location</strong>
                            <div class="text-muted"><?php echo htmlspecialchars($pickup_address); ?></div>
                        </div>
                    </div>
                    
                    <div class="location-item">
                        <div class="location-icon dropoff-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <strong>Drop-off Location</strong>
                            <div class="text-muted"><?php echo htmlspecialchars($dropoff_address); ?></div>
                        </div>
                    </div>
                    
                    <!-- Map -->
                    <div class="map-container">
                        <div id="map"></div>
                    </div>
                    
                    <!-- Trip Details -->
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="h5 text-primary"><?php echo number_format($distance, 1); ?> km</div>
                            <small class="text-muted">Distance</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 text-primary"><?php echo round($duration); ?> min</div>
                            <small class="text-muted">Duration</small>
                        </div>
                        <div class="col-4">
                            <div class="h5 text-success"><?php echo formatCurrency($fare); ?></div>
                            <small class="text-muted">Base Fare</small>
                        </div>
                    </div>
                </div>
                
                <!-- Available Riders -->
                <?php if (!empty($available_riders)): ?>
                <div class="ride-summary">
                    <h5 class="mb-3"><i class="fas fa-users me-2"></i>Available Riders Nearby</h5>
                    <div class="row">
                        <?php foreach (array_slice($available_riders, 0, 6) as $rider): ?>
                        <div class="col-md-6 mb-3">
                            <div class="rider-card">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-user-circle fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($rider['name']); ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-car me-1"></i><?php echo ucfirst($rider['vehicle_type']); ?>
                                            <span class="ms-2">
                                                <i class="fas fa-star text-warning me-1"></i><?php echo number_format($rider['rating'], 1); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?php echo number_format($rider['distance'], 1); ?> km away</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Booking Form -->
                <div class="ride-summary">
                    <h5 class="mb-4"><i class="fas fa-credit-card me-2"></i>Booking Details</h5>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="bookingForm">
                        <!-- Coupon Code -->
                        <div class="mb-3">
                            <label class="form-label">Coupon Code (Optional)</label>
                            <div class="coupon-input">
                                <input type="text" class="form-control" name="coupon_code" id="couponCode" placeholder="Enter coupon code" value="<?php echo htmlspecialchars($_POST['coupon_code'] ?? ''); ?>">
                                <button type="button" class="btn btn-sm btn-outline-primary apply-coupon-btn" onclick="applyCoupon()">Apply</button>
                            </div>
                            <small class="text-muted">Try: STUDENT10, RIDE20</small>
                        </div>
                        
                        <!-- Fare Breakdown -->
                        <div class="fare-breakdown">
                            <div class="fare-row">
                                <span>Base Fare</span>
                                <span id="baseFare"><?php echo formatCurrency($fare); ?></span>
                            </div>
                            <div class="fare-row" id="discountRow" style="display: none;">
                                <span class="text-success">Discount</span>
                                <span class="text-success" id="discountAmount">- <?php echo formatCurrency(0); ?></span>
                            </div>
                            <div class="fare-row total">
                                <span>Total Fare</span>
                                <span id="totalFare"><?php echo formatCurrency($fare); ?></span>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash" checked>
                                <label class="form-check-label" for="cash">
                                    <i class="fas fa-money-bill-wave me-2"></i>Cash
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="bkash" value="bkash">
                                <label class="form-check-label" for="bkash">
                                    <i class="fas fa-mobile-alt me-2"></i>bKash
                                </label>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="mb-3">
                            <label class="form-label">Special Instructions (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any special instructions for the rider..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Book Button -->
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-car me-2"></i>Book Ride Now
                        </button>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                Your ride will be confirmed once a rider accepts
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Mapbox JS -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    
    <script>
        // Initialize Mapbox
        mapboxgl.accessToken = '<?php echo MAPBOX_API_KEY; ?>';
        
        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [<?php echo $pickup_lng; ?>, <?php echo $pickup_lat; ?>],
            zoom: 12
        });
        
        // Add markers
        const pickupMarker = new mapboxgl.Marker({ color: '#4CAF50' })
            .setLngLat([<?php echo $pickup_lng; ?>, <?php echo $pickup_lat; ?>])
            .addTo(map);
            
        const dropoffMarker = new mapboxgl.Marker({ color: '#1976d2' })
            .setLngLat([<?php echo $dropoff_lng; ?>, <?php echo $dropoff_lat; ?>])
            .addTo(map);
        
        // Draw route
        fetch(`https://api.mapbox.com/directions/v5/mapbox/driving/<?php echo $pickup_lng; ?>,<?php echo $pickup_lat; ?>;<?php echo $dropoff_lng; ?>,<?php echo $dropoff_lat; ?>?access_token=${mapboxgl.accessToken}&geometries=geojson`)
            .then(response => response.json())
            .then(data => {
                if (data.routes && data.routes.length > 0) {
                    const route = data.routes[0];
                    
                    map.addSource('route', {
                        type: 'geojson',
                        data: {
                            type: 'Feature',
                            properties: {},
                            geometry: route.geometry
                        }
                    });
                    
                    map.addLayer({
                        id: 'route',
                        type: 'line',
                        source: 'route',
                        layout: {
                            'line-join': 'round',
                            'line-cap': 'round'
                        },
                        paint: {
                            'line-color': '#1976d2',
                            'line-width': 5,
                            'line-opacity': 0.8
                        }
                    });
                    
                    // Fit map to route
                    const bounds = new mapboxgl.LngLatBounds();
                    bounds.extend([<?php echo $pickup_lng; ?>, <?php echo $pickup_lat; ?>]);
                    bounds.extend([<?php echo $dropoff_lng; ?>, <?php echo $dropoff_lat; ?>]);
                    map.fitBounds(bounds, { padding: 50 });
                }
            });
        
        // Add rider markers
        <?php foreach ($available_riders as $rider): ?>
            <?php if ($rider['current_lat'] && $rider['current_lng']): ?>
                const riderMarker<?php echo $rider['id']; ?> = new mapboxgl.Marker({ color: '#FF9800' })
                    .setLngLat([<?php echo $rider['current_lng']; ?>, <?php echo $rider['current_lat']; ?>])
                    .setPopup(new mapboxgl.Popup().setHTML(`
                        <div class="text-center">
                            <strong><?php echo htmlspecialchars($rider['name']); ?></strong><br>
                            <small><?php echo ucfirst($rider['vehicle_type']); ?> • ⭐ <?php echo number_format($rider['rating'], 1); ?></small>
                        </div>
                    `))
                    .addTo(map);
            <?php endif; ?>
        <?php endforeach; ?>
        
        // Coupon application
        function applyCoupon() {
            const couponCode = document.getElementById('couponCode').value.trim();
            if (!couponCode) return;
            
            // In a real application, this would be an AJAX call
            // For now, we'll simulate the discount calculation
            const baseFare = <?php echo $fare; ?>;
            let discount = 0;
            
            // Demo coupon logic
            if (couponCode.toUpperCase() === 'STUDENT10') {
                discount = baseFare * 0.10;
            } else if (couponCode.toUpperCase() === 'RIDE20') {
                discount = Math.min(20, baseFare);
            }
            
            if (discount > 0) {
                const finalFare = Math.max(baseFare - discount, 20);
                
                document.getElementById('discountRow').style.display = 'flex';
                document.getElementById('discountAmount').textContent = '- ' + discount.toFixed(2) + ' BDT';
                document.getElementById('totalFare').textContent = finalFare.toFixed(2) + ' BDT';
                
                // Show success message
                const alert = document.createElement('div');
                alert.className = 'alert alert-success alert-dismissible fade show mt-2';
                alert.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>Coupon applied successfully!
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                `;
                document.getElementById('couponCode').parentNode.appendChild(alert);
            } else {
                // Show error message
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger alert-dismissible fade show mt-2';
                alert.innerHTML = `
                    <i class="fas fa-exclamation-circle me-2"></i>Invalid coupon code
                    <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
                `;
                document.getElementById('couponCode').parentNode.appendChild(alert);
            }
        }
        
        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
            }
        });
    </script>
</body>
</html>
