<?php
/**
 * Main Configuration File
 * CMMS System Configuration
 */

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application constants
define('APP_NAME', 'CMMS - Hệ thống quản lý thiết bị');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://baotricf.iot-mmb.site');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Session configuration
ini_set('session.gc_maxlifetime', 3600); // 1 hour
session_set_cookie_params(3600);

// Application settings
$config = [
    'app' => [
        'name' => APP_NAME,
        'version' => APP_VERSION,
        'url' => APP_URL,
        'timezone' => 'Asia/Ho_Chi_Minh',
        'language' => 'vi',
        'theme_color' => '#1e3a8a'
    ],
    
    'upload' => [
        'max_size' => MAX_FILE_SIZE,
        'allowed_types' => [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']
        ],
        'paths' => [
            'equipment_images' => UPLOAD_PATH . 'equipment_images/',
            'manuals' => UPLOAD_PATH . 'manuals/',
            'temp' => UPLOAD_PATH . 'temp/'
        ]
    ],
    
    'pagination' => [
        'per_page' => 20,
        'max_per_page' => 100
    ],
    
    'security' => [
        'password_min_length' => 6,
        'session_timeout' => 36000,
        'max_login_attempts' => 5,
        'lockout_duration' => 900 // 15 minutes
    ],
    
    'modules' => [
        'structure' => [
            'name' => 'Cấu trúc thiết bị',
            'icon' => 'fas fa-sitemap',
            'url' => '/modules/structure/'
        ],
        'equipment' => [
            'name' => 'Quản lý thiết bị',
            'icon' => 'fas fa-cogs',
            'url' => '/modules/equipment/'
        ],
        'bom' => [
            'name' => 'BOM thiết bị',
            'icon' => 'fas fa-list',
            'url' => '/modules/bom/'
        ],
        'maintenance' => [
            'name' => 'Kế hoạch bảo trì',
            'icon' => 'fas fa-wrench',
            'url' => '/modules/maintenance/'
        ],
        'calibration' => [
            'name' => 'Hiệu chuẩn',
            'icon' => 'fas fa-balance-scale',
            'url' => '/modules/calibration/'
        ],
        'inventory' => [
            'name' => 'Quản lý tồn kho',
            'icon' => 'fas fa-warehouse',
            'url' => '/modules/inventory/'
        ],
        'workorder' => [
            'name' => 'Quản lý công việc',
            'icon' => 'fas fa-tasks',
            'url' => '/modules/workorder/'
        ],
        'users' => [
            'name' => 'Quản lý người dùng',
            'icon' => 'fas fa-users',
            'url' => '/modules/users/'
        ]
    ]
];

// Create upload directories if not exist
foreach ($config['upload']['paths'] as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Helper functions
function getConfig($key = null) {
    global $config;
    if ($key === null) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return null;
        }
        $value = $value[$k];
    }
    
    return $value;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateInput($input, $type = 'string', $options = []) {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, $options);
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT, $options);
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
        case 'string':
        default:
            return trim($input);
    }
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '';
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function redirect($url, $permanent = false) {
    if ($permanent) {
        header('HTTP/1.1 301 Moved Permanently');
    }
    header('Location: ' . $url);
    exit();
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

function successResponse($data = [], $message = 'Thành công') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}
?>