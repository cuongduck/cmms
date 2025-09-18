<?php
/**
 * Transactions Management Module
 * /modules/transactions/index.php
 */

$pageTitle = 'Quản lý giao dịch xuất kho';
$currentModule = 'transactions';
$moduleCSS = 'transactions';
$moduleJS = 'transactions';

require_once '../../includes/header.php';
requirePermission('inventory', 'view');

// Helper functions
function getBrandyName($brandy) {
    $brands = [
        'IN0000' => 'Mì',
        'ID0000' => 'CSD', 
        'FS0000' => 'Mắm',
        'WPBB20' => 'PET',
        'WPNA30' => 'Nêm',
        'RB0000' => 'Phở',
        '0' => 'Bảo trì chung'
    ];
    return $brands[$brandy] ?? 'Khác';
}

function getBrandyClass($brandy) {
    $classes = [
        'IN0000' => 'bg-primary',
        'ID0000' => 'bg-success', 
        'FS0000' => 'bg-warning',
        'WPBB20' => 'bg-info',
        'WPNA30' => 'bg-secondary',
        'RB0000' => 'bg-danger',
        '0' => 'bg-dark'
    ];
    return $classes[$brandy] ?? 'bg-light text-dark';
}

function safeNumberFormat($number, $decimals = 0) {
    return number_format($number ?? 0, $decimals);
}

