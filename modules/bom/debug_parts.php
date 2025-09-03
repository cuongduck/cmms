<?php
/**
 * Debug Parts API
 * Test parts API endpoints
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Parts API Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Parts API Test</h1>
    
    <div class="test-section">
        <h2>1. Test Parts List</h2>
        <button onclick="testPartsList()">Test Parts List</button>
        <div id="partsListResult"></div>
    </div>
    
    <div class="test-section">
        <h2>2. Test Part Details</h2>
        <input type="number" id="partIdInput" placeholder="Enter part ID" value="1">
        <button onclick="testPartDetails()">Test Part Details</button>
        <div id="partDetailsResult"></div>
    </div>
    
    <div class="test-section">
        <h2>3. Test Create Part</h2>
        <button onclick="testCreatePart()">Test Create Part</button>
        <div id="createPartResult"></div>
    </div>
    
    <div class="test-section">
        <h2>4. Database Check</h2>
        <?php
        try {
            require_once '../../config/database.php';
            $db = Database::getInstance();
            
            echo '<p class="success">✓ Database connection successful</p>';
            
            // Check parts table
            $result = $db->fetch("SELECT COUNT(*) as count FROM parts");
            echo '<p>Parts count: ' . $result['count'] . '</p>';
            
            // Show sample parts
            $parts = $db->fetchAll("SELECT id, part_code, part_name FROM parts LIMIT 5");
            echo '<h4>Sample Parts:</h4><pre>' . print_r($parts, true) . '</pre>';
            
        } catch (Exception $e) {
            echo '<p class="error">✗ Database error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <script>
        function testPartsList() {
            const resultDiv = document.getElementById('partsListResult');
            resultDiv.innerHTML = '<p class="info">Testing parts list...</p>';
            
            fetch('/modules/bom/api/parts.php?action=list&page=1', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Parts list response status:', response.status);
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    displayResult(resultDiv, data, text);
                } catch (e) {
                    resultDiv.innerHTML = `
                        <h4 class="error">JSON Parse Error</h4>
                        <p class="error">${e.message}</p>
                        <h5>Raw Response:</h5>
                        <pre>${text}</pre>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
            });
        }
        
        function testPartDetails() {
            const partId = document.getElementById('partIdInput').value;
            const resultDiv = document.getElementById('partDetailsResult');
            
            if (!partId) {
                resultDiv.innerHTML = '<p class="error">Please enter a part ID</p>';
                return;
            }
            
            resultDiv.innerHTML = '<p class="info">Testing part details...</p>';
            
            fetch(`/modules/bom/api/parts.php?action=getDetails&id=${partId}`, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Part details response status:', response.status);
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    displayResult(resultDiv, data, text);
                } catch (e) {
                    resultDiv.innerHTML = `
                        <h4 class="error">JSON Parse Error</h4>
                        <p class="error">${e.message}</p>
                        <h5>Raw Response:</h5>
                        <pre>${text}</pre>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
            });
        }
        
        function testCreatePart() {
            const resultDiv = document.getElementById('createPartResult');
            resultDiv.innerHTML = '<p class="info">Testing create part...</p>';
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('part_code', 'TEST' + Date.now());
            formData.append('part_name', 'Test Part ' + Date.now());
            formData.append('unit', 'Cái');
            formData.append('category', 'Test');
            formData.append('unit_price', '1000');
            
            fetch('/modules/bom/api/parts.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Create part response status:', response.status);
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    displayResult(resultDiv, data, text);
                } catch (e) {
                    resultDiv.innerHTML = `
                        <h4 class="error">JSON Parse Error</h4>
                        <p class="error">${e.message}</p>
                        <h5>Raw Response:</h5>
                        <pre>${text}</pre>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<p class="error">Error: ${error.message}</p>`;
            });
        }
        
        function displayResult(div, data, rawText) {
            let html = '';
            
            if (data.success) {
                html += '<p class="success">✓ API call successful</p>';
            } else {
                html += '<p class="error">✗ API call failed</p>';
                html += `<p class="error">Error: ${data.message || 'Unknown error'}</p>`;
            }
            
            html += '<h5>Parsed JSON:</h5>';
            html += `<pre>${JSON.stringify(data, null, 2)}</pre>`;
            
            if (rawText.length < 2000) {
                html += '<h5>Raw Response:</h5>';
                html += `<pre>${rawText}</pre>`;
            }
            
            div.innerHTML = html;
        }
    </script>
</body>
</html>