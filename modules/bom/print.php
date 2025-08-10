<?php
// modules/bom/print.php - Print BOM page
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Kiểm tra quyền truy cập
requirePermission(['admin', 'to_truong', 'user', 'viewer']);

$ids = $_GET['ids'] ?? '';
if (empty($ids)) {
    die('Không có dữ liệu để in');
}

$id_array = explode(',', $ids);
$placeholders = str_repeat('?,', count($id_array) - 1) . '?';

try {
    $sql = "SELECT bom.*, tb.id_thiet_bi, tb.ten_thiet_bi, 
                   x.ten_xuong, pl.ten_line,
                   dm.ten_dong_may, vt.ma_item, vt.ten_vat_tu, vt.dvt, vt.gia, vt.chung_loai
            FROM bom_thiet_bi bom
            JOIN thiet_bi tb ON bom.id_thiet_bi = tb.id
            JOIN xuong x ON tb.id_xuong = x.id
            JOIN production_line pl ON tb.id_line = pl.id
            JOIN vat_tu vt ON bom.id_vat_tu = vt.id
            LEFT JOIN dong_may dm ON bom.id_dong_may = dm.id
            WHERE bom.id IN ($placeholders)
            ORDER BY tb.id_thiet_bi, dm.ten_dong_may, vt.ma_item";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($id_array);
    $bom_items = $stmt->fetchAll();
    
    if (empty($bom_items)) {
        die('Không tìm thấy dữ liệu BOM');
    }
    
    // Group by equipment
    $grouped_bom = [];
    foreach ($bom_items as $item) {
        $key = $item['id_thiet_bi'];
        if (!isset($grouped_bom[$key])) {
            $grouped_bom[$key] = [
                'equipment' => $item,
                'items' => []
            ];
        }
        $grouped_bom[$key]['items'][] = $item;
    }
    
} catch (Exception $e) {
    die('Lỗi truy vấn dữ liệu: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In BOM Thiết bị</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .document-info {
            text-align: right;
            margin-bottom: 15px;
        }
        
        .equipment-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .equipment-header {
            background-color: #f0f0f0;
            padding: 8px;
            border: 1px solid #000;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .equipment-info {
            margin-bottom: 10px;
            padding: 5px;
            border-left: 3px solid #333;
            background-color: #fafafa;
        }
        
        .bom-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .bom-table th,
        .bom-table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        
        .bom-table th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 20px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .signature-box {
            text-align: center;
            width: 30%;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            height: 50px;
            margin-bottom: 5px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .equipment-section {
                page-break-after: auto;
            }
        }
        
        .chung-loai-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-linh-kien { background-color: #007bff; color: white; }
        .badge-vat-tu { background-color: #28a745; color: white; }
        .badge-cong-cu { background-color: #ffc107; color: black; }
        .badge-hoa-chat { background-color: #dc3545; color: white; }
        .badge-khac { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="no-print" style="position: fixed; top: 10px; right: 10px; z-index: 1000; background: white; padding: 10px; border: 1px solid #ccc;">
        <button onclick="window.print()" style="margin-right: 10px; padding: 5px 15px;">
            <i class="fas fa-print"></i> In
        </button>
        <button onclick="window.close()" style="padding: 5px 15px;">
            <i class="fas fa-times"></i> Đóng
        </button>
    </div>

    <!-- Document Header -->
    <div class="header">
        <div class="company-name">CÔNG TY SẢN XUẤT THỰC PHẨM</div>
        <div class="document-title">DANH MỤC VẬT TƯ THIẾT BỊ (BOM)</div>
    </div>
    
    <div class="document-info">
        <strong>Ngày in:</strong> <?= date('d/m/Y H:i') ?><br>
        <strong>Người in:</strong> <?= htmlspecialchars($_SESSION['full_name']) ?>
    </div>

    <!-- BOM Content -->
    <?php foreach ($grouped_bom as $equipment_id => $group): ?>
        <div class="equipment-section">
            <!-- Equipment Header -->
            <div class="equipment-header">
                <i class="fas fa-cogs"></i> THIẾT BỊ: <?= htmlspecialchars($group['equipment']['id_thiet_bi']) ?> - <?= htmlspecialchars($group['equipment']['ten_thiet_bi']) ?>
            </div>
            
            <!-- Equipment Info -->
            <div class="equipment-info">
                <div style="display: flex; justify-content: space-between;">
                    <div>
                        <strong>Vị trí:</strong> <?= htmlspecialchars($group['equipment']['ten_xuong']) ?> - <?= htmlspecialchars($group['equipment']['ten_line']) ?><br>
                        <strong>Số lượng vật tư:</strong> <?= count($group['items']) ?> items
                    </div>
                    <div>
                        <?php
                        $total_value = 0;
                        foreach ($group['items'] as $item) {
                            $total_value += ($item['so_luong'] * $item['gia']);
                        }
                        ?>
                        <strong>Tổng giá trị:</strong> <?= number_format($total_value, 0, ',', '.') ?> ₫
                    </div>
                </div>
            </div>

            <!-- BOM Table -->
            <table class="bom-table">
                <thead>
                    <tr>
                        <th width="5%">STT</th>
                        <th width="12%">Mã vật tư</th>
                        <th width="25%">Tên vật tư</th>
                        <th width="15%">Dòng máy</th>
                        <th width="8%">SL</th>
                        <th width="6%">ĐVT</th>
                        <th width="12%">Đơn giá</th>
                        <th width="12%">Thành tiền</th>
                        <th width="5%">Loại</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $stt = 1;
                    $equipment_total = 0;
                    foreach ($group['items'] as $item): 
                        $thanh_tien = $item['so_luong'] * $item['gia'];
                        $equipment_total += $thanh_tien;
                        
                        // Determine badge class
                        $badge_class = 'badge-' . ($item['chung_loai'] ?? 'khac');
                        $chung_loai_text = [
                            'linh_kien' => 'LK',
                            'vat_tu' => 'VT', 
                            'cong_cu' => 'CC',
                            'hoa_chat' => 'HC',
                            'khac' => 'KC'
                        ][$item['chung_loai']] ?? 'KC';
                    ?>
                    <tr>
                        <td class="text-center"><?= $stt++ ?></td>
                        <td><code><?= htmlspecialchars($item['ma_item']) ?></code></td>
                        <td><?= htmlspecialchars($item['ten_vat_tu']) ?></td>
                        <td><?= htmlspecialchars($item['ten_dong_may'] ?: 'Chung') ?></td>
                        <td class="text-right"><?= number_format($item['so_luong'], 3, ',', '.') ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['dvt']) ?></td>
                        <td class="text-right"><?= number_format($item['gia'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($thanh_tien, 0, ',', '.') ?></td>
                        <td class="text-center">
                            <span class="chung-loai-badge <?= $badge_class ?>"><?= $chung_loai_text ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Total Row -->
                    <tr class="total-row">
                        <td colspan="7" class="text-right"><strong>TỔNG CỘNG:</strong></td>
                        <td class="text-right"><strong><?= number_format($equipment_total, 0, ',', '.') ?> ₫</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <!-- Equipment Notes -->
            <?php 
            $notes = array_filter(array_column($group['items'], 'ghi_chu'));
            if (!empty($notes)): 
            ?>
            <div style="margin-top: 10px;">
                <strong>Ghi chú:</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <?php foreach (array_unique($notes) as $note): ?>
                        <li><?= htmlspecialchars($note) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (next($grouped_bom)): ?>
            <div style="page-break-before: page;"></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Document Footer -->
    <div class="footer">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Tổng số thiết bị:</strong> <?= count($grouped_bom) ?><br>
                <strong>Tổng số vật tư:</strong> <?= count($bom_items) ?> items
            </div>
            <div>
                <?php 
                $grand_total = 0;
                foreach ($grouped_bom as $group) {
                    foreach ($group['items'] as $item) {
                        $grand_total += ($item['so_luong'] * $item['gia']);
                    }
                }
                ?>
                <strong style="font-size: 14px;">TỔNG GIÁ TRỊ: <?= number_format($grand_total, 0, ',', '.') ?> ₫</strong>
            </div>
        </div>
        
        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div><strong>Người lập</strong></div>
                <div class="signature-line"></div>
                <div><?= htmlspecialchars($_SESSION['full_name']) ?></div>
            </div>
            <div class="signature-box">
                <div><strong>Tổ trưởng</strong></div>
                <div class="signature-line"></div>
                <div>(...........................)</div>
            </div>
            <div class="signature-box">
                <div><strong>Giám đốc</strong></div>
                <div class="signature-line"></div>
                <div>(...........................)</div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-style: italic;">
            <small>Tài liệu này được tạo tự động từ hệ thống CMMS - <?= date('d/m/Y H:i:s') ?></small>
        </div>
    </div>

    <script>
        // Auto print when page loads
        window.onload = function() {
            setTimeout(function() {
                // window.print(); // Uncomment to auto-print
            }, 500);
        };
        
        // Print function
        function printDocument() {
            window.print();
        }
        
        // Close function
        function closeWindow() {
            window.close();
        }
    </script>
</body>
</html>