function safeCurrencyFormat($amount) {
    return number_format($amount ?? 0, 0) . ' đ';
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$brandy = $_GET['brandy'] ?? '';
$requester = $_GET['requester'] ?? '';
$view_type = $_GET['view_type'] ?? 'month';

// SỬA LẠI: Xử lý month và year parameter
if ($view_type === 'year') {
    // Nếu là lũy kế năm, lấy từ parameter 'year' hoặc 'month'
    $year = $_GET['year'] ?? $_GET['month'] ?? date('Y');
    // Đảm bảo chỉ lấy 4 số đầu (năm)
    $month = substr($year, 0, 4);
} else {
    // Nếu là theo tháng
    $month = $_GET['month'] ?? date('Y-m');
}

$page = intval($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

try {
    // Get overview statistics - SỬA LẠI
    if ($view_type === 'year') {
        $year = substr($month, 0, 4);
        $dateFilter = "YEAR(TransactionDate) = ?";
        $dateParams = [$year];
        $periodLabel = "Năm $year";
    } else {
        $dateFilter = "DATE_FORMAT(TransactionDate, '%Y-%m') = ?";
        $dateParams = [$month];
        $periodLabel = "Tháng " . date('m/Y', strtotime($month . '-01'));
    }

    // Get statistics by brandy
    // Get statistics by brandy - SỬA LẠI
    $statsSql = "SELECT 
        Brandy,
        COUNT(*) as transaction_count,
        SUM(TransactedQty) as total_qty,
        SUM(TotalAmount) as total_amount
    FROM transaction 
    WHERE TransactionType = 'Issue' 
    AND Status IN ('Posted', 'Approved') 
    AND $dateFilter
    GROUP BY Brandy
    ORDER BY total_amount DESC";

    $stats = $db->fetchAll($statsSql, $dateParams);

    // Get total for period - SỬA LẠI
    $totalSql = "SELECT 
        COUNT(*) as total_transactions,
        SUM(TransactedQty) as total_qty,
        SUM(TotalAmount) as total_amount
    FROM transaction 
    WHERE TransactionType = 'Issue' 
    AND Status IN ('Posted', 'Approved') 
    AND $dateFilter";

    $totalStats = $db->fetch($totalSql, $dateParams);
    
    $search_all = isset($_GET['search_all']) ? true : false;
// Build main query for transactions
$sql = "SELECT 
    t.*,
    t.TransactedQty as abs_qty,
    t.TotalAmount as abs_amount
FROM transaction t 
WHERE t.TransactionType = 'Issue' 
AND t.Status IN ('Posted', 'Approved')";

$params = [];

// SỬA LẠI: Chỉ apply date filter khi không phải tìm kiếm toàn bộ
if (!$search_all || empty($search)) {
    $sql .= " AND $dateFilter";
    $params = array_merge($params, $dateParams);
}

// Apply other filters
if (!empty($search)) {
    $sql .= " AND (t.ItemCode LIKE ? OR t.ItemDesc LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if (!empty($brandy)) {
    $sql .= " AND t.Brandy = ?";
    $params[] = $brandy;
}

if (!empty($requester)) {
    $sql .= " AND t.Requester LIKE ?";
    $params[] = '%' . $requester . '%';
}

// Get total count for pagination - SỬA LẠI
$countSql = "SELECT COUNT(*) as total 
             FROM transaction t 
             WHERE t.TransactionType = 'Issue' 
             AND t.Status IN ('Posted', 'Approved')";

$countParams = [];

// Chỉ apply date filter cho count khi không phải tìm kiếm toàn bộ
if (!$search_all || empty($search)) {
    $countSql .= " AND $dateFilter";
    $countParams = array_merge($countParams, $dateParams);
}

if (!empty($search)) {
    $countSql .= " AND (t.ItemCode LIKE ? OR t.ItemDesc LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $countParams = array_merge($countParams, [$searchTerm, $searchTerm]);
}

if (!empty($brandy)) {
    $countSql .= " AND t.Brandy = ?";
    $countParams[] = $brandy;
}

if (!empty($requester)) {
    $countSql .= " AND t.Requester LIKE ?";
    $countParams[] = '%' . $requester . '%';
}

$total = $db->fetch($countSql, $countParams)['total'] ?? 0;

    // Get budget data
    $budgetSql = "SELECT * FROM monthly_budgets WHERE month = ?";
    $budgets = $db->fetchAll($budgetSql, [$month]);
    $budgetData = [];
    foreach ($budgets as $budget) {
        $budgetData[$budget['brandy']] = $budget;
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Lỗi: " . $e->getMessage() . "</div>";
    $stats = [];
    $totalStats = ['total_transactions' => 0, 'total_qty' => 0, 'total_amount' => 0];
    $transactions = [];
    $requesters = [];
    $total = 0;
    $budgetData = [];
}

$pagination = paginate($total, $page, $per_page, 'index.php');
?>

<!-- Period Selection -->
<!-- Period Selection - SỬA LẠI -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end" id="periodForm">
                    <div class="col-md-3">
                        <label class="form-label">Loại xem</label>
                        <select class="form-select" name="view_type" onchange="toggleDateInput()">
                            <option value="month" <?php echo $view_type === 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                            <option value="year" <?php echo $view_type === 'year' ? 'selected' : ''; ?>>Lũy kế năm</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3" id="monthInput">
                        <label class="form-label">Tháng/Năm</label>
                        <input type="month" class="form-control" name="month" value="<?php echo $view_type === 'month' ? $month : date('Y-m'); ?>">
                    </div>
                    
                    <div class="col-md-3" id="yearInput" style="display: none;">
                        <label class="form-label">Năm</label>
                        <input type="number" class="form-control" name="year" value="<?php echo $view_type === 'year' ? $month : date('Y'); ?>" min="2020" max="2030">
                    </div>
                    
                    <!-- Giữ các filter khác -->
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="brandy" value="<?php echo htmlspecialchars($brandy); ?>">
                    <input type="hidden" name="requester" value="<?php echo htmlspecialchars($requester); ?>">
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-line me-1"></i>Xem báo cáo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <div class="h3 mb-1"><?php echo safeCurrencyFormat($totalStats['total_amount']); ?></div>
                <div>Tổng chi phí <?php echo $periodLabel; ?></div>
                <small><?php echo safeNumberFormat($totalStats['total_transactions']); ?> giao dịch</small>
            </div>
        </div>
    </div>
</div>

<!-- Overview Statistics -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-chart-pie me-2"></i>Chi phí theo ngành hàng - <?php echo $periodLabel; ?>
        </h5>
        <?php if ($view_type === 'month'): ?>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="showBudgetModal()">
            <i class="fas fa-dollar-sign me-1"></i>Quản lý ngân sách
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="row">
            <?php if (empty($stats)): ?>
            <div class="col-12 text-center py-4 text-muted">
                <i class="fas fa-chart-pie fa-2x mb-2"></i>
                <p>Không có dữ liệu cho kỳ này</p>
            </div>
            <?php else: ?>
            <?php foreach ($stats as $stat): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card border">
                    <div class="card-body text-center p-3">
                        <div class="mb-2">
                            <span class="badge <?php echo getBrandyClass($stat['Brandy']); ?> fs-6">
                                <?php echo getBrandyName($stat['Brandy']); ?>
                            </span>
                        </div>
                        <div class="h5 mb-1 text-primary">
                            <?php echo safeCurrencyFormat($stat['total_amount']); ?>
                        </div>
                        <small class="text-muted">
                            <?php echo safeNumberFormat($stat['transaction_count']); ?> giao dịch
                        </small>
                        
                        <?php if ($view_type === 'month' && isset($budgetData[$stat['Brandy']])): ?>
                        <?php 
                        $budget = $budgetData[$stat['Brandy']];
                        $usedPercent = $budget['budget_amount'] > 0 ? ($stat['total_amount'] / $budget['budget_amount']) * 100 : 0;
                        $progressClass = $usedPercent > 100 ? 'bg-danger' : ($usedPercent > 80 ? 'bg-warning' : 'bg-success');
                        ?>
                        <div class="mt-2">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?php echo $progressClass; ?>" 
                                     style="width: <?php echo min($usedPercent, 100); ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?php echo number_format($usedPercent, 1); ?>% / <?php echo safeCurrencyFormat($budget['budget_amount']); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<!-- Filters - SỬA LẠI -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter me-2"></i>Bộ lọc tìm kiếm
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <!-- Giữ period selection -->
            <input type="hidden" name="view_type" value="<?php echo $view_type; ?>">
            <?php if ($view_type === 'year'): ?>
                <input type="hidden" name="year" value="<?php echo $month; ?>">
            <?php else: ?>
                <input type="hidden" name="month" value="<?php echo $month; ?>">
            <?php endif; ?>
            
            <div class="col-md-4">
                <label class="form-label">Tìm kiếm</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Mã vật tư, tên vật tư...">
                    <button class="btn btn-outline-secondary" type="button" onclick="toggleSearchAll()">
                        <i class="fas fa-globe"></i>
                    </button>
                </div>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="search_all" value="1" 
                           <?php echo $search_all ? 'checked' : ''; ?> id="searchAllCheck">
                    <label class="form-check-label small text-muted" for="searchAllCheck">
                        Tìm trong toàn bộ dữ liệu (không giới hạn thời gian)
                    </label>
                </div>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Ngành hàng</label>
                <select class="form-select" name="brandy">
                    <option value="">Tất cả</option>
                    <option value="IN0000" <?php echo $brandy === 'IN0000' ? 'selected' : ''; ?>>Mì</option>
                    <option value="ID0000" <?php echo $brandy === 'ID0000' ? 'selected' : ''; ?>>CSD</option>
                    <option value="FS0000" <?php echo $brandy === 'FS0000' ? 'selected' : ''; ?>>Mắm</option>
                    <option value="WPBB20" <?php echo $brandy === 'WPBB20' ? 'selected' : ''; ?>>PET</option>
                    <option value="WPNA30" <?php echo $brandy === 'WPNA30' ? 'selected' : ''; ?>>Nêm</option>
                    <option value="RB0000" <?php echo $brandy === 'RB0000' ? 'selected' : ''; ?>>Phở</option>
                    <option value="0" <?php echo $brandy === '0' ? 'selected' : ''; ?>>Bảo trì chung</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Người yêu cầu</label>
                <select class="form-select" name="requester">
                    <option value="">Tất cả</option>
                    <?php foreach ($requesters as $req): ?>
                    <option value="<?php echo htmlspecialchars($req['Requester']); ?>" 
                            <?php echo $requester === $req['Requester'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($req['Requester']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Lọc
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-success" onclick="exportData('excel')">
                        <i class="fas fa-file-excel me-1"></i>Excel
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="exportData('csv')">
                        <i class="fas fa-file-csv me-1"></i>CSV
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>Giao dịch xuất kho
            <span class="badge bg-secondary ms-2"><?php echo safeNumberFormat($total); ?> giao dịch</span>
        </h5>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width: 80px;">Số phiếu</th>
                        <th style="width: 100px;">Ngày giao dịch</th>
                        <th style="width: 100px;">Mã vật tư</th>
                        <th>Tên vật tư</th>
                        <th style="width: 80px;" class="text-end">Số lượng</th>
                        <th style="width: 60px;">ĐVT</th>
                        <th style="width: 100px;" class="text-end">Đơn giá</th>
                        <th style="width: 120px;" class="text-end">Tổng tiền</th>
                        <th style="width: 100px;">Ngành hàng</th>
                        <th style="width: 80px;">Kho</th>
                        <th style="width: 100px;">Người yêu cầu</th>
                        <th>Lý do xuất</th>
                                                <th style="width: 80px;">Trạng thái</th>

                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="13" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            Không có dữ liệu giao dịch
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $trans): ?>
                    <tr>
                        <td>
                            <code class="text-primary"><?php echo htmlspecialchars($trans['Number']); ?></code>
                        </td>

                        <td>
                            <small><?php echo formatDateTime($trans['TransactionDate']); ?></small>
                        </td>
                        <td>
                            <code><?php echo htmlspecialchars($trans['ItemCode']); ?></code>
                        </td>
                        <td>
                            <div class="fw-medium"><?php echo htmlspecialchars($trans['ItemDesc']); ?></div>
                        </td>
                        <td class="text-end fw-bold text-danger">
                            <?php echo safeNumberFormat($trans['abs_qty'], 2); ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($trans['UOM']); ?></span>
                        </td>
                        <td class="text-end">
                            <?php echo safeCurrencyFormat($trans['Price']); ?>
                        </td>
                        <td class="text-end fw-bold text-primary">
                            <?php echo safeCurrencyFormat($trans['abs_amount']); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo getBrandyClass($trans['Brandy']); ?>">
                                <?php echo getBrandyName($trans['Brandy']); ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($trans['Locator'] ?? '-'); ?></small>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($trans['Requester'] ?? '-'); ?></small>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($trans['Reason'] ?? '-'); ?></small>
                        </td>
                                                <td>
    <span class="badge <?php echo $trans['Status'] === 'Posted' ? 'bg-success' : 'bg-primary'; ?>">
        <?php echo htmlspecialchars($trans['Status']); ?>
    </span>
</td
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?php echo buildPaginationHtml($pagination, 'index.php?' . http_build_query(array_filter($_GET, function($key) { return $key !== 'page'; }, ARRAY_FILTER_USE_KEY))); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Budget Management Modal -->
<div class="modal fade" id="budgetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-dollar-sign me-2"></i>Quản lý ngân sách tháng <?php echo date('m/Y', strtotime($month . '-01')); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="budgetForm">
                    <input type="hidden" name="month" value="<?php echo $month; ?>">
                    
                    <div class="row">
                        <?php 
                        $brandies = [
                            'IN0000' => 'Mì',
                            'ID0000' => 'CSD', 
                            'FS0000' => 'Mắm',
                            'WPBB20' => 'PET',
                            'WPNA30' => 'Nêm',
                            'RB0000' => 'Phở',
                            '0' => 'Bảo trì chung'
                        ];
                        ?>
                        <?php foreach ($brandies as $brandyCode => $brandyName): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <span class="badge <?php echo getBrandyClass($brandyCode); ?> me-2"><?php echo $brandyName; ?></span>
                                Ngân sách (VNĐ)
                            </label>
                            <input type="number" class="form-control" 
                                   name="budget[<?php echo $brandyCode; ?>]" 
                                   value="<?php echo $budgetData[$brandyCode]['budget_amount'] ?? ''; ?>"
                                   placeholder="Nhập ngân sách..."
                                   min="0" step="1000">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Ngân sách được sử dụng để kiểm soát chi phí và hiển thị cảnh báo khi vượt ngưỡng.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="saveBudget()">
                    <i class="fas fa-save me-1"></i>Lưu ngân sách
                </button>
            </div>
        </div>
    </div>
</div>
<?php if ($search_all && !empty($search)): ?>
<div class="alert alert-info alert-dismissible fade show">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Tìm kiếm toàn bộ:</strong> Đang hiển thị kết quả cho "<?php echo htmlspecialchars($search); ?>" trong toàn bộ dữ liệu, không giới hạn thời gian.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<script>
function toggleDateInput() {
    const viewType = document.querySelector('[name="view_type"]').value;
    const monthInput = document.getElementById('monthInput');
    const yearInput = document.getElementById('yearInput');
    
    if (viewType === 'year') {
        monthInput.style.display = 'none';
        yearInput.style.display = 'block';
        
        // Update form to use year value for month parameter
        const yearValue = document.querySelector('[name="year"]').value;
        document.querySelector('[name="month"]').value = yearValue;
    } else {
        monthInput.style.display = 'block';
        yearInput.style.display = 'none';
    }
}

function showBudgetModal() {
    new bootstrap.Modal(document.getElementById('budgetModal')).show();
}

function saveBudget() {
    const formData = new FormData(document.getElementById('budgetForm'));
    
    CMMS.showLoading();
    
    fetch('api/save_budget.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        CMMS.hideLoading();
        if (data.success) {
            CMMS.showToast(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('budgetModal')).hide();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            CMMS.showToast(data.message, 'error');
        }
    })
    .catch(error => {
        CMMS.hideLoading();
        console.error('Error:', error);
        CMMS.showToast('Có lỗi xảy ra', 'error');
    });
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    window.open(`api/export.php?${params.toString()}`, '_blank');
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    toggleDateInput();
    
    // Auto submit form when view type changes
    document.querySelector('[name="view_type"]').addEventListener('change', function() {
        setTimeout(() => {
            document.querySelector('form').submit();
        }, 100);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>