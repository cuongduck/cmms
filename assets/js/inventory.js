/**
 * Inventory Module JavaScript
 * /assets/js/inventory.js
 */

class InventoryManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeDataTable();
    }

    bindEvents() {
        // Quick search với debounce
        const quickSearch = document.getElementById('quickSearch');
        if (quickSearch) {
            let searchTimeout;
            quickSearch.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                if (query.length < 2) {
                    this.hideSuggestions();
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    this.fetchSearchSuggestions(query);
                }, 300);
            });

            quickSearch.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.performQuickSearch();
                }
            });
        }

        // Click outside để ẩn suggestions
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#quickSearch') && !e.target.closest('#searchSuggestions')) {
                this.hideSuggestions();
            }
        });

        // Filter buttons
        this.bindFilterButtons();
    }

    bindFilterButtons() {
        // BOM status filter buttons
        const bomFilterButtons = document.querySelectorAll('[onclick*="filterByBomStatus"]');
        bomFilterButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const status = button.textContent.includes('Trong BOM') ? 'in_bom' : 
                              button.textContent.includes('Ngoài BOM') ? 'out_bom' : 'all';
                this.filterByBomStatus(status);
            });
        });
    }

    async fetchSearchSuggestions(query) {
        try {
            const response = await fetch(`api/search_suggestions.php?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            this.displaySearchSuggestions(data.suggestions);
        } catch (error) {
            console.error('Error fetching suggestions:', error);
        }
    }

    displaySearchSuggestions(suggestions) {
        const container = document.getElementById('searchSuggestions');
        if (!container) return;

        if (suggestions.length === 0) {
            this.hideSuggestions();
            return;
        }

        let html = '';
        suggestions.forEach(item => {
            html += `
                <a href="#" class="dropdown-item suggestion-item" data-value="${item.value}">
                    <div class="fw-medium">${this.highlightMatch(item.label, document.getElementById('quickSearch').value)}</div>
                    <small class="text-muted">${item.type}</small>
                </a>
            `;
        });

        container.innerHTML = html;
        container.style.display = 'block';

        // Bind click events for suggestions
        container.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                this.selectSuggestion(item.dataset.value);
            });
        });
    }

    highlightMatch(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    selectSuggestion(value) {
        document.getElementById('quickSearch').value = value;
        this.hideSuggestions();
        this.performQuickSearch();
    }

    hideSuggestions() {
        const container = document.getElementById('searchSuggestions');
        if (container) {
            container.style.display = 'none';
        }
    }

    performQuickSearch() {
        const query = document.getElementById('quickSearch').value.trim();
        if (query) {
            const url = new URL(window.location);
            url.searchParams.set('search', query);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }
    }

    filterByBomStatus(status) {
        const url = new URL(window.location);
        if (status === 'all') {
            url.searchParams.delete('bom_status');
        } else {
            url.searchParams.set('bom_status', status);
        }
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    async viewItemDetails(itemCode) {
        try {
            const response = await fetch(`api/item_details.php?item_code=${encodeURIComponent(itemCode)}`);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('itemDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('itemDetailsModal'));
                modal.show();
            } else {
                this.showAlert('Không thể tải chi tiết vật tư: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Có lỗi xảy ra khi tải chi tiết vật tư', 'error');
        }
    }

    async showItemTransactions(itemCode) {
        try {
            const response = await fetch(`api/transactions.php?type=item&item_code=${encodeURIComponent(itemCode)}`);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('transactionContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
                modal.show();
            } else {
                this.showAlert('Không thể tải lịch sử giao dịch: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Có lỗi xảy ra khi tải lịch sử giao dịch', 'error');
        }
    }

    async showAllTransactions() {
        try {
            const response = await fetch('api/transactions.php?type=all');
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('transactionContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
                modal.show();
            } else {
                this.showAlert('Không thể tải lịch sử giao dịch: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Có lỗi xảy ra khi tải lịch sử giao dịch', 'error');
        }
    }

    exportInventory() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        window.location.href = 'api/export.php?' + params.toString();
    }

    initializeDataTable() {
        // Thêm sorting cho table nếu cần
        const table = document.getElementById('inventoryTable');
        if (table) {
            this.makeSortable(table);
        }
    }

    makeSortable(table) {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            if (header.textContent.trim() && !header.textContent.includes('Thao tác')) {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    this.sortTable(table, index);
                });
            }
        });
    }

    sortTable(table, columnIndex) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isNumeric = this.isNumericColumn(rows, columnIndex);
        
        // Determine sort direction
        const currentDirection = table.dataset.sortDirection === 'asc' ? 'desc' : 'asc';
        table.dataset.sortDirection = currentDirection;
        
        rows.sort((a, b) => {
            const aVal = a.cells[columnIndex]?.textContent.trim() || '';
            const bVal = b.cells[columnIndex]?.textContent.trim() || '';
            
            if (isNumeric) {
                const aNum = parseFloat(aVal.replace(/[^\d.-]/g, '')) || 0;
                const bNum = parseFloat(bVal.replace(/[^\d.-]/g, '')) || 0;
                return currentDirection === 'asc' ? aNum - bNum : bNum - aNum;
            } else {
                return currentDirection === 'asc' ? 
                    aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            }
        });

        // Re-append sorted rows
        rows.forEach(row => tbody.appendChild(row));
        
        // Update header indicators
        table.querySelectorAll('th').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
        table.querySelectorAll('th')[columnIndex].classList.add(`sorted-${currentDirection}`);
    }

    isNumericColumn(rows, columnIndex) {
        const sampleValues = rows.slice(0, 5).map(row => 
            row.cells[columnIndex]?.textContent.trim() || ''
        );
        
        return sampleValues.every(val => 
            !isNaN(parseFloat(val.replace(/[^\d.-]/g, '')))
        );
    }

    showAlert(message, type = 'info') {
        const alertClass = type === 'error' ? 'alert-danger' : 
                          type === 'success' ? 'alert-success' : 'alert-info';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Insert at top of main content
        const mainContent = document.querySelector('main .pt-3');
        if (mainContent) {
            mainContent.insertAdjacentHTML('afterbegin', alertHtml);
        }
    }

    // Utility functions for formatting
    formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    formatNumber(number, decimals = 0) {
        return new Intl.NumberFormat('vi-VN', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.inventoryManager = new InventoryManager();
});

// Global functions for onclick handlers (backwards compatibility)
function viewItemDetails(itemCode) {
    window.inventoryManager.viewItemDetails(itemCode);
}

function showItemTransactions(itemCode) {
    window.inventoryManager.showItemTransactions(itemCode);
}

function showAllTransactions() {
    window.inventoryManager.showAllTransactions();
}

function exportInventory() {
    window.inventoryManager.exportInventory();
}

function filterByBomStatus(status) {
    window.inventoryManager.filterByBomStatus(status);
}

function performQuickSearch() {
    window.inventoryManager.performQuickSearch();
}