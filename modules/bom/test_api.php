<?php
/**
 * Quick API Test
 * Place this in /modules/bom/ folder and access via browser
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>BOM API Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>BOM API Test</h1>
    
    <div id="results"></div>
    
    <script>
        function testAPI() {
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '<p class="info">Testing API...</p>';
            
            fetch('/modules/bom/api/bom.php?action=list&page=1', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text(); // Get as text first
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    displayResults(data, text);
                } catch (e) {
                    resultsDiv.innerHTML = `
                        <h3 class="error">JSON Parse Error</h3>
                        <p class="error">${e.message}</p>
                        <h4>Raw Response:</h4>
                        <pre>${text}</pre>
                    `;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                resultsDiv.innerHTML = `
                    <h3 class="error">Fetch Error</h3>
                    <p class="error">${error.message}</p>
                `;
            });
        }
        
        function displayResults(data, rawText) {
            const resultsDiv = document.getElementById('results');
            
            let html = '<h3>API Test Results</h3>';
            
            if (data.success) {
                html += '<p class="success">✓ API call successful</p>';
                html += `<p>Data type: ${Array.isArray(data.data) ? 'Array' : typeof data.data}</p>`;
                html += `<p>Data length: ${Array.isArray(data.data) ? data.data.length : 'N/A'}</p>`;
            } else {
                html += '<p class="error">✗ API call failed</p>';
                html += `<p class="error">Error: ${data.message || 'Unknown error'}</p>`;
            }
            
            html += '<h4>Parsed JSON:</h4>';
            html += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            
            html += '<h4>Raw Response:</h4>';
            html += `<pre>${rawText}</pre>`;
            
            resultsDiv.innerHTML = html;
        }
        
        // Test on page load
        window.onload = function() {
            testAPI();
        };
    </script>
    
    <button onclick="testAPI()">Test API Again</button>
    <button onclick="location.reload()">Refresh Page</button>
    
    <h2>Database Test</h2>
    <?php
    try {
        require_once '../../config/database.php';
        $db = Database::getInstance();
        
        echo '<p class="success">✓ Database connection successful</p>';
        
        // Test machine_bom table
        $result = $db->fetch("SELECT COUNT(*) as count FROM machine_bom");
        echo '<p>machine_bom table records: ' . $result['count'] . '</p>';
        
        // Test machine_types table  
        $result = $db->fetch("SELECT COUNT(*) as count FROM machine_types");
        echo '<p>machine_types table records: ' . $result['count'] . '</p>';
        
        // Test parts table
        $result = $db->fetch("SELECT COUNT(*) as count FROM parts");
        echo '<p>parts table records: ' . $result['count'] . '</p>';
        
        // Test users table
        $result = $db->fetch("SELECT COUNT(*) as count FROM users");
        echo '<p>users table records: ' . $result['count'] . '</p>';
        
        // Sample query
        echo '<h3>Sample BOM Query:</h3>';
        $sql = "SELECT mb.id, mb.bom_name, mb.bom_code, mt.name as machine_type_name
                FROM machine_bom mb
                JOIN machine_types mt ON mb.machine_type_id = mt.id
                LIMIT 3";
        $boms = $db->fetchAll($sql);
        echo '<pre>' . print_r($boms, true) . '</pre>';
        
    } catch (Exception $e) {
        echo '<p class="error">✗ Database error: ' . $e->getMessage() . '</p>';
    }
    ?>
    
    <h2>Session Test</h2>
    <?php
    session_start();
    if (!isset($_SESSION['user_id'])) {
        echo '<p class="error">✗ No active session. Please login first.</p>';
        echo '<p><a href="/login.php">Go to Login</a></p>';
    } else {
        echo '<p class="success">✓ User logged in: ' . ($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown') . '</p>';
    }
    ?>
    
</body>
</html>