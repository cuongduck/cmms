<?php
/**
 * Debug BOM API
 * Temporary file để debug API response
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug BOM API</h2>";

// Test database connection
try {
    require_once '../../../config/database.php';
    echo "<p style='color: green'>✓ Database connection OK</p>";
    
    // Test basic query
    $db = Database::getInstance();
    $result = $db->fetch("SELECT COUNT(*) as count FROM machine_bom");
    echo "<p>Total BOMs in database: " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red'>✗ Database error: " . $e->getMessage() . "</p>";
    exit;
}

// Test API endpoint
echo "<h3>Testing API Endpoint</h3>";
$apiUrl = '/modules/bom/api/bom.php?action=list&page=1';
echo "<p>API URL: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";

// Make request to API
$fullUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $apiUrl;
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'X-Requested-With: XMLHttpRequest'
        ]
    ]
]);

$response = file_get_contents($fullUrl, false, $context);
echo "<h4>Raw API Response:</h4>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo htmlspecialchars($response);
echo "</pre>";

// Try to decode JSON
$data = json_decode($response, true);
if ($data === null) {
    echo "<p style='color: red'>✗ Invalid JSON response</p>";
    echo "<p>JSON Error: " . json_last_error_msg() . "</p>";
} else {
    echo "<p style='color: green'>✓ Valid JSON response</p>";
    echo "<h4>Parsed Data Structure:</h4>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

// Test direct query
echo "<h3>Testing Direct Database Query</h3>";
try {
    $sql = "SELECT mb.*, mt.name as machine_type_name, mt.code as machine_type_code,
                   u.full_name as created_by_name,
                   COUNT(bi.id) as total_items,
                   SUM(bi.quantity * COALESCE(p.unit_price, 0)) as total_cost
            FROM machine_bom mb
            JOIN machine_types mt ON mb.machine_type_id = mt.id  
            LEFT JOIN users u ON mb.created_by = u.id
            LEFT JOIN bom_items bi ON mb.id = bi.bom_id
            LEFT JOIN parts p ON bi.part_id = p.id
            WHERE 1=1
            GROUP BY mb.id 
            ORDER BY mb.created_at DESC
            LIMIT 20";
    
    $boms = $db->fetchAll($sql);
    echo "<p>Found " . count($boms) . " BOMs</p>";
    
    if (!empty($boms)) {
        echo "<h4>Sample BOM Data:</h4>";
        echo "<pre>";
        print_r($boms[0]);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red'>✗ Query error: " . $e->getMessage() . "</p>";
}
?>