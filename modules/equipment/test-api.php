<?php
// modules/equipment/test-api.php - API Performance Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../config/functions.php';

// Check authentication
if (!isLoggedIn()) {
    die('Not authenticated');
}

// Get test type
$test = $_GET['test'] ?? 'basic';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Equipment API Performance Test</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-result { 
            margin: 10px 0; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            background: #f9f9f9;
        }
        .loading { color: #007bff; }
        .success { color: #28a745; background: #d4edda; }
        .error { color: #dc3545; background: #f8d7da; }
        .timing { font-weight: bold; color: #6f42c1; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { 
            padding: 8px 16px; 
            margin: 5px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            background: #007bff;
            color: white;
        }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🧪 Equipment API Performance Test</h1>
    <p>Trang này giúp kiểm tra performance của API thiết bị</p>
    
    <div>
        <button onclick="runTest('database')">Test Database Connection</button>
        <button onclick="runTest('simple_query')">Test Simple Query</button>
        <button onclick="runTest('complex_query')">Test Complex Query</button>
        <button onclick="runTest('equipment_list')">Test Equipment List API</button>
        <button onclick="runTest('dropdown_data')">Test Dropdown Data</button>
        <button onclick="runTest('all')">Run All Tests</button>
        <button onclick="clearResults()">Clear Results</button>
    </div>
    
    <div id="results"></div>

    <script>
        async function runTest(testType) {
            const resultsDiv = document.getElementById('results');
            
            if (testType === 'all') {
                const tests = ['database', 'simple_query', 'complex_query', 'equipment_list', 'dropdown_data'];
                for (const test of tests) {
                    await runSingleTest(test);
                }
                return;
            }
            
            await runSingleTest(testType);
        }
        
        async function runSingleTest(testType) {
            const resultsDiv = document.getElementById('results');
            const testDiv = document.createElement('div');
            testDiv.className = 'test-result loading';
            testDiv.innerHTML = `<h3>🔄 Testing: ${testType}</h3><p>Running test...</p>`;
            resultsDiv.appendChild(testDiv);
            
            const startTime = performance.now();
            
            try {
                const response = await fetch(`?test=${testType}&t=${Date.now()}`);
                const endTime = performance.now();
                const duration = endTime - startTime;
                
                if (response.ok) {
                    const result = await response.text();
                    testDiv.className = 'test-result success';
                    testDiv.innerHTML = `
                        <h3>✅ ${testType.toUpperCase()} - SUCCESS</h3>
                        <p class="timing">⏱️ Time: ${duration.toFixed(2)}ms</p>
                        <pre>${result}</pre>
                    `;
                } else {
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                const endTime = performance.now();
                const duration = endTime - startTime;
                
                testDiv.className = 'test-result error';
                testDiv.innerHTML = `
                    <h3>❌ ${testType.toUpperCase()} - ERROR</h3>
                    <p class="timing">⏱️ Time: ${duration.toFixed(2)}ms</p>
                    <p>Error: ${error.message}</p>
                `;
            }
        }
        
        function clearResults() {
            document.getElementById('results').innerHTML = '';
        }
    </script>
</body>
</html>

<?php
if (!isset($_GET['test'])) {
    exit(); // Show HTML form
}

// Start timing
$start_time = microtime(true);

try {
    switch ($_GET['test']) {
        case 'database':
            testDatabaseConnection();
            break;
        case 'simple_query':
            testSimpleQuery();
            break;
        case 'complex_query':
            testComplexQuery();
            break;
        case 'equipment_list':
            testEquipmentListAPI();
            break;
        case 'dropdown_data':
            testDropdownData();
            break;
        default:
            echo "Unknown test type";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "ERROR: " . $e->getMessage();
}

function testDatabaseConnection() {
    global $db, $start_time;
    
    echo "=== DATABASE CONNECTION TEST ===\n";
    echo "Start time: " . date('Y-m-d H:i:s.u') . "\n\n";
    
    // Test basic connection
    $query_start = microtime(true);
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "✅ Database connection: OK\n";
    echo "⏱️  Simple query time: " . number_format($query_time, 2) . "ms\n\n";
    
    // Test database info
    $query_start = microtime(true);
    $stmt = $db->query("SELECT DATABASE() as db_name, VERSION() as version");
    $info = $stmt->fetch();
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "Database: " . $info['db_name'] . "\n";
    echo "Version: " . $info['version'] . "\n";
    echo "⏱️  Info query time: " . number_format($query_time, 2) . "ms\n\n";
    
    $total_time = (microtime(true) - $start_time) * 1000;
    echo "🎯 Total test time: " . number_format($total_time, 2) . "ms\n";
}

function testSimpleQuery() {
    global $db, $start_time;
    
    echo "=== SIMPLE QUERY TEST ===\n";
    echo "Testing basic table queries...\n\n";
    
    // Test thiết bị count
    $query_start = microtime(true);
    $stmt = $db->query("SELECT COUNT(*) as count FROM thiet_bi");
    $count = $stmt->fetch()['count'];
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "✅ Equipment count: " . $count . "\n";
    echo "⏱️  Query time: " . number_format($query_time, 2) . "ms\n\n";
    
    // Test xưởng
    $query_start = microtime(true);
    $stmt = $db->query("SELECT COUNT(*) as count FROM xuong");
    $count = $stmt->fetch()['count'];
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "✅ Xưởng count: " . $count . "\n";
    echo "⏱️  Query time: " . number_format($query_time, 2) . "ms\n\n";
    
    // Test với LIMIT
    $query_start = microtime(true);
    $stmt = $db->query("SELECT id, id_thiet_bi, ten_thiet_bi FROM thiet_bi LIMIT 10");
    $results = $stmt->fetchAll();
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "✅ Sample equipment (10 records): " . count($results) . "\n";
    echo "⏱️  Query time: " . number_format($query_time, 2) . "ms\n\n";
    
    $total_time = (microtime(true) - $start_time) * 1000;
    echo "🎯 Total test time: " . number_format($total_time, 2) . "ms\n";
}

function testComplexQuery() {
    global $db, $start_time;
    
    echo "=== COMPLEX QUERY TEST ===\n";
    echo "Testing JOIN queries...\n\n";
    
    // Test original complex query
    $query_start = microtime(true);
    $sql = "SELECT tb.*, x.ten_xuong, pl.ten_line, kv.ten_khu_vuc, dm.ten_dong_may, ctb.ten_cum, u.full_name as chu_quan_name
            FROM thiet_bi tb
            LEFT JOIN xuong x ON tb.id_xuong = x.id
            LEFT JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
            LEFT JOIN dong_may dm ON tb.id_dong_may = dm.id
            LEFT JOIN cum_thiet_bi ctb ON tb.id_cum_thiet_bi = ctb.id
            LEFT JOIN users u ON tb.nguoi_chu_quan = u.id
            ORDER BY tb.created_at DESC
            LIMIT 15";
    
    $stmt = $db->query($sql);
    $results = $stmt->fetchAll();
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "✅ Complex JOIN query: " . count($results) . " records\n";
    echo "⏱️  Query time: " . number_format($query_time, 2) . "ms\n\n";
    
    // Test with WHERE conditions
    $query_start = microtime(true);
    $sql = "SELECT tb.*, x.ten_xuong, pl.ten_line, kv.ten_khu_vuc
            FROM thiet_bi tb
            LEFT JOIN xuong x ON tb.id_xuong = x.id
            LEFT JOIN production_line pl ON tb.id_line = pl.id
            LEFT JOIN khu_vuc kv ON tb.id_khu_vuc = kv.id
            WHERE tb.tinh_trang = 'hoat_dong'
            ORDER BY tb.created_at DESC
            LIMIT 15";
    
    $stmt = $db->query($sql);
    $results = $stmt->fetchAll();
    $query_time = (microtime(true) - $query_start) * 1000;
    
    echo "✅ Filtered JOIN query: " . count($results) . " records\n";
    echo "⏱️  Query time: " . number_format($query_time, 2) . "ms\n\n";
    
    // Test view query (if exists)
    try {
        $query_start = microtime(true);
        $stmt = $db->query("SELECT * FROM v_equipment_optimized LIMIT 15");
        $results = $stmt->fetchAll();
        $query_time = (microtime(true) - $query_start) * 1000;
        
        echo "✅ Optimized view query: " . count($results) . " records\n";
        echo "⏱️  View query time: " . number_format($query_time, 2) . "ms\n\n";
    } catch (Exception $e) {
        echo "❌ Optimized view not found: " . $e->getMessage() . "\n\n";
    }
    
    $total_time = (microtime(true) - $start_time) * 1000;
    echo "🎯 Total test time: " . number_format($total_time, 2) . "ms\n";
}

function testEquipmentListAPI() {
    global $start_time;
    
    echo "=== EQUIPMENT LIST API TEST ===\n";
    echo "Simulating actual API call...\n\n";
    
    // Simulate POST data
    $_POST['action'] = 'list';
    $_POST['page'] = 1;
    $_POST['search'] = '';
    $_POST['xuong'] = '';
    $_POST['line'] = '';
    $_POST['tinh_trang'] = '';
    
    $api_start = microtime(true);
    
    // Include and test API
    ob_start();
    try {
        include 'api.php';
    } catch (Exception $e) {
        ob_end_clean();
        throw $e;
    }
    $api_output = ob_get_clean();
    
    $api_time = (microtime(true) - $api_start) * 1000;
    
    echo "✅ API Response received\n";
    echo "⏱️  API execution time: " . number_format($api_time, 2) . "ms\n";
    echo "📦 Response size: " . strlen($api_output) . " bytes\n\n";
    
    // Try to decode JSON response
    $json_data = json_decode($api_output, true);
    if ($json_data) {
        echo "✅ JSON decode: SUCCESS\n";
        echo "📊 Records returned: " . (isset($json_data['data']) ? count($json_data['data']) : 'N/A') . "\n";
        if (isset($json_data['pagination'])) {
            echo "📄 Total records: " . ($json_data['pagination']['total_records'] ?? 'N/A') . "\n";
        }
    } else {
        echo "❌ JSON decode: FAILED\n";
        echo "Raw output (first 500 chars):\n" . substr($api_output, 0, 500) . "\n";
    }
    
    $total_time = (microtime(true) - $start_time) * 1000;
    echo "\n🎯 Total test time: " . number_format($total_time, 2) . "ms\n";
}

function testDropdownData() {
    global $start_time;
    
    echo "=== DROPDOWN DATA TEST ===\n";
    echo "Testing dropdown API endpoints...\n\n";
    
    // Test xuong
    $query_start = microtime(true);
    $_GET['action'] = 'get_xuong';
    ob_start();
    include 'api.php';
    $output = ob_get_clean();
    $query_time = (microtime(true) - $query_start) * 1000;
    
    $data = json_decode($output, true);
    echo "✅ Xưởng API: " . (isset($data['data']) ? count($data['data']) : 'ERROR') . " items\n";
    echo "⏱️  Time: " . number_format($query_time, 2) . "ms\n\n";
    
    // Test khu vuc (need xuong_id)
    if (isset($data['data'][0]['id'])) {
        $query_start = microtime(true);
        $_GET['action'] = 'get_khu_vuc';
        $_GET['xuong_id'] = $data['data'][0]['id'];
        ob_start();
        include 'api.php';
        $output = ob_get_clean();
        $query_time = (microtime(true) - $query_start) * 1000;
        
        $khu_vuc_data = json_decode($output, true);
        echo "✅ Khu vực API: " . (isset($khu_vuc_data['data']) ? count($khu_vuc_data['data']) : 'ERROR') . " items\n";
        echo "⏱️  Time: " . number_format($query_time, 2) . "ms\n\n";
    }
    
    // Test lines
    if (isset($data['data'][0]['id'])) {
        $query_start = microtime(true);
        $_GET['action'] = 'get_lines';
        $_GET['xuong_id'] = $data['data'][0]['id'];
        ob_start();
        include 'api.php';
        $output = ob_get_clean();
        $query_time = (microtime(true) - $query_start) * 1000;
        
        $lines_data = json_decode($output, true);
        echo "✅ Lines API: " . (isset($lines_data['data']) ? count($lines_data['data']) : 'ERROR') . " items\n";
        echo "⏱️  Time: " . number_format($query_time, 2) . "ms\n\n";
    }
    
    $total_time = (microtime(true) - $start_time) * 1000;
    echo "🎯 Total test time: " . number_format($total_time, 2) . "ms\n";
}
?>