<?php
require_once '../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$step = 'email'; // email, code, reset

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['send_code'])) {
        $email = sanitize($_POST['email']);
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate reset code
                $reset_code = sprintf("%06d", mt_rand(100000, 999999));
                $reset_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $stmt = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
                $stmt->execute([$reset_code, $reset_expires, $email]);
                
                // In a real application, send email here
                // For demo purposes, we'll show the code
                $_SESSION['reset_email'] = $email;
                $_SESSION['demo_reset_code'] = $reset_code;
                $success = "Reset code sent to your email. For demo purposes, your code is: <strong>$reset_code</strong>";
                $step = 'code';
            } else {
                $error = 'Email address not found';
            }
        }
    } elseif (isset($_POST['verify_code'])) {
        $email = $_SESSION['reset_email'] ?? '';
        $code = sanitize($_POST['code']);
        
        if (empty($code)) {
            $error = 'Please enter the verification code';
            $step = 'code';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$email, $code]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $step = 'reset';
            } else {
                $error = 'Invalid or expired verification code';
                $step = 'code';
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $user_id = $_SESSION['reset_user_id'] ?? 0;
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields';
            $step = 'reset';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
            $step = 'reset';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
            $step = 'reset';
        } else {
            $db = getDB();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            
            // Clear session variables
            unset($_SESSION['reset_email'], $_SESSION['demo_reset_code'], $_SESSION['reset_user_id']);
            
            $success = 'Password reset successfully! You can now login with your new password.';
            $step = 'complete';
        }
    }
}

// Determine current step
if (isset($_SESSION['reset_user_id'])) {
    $step = 'reset';
} elseif (isset($_SESSION['reset_email'])) {
    $step = 'code';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    
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
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background: #4caf50;
            color: white;
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
                <h2 class="mb-2"><i class="fas fa-key me-2"></i>Reset Password</h2>
                <p class="mb-0">We'll help you get back in</p>
            </div>
            
            <div class="auth-body">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step == 'email' ? 'active' : ($step != 'email' ? 'completed' : ''); ?>">1</div>
                    <div class="step <?php echo $step == 'code' ? 'active' : ($step == 'reset' || $step == 'complete' ? 'completed' : ''); ?>">2</div>
                    <div class="step <?php echo $step == 'reset' ? 'active' : ($step == 'complete' ? 'completed' : ''); ?>">3</div>
                </div>
                
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
                
                <?php if ($step == 'email'): ?>
                    <form method="POST" action="">
                        <div class="text-center mb-4">
                            <h5>Enter Your Email</h5>
                            <p class="text-muted">We'll send you a verification code</p>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@university.edu" required>
                            <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                        </div>
                        
                        <button type="submit" name="send_code" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-paper-plane me-2"></i>Send Code
                        </button>
                    </form>
                <?php elseif ($step == 'code'): ?>
                    <form method="POST" action="">
                        <div class="text-center mb-4">
                            <h5>Enter Verification Code</h5>
                            <p class="text-muted">Check your email for the 6-digit code</p>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control text-center" id="code" name="code" placeholder="123456" maxlength="6" required style="font-size: 24px; letter-spacing: 10px;">
                            <label for="code"><i class="fas fa-shield-alt me-2"></i>Verification Code</label>
                        </div>
                        
                        <button type="submit" name="verify_code" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-check me-2"></i>Verify Code
                        </button>
                        
                        <div class="text-center">
                            <a href="forgot-password.php" class="text-decoration-none">← Back to email</a>
                        </div>
                    </form>
                <?php elseif ($step == 'reset'): ?>
                    <form method="POST" action="">
                        <div class="text-center mb-4">
                            <h5>Set New Password</h5>
                            <p class="text-muted">Choose a strong password</p>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="password" name="password" placeholder="New Password" required>
                            <label for="password"><i class="fas fa-lock me-2"></i>New Password</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                            <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                        </div>
                        
                        <button type="submit" name="reset_password" class="btn btn-primary btn-lg w-100 mb-3">
                            <i class="fas fa-save me-2"></i>Reset Password
                        </button>
                    </form>
                <?php elseif ($step == 'complete'): ?>
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5>Password Reset Complete!</h5>
                        <p class="text-muted mb-4">Your password has been successfully updated</p>
                        <a href="login.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>Login Now
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($step != 'complete'): ?>
                    <div class="text-center mt-4">
                        <a href="login.php" class="text-decoration-none">← Back to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    
    <script>
        // Auto-focus inputs
        <?php if ($step == 'email'): ?>
            document.getElementById('email').focus();
        <?php elseif ($step == 'code'): ?>
            document.getElementById('code').focus();
        <?php elseif ($step == 'reset'): ?>
            document.getElementById('password').focus();
        <?php endif; ?>
        
        // Code input formatting
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '');
            });
        }
        
        // Password confirmation validation
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword) {
            confirmPassword.addEventListener('input', function() {
                const password = document.getElementById('password').value;
                const confirmPassword = this.value;
                
                if (password !== confirmPassword) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>
