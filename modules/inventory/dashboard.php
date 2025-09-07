<?php
/**
 * Inventory Dashboard
 * /modules/inventory/dashboard.php
 */

$pageTitle = 'Dashboard Tồn Kho';
$currentModule = 'inventory';
$moduleCSS = 'inventory';
$moduleJS = 'inventory-dashboard';

require_once '../../includes/header.php';
requirePermission('inventory', 'view');

// Get summary statistics
$stats = [
    'total_items' => $db->fetch("SELECT COUNT(*) as count FROM onhand")['count'],
    'in_bom_items' => $db->fetch("SELECT COUNT(DISTINCT o.ItemCode) as count FROM onhand o JOIN parts p ON o.ItemCode = p.part_code")['count'],
    'out_of_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand WHERE Onhand <= 0")['count'],
    'low_stock' => $db->fetch("SELECT COUNT(*) as count FROM onhand o LEFT JOIN parts p ON o.ItemCode = p.part_code WHERE p.min_stock > 0 AND o.Onhand < p.min_stock AND o.Onhand > 0")['count'],
    'total_value' => $db->fetch("SELECT SUM(OH_Value) as total FROM onhand")['total'] ?? 0
];

// Get category breakdown
$categoryBreakdown = $db->fetchAll("
    SELECT 
        COALESCE(p.category, 'Không phân loại') as category,
        COUNT(*) as count,
        SUM(o.OH_Value) as total_value,
        SUM(CASE WHEN o.Onhand <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    GROUP BY COALESCE(p.category, 'Không phân loại')
    ORDER BY count DESC
    LIMIT 10
");

// Get items needing attention
$attentionItems = $db->fetchAll("
    SELECT 
        o.ItemCode,
        o.Itemname,
        o.Onhand,
        o.UOM,
        p.min_stock,
        p.part_name,
        CASE
            WHEN o.Onhand <= 0 THEN 'Hết hàng'
            WHEN p.min_stock > 0 AND o.Onhand < p.min_stock THEN 'Thiếu hàng'
            ELSE 'Bình thường'
        END as status
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE o.Onhand <= 0 OR (p.min_stock > 0 AND o.Onhand < p.min_stock)
    ORDER BY 
        CASE WHEN o.Onhand <= 0 THEN 1 ELSE 2 END,
        o.Onhand ASC
    LIMIT 15
");

// Get top value items
$topItems = $db->fetchAll("
    SELECT 
        o.ItemCode,
        o.Itemname,
        o.Onhand,
        o.UOM,
        o.OH_Value,
        p.part_name
    FROM onhand o
    LEFT JOIN parts p ON o.ItemCode = p.part_code
    WHERE o.OH_Value > 0
    ORDER BY o.OH_Value DESC
    LIMIT 10
");

// Get recent transactions summary
$recentTransactions = $db->fetchAll("
    SELECT 
        DATE(TransactionDate) as transaction_date,
        COUNT(*) as transaction_count,
        SUM(CASE WHEN TransactedQty > 0 THEN TransactedQty ELSE 0 END) as total_in,
        SUM(CASE WHEN TransactedQty < 0 THEN ABS(TransactedQty) ELSE 0 END) as total_out
    FROM transaction 
    WHERE TransactionDate >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(TransactionDate)
    ORDER BY transaction_date DESC
");
?>

<!-- Quick Stats Row -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div>
                        <div class="stat-number" data-stat="total_items"><?php echo number_format($stats['total_items']); ?></div>
                        <div class="stat-label">Tổng mặt hàng</div>
                    </div>
                </div>
            </div>
    </div>
</div>

<!-- Charts and Analysis Row -->
<div class="row mb-4">
    <!-- Stock Status Chart -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Phân tích trạng thái tồn kho
                </h5>
            </div>
            <div class="card-body">
                <canvas id="stockStatusChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Thao tác nhanh
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-warehouse me-2"></i>Xem tồn kho
                    </a>
                    
                    <?php if (hasPermission('inventory', 'export')): ?>
                    <button type="button" class="btn btn-success" onclick="exportLowStock()">
                        <i class="fas fa-exclamation-triangle me-2"></i>Xuất hàng thiếu
                    </button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-info" onclick="showTransactionHistory('all')">
                        <i class="fas fa-history me-2"></i>Lịch sử giao dịch
                    </button>
                    
                    <button type="button" class="btn btn-warning" onclick="generateReport()">
                        <i class="fas fa-file-pdf me-2"></i>Tạo báo cáo
                    </button>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">Cập nhật cuối: <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Breakdown -->
<div class="row mb-4">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tags me-2"></i>Phân bố theo danh mục
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Danh mục</th>
                                <th class="text-center">Số lượng</th>
                                <th class="text-end">Giá trị</th>
                                <th class="text-center">Hết hàng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryBreakdown as $category): ?>
                            <tr>
                                <td>
                                    <a href="index.php?category=<?php echo urlencode($category['category']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($category['category']); ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?php echo number_format($category['count']); ?></span>
                                </td>
                                <td class="text-end fw-bold">
                                    <?php echo formatCurrency($category['total_value']); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($category['out_of_stock_count'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $category['out_of_stock_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-success">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line me-2"></i>Giao dịch 7 ngày qua
                </h5>
            </div>
            <div class="card-body">
                <canvas id="transactionChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Items Needing Attention -->
<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Vật tư cần chú ý
                </h5>
                <a href="index.php?status=out_of_stock" class="btn btn-sm btn-outline-warning">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($attentionItems)): ?>
                <div class="text-center py-4 text-success">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>Tuyệt vời!</h5>
                    <p class="text-muted">Không có vật tư nào cần chú ý</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Mã vật tư</th>
                                <th>Tên vật tư</th>
                                <th class="text-end">Tồn hiện tại</th>
                                <th class="text-end">Tồn tối thiểu</th>
                                <th>Trạng thái</th>
                                <th class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attentionItems as $item): ?>
                            <tr>
                                <td>
                                    <code class="text-primary"><?php echo htmlspecialchars($item['ItemCode']); ?></code>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($item['Itemname']); ?></div>
                                    <?php if ($item['part_name']): ?>
                                        <small class="text-muted">BOM: <?php echo htmlspecialchars($item['part_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold <?php echo $item['Onhand'] <= 0 ? 'text-danger' : 'text-warning'; ?>">
                                        <?php echo number_format($item['Onhand'], 2); ?> <?php echo htmlspecialchars($item['UOM']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php echo number_format($item['min_stock'] ?? 0, 2); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $item['status'] === 'Hết hàng' ? 'bg-danger' : 'bg-warning'; ?>">
                                        <?php echo $item['status']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="viewItemDetails('<?php echo $item['ItemCode']; ?>')"
                                            title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Top Value Items -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-crown me-2 text-warning"></i>Top giá trị cao
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($topItems, 0, 8) as $index => $item): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center border-0">
                        <div class="flex-grow-1">
                            <div class="fw-medium"><?php echo htmlspecialchars($item['Itemname']); ?></div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($item['ItemCode']); ?> - 
                                <?php echo number_format($item['Onhand'], 0); ?> <?php echo htmlspecialchars($item['UOM']); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-success"><?php echo formatCurrency($item['OH_Value']); ?></div>
                            <span class="badge bg-primary">#<?php echo $index + 1; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Item Details -->
<div class="modal fade" id="itemDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết vật tư</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="itemDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Modal for Transaction History -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lịch sử giao dịch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart data
const stockData = {
    normal: <?php echo $stats['total_items'] - $stats['out_of_stock'] - $stats['low_stock']; ?>,
    low_stock: <?php echo $stats['low_stock']; ?>,
    out_of_stock: <?php echo $stats['out_of_stock']; ?>
};

const transactionData = <?php echo json_encode($recentTransactions); ?>;

// Stock Status Chart
const stockCtx = document.getElementById('stockStatusChart').getContext('2d');
new Chart(stockCtx, {
    type: 'doughnut',
    data: {
        labels: ['Bình thường', 'Thiếu hàng', 'Hết hàng'],
        datasets: [{
            data: [stockData.normal, stockData.low_stock, stockData.out_of_stock],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Transaction Chart
const transCtx = document.getElementById('transactionChart').getContext('2d');
new Chart(transCtx, {
    type: 'line',
    data: {
        labels: transactionData.map(item => new Date(item.transaction_date).toLocaleDateString('vi-VN')),
        datasets: [
            {
                label: 'Nhập kho',
                data: transactionData.map(item => item.total_in),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Xuất kho',
                data: transactionData.map(item => item.total_out),
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN').format(value);
                    }
                }
            }
        },
        plugins: {
            legend: {
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(context.parsed.y);
                    }
                }
            }
        }
    }
});

// Dashboard functions
function viewItemDetails(itemCode) {
    CMMS.ajax({
        url: 'api/item_details.php?item_code=' + encodeURIComponent(itemCode),
        method: 'GET',
        success: (data) => {
            document.getElementById('itemDetailsContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('itemDetailsModal')).show();
        },
        error: () => {
            CMMS.showToast('Không thể tải chi tiết vật tư', 'error');
        }
    });
}

function showTransactionHistory(type) {
    CMMS.ajax({
        url: 'api/transactions.php?type=' + type,
        method: 'GET',
        success: (data) => {
            document.getElementById('transactionContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('transactionModal')).show();
        },
        error: () => {
            CMMS.showToast('Không thể tải lịch sử giao dịch', 'error');
        }
    });
}

function exportLowStock() {
    window.location.href = 'api/export.php?status=low_stock&export=excel';
}

function generateReport() {
    CMMS.showToast('Tính năng đang phát triển...', 'info');
}

// Auto refresh every 5 minutes
setInterval(() => {
    if (document.visibilityState === 'visible') {
        window.location.reload();
    }
}, 300000);
</script>

<?php require_once '../../includes/footer.php'; ?>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    <div>
                        <div class="stat-number" data-stat="in_bom_items"><?php echo number_format($stats['in_bom_items']); ?></div>
                        <div class="stat-label">Vật tư BOM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stat-number" data-stat="low_stock"><?php echo number_format($stats['low_stock'] + $stats['out_of_stock']); ?></div>
                        <div class="stat-label">Cảnh báo tồn kho</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon me-3">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div>
                        <div class="stat-number" data-stat="total_value"><?php echo formatCurrency($stats['total_value']); ?></div>
                        <div class="stat-label">Tổng giá trị</div>
                    </div>
                </div>
            </div>
        </div>