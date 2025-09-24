<?php
/**
 * Main Configuration File
 * UniVroom - By Students, For Students
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_NAME', 'UniVroom');
define('SITE_URL', 'http://localhost/univroom');
define('SITE_DESCRIPTION', 'By Students, For Students');

// Mapbox Configuration
define('MAPBOX_API_KEY', 'pk.eyJ1Ijoic2hpaGFic2t5dGFyIiwiYSI6ImNtZnYxcGN5eTAyc24yanF6a3J4NHBwdWQifQ.L4o9UKRA8f8cADyrMCMJAg');

// Email Configuration (for password reset)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', ''); // Add your email
define('SMTP_PASSWORD', ''); // Add your app password
define('FROM_EMAIL', 'noreply@univroom.com');
define('FROM_NAME', 'UniVroom');

// Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Ride Configuration
define('BASE_FARE_PER_KM', 15); // 15 BDT per km
define('MINIMUM_FARE', 20); // Minimum 20 BDT

// Pagination
define('ITEMS_PER_PAGE', 12);

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'database.php';

// Helper Functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

function isRider() {
    return isset($_SESSION['user_id']) && isset($_SESSION['is_rider']) && $_SESSION['is_rider'];
}

function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function formatCurrency($amount) {
    return number_format($amount, 2) . ' BDT';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Auto-create upload directories
$upload_dirs = [
    'uploads/profiles/',
    'uploads/products/',
    'uploads/vehicles/',
    'uploads/temp/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}
?>
