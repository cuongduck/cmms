/**
 * Inventory Module JavaScript
 * /assets/js/inventory.js
 */

if (!window.CMMS) {
    window.CMMS = {};
}

Object.assign(window.CMMS, {
    inventory: {
        // Current filters
        currentFilters: {
            search: '',
            category: '',
            status: '',
            bom_status: '',
            page: 1
        },
        
        // Initialize inventory module
        init: function() {
            console.log('Starting inventory init...');
            this.bindEvents();
            this.initializeTooltips();
            this.setupAutoRefresh();
            this.loadStoredFilters();
            this.table.init();
            this.search.setupQuickSearch();
            console.log('Inventory module fully initialized');
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Filter form submission
            const filterForm = document.querySelector('.card-body form');
            if (filterForm) {
                filterForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.applyFilters();
                });
            }
            
            // Real-time search
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.updateFilter('search', e.target.value);
                        this.applyFilters();
                    }, 500);
                });
            }
            
            // Filter dropdowns
            const filterSelects = document.querySelectorAll('select[name="category"], select[name="status"], select[name="bom_status"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', (e) => {
                    this.updateFilter(e.target.name, e.target.value);
                    this.applyFilters();
                });
            });
            
            // Export buttons
            const exportBtn = document.querySelector('[onclick="exportInventory()"]');
            if (exportBtn) {
                exportBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.exportData('excel');
                });
            }
            
            // Table row clicks for mobile
            this.setupMobileRowClicks();
        },
        
        // Setup mobile row click handlers
        setupMobileRowClicks: function() {
            if (window.innerWidth <= 768) {
                const tableRows = document.querySelectorAll('#inventoryTable tbody tr');
                tableRows.forEach(row => {
                    row.addEventListener('click', (e) => {
                        if (!e.target.closest('.btn')) {
                            const itemCode = row.querySelector('code').textContent;
                            this.showItemDetails(itemCode);
                        }
                    });
                });
            }
        },
        
        // Initialize tooltips
        initializeTooltips: function() {
            const tooltipElements = document.querySelectorAll('[title]');
            tooltipElements.forEach(element => {
                new bootstrap.Tooltip(element);
            });
        },
        
        // Setup auto-refresh
        setupAutoRefresh: function() {
            setInterval(() => {
                if (document.visibilityState === 'visible' && !document.querySelector('.modal.show')) {
                    this.refreshData();
                }
            }, 300000); // 5 minutes
        },
        
        // Load stored filters from localStorage
        loadStoredFilters: function() {
            const stored = localStorage.getItem('cmms_inventory_filters');
            if (stored) {
                try {
                    const filters = JSON.parse(stored);
                    Object.assign(this.currentFilters, filters);
                    this.applyStoredFilters();
                } catch (e) {
                    console.error('Error loading stored filters:', e);
                }
            }
        },
        
        // Apply stored filters to form
        applyStoredFilters: function() {
            Object.keys(this.currentFilters).forEach(key => {
                const element = document.querySelector(`[name="${key}"]`);
                if (element && this.currentFilters[key]) {
                    element.value = this.currentFilters[key];
                }
            });
        },
        
        // Update filter value
        updateFilter: function(key, value) {
            this.currentFilters[key] = value;
            this.saveFilters();
        },
        
        // Save filters to localStorage
        saveFilters: function() {
            localStorage.setItem('cmms_inventory_filters', JSON.stringify(this.currentFilters));
        },
        
        // Apply current filters
        applyFilters: function() {
            const params = new URLSearchParams();
            
            Object.keys(this.currentFilters).forEach(key => {
                if (this.currentFilters[key]) {
                    params.set(key, this.currentFilters[key]);
                }
            });
            
            window.location.href = 'index.php?' + params.toString();
        },
        
        // Refresh data without page reload
        refreshData: function() {
            const currentUrl = new URL(window.location.href);
            
            fetch(currentUrl.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTable = doc.querySelector('#inventoryTable');
                    const currentTable = document.querySelector('#inventoryTable');
                    
                    if (newTable && currentTable) {
                        currentTable.innerHTML = newTable.innerHTML;
                        this.setupMobileRowClicks();
                        this.initializeTooltips();
                        CMMS.showToast('Dữ liệu đã được cập nhật', 'success');
                    }
                })
                .catch(error => {
                    console.error('Error refreshing data:', error);
                });
        },
        
        // Export inventory data
        exportData: function(format = 'excel') {
            const params = new URLSearchParams(window.location.search);
            params.set('export', format);
            
            const link = document.createElement('a');
            link.href = 'api/export.php?' + params.toString();
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            CMMS.showToast('Đang xuất dữ liệu...', 'info');
        },
        
        // Show item details modal
        showItemDetails: function(itemCode) {
            CMMS.ajax({
                url: 'api/item_details.php?item_code=' + encodeURIComponent(itemCode),
                method: 'GET',
                success: (data) => {
                    document.getElementById('itemDetailsContent').innerHTML = data.html;
                    const modal = new bootstrap.Modal(document.getElementById('itemDetailsModal'));
                    modal.show();
                    
                    setTimeout(() => {
                        this.initializeModalTooltips();
                    }, 100);
                },
                error: (error) => {
                    CMMS.showToast('Không thể tải chi tiết vật tư', 'error');
                }
            });
        },
        
        // Show transaction history
        showTransactions: function(itemCode) {
            this.loadTransactionHistory('item', itemCode);
        },
        
        // Show transaction history by type
        showTransactionHistory: function(type, itemCode = null) {
            this.loadTransactionHistory(type, itemCode);
        },
        
        // Load transaction history
        loadTransactionHistory: function(type, itemCode = null, page = 1) {
            let url = `api/transactions.php?type=${type}&page=${page}`;
            if (itemCode) {
                url += '&item_code=' + encodeURIComponent(itemCode);
            }
            
            CMMS.ajax({
                url: url,
                method: 'GET',
                success: (data) => {
                    document.getElementById('transactionContent').innerHTML = data.html;
                    const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
                    modal.show();
                    
                    setTimeout(() => {
                        this.initializeModalTooltips();
                    }, 100);
                },
                error: (error) => {
                    CMMS.showToast('Không thể tải lịch sử giao dịch', 'error');
                }
            });
        },
        
        // Initialize tooltips in modals
        initializeModalTooltips: function() {
            const modalTooltips = document.querySelectorAll('.modal [title]');
            modalTooltips.forEach(element => {
                new bootstrap.Tooltip(element);
            });
        },
        
        // Search functionality
        search: {
            showAdvancedSearch: function() {
                console.log('Advanced search modal');
            },
            
            setupQuickSearch: function() {
                const searchInput = document.querySelector('input[name="search"]');
                if (!searchInput) return;
                
                let suggestionsContainer = document.getElementById('searchSuggestions');
                if (!suggestionsContainer) {
                    suggestionsContainer = document.createElement('div');
                    suggestionsContainer.id = 'searchSuggestions';
                    suggestionsContainer.className = 'position-absolute bg-white border rounded shadow-sm';
                    suggestionsContainer.style.cssText = 'top: 100%; left: 0; right: 0; z-index: 1000; display: none;';
                    searchInput.parentElement.style.position = 'relative';
                    searchInput.parentElement.appendChild(suggestionsContainer);
                }
                
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    const query = e.target.value.trim();
                    
                    if (query.length >= 2) {
                        searchTimeout = setTimeout(() => {
                            this.loadSuggestions(query, suggestionsContainer);
                        }, 300);
                    } else {
                        suggestionsContainer.style.display = 'none';
                    }
                });
                
                document.addEventListener('click', (e) => {
                    if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                        suggestionsContainer.style.display = 'none';
                    }
                });
            },
            
            loadSuggestions: function(query, container) {
                CMMS.ajax({
                    url: 'api/search_suggestions.php?q=' + encodeURIComponent(query),
                    method: 'GET',
                    success: (data) => {
                        if (data.suggestions && data.suggestions.length > 0) {
                            container.innerHTML = data.suggestions.map(item => 
                                `<div class="p-2 border-bottom cursor-pointer hover-bg-light" onclick="CMMS.inventory.selectSuggestion('${item.value}')">
                                    <small class="text-muted">${item.type}</small><br>
                                    <strong>${item.label}</strong>
                                </div>`
                            ).join('');
                            container.style.display = 'block';
                        } else {
                            container.style.display = 'none';
                        }
                    },
                    error: () => {
                        container.style.display = 'none';
                    }
                });
            },
            
            selectSuggestion: function(value) {
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.value = value;
                    document.getElementById('searchSuggestions').style.display = 'none';
                    CMMS.inventory.updateFilter('search', value);
                    CMMS.inventory.applyFilters();
                }
            }
        },
        
        // Statistics functionality
        stats: {
            updateStats: function() {
                CMMS.ajax({
                    url: 'api/stats.php',
                    method: 'GET',
                    success: (data) => {
                        // Update stat cards if needed
                        if (data.total_items) {
                            document.querySelector('.stat-card.bg-primary .stat-number').textContent = 
                                CMMS.inventory.utils.formatNumber(data.total_items);
                        }
                        if (data.in_bom_items) {
                            document.querySelector('.stat-card.bg-info .stat-number').textContent = 
                                CMMS.inventory.utils.formatNumber(data.in_bom_items);
                        }
                        if (data.low_stock || data.out_of_stock) {
                            document.querySelector('.stat-card.bg-warning .stat-number').textContent = 
                                CMMS.inventory.utils.formatNumber((data.low_stock || 0) + (data.out_of_stock || 0));
                        }
                        if (data.total_value) {
                            document.querySelector('.stat-card.bg-success .stat-number').textContent = 
                                CMMS.inventory.utils.formatCurrency(data.total_value);
                        }
                    },
                    error: (error) => {
                        console.warn('Failed to update stats:', error);
                    }
                });
            }
        },
        
        // Table functionality
        table: {
            init: function() {
                console.log('Found inventory table, initializing table functionality...');
                this.setupRowSelection();
                this.setupColumnToggle();
            },
            
            setupRowSelection: function() {
                const table = document.getElementById('inventoryTable');
                if (!table) return;
                
                const headerRow = table.querySelector('thead tr');
                const headerCheckbox = document.createElement('th');
                headerCheckbox.innerHTML = '<input type="checkbox" class="form-check-input" id="selectAll">';
                headerRow.insertBefore(headerCheckbox, headerRow.firstChild);
                
                const bodyRows = table.querySelectorAll('tbody tr');
                bodyRows.forEach(row => {
                    const checkbox = document.createElement('td');
                    checkbox.innerHTML = '<input type="checkbox" class="form-check-input row-select">';
                    row.insertBefore(checkbox, row.firstChild);
                });
                
                const selectAllCheckbox = document.getElementById('selectAll');
                selectAllCheckbox.addEventListener('change', (e) => {
                    const rowCheckboxes = document.querySelectorAll('.row-select');
                    rowCheckboxes.forEach(cb => cb.checked = e.target.checked);
                    this.updateBulkActions();
                });
                
                const rowCheckboxes = document.querySelectorAll('.row-select');
                rowCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        this.updateBulkActions();
                    });
                });
            },
            
            updateBulkActions: function() {
                const selectedRows = document.querySelectorAll('.row-select:checked');
                const bulkActionsContainer = document.getElementById('bulkActions');
                
                if (selectedRows.length > 0) {
                    if (!bulkActionsContainer) {
                        this.createBulkActionsContainer();
                    }
                    bulkActionsContainer.style.display = 'block';
                    bulkActionsContainer.querySelector('.selected-count').textContent = selectedRows.length;
                } else if (bulkActionsContainer) {
                    bulkActionsContainer.style.display = 'none';
                }
            },
            
            createBulkActionsContainer: function() {
                const container = document.createElement('div');
                container.id = 'bulkActions';
                container.className = 'alert alert-info d-flex justify-content-between align-items-center';
                container.style.display = 'none';
                container.innerHTML = `
                    <div>
                        <i class="fas fa-check-square me-2"></i>
                        Đã chọn <span class="selected-count">0</span> mục
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="CMMS.inventory.table.exportSelected()">
                            <i class="fas fa-download me-1"></i>Xuất Excel
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="CMMS.inventory.table.clearSelection()">
                            <i class="fas fa-times me-1"></i>Bỏ chọn
                        </button>
                    </div>
                `;
                
                const table = document.getElementById('inventoryTable');
                table.parentNode.insertBefore(container, table);
            },
            
            exportSelected: function() {
                const selectedRows = document.querySelectorAll('.row-select:checked');
                const itemCodes = Array.from(selectedRows).map(cb => {
                    const row = cb.closest('tr');
                    return row.querySelector('code').textContent;
                });
                
                if (itemCodes.length === 0) {
                    CMMS.showToast('Vui lòng chọn ít nhất một mục', 'warning');
                    return;
                }
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'api/export.php';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.name = 'selected_items';
                input.value = JSON.stringify(itemCodes);
                form.appendChild(input);
                
                const exportType = document.createElement('input');
                exportType.name = 'export';
                exportType.value = 'excel';
                form.appendChild(exportType);
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
                
                CMMS.showToast('Đang xuất dữ liệu đã chọn...', 'info');
            },
            
            clearSelection: function() {
                const checkboxes = document.querySelectorAll('.row-select, #selectAll');
                checkboxes.forEach(cb => cb.checked = false);
                this.updateBulkActions();
            },
            
            setupColumnToggle: function() {
                console.log('Setting up column toggle...');
                const container = document.getElementById('columnToggleContainer');
                if (!container) {
                    console.warn('Column toggle container not found! Check index.php for <div id="columnToggleContainer">');
                    return;
                }
                
                const columnToggle = document.createElement('div');
                columnToggle.className = 'btn-group';
                columnToggle.innerHTML = `
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-columns me-1"></i>Cột hiển thị
                    </button>
                    <ul class="dropdown-menu" id="columnToggleMenu">
                        <!-- Populated by JavaScript -->
                    </ul>
                `;
                
                container.appendChild(columnToggle);
                
                this.populateColumnToggle();
            },
            
            populateColumnToggle: function() {
                const menu = document.getElementById('columnToggleMenu');
                const headers = document.querySelectorAll('#inventoryTable th');
                
                headers.forEach((header, index) => {
                    if (index === 0) return; // Skip checkbox column
                    
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <label class="dropdown-item">
                            <input type="checkbox" class="form-check-input me-2" checked data-column="${index}">
                            ${header.textContent.trim()}
                        </label>
                    `;
                    
                    const checkbox = li.querySelector('input');
                    checkbox.addEventListener('change', (e) => {
                        this.toggleColumn(e.target.dataset.column, e.target.checked);
                    });
                    
                    menu.appendChild(li);
                });
            },
            
            toggleColumn: function(columnIndex, visible) {
                const table = document.getElementById('inventoryTable');
                const headers = table.querySelectorAll('th');
                const rows = table.querySelectorAll('tbody tr');
                
                headers[columnIndex].style.display = visible ? '' : 'none';
                rows.forEach(row => {
                    row.children[columnIndex].style.display = visible ? '' : 'none';
                });
            }
        },
        
        utils: {
            formatCurrency: function(amount) {
                return new Intl.NumberFormat('vi-VN', {
                    style: 'currency',
                    currency: 'VND'
                }).format(amount);
            },
            
            formatNumber: function(number, decimals = 0) {
                return new Intl.NumberFormat('vi-VN', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }).format(number);
            },
            
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            },
            
            generatePDFReport: function(data) {
                console.log('Generating PDF report...');
            },
            
            printTable: function() {
                const printWindow = window.open('', '_blank');
                const table = document.getElementById('inventoryTable').cloneNode(true);
                
                const actionColumns = table.querySelectorAll('th:last-child, td:last-child');
                actionColumns.forEach(col => col.remove());
                
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Báo cáo tồn kho - ${new Date().toLocaleDateString('vi-VN')}</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; }
                                table { width: 100%; border-collapse: collapse; font-size: 12px; }
                                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; font-weight: bold; }
                                .text-end { text-align: right; }
                                .text-center { text-align: center; }
                                @media print { 
                                    body { margin: 0; } 
                                    table { font-size: 10px; }
                                }
                            </style>
                        </head>
                        <body>
                            <h2>Báo cáo tồn kho</h2>
                            <p>Ngày xuất: ${new Date().toLocaleString('vi-VN')}</p>
                            ${table.outerHTML}
                        </body>
                    </html>
                `);
                
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            }
        }
    }
});

