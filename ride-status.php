<?php
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$ride_id = intval($_GET['id'] ?? 0);

if (!$ride_id) {
    redirect('index.php');
}

// Get ride details
$stmt = $db->prepare("
    SELECT r.*, 
           u.name as user_name, u.phone as user_phone,
           rider_user.name as rider_name, rider_user.phone as rider_phone,
           riders.vehicle_type, riders.brand, riders.model, riders.plate_no, riders.rating as rider_rating
    FROM rides r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN riders ON r.rider_id = riders.id
    LEFT JOIN users rider_user ON riders.user_id = rider_user.id
    WHERE r.id = ? AND (r.user_id = ? OR riders.user_id = ?)
");
$stmt->execute([$ride_id, $user_id, $user_id]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ride) {
    redirect('index.php');
}

$is_rider = ($ride['rider_id'] && isRider() && $_SESSION['user_id'] != $ride['user_id']);
$is_passenger = ($ride['user_id'] == $user_id);

// Handle ride actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action == 'cancel_ride' && $is_passenger && in_array($ride['status'], ['requested', 'accepted'])) {
            $stmt = $db->prepare("UPDATE rides SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ride_id]);
            
            // Notify rider if assigned
            if ($ride['rider_id']) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, data, created_at) VALUES (?, 'Ride Cancelled', 'The passenger has cancelled the ride', 'ride', JSON_OBJECT('ride_id', ?), NOW())");
                $stmt->execute([$ride['rider_id'], $ride_id]);
            }
            
            redirect('rides.php');
            
        } elseif ($action == 'accept_ride' && $is_rider && $ride['status'] == 'requested') {
            $stmt = $db->prepare("UPDATE rides SET rider_id = (SELECT id FROM riders WHERE user_id = ?), status = 'accepted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user_id, $ride_id]);
            
            // Notify passenger
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, data, created_at) VALUES (?, 'Ride Accepted', 'Your ride has been accepted by a rider', 'ride', JSON_OBJECT('ride_id', ?), NOW())");
            $stmt->execute([$ride['user_id'], $ride_id]);
            
            redirect("ride-status.php?id=$ride_id");
            
        } elseif ($action == 'start_ride' && $is_rider && $ride['status'] == 'accepted') {
            $stmt = $db->prepare("UPDATE rides SET status = 'picked_up', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ride_id]);
            
            redirect("ride-status.php?id=$ride_id");
            
        } elseif ($action == 'complete_ride' && $is_rider && $ride['status'] == 'picked_up') {
            $stmt = $db->prepare("UPDATE rides SET status = 'completed', payment_status = 'paid', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ride_id]);
            
            // Update rider stats
            $stmt = $db->prepare("UPDATE riders SET total_trips = total_trips + 1, total_earnings = total_earnings + ? WHERE user_id = ?");
            $stmt->execute([$ride['final_fare'], $user_id]);
            
            redirect("ride-status.php?id=$ride_id");
        }
    } catch (Exception $e) {
        $error = 'Action failed: ' . $e->getMessage();
    }
}

