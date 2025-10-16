<?php
/**
 * Category Keywords Management - FULL VERSION with EDIT & DELETE
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
// Lấy tất cả keywords có sẵn
$keywords = $db->fetchAll("SELECT category, keyword FROM category_keywords ORDER BY category, keyword");
foreach ($keywords as $row) {
    if (!empty($row['keyword'])) {
        $keywordsByCategory[$row['category']][] = $row['keyword'];
    } else {
        // Nếu keyword rỗng, vẫn khởi tạo mảng rỗng
        if (!isset($keywordsByCategory[$row['category']])) {
            $keywordsByCategory[$row['category']] = [];
        }
    }
}

// Lấy tất cả categories DISTINCT (bao gồm cả category không có keyword)
$allCategoriesResult = $db->fetchAll("SELECT DISTINCT category FROM category_keywords ORDER BY category");
$allCategories = array_column($allCategoriesResult, 'category');

// Đảm bảo mọi category đều có entry trong $keywordsByCategory
foreach ($allCategories as $cat) {
    if (!isset($keywordsByCategory[$cat])) {
        $keywordsByCategory[$cat] = [];
    }
}
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Quản lý từ khóa phân loại tự động
                </h5>
                <button type="button" class="btn btn-success btn-sm" onclick="showAddCategoryModal()">
                    <i class="fas fa-plus me-1"></i>Thêm danh mục mới
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Hệ thống sẽ tự động phân loại danh mục dựa trên từ khóa trong tên vật tư. 
                    Bạn có thể thêm/sửa/xóa từ khóa cho mỗi danh mục.
                </div>
                
                <form id="keywordsForm">
                    <?php foreach ($allCategories as $category): ?>
                    <div class="category-section mb-4 border p-3 rounded" data-category="<?php echo htmlspecialchars($category); ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-primary mb-0">
                                <i class="fas fa-folder me-2"></i>
                                <span class="category-name"><?php echo htmlspecialchars($category); ?></span>
                            </h6>
                            <div class="btn-group btn-group-sm">
                                <!-- Nút Edit Category -->
                                <button type="button" class="btn btn-outline-warning" 
                                        onclick="editCategory('<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')"
                                        title="Sửa tên danh mục">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Nút Delete Category -->
                                <button type="button" class="btn btn-outline-danger" 
                                        onclick="deleteCategory('<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')"
                                        title="Xóa danh mục">
                                    <i class="fas fa-trash"></i>
                                </button>
                                
                                <!-- Nút Add Keyword -->
                                <button type="button" class="btn btn-outline-success" 
                                        onclick="addKeyword('<?php echo htmlspecialchars($category, ENT_QUOTES); ?>')">
                                    <i class="fas fa-plus me-1"></i>Thêm từ khóa
                                </button>
                            </div>
                        </div>
                        
                        <div class="keywords-container" data-category="<?php echo htmlspecialchars($category); ?>">
                            <?php 
                            $currentKeywords = $keywordsByCategory[$category] ?? [];
                            if (empty($currentKeywords)): 
                            ?>
                                <p class="text-muted mb-0">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Chưa có từ khóa nào. Nhấn "Thêm từ khóa" để bắt đầu.
                                </p>
                            <?php else: ?>
                                <?php foreach ($currentKeywords as $keyword): ?>
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
                            <?php endif; ?>
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

<!-- Modal Thêm Danh Mục Mới -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-folder-plus me-2"></i>
                    Thêm danh mục mới
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="newCategoryName" class="form-label">
                        Tên danh mục mới <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="newCategoryName" class="form-control" 
                           placeholder="VD: Thiết bị đo lường" required>
                </div>
                <div class="mb-3">
                    <label for="firstKeyword" class="form-label">Từ khóa đầu tiên (tùy chọn)</label>
                    <input type="text" id="firstKeyword" class="form-control" 
                           placeholder="VD: do luong, cam bien">
                    <small class="text-muted">Để trống sẽ tự động dùng tên danh mục làm từ khóa</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="addNewCategory()">
                    <i class="fas fa-plus me-1"></i>Thêm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa Tên Danh Mục -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Sửa tên danh mục
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="oldCategoryName" class="form-label">Tên danh mục hiện tại</label>
                    <input type="text" id="oldCategoryName" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label for="editCategoryName" class="form-label">
                        Tên danh mục mới <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="editCategoryName" class="form-control" 
                           placeholder="Nhập tên mới" required>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Lưu ý:</strong> Tất cả từ khóa của danh mục này sẽ được chuyển sang tên mới.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveEditCategory()">
                    <i class="fas fa-save me-1"></i>Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Hiển thị modal thêm danh mục mới
 */
function showAddCategoryModal() {
    const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
    document.getElementById('newCategoryName').value = '';
    document.getElementById('firstKeyword').value = '';
    modal.show();
}

/**
 * Thêm danh mục mới
 */
