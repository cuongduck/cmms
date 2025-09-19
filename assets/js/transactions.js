/**
 * Transactions Module JavaScript
 */

// Global variables
let budgetModal;

document.addEventListener('DOMContentLoaded', function() {
    initializeTransactionsModule();
    toggleDateInput();
    
    document.querySelector('[name="view_type"]').addEventListener('change', function() {
        toggleDateInput();
        // Auto submit after short delay
        setTimeout(() => {
            document.getElementById('periodForm').submit();
        }, 100);
    });
    
    // Auto submit when search_all checkbox changes
    document.getElementById('searchAllCheck').addEventListener('change', function() {
        if (document.querySelector('[name="search"]').value.trim()) {
            setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 100);
        }
    });
});

function initializeTransactionsModule() {
    // Initialize modals
    budgetModal = new bootstrap.Modal(document.getElementById('budgetModal'));
    
    // Initialize tooltips
    initializeTooltips();
    
    // Setup form event listeners
    setupFormEvents();
    
    // Setup table interactions
    setupTableInteractions();
    
    // Setup export functionality
    setupExportButtons();
    
    console.log('Transactions module initialized');
}

function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function setupFormEvents() {
    // Auto-submit form when period changes
    const viewTypeSelect = document.querySelector('[name="view_type"]');
    if (viewTypeSelect) {
        viewTypeSelect.addEventListener('change', function() {
            toggleDateInput();
            // Auto submit after short delay
            setTimeout(() => {
                document.querySelector('form').submit();
            }, 100);
        });
    }
    
    // Handle budget form submission
    const budgetForm = document.getElementById('budgetForm');
    if (budgetForm) {
        budgetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveBudget();
        });
    }
    
    // Format currency inputs
    const budgetInputs = document.querySelectorAll('.budget-input');
    budgetInputs.forEach(input => {
        // Format khi nhập
        input.addEventListener('input', function() {
            formatCurrencyInput(this);
        });
        
        // Focus - hiển thị số thô
        input.addEventListener('focus', function() {
            if (this.dataset.realValue) {
                this.value = this.dataset.realValue;
            }
            this.style.borderColor = '';
        });
        
        // Blur - format lại
        input.addEventListener('blur', function() {
            formatCurrencyInput(this);
        });
        
        // Ngăn paste nội dung không hợp lệ
        input.addEventListener('paste', function(e) {
            setTimeout(() => {
                let value = this.value.replace(/[^\d,]/g, '');
                this.value = value;
                formatCurrencyInput(this);
            }, 10);
        });
    });
}

function setupTableInteractions() {
    // Add row hover effects
    const tableRows = document.querySelectorAll('.table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
    
    // Add sorting functionality to table headers
    const sortableHeaders = document.querySelectorAll('.table th[data-sort]');
    sortableHeaders.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', function() {
            sortTable(this.dataset.sort);
        });
    });
}

function setupExportButtons() {
    const exportButtons = document.querySelectorAll('[onclick^="exportData"]');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const format = this.textContent.toLowerCase().includes('excel') ? 'excel' : 'csv';
            exportData(format);
        });
    });
}

function toggleDateInput() {
    const viewType = document.querySelector('[name="view_type"]').value;
    const monthInput = document.getElementById('monthInput');
    const yearInput = document.getElementById('yearInput');
    
    if (viewType === 'year') {
        monthInput.style.display = 'none';
        yearInput.style.display = 'block';
    } else {
        monthInput.style.display = 'block';
        yearInput.style.display = 'none';
    }
}

function toggleSearchAll() {
    const checkbox = document.getElementById('searchAllCheck');
    checkbox.checked = !checkbox.checked;
    
    if (checkbox.checked) {
        alert('Tìm kiếm sẽ áp dụng cho toàn bộ dữ liệu, không giới hạn thời gian');
    }
}


function showBudgetModal() {
    if (budgetModal) {
        budgetModal.show();
    }
}

function saveBudget() {
    const formData = new FormData(document.getElementById('budgetForm'));
    
    showLoading();
    
    fetch('api/save_budget.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(data.message, 'success');
            budgetModal.hide();
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showToast('Có lỗi xảy ra', 'error');
    });
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    
    // Show loading for export
    showToast('Đang chuẩn bị file xuất...', 'info');
    
    // Open in new window
    window.open(`api/export.php?${params.toString()}`, '_blank');
}

function formatCurrencyInput(input) {
    let value = input.value.replace(/[^\d]/g, '');
    
    // Giới hạn tối đa 13 chữ số (DECIMAL(15,2))
    if (value.length > 13) {
        value = value.substring(0, 13);
        showToast('Giá trị tối đa là 9,999,999,999,999 VNĐ', 'warning');
    }
    
    if (value) {
        // Lưu giá trị thực
        input.dataset.realValue = value;
        // Hiển thị có định dạng
        input.value = parseInt(value).toLocaleString('vi-VN');
    } else {
        input.dataset.realValue = '';
        input.value = '';
    }
}
function validateBudgetAmount(amount) {
    const maxAmount = 9999999999999; // 13 chữ số
    const numAmount = parseFloat(amount);
    
    if (isNaN(numAmount)) {
        return { valid: false, message: 'Vui lòng nhập số hợp lệ' };
    }
    
    if (numAmount < 0) {
        return { valid: false, message: 'Ngân sách không thể âm' };
    }
    
    if (numAmount > maxAmount) {
        return { 
            valid: false, 
            message: 'Ngân sách tối đa là 9,999,999,999,999 VNĐ' 
        };
    }
    
    return { valid: true };
}

function sortTable(column) {
    // Implement client-side sorting if needed
    console.log('Sorting by:', column);
}

function showLoading() {
    if (window.CMMS && CMMS.showLoading) {
        CMMS.showLoading();
    } else {
        // Fallback loading
        document.body.style.cursor = 'wait';
    }
}

function hideLoading() {
    if (window.CMMS && CMMS.hideLoading) {
        CMMS.hideLoading();
    } else {
        // Fallback
        document.body.style.cursor = 'default';
    }
}

function showToast(message, type) {
    if (window.CMMS && CMMS.showToast) {
        CMMS.showToast(message, type);
    } else {
        // Fallback toast
        alert(message);
    }
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount);
}

function formatNumber(number, decimals = 0) {
    return new Intl.NumberFormat('vi-VN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// Auto-refresh data every 5 minutes
setInterval(function() {
    const lastUpdate = localStorage.getItem('transactions_last_update');
    const now = Date.now();
    
    if (!lastUpdate || (now - parseInt(lastUpdate)) > 300000) { // 5 minutes
        console.log('Auto-refreshing transactions data...');
        localStorage.setItem('transactions_last_update', now.toString());
        // Optionally refresh the page or fetch new data
    }
}, 300000);