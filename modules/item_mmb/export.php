<?php
/**
 * Export Items MMB to Excel
 * /modules/item_mmb/export.php
 */

require_once 'config.php';

// Check permission
requirePermission('item_mmb', 'export');

// Get filters from query string
$filters = [
    'search' => $_GET['search'] ?? '',
    'vendor' => $_GET['vendor'] ?? '',
    'sort' => $_GET['sort'] ?? 'ID',
    'order' => $_GET['order'] ?? 'DESC'
];

// Export
exportItemsMMBToExcel($filters);
?>