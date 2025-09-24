<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $is_rider = isset($_POST['is_rider']);
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (!preg_match('/\.(edu|ac\.)/i', $email)) {
        $error = 'Please use your institutional email (.edu or .ac domain)';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!$terms) {
        $error = 'Please accept the terms and conditions';
    } else {
        $db = getDB();
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email address already registered';
        } else {
            // Create user account
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $verification_token = generateToken();
            $role = $is_rider ? 'rider' : 'user';
            
            try {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role, phone, verification_token, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $password_hash, $role, $phone, $verification_token]);
                
                $user_id = $db->lastInsertId();
                
                // If registering as rider, create rider profile
                if ($is_rider) {
                    $vehicle_type = sanitize($_POST['vehicle_type']);
                    $plate_no = sanitize($_POST['plate_no']);
                    
                    if (empty($vehicle_type) || empty($plate_no)) {
                        throw new Exception('Vehicle information is required for riders');
                    }
                    
                    $stmt = $db->prepare("INSERT INTO riders (user_id, vehicle_type, plate_no, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
                    $stmt->execute([$user_id, $vehicle_type, $plate_no]);
                }
                
                $db->commit();
                
                // In a real application, you would send verification email here
                // For demo purposes, we'll auto-verify
                $stmt = $db->prepare("UPDATE users SET verified = TRUE WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $success = 'Account created successfully! You can now login.';
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
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
    <title>Sign Up - <?php echo SITE_NAME; ?></title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1976d2;
            --secondary-color: #424242;
        }
        
        .auth-container {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        
        .auth-header {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .auth-body {
            padding: 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #1565c0;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .rider-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: none;
        }
        
        .rider-fields.show {
            display: block;
        }
        
        @media (max-width: 576px) {
            .auth-header, .auth-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2 class="mb-2"><i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?></h2>
                <p class="mb-0">Join the student community!</p>
            </div>
            
            <div class="auth-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <div class="mt-2">
                            <a href="login.php" class="btn btn-success btn-sm">Login Now</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="registerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="name" name="name" placeholder="Full Name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                <label for="name"><i class="fas fa-user me-2"></i>Full Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone Number" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                <label for="phone"><i class="fas fa-phone me-2"></i>Phone Number</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@university.edu" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <label for="email"><i class="fas fa-envelope me-2"></i>Institutional Email</label>
                        <div class="form-text">Use your .edu or .ac email address</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_rider" name="is_rider" <?php echo isset($_POST['is_rider']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_rider">
                            <i class="fas fa-car me-2"></i>I want to register as a rider (provide rides)
                        </label>
                    </div>
                    
                    <div class="rider-fields" id="riderFields">
                        <h6 class="mb-3"><i class="fas fa-car me-2"></i>Vehicle Information</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="vehicle_type" name="vehicle_type">
                                        <option value="">Select Vehicle Type</option>
                                        <option value="car" <?php echo ($_POST['vehicle_type'] ?? '') == 'car' ? 'selected' : ''; ?>>Car</option>
                                        <option value="bike" <?php echo ($_POST['vehicle_type'] ?? '') == 'bike' ? 'selected' : ''; ?>>Motorcycle</option>
                                        <option value="rickshaw" <?php echo ($_POST['vehicle_type'] ?? '') == 'rickshaw' ? 'selected' : ''; ?>>Rickshaw</option>
                                        <option value="cng" <?php echo ($_POST['vehicle_type'] ?? '') == 'cng' ? 'selected' : ''; ?>>CNG</option>
                                    </select>
                                    <label for="vehicle_type">Vehicle Type</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="plate_no" name="plate_no" placeholder="DHA-1234" value="<?php echo htmlspecialchars($_POST['plate_no'] ?? ''); ?>">
                                    <label for="plate_no">License Plate</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Your rider account will be reviewed by admin before activation.
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none fw-bold">Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        // Toggle rider fields
        document.getElementById('is_rider').addEventListener('change', function() {
            const riderFields = document.getElementById('riderFields');
            const vehicleType = document.getElementById('vehicle_type');
            const plateNo = document.getElementById('plate_no');
            
            if (this.checked) {
                riderFields.classList.add('show');
                vehicleType.required = true;
                plateNo.required = true;
            } else {
                riderFields.classList.remove('show');
                vehicleType.required = false;
                plateNo.required = false;
                vehicleType.value = '';
                plateNo.value = '';
            }
        });
        
        // Initialize rider fields state
        if (document.getElementById('is_rider').checked) {
            document.getElementById('riderFields').classList.add('show');
            document.getElementById('vehicle_type').required = true;
            document.getElementById('plate_no').required = true;
        }
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Email validation
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const isInstitutional = /\.(edu|ac\.)/i.test(email);
            
            if (email && !isInstitutional) {
                this.setCustomValidity('Please use your institutional email (.edu or .ac domain)');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-focus first input
        document.getElementById('name').focus();
    </script>
</body>
</html>