// Get messages for this ride
$stmt = $db->prepare("
    SELECT m.*, u.name as sender_name 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.ride_id = ? 
    ORDER BY m.created_at ASC
");
$stmt->execute([$ride_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark messages as read
$stmt = $db->prepare("UPDATE messages SET is_read = TRUE WHERE ride_id = ? AND receiver_id = ?");
$stmt->execute([$ride_id, $user_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ride Status - <?php echo SITE_NAME; ?></title>
    
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
            --danger-color: #f44336;
        }
        
        .ride-header {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 30px 0;
        }
        
        .status-badge {
            font-size: 1.1rem;
            padding: 8px 16px;
            border-radius: 20px;
        }
        
        .ride-info {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
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
        
        .map-container {
            height: 400px;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .rider-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .chat-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            height: 500px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
        }
        
        .message.own {
            justify-content: flex-end;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            position: relative;
        }
        
        .message.own .message-bubble {
            background: var(--primary-color);
            color: white;
        }
        
        .message:not(.own) .message-bubble {
            background: #f1f1f1;
            color: #333;
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            background: white;
            border: 3px solid #e0e0e0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 2;
            color: #999;
        }
        
        .step.completed {
            border-color: var(--success-color);
            background: var(--success-color);
            color: white;
        }
        
        .step.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .ride-info {
                padding: 20px;
            }
            
            .chat-container {
                height: 400px;
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
                <a class="nav-link" href="rides.php">
                    <i class="fas fa-list me-1"></i>My Rides
                </a>
            </div>
        </div>
    </nav>

    <!-- Ride Header -->
    <div class="ride-header">
        <div class="container text-center">
            <h2>Ride #<?php echo $ride['id']; ?></h2>
            <div class="mt-3">
                <?php
                $status_colors = [
                    'requested' => 'warning',
                    'accepted' => 'info',
                    'picked_up' => 'primary',
                    'in_progress' => 'primary',
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
    </div>

    <div class="container my-4">
        <div class="row">
            <div class="col-lg-8">
                <!-- Progress Steps -->
                <div class="ride-info">
                    <h5 class="mb-4">Ride Progress</h5>
                    <div class="progress-steps">
                        <div class="step <?php echo in_array($ride['status'], ['requested', 'accepted', 'picked_up', 'completed']) ? 'completed' : ($ride['status'] == 'requested' ? 'active' : ''); ?>">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="step <?php echo in_array($ride['status'], ['accepted', 'picked_up', 'completed']) ? 'completed' : ($ride['status'] == 'accepted' ? 'active' : ''); ?>">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="step <?php echo in_array($ride['status'], ['picked_up', 'completed']) ? 'completed' : ($ride['status'] == 'picked_up' ? 'active' : ''); ?>">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="step <?php echo $ride['status'] == 'completed' ? 'completed' : ($ride['status'] == 'completed' ? 'active' : ''); ?>">
                            <i class="fas fa-flag-checkered"></i>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-3">
                            <small>Requested</small>
                        </div>
                        <div class="col-3">
                            <small>Accepted</small>
                        </div>
                        <div class="col-3">
                            <small>Picked Up</small>
                        </div>
                        <div class="col-3">
                            <small>Completed</small>
                        </div>
                    </div>
                </div>
                
                <!-- Ride Details -->
                <div class="ride-info">
                    <h5 class="mb-4">Trip Details</h5>
                    
                    <div class="location-item">
                        <div class="location-icon pickup-icon">
                            <i class="fas fa-circle"></i>
                        </div>
                        <div>
                            <strong>Pickup Location</strong>
                            <div class="text-muted"><?php echo htmlspecialchars($ride['pickup_address']); ?></div>
                        </div>
                    </div>
                    
                    <div class="location-item">
                        <div class="location-icon dropoff-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <strong>Drop-off Location</strong>
                            <div class="text-muted"><?php echo htmlspecialchars($ride['dropoff_address']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Map -->
                    <div class="map-container">
                        <div id="map"></div>
                    </div>
                    
                    <!-- Trip Stats -->
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="h6 text-primary"><?php echo number_format($ride['distance'], 1); ?> km</div>
                            <small class="text-muted">Distance</small>
                        </div>
                        <div class="col-3">
                            <div class="h6 text-primary"><?php echo $ride['duration'] ?? 'N/A'; ?> min</div>
                            <small class="text-muted">Duration</small>
                        </div>
                        <div class="col-3">
                            <div class="h6 text-success"><?php echo formatCurrency($ride['final_fare']); ?></div>
                            <small class="text-muted">Fare</small>
                        </div>
                        <div class="col-3">
                            <div class="h6 text-info"><?php echo ucfirst($ride['payment_method']); ?></div>
                            <small class="text-muted">Payment</small>
                        </div>
                    </div>
                </div>
                
                <!-- Rider/Passenger Info -->
                <?php if ($ride['rider_name']): ?>
                    <div class="ride-info">
                        <h5 class="mb-3">
                            <?php echo $is_passenger ? 'Your Rider' : 'Passenger'; ?>
                        </h5>
                        
                        <div class="rider-info">
                            <div class="row align-items-center">
                                <div class="col-md-2 text-center">
                                    <i class="fas fa-user-circle fa-3x text-primary"></i>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($is_passenger ? $ride['rider_name'] : $ride['user_name']); ?></h6>
                                    <div class="text-muted">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($is_passenger ? $ride['rider_phone'] : $ride['user_phone']); ?>
                                    </div>
                                    <?php if ($is_passenger && $ride['vehicle_type']): ?>
                                        <div class="text-muted">
                                            <i class="fas fa-car me-1"></i><?php echo ucfirst($ride['vehicle_type']); ?>
                                            <?php if ($ride['brand'] && $ride['model']): ?>
                                                - <?php echo htmlspecialchars($ride['brand'] . ' ' . $ride['model']); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted">
                                            <i class="fas fa-hashtag me-1"></i><?php echo htmlspecialchars($ride['plate_no']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-center">
                                    <?php if ($is_passenger && $ride['rider_rating'] > 0): ?>
                                        <div class="mb-2">
                                            <span class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?php echo $i <= $ride['rider_rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                            <div><small><?php echo number_format($ride['rider_rating'], 1); ?> rating</small></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="tel:<?php echo $is_passenger ? $ride['rider_phone'] : $ride['user_phone']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-phone me-1"></i>Call
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <?php if (in_array($ride['status'], ['requested', 'accepted', 'picked_up'])): ?>
                    <div class="ride-info">
                        <h5 class="mb-3">Actions</h5>
                        
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($is_passenger && $ride['status'] == 'requested'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="cancel_ride">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this ride?')">
                                        <i class="fas fa-times me-2"></i>Cancel Ride
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($is_rider && $ride['status'] == 'requested'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="accept_ride">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-2"></i>Accept Ride
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($is_rider && $ride['status'] == 'accepted'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="start_ride">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-play me-2"></i>Start Ride
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($is_rider && $ride['status'] == 'picked_up'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="complete_ride">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-flag-checkered me-2"></i>Complete Ride
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Chat -->
                <?php if ($ride['rider_name']): ?>
                    <div class="chat-container">
                        <h5 class="mb-3">
                            <i class="fas fa-comments me-2"></i>Chat
                        </h5>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <div class="text-center text-muted">
                                    <i class="fas fa-comment fa-2x mb-2"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message <?php echo $message['sender_id'] == $user_id ? 'own' : ''; ?>">
                                        <div class="message-bubble">
                                            <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                            <div class="message-time"><?php echo date('H:i', strtotime($message['created_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form id="chatForm" onsubmit="sendMessage(event)">
                            <div class="input-group">
                                <input type="text" class="form-control" id="messageInput" placeholder="Type a message..." required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
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
            center: [<?php echo $ride['pickup_lng']; ?>, <?php echo $ride['pickup_lat']; ?>],
            zoom: 12
        });
        
        // Add markers
        const pickupMarker = new mapboxgl.Marker({ color: '#4CAF50' })
            .setLngLat([<?php echo $ride['pickup_lng']; ?>, <?php echo $ride['pickup_lat']; ?>])
            .setPopup(new mapboxgl.Popup().setHTML('<strong>Pickup</strong><br><?php echo htmlspecialchars($ride['pickup_address']); ?>'))
            .addTo(map);
            
        const dropoffMarker = new mapboxgl.Marker({ color: '#1976d2' })
            .setLngLat([<?php echo $ride['dropoff_lng']; ?>, <?php echo $ride['dropoff_lat']; ?>])
            .setPopup(new mapboxgl.Popup().setHTML('<strong>Drop-off</strong><br><?php echo htmlspecialchars($ride['dropoff_address']); ?>'))
            .addTo(map);
        
        // Draw route
        fetch(`https://api.mapbox.com/directions/v5/mapbox/driving/<?php echo $ride['pickup_lng']; ?>,<?php echo $ride['pickup_lat']; ?>;<?php echo $ride['dropoff_lng']; ?>,<?php echo $ride['dropoff_lat']; ?>?access_token=${mapboxgl.accessToken}&geometries=geojson`)
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
                    bounds.extend([<?php echo $ride['pickup_lng']; ?>, <?php echo $ride['pickup_lat']; ?>]);
                    bounds.extend([<?php echo $ride['dropoff_lng']; ?>, <?php echo $ride['dropoff_lat']; ?>]);
                    map.fitBounds(bounds, { padding: 50 });
                }
            });
        
        // Chat functionality
        function sendMessage(event) {
            event.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message) return;
            
            // Add message to chat immediately (optimistic update)
            addMessageToChat(message, true, new Date());
            messageInput.value = '';
            
            // Send to server
            fetch('api/send-message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ride_id: <?php echo $ride_id; ?>,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Failed to send message');
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
            });
        }
        
        function addMessageToChat(message, isOwn, timestamp) {
            const chatMessages = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwn ? 'own' : ''}`;
            
            const time = timestamp.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    <div>${message.replace(/\n/g, '<br>')}</div>
                    <div class="message-time">${time}</div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Auto-refresh for real-time updates
        setInterval(function() {
            // Check for new messages
            fetch(`api/get-messages.php?ride_id=<?php echo $ride_id; ?>&last_check=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            if (msg.sender_id != <?php echo $user_id; ?>) {
                                addMessageToChat(msg.message, false, new Date(msg.created_at));
                            }
                        });
                    }
                })
                .catch(error => console.error('Error fetching messages:', error));
            
            // Check for ride status updates
            fetch(`api/get-ride-status.php?ride_id=<?php echo $ride_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status !== '<?php echo $ride['status']; ?>') {
                        location.reload(); // Reload page if status changed
                    }
                })
                .catch(error => console.error('Error checking ride status:', error));
        }, 10000); // Check every 10 seconds
        
        // Scroll chat to bottom
        document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
    </script>
</body>
</html>
