<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

requireLogin();

$equipmentId = (int)($_GET['id'] ?? 1);

echo "<h3>Debug Settings Images for Equipment ID: {$equipmentId}</h3>";

// Check if equipment exists
$equipment = $db->fetch("SELECT * FROM equipment WHERE id = ?", [$equipmentId]);
echo "<h4>Equipment Info:</h4>";
if ($equipment) {
    echo "<pre>" . print_r($equipment, true) . "</pre>";
} else {
    echo "<p style='color: red;'>Equipment not found!</p>";
}

// Check settings images
$sql = "SELECT * FROM equipment_settings_images WHERE equipment_id = ?";
$settings = $db->fetchAll($sql, [$equipmentId]);

echo "<h4>Settings Images Count: " . count($settings) . "</h4>";

if (!empty($settings)) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Image Path</th><th>Title</th><th>Category</th><th>File Exists</th><th>Full Path</th></tr>";
    
    foreach ($settings as $setting) {
        $relativePath = ltrim($setting['image_path'], '/');
        $fullPath = BASE_PATH . '/' . $relativePath;
        $fileExists = file_exists($fullPath) ? 'YES' : 'NO';
        $imageUrl = APP_URL . '/' . $relativePath;
        
        echo "<tr>";
        echo "<td>{$setting['id']}</td>";
        echo "<td>{$setting['image_path']}</td>";
        echo "<td>{$setting['title']}</td>";
        echo "<td>{$setting['category']}</td>";
        echo "<td style='color: " . ($fileExists == 'YES' ? 'green' : 'red') . ";'>{$fileExists}</td>";
        echo "<td>{$fullPath}</td>";
        echo "</tr>";
        
        if ($fileExists == 'YES') {
            echo "<tr><td colspan='6'>";
            echo "<img src='{$imageUrl}' style='max-width: 200px; max-height: 150px;' alt='Preview'>";
            echo "<br>URL: <a href='{$imageUrl}' target='_blank'>{$imageUrl}</a>";
            echo "</td></tr>";
        }
    }
    echo "</table>";
} else {
    echo "<p>No settings images found.</p>";
}

// Check database structure
echo "<h4>Table Structure:</h4>";
try {
    $structure = $db->fetchAll("DESCRIBE equipment_settings_images");
    echo "<pre>" . print_r($structure, true) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check upload directory
$uploadDir = BASE_PATH . '/uploads/equipment/settings/';
echo "<h4>Upload Directory: {$uploadDir}</h4>";
echo "<p>Directory exists: " . (is_dir($uploadDir) ? 'YES' : 'NO') . "</p>";
echo "<p>Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "</p>";

if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    echo "<p>Files in directory: " . count($files) . "</p>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<p>- {$file}</p>";
        }
    }
}
?>