// Global functions for backward compatibility
function exportInventory() {
    CMMS.inventory.exportData('excel');
}

function viewItemDetails(itemCode) {
    CMMS.inventory.showItemDetails(itemCode);
}

function showTransactions(itemCode) {
    CMMS.inventory.showTransactions(itemCode);
}

function showTransactionHistory(type, itemCode = null) {
    CMMS.inventory.showTransactionHistory(type, itemCode);
}

function loadTransactionHistory(type, itemCode = null) {
    CMMS.inventory.loadTransactionHistory(type, itemCode);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready, initializing inventory...');
    
    try {
        // Initialize inventory module
        CMMS.inventory.init();
        
        // Initialize table functionality only if table exists
        const inventoryTable = document.getElementById('inventoryTable');
        if (inventoryTable) {
            console.log('Found inventory table, initializing table functionality...');
        } else {
            console.log('No inventory table found, skipping table initialization');
        }
        
        // Setup search functionality only if search input exists
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            console.log('Found search input, setting up quick search...');
        }
        
        // Update stats periodically only if stats elements exist
        const statsElements = document.querySelectorAll('[data-stat]');
        if (statsElements.length > 0) {
            console.log('Found stats elements, setting up periodic updates...');
            setInterval(() => {
                if (document.visibilityState === 'visible') {
                    CMMS.inventory.stats.updateStats();
                }
            }, 60000); // Every minute
        }
        
        console.log('Inventory module fully initialized');
        
    } catch (error) {
        console.error('Error during inventory initialization:', error);
        if (window.location.hostname === 'localhost') {
            CMMS.showToast('Lỗi khởi tạo module inventory: ' + error.message, 'error');
        }
    }
});