<?php
/**
 * Category Keywords Management
 * /modules/spare_parts/category_keywords.php
 */

require_once 'config.php';
requirePermission('spare_parts', 'edit');

$pageTitle = 'Quản lý từ khóa danh mục';
$currentModule = 'spare_parts';

$breadcrumb = [
    ['title' => 'Quản lý Spare Parts', 'url' => 'index.php'],
    ['title' => 'Quản lý từ khóa danh mục', 'url' => '']
];

require_once '../../includes/header.php';

// Get current keywords
$keywordsByCategory = [];
$keywords = $db->fetchAll("SELECT category, keyword FROM category_keywords ORDER BY category, keyword");
foreach ($keywords as $row) {
    $keywordsByCategory[$row['category']][] = $row['keyword'];
}

// Get all categories
$allCategories = array_keys($sparePartsConfig['auto_categorization']);
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Quản lý từ khóa phân loại tự động
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Hệ thống sẽ tự động phân loại danh mục dựa trên từ khóa trong tên vật tư. 
                    Bạn có thể thêm/sửa/xóa từ khóa cho mỗi danh mục.
                </div>
                
                <form id="keywordsForm">
                    <?php foreach ($allCategories as $category): ?>
                    <div class="category-section mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-primary"><?php echo htmlspecialchars($category); ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-success" 
                                    onclick="addKeyword('<?php echo htmlspecialchars($category); ?>')">
                                <i class="fas fa-plus me-1"></i>Thêm từ khóa
                            </button>
                        </div>
                        
                        <div class="keywords-container" data-category="<?php echo htmlspecialchars($category); ?>">
                            <?php 
                            $currentKeywords = $keywordsByCategory[$category] ?? [];
                            foreach ($currentKeywords as $keyword): 
                            ?>
                            <div class="keyword-item d-inline-block me-2 mb-2">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control keyword-input" 
                                           value="<?php echo htmlspecialchars($keyword); ?>" 
                                           data-category="<?php echo htmlspecialchars($category); ?>"
                                           data-original="<?php echo htmlspecialchars($keyword); ?>">
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="removeKeyword(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-end">
                        <button type="button" onclick="saveKeywords()" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Lưu tất cả
                        </button>
                        <button type="button" onclick="testCategorization()" class="btn btn-outline-info">
                            <i class="fas fa-vial me-2"></i>Test phân loại
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Test Panel -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-vial me-2"></i>
                    Test phân loại
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="testItemName" class="form-label">Nhập tên vật tư để test:</label>
                    <input type="text" id="testItemName" class="form-control" 
                           placeholder="VD: servo motor driver">
                </div>
                <button onclick="runTest()" class="btn btn-info btn-sm w-100">
                    <i class="fas fa-play me-1"></i>Chạy test
                </button>
                <div id="testResult" class="mt-3"></div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Thống kê từ khóa
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($allCategories as $category): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span><?php echo htmlspecialchars($category); ?>:</span>
                    <span class="badge bg-primary">
                        <?php echo count($keywordsByCategory[$category] ?? []); ?> từ khóa
                    </span>
                </div>
                <?php endforeach; ?>
                
                <hr>
                <div class="text-center">
                    <strong>Tổng: <?php echo array_sum(array_map('count', $keywordsByCategory)); ?> từ khóa</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addKeyword(category) {
    const container = document.querySelector(`.keywords-container[data-category="${category}"]`);
    const keywordItem = document.createElement('div');
    keywordItem.className = 'keyword-item d-inline-block me-2 mb-2';
    keywordItem.innerHTML = `
        <div class="input-group input-group-sm">
            <input type="text" class="form-control keyword-input" 
                   placeholder="Nhập từ khóa..." 
                   data-category="${category}">
            <button type="button" class="btn btn-outline-danger" onclick="removeKeyword(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(keywordItem);
    keywordItem.querySelector('input').focus();
}

function removeKeyword(button) {
    button.closest('.keyword-item').remove();
}

function saveKeywords() {
    const categories = {};
    
    document.querySelectorAll('.keywords-container').forEach(container => {
        const category = container.dataset.category;
        categories[category] = [];
        
        container.querySelectorAll('.keyword-input').forEach(input => {
            const keyword = input.value.trim();
            if (keyword) {
                categories[category].push(keyword);
            }
        });
    });
    
    CMMS.ajax({
        url: 'api/category_keywords.php',
        method: 'POST',
        body: JSON.stringify({
            action: 'save_all',
            categories: categories
        }),
        headers: {
            'Content-Type': 'application/json'
        },
        success: (data) => {
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        }
    });
}

function runTest() {
    const itemName = document.getElementById('testItemName').value.trim();
    if (!itemName) {
        CMMS.showToast('Vui lòng nhập tên vật tư', 'warning');
        return;
    }
    
    CMMS.ajax({
        url: 'api/spare_parts.php?action=detect_category&item_name=' + encodeURIComponent(itemName),
        method: 'GET',
        success: (data) => {
            const resultDiv = document.getElementById('testResult');
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>Kết quả:</strong><br>
                        Danh mục: <span class="badge bg-primary">${data.data.category}</span><br>
                        Độ tin cậy: <span class="badge bg-info">${data.data.confidence}%</span><br>
                        <small class="text-muted">Gợi ý khác: ${data.data.suggestions.join(', ')}</small>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger">Lỗi: ${data.message}</div>`;
            }
        }
    });
}

function testCategorization() {
    // Hiển thị modal với các test cases phổ biến
    const testCases = [
        'servo driver motor',
        'van điện từ 3/2',
        'relay 24v', 
        'biến tần 3hp',
        'encoder 1024 xung',
        'motor giảm tốc 1hp'
    ];
    
    let results = '<h6>Test với các ví dụ phổ biến:</h6>';
    
    testCases.forEach(testCase => {
        CMMS.ajax({
            url: 'api/spare_parts.php?action=detect_category&item_name=' + encodeURIComponent(testCase),
            method: 'GET',
            success: (data) => {
                if (data.success) {
                    results += `<div class="mb-2">
                        <strong>"${testCase}"</strong><br>
                        → <span class="badge bg-primary">${data.data.category}</span> 
                        (${data.data.confidence}%)
                    </div>`;
                }
            }
        });
    });
    
    setTimeout(() => {
        document.getElementById('testResult').innerHTML = results;
    }, 1000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>