function addNewCategory() {
    const categoryName = document.getElementById('newCategoryName').value.trim();
    const firstKeyword = document.getElementById('firstKeyword').value.trim();
    
    if (!categoryName) {
        CMMS.showToast('Vui lòng nhập tên danh mục', 'warning');
        return;
    }
    
    CMMS.showLoading();
    
    CMMS.ajax({
        url: 'api/category_keywords.php',
        method: 'POST',
        body: new URLSearchParams({
            action: 'add_category',
            category: categoryName,
            first_keyword: firstKeyword
        }),
        success: (data) => {
            CMMS.hideLoading();
            
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                
                // Đóng modal
                bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
                
                // Reload trang sau 1 giây
                setTimeout(() => location.reload(), 1000);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        },
        error: (error, response) => {
            CMMS.hideLoading();
            CMMS.showToast(response?.message || 'Lỗi khi thêm danh mục', 'error');
        }
    });
}

/**
 * Hiển thị modal edit category
 */
function editCategory(categoryName) {
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    document.getElementById('oldCategoryName').value = categoryName;
    document.getElementById('editCategoryName').value = categoryName;
    modal.show();
    
    // Focus vào input
    setTimeout(() => {
        const input = document.getElementById('editCategoryName');
        input.focus();
        input.select();
    }, 500);
}

/**
 * Lưu tên category mới
 */
function saveEditCategory() {
    const oldName = document.getElementById('oldCategoryName').value.trim();
    const newName = document.getElementById('editCategoryName').value.trim();
    
    if (!newName) {
        CMMS.showToast('Vui lòng nhập tên danh mục mới', 'warning');
        return;
    }
    
    if (oldName === newName) {
        CMMS.showToast('Tên mới phải khác tên cũ', 'warning');
        return;
    }
    
    CMMS.showLoading();
    
    CMMS.ajax({
        url: 'api/category_keywords.php',
        method: 'POST',
        body: new URLSearchParams({
            action: 'edit_category',
            old_category: oldName,
            new_category: newName
        }),
        success: (data) => {
            CMMS.hideLoading();
            
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                
                // Đóng modal
                bootstrap.Modal.getInstance(document.getElementById('editCategoryModal')).hide();
                
                // Reload trang sau 1 giây
                setTimeout(() => location.reload(), 1000);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        },
        error: (error, response) => {
            CMMS.hideLoading();
            CMMS.showToast(response?.message || 'Lỗi khi sửa danh mục', 'error');
        }
    });
}

/**
 * Xóa category
 */
function deleteCategory(categoryName) {
    if (!confirm(`Bạn có chắc muốn xóa danh mục "${categoryName}" và TẤT CẢ từ khóa của nó?\n\nHành động này không thể hoàn tác!`)) {
        return;
    }
    
    CMMS.showLoading();
    
    CMMS.ajax({
        url: 'api/category_keywords.php',
        method: 'POST',
        body: new URLSearchParams({
            action: 'delete_category',
            category: categoryName
        }),
        success: (data) => {
            CMMS.hideLoading();
            
            if (data.success) {
                CMMS.showToast(data.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                CMMS.showToast(data.message, 'error');
            }
        },
        error: (error, response) => {
            CMMS.hideLoading();
            CMMS.showToast(response?.message || 'Lỗi khi xóa danh mục', 'error');
        }
    });
}

/**
 * Thêm keyword mới vào category
 */
function addKeyword(category) {
    const container = document.querySelector(`.keywords-container[data-category="${category}"]`);
    
    // Xóa thông báo "Chưa có từ khóa" nếu có
    const emptyMsg = container.querySelector('p.text-muted');
    if (emptyMsg) emptyMsg.remove();
    
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

/**
 * Xóa keyword
 */
function removeKeyword(button) {
    const item = button.closest('.keyword-item');
    const container = item.closest('.keywords-container');
    
    item.remove();
    
    // Nếu không còn keyword nào, hiển thị thông báo
    if (container.querySelectorAll('.keyword-item').length === 0) {
        container.innerHTML = `
            <p class="text-muted mb-0">
                <i class="fas fa-info-circle me-1"></i>
                Chưa có từ khóa nào. Nhấn "Thêm từ khóa" để bắt đầu.
            </p>
        `;
    }
}

/**
 * Lưu tất cả keywords
 */
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
    
    CMMS.showLoading();
    
    fetch('api/category_keywords.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'save_all',
            categories: categories
        })
    })
    .then(response => response.json())
    .then(data => {
        CMMS.hideLoading();
        
        if (data.success) {
            CMMS.showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            CMMS.showToast(data.message, 'error');
        }
    })
    .catch(error => {
        CMMS.hideLoading();
        console.error('Error:', error);
        CMMS.showToast('Lỗi kết nối', 'error');
    });
}

/**
 * Test phân loại một item
 */
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
                        <small class="text-muted">Gợi ý khác: ${data.data.suggestions.join(', ') || 'Không có'}</small>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `<div class="alert alert-danger">Lỗi: ${data.message}</div>`;
            }
        }
    });
}

/**
 * Test phân loại với nhiều ví dụ
 */
function testCategorization() {
    const testCases = [
        'servo driver motor',
        'van điện từ 3/2',
        'relay 24v', 
        'biến tần 3hp',
        'encoder 1024 xung',
        'motor giảm tốc 1hp'
    ];
    
    let results = '<h6>Test với các ví dụ phổ biến:</h6>';
    let completed = 0;
    
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
                
                completed++;
                if (completed === testCases.length) {
                    document.getElementById('testResult').innerHTML = results;
                }
            }
        });
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>