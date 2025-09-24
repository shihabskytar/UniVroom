<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password_hash, role, verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['verified']) {
                $error = 'Please verify your email address first';
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Check if user is also a rider
                if ($user['role'] == 'rider') {
                    $stmt = $db->prepare("SELECT status FROM riders WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $rider = $stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['is_rider'] = true;
                    $_SESSION['rider_status'] = $rider['status'];
                }
                
                // Set remember me cookie
                if ($remember) {
                    $token = generateToken();
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                    
                    // Store token in database (you might want to create a remember_tokens table)
                    $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $user['id']]);
                }
                
                // Redirect to intended page or dashboard
                $redirect_to = $_GET['redirect'] ?? 'index.php';
                redirect($redirect_to);
            }
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
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
            max-width: 400px;
            width: 100%;
        }
        
        .auth-header {
            background: linear-gradient(135deg, var(--primary-color), #1565c0);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .auth-body {
            padding: 40px 30px;
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
        
        .social-login {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 20px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 576px) {
            .auth-header, .auth-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h2 class="mb-2"><i class="fas fa-car me-2"></i><?php echo SITE_NAME; ?></h2>
                <p class="mb-0">Welcome back!</p>
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
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@university.edu" required>
                        <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                        </div>
                        <div class="col-6 text-end">
                            <a href="forgot-password.php" class="text-decoration-none">Forgot password?</a>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
                
                <div class="text-center">
                    <p class="mb-0">Don't have an account? <a href="register.php" class="text-decoration-none fw-bold">Sign up</a></p>
                </div>
                
                <!-- Demo Accounts -->
                <div class="social-login">
                    <h6 class="text-center mb-3">Demo Accounts</h6>
                    <div class="row">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100" onclick="fillDemo('user')">
                                <i class="fas fa-user me-1"></i>User
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="fillDemo('rider')">
                                <i class="fas fa-car me-1"></i>Rider
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        function fillDemo(type) {
            const demoAccounts = {
                user: {
                    email: 'john@university.edu',
                    password: 'password123'
                },
                rider: {
                    email: 'jane@university.edu',
                    password: 'password123'
                }
            };
            
            if (demoAccounts[type]) {
                document.getElementById('email').value = demoAccounts[type].email;
                document.getElementById('password').value = demoAccounts[type].password;
            }
        }
        
        // Auto-focus first input
        document.getElementById('email').focus();
    </script>
</body>
</html>
