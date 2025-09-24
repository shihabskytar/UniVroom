<?php
require_once 'config/config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$user = null;

if ($isLoggedIn) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If user not found in database, logout the session
        if (!$user) {
            session_destroy();
            $isLoggedIn = false;
            $user = null;
        }
    } catch (Exception $e) {
        // If database error, treat as not logged in
        $isLoggedIn = false;
        $user = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - By Students, For Students</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Mapbox CSS -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --secondary-color: #424242;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 100px 0;
        }
        
        .ride-panel {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: -50px;
            position: relative;
            z-index: 10;
        }
        
        .map-container {
            height: 500px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            background: #f8f9fa;
            position: relative;
        }
        
        #map {
            width: 100%;
            height: 100%;
            border-radius: 15px;
        }
        
        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            color: #666;
            font-size: 14px;
        }
        
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
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
        
        .fare-display {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        .location-input {
            position: relative;
        }
        
        .location-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .suggestion-item:hover {
            background: #f5f5f5;
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0;
            }
            
            .ride-panel {
                margin: 20px;
                padding: 20px;
            }
            
            .map-container {
                height: 300px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--primary-color);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="marketplace/">Marketplace</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-mdb-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user['name'] ?? 'User'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="rides.php"><i class="fas fa-car me-2"></i>My Rides</a></li>
                                <li><a class="dropdown-item" href="orders.php"><i class="fas fa-shopping-bag me-2"></i>My Orders</a></li>
                                <?php if (isRider()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="rider/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Rider Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2" href="auth/register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">UniVroom</h1>
            <p class="lead mb-4">By Students, For Students</p>
            <p class="mb-5">Safe rides and student marketplace - all in one platform</p>
            <?php if (!$isLoggedIn): ?>
                <a href="auth/register.php" class="btn btn-light btn-lg me-3">Get Started</a>
                <a href="auth/login.php" class="btn btn-outline-light btn-lg">Login</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container-fluid">
        <div class="row">
            <!-- Left Panel - Ride Booking -->
            <div class="col-lg-4 col-md-12">
                <div class="ride-panel">
                    <h3 class="mb-4"><i class="fas fa-map-marker-alt me-2"></i>Book a Ride</h3>
                    
                    <?php if ($isLoggedIn): ?>
                        <form id="rideBookingForm">
                            <div class="mb-3 location-input">
                                <label class="form-label">Pickup Location</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="pickupInput" placeholder="Enter pickup location" required>
                                    <button class="btn btn-outline-secondary" type="button" id="getCurrentLocation">
                                        <i class="fas fa-crosshairs"></i>
                                    </button>
                                </div>
                                <div class="location-suggestions" id="pickupSuggestions"></div>
                            </div>
                            
                            <div class="mb-3 location-input">
                                <label class="form-label">Drop-off Location</label>
                                <input type="text" class="form-control" id="dropoffInput" placeholder="Enter destination" required>
                                <div class="location-suggestions" id="dropoffSuggestions"></div>
                            </div>
                            
                            <div class="fare-display" id="fareDisplay" style="display: none;">
                                <h5 class="mb-2">Estimated Fare</h5>
                                <div class="h4 mb-1" id="fareAmount">-</div>
                                <small id="fareDetails">Distance: - | Duration: -</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100" id="bookRideBtn" disabled>
                                <i class="fas fa-car me-2"></i>Book Ride
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-user-lock fa-3x text-muted mb-3"></i>
                            <h5>Login Required</h5>
                            <p class="text-muted">Please login to book a ride</p>
                            <a href="auth/login.php" class="btn btn-primary">Login Now</a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="mt-4">
                        <h6 class="mb-3">Quick Actions</h6>
                        <div class="row">
                            <div class="col-6">
                                <a href="marketplace/" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-shopping-bag d-block mb-1"></i>
                                    <small>Marketplace</small>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo $isLoggedIn ? 'rides.php' : 'auth/login.php'; ?>" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-history d-block mb-1"></i>
                                    <small>My Rides</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Map -->
            <div class="col-lg-8 col-md-12">
                <div class="map-container">
                    <div class="map-loading" id="mapLoading">
                        <i class="fas fa-spinner fa-spin me-2"></i>Loading map...
                    </div>
                    <div id="map"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col">
                    <h2 class="fw-bold">Why Choose UniVroom?</h2>
                    <p class="text-muted">Built by students, for students</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                            <h5>Safe & Secure</h5>
                            <p class="text-muted">Verified student riders and secure payment options</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                            <h5>Affordable Rates</h5>
                            <p class="text-muted">Student-friendly pricing at just 15 BDT per km</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-shopping-cart fa-3x text-warning mb-3"></i>
                            <h5>Student Marketplace</h5>
                            <p class="text-muted">Buy and sell items with fellow students</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile Bottom Navigation -->
    <div class="bottom-nav d-md-none">
        <div class="d-flex">
            <a href="index.php" class="nav-item active">
                <i class="fas fa-home d-block"></i>
                <small>Home</small>
            </a>
            <a href="rides.php" class="nav-item">
                <i class="fas fa-car d-block"></i>
                <small>Rides</small>
            </a>
            <a href="marketplace/" class="nav-item">
                <i class="fas fa-shopping-bag d-block"></i>
                <small>Shop</small>
            </a>
            <a href="<?php echo $isLoggedIn ? 'profile.php' : 'auth/login.php'; ?>" class="nav-item">
                <i class="fas fa-user d-block"></i>
                <small>Profile</small>
            </a>
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
            center: [90.4125, 23.8103], // Dhaka center
            zoom: 12
        });
        
        // Hide loading indicator when map loads
        map.on('load', function() {
            document.getElementById('mapLoading').style.display = 'none';
            console.log('Map loaded successfully');
        });
        
        // Handle map errors
        map.on('error', function(e) {
            console.error('Map error:', e);
            document.getElementById('mapLoading').innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Map failed to load';
        });
        
        // Add navigation controls
        map.addControl(new mapboxgl.NavigationControl());
        
        // Variables for route and markers
        let pickupMarker, dropoffMarker;
        let pickupCoords, dropoffCoords;
        
        // Get current location
        document.getElementById('getCurrentLocation')?.addEventListener('click', function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Reverse geocoding to get address
                    fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${mapboxgl.accessToken}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.features.length > 0) {
                                document.getElementById('pickupInput').value = data.features[0].place_name;
                                pickupCoords = [lng, lat];
                                updatePickupMarker();
                                calculateFare();
                            }
                        });
                });
            }
        });
        
        // Location input handlers
        document.getElementById('pickupInput')?.addEventListener('input', function() {
            searchLocation(this.value, 'pickup');
        });
        
        document.getElementById('dropoffInput')?.addEventListener('input', function() {
            searchLocation(this.value, 'dropoff');
        });
        
        // Search location function
        function searchLocation(query, type) {
            if (query.length < 3) return;
            
            fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${mapboxgl.accessToken}&country=BD&limit=5`)
                .then(response => response.json())
                .then(data => {
                    const suggestions = document.getElementById(type + 'Suggestions');
                    suggestions.innerHTML = '';
                    
                    data.features.forEach(feature => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.textContent = feature.place_name;
                        div.addEventListener('click', function() {
                            document.getElementById(type + 'Input').value = feature.place_name;
                            
                            if (type === 'pickup') {
                                pickupCoords = feature.center;
                                updatePickupMarker();
                            } else {
                                dropoffCoords = feature.center;
                                updateDropoffMarker();
                            }
                            
                            suggestions.style.display = 'none';
                            calculateFare();
                        });
                        suggestions.appendChild(div);
                    });
                    
                    suggestions.style.display = data.features.length > 0 ? 'block' : 'none';
                });
        }
        
        // Update markers
        function updatePickupMarker() {
            if (pickupMarker) pickupMarker.remove();
            if (pickupCoords) {
                pickupMarker = new mapboxgl.Marker({ color: '#4CAF50' })
                    .setLngLat(pickupCoords)
                    .addTo(map);
                map.flyTo({ center: pickupCoords, zoom: 14 });
            }
        }
        
        function updateDropoffMarker() {
            if (dropoffMarker) dropoffMarker.remove();
            if (dropoffCoords) {
                dropoffMarker = new mapboxgl.Marker({ color: '#F44336' })
                    .setLngLat(dropoffCoords)
                    .addTo(map);
            }
        }
        
        // Calculate fare
        function calculateFare() {
            if (!pickupCoords || !dropoffCoords) return;
            
            // Calculate distance using Mapbox Directions API
            fetch(`https://api.mapbox.com/directions/v5/mapbox/driving/${pickupCoords[0]},${pickupCoords[1]};${dropoffCoords[0]},${dropoffCoords[1]}?access_token=${mapboxgl.accessToken}&geometries=geojson`)
                .then(response => response.json())
                .then(data => {
                    if (data.routes && data.routes.length > 0) {
                        const route = data.routes[0];
                        const distance = (route.distance / 1000).toFixed(2); // Convert to km
                        const duration = Math.round(route.duration / 60); // Convert to minutes
                        const fare = (distance * <?php echo BASE_FARE_PER_KM; ?>).toFixed(2);
                        
                        // Display fare
                        document.getElementById('fareAmount').textContent = fare + ' BDT';
                        document.getElementById('fareDetails').textContent = `Distance: ${distance} km | Duration: ${duration} min`;
                        document.getElementById('fareDisplay').style.display = 'block';
                        document.getElementById('bookRideBtn').disabled = false;
                        
                        // Draw route on map
                        drawRoute(route.geometry);
                        
                        // Fit map to show both markers
                        const bounds = new mapboxgl.LngLatBounds();
                        bounds.extend(pickupCoords);
                        bounds.extend(dropoffCoords);
                        map.fitBounds(bounds, { padding: 50 });
                    }
                });
        }
        
        // Draw route on map
        function drawRoute(geometry) {
            if (map.getSource('route')) {
                map.removeLayer('route');
                map.removeSource('route');
            }
            
            map.addSource('route', {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    properties: {},
                    geometry: geometry
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
        }
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.location-input')) {
                document.querySelectorAll('.location-suggestions').forEach(el => {
                    el.style.display = 'none';
                });
            }
        });
        
        // Handle ride booking form
        document.getElementById('rideBookingForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!pickupCoords || !dropoffCoords) {
                alert('Please select both pickup and drop-off locations');
                return;
            }
            
            // Redirect to ride booking page with coordinates
            const params = new URLSearchParams({
                pickup_lat: pickupCoords[1],
                pickup_lng: pickupCoords[0],
                pickup_address: document.getElementById('pickupInput').value,
                dropoff_lat: dropoffCoords[1],
                dropoff_lng: dropoffCoords[0],
                dropoff_address: document.getElementById('dropoffInput').value
            });
            
            window.location.href = 'book-ride.php?' + params.toString();
        });
    </script>
</body>
</html>
