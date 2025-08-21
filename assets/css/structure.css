/* Structure Module CSS */

.structure-tree {
    padding: 1rem;
    max-height: 600px;
    overflow-y: auto;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.tree-node {
    margin-bottom: 0.125rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.tree-item {
    display: flex;
    align-items: center;
    padding: 0.75rem 0.5rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
    position: relative;
    cursor: pointer;
    min-height: 48px;
}

.tree-item:hover {
    background-color: #f8fafc;
    transform: translateX(2px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.tree-toggle {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 50%;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
    flex-shrink: 0;
}

.tree-toggle:hover {
    background-color: #e2e8f0;
    transform: scale(1.1);
}

.tree-toggle i {
    font-size: 0.75rem;
    color: #64748b;
    transition: all 0.2s ease;
}

.tree-node.expanded > .tree-item .tree-toggle i {
    transform: rotate(90deg);
    color: #1e3a8a;
}

.tree-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    border-radius: 0.5rem;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.tree-icon i {
    font-size: 1.1rem;
}

.tree-label {
    flex: 1;
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    color: #374151;
    gap: 0.5rem;
    min-height: 24px;
}

.tree-label strong {
    font-weight: 600;
    color: #1f2937;
}

.tree-actions {
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s ease;
    margin-left: auto;
}

.tree-item:hover .tree-actions {
    opacity: 1;
}

.tree-actions .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border: none;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}

.tree-actions .btn:hover {
    transform: scale(1.05);
}

.tree-children {
    margin-left: 2rem;
    border-left: 2px solid #e2e8f0;
    padding-left: 1rem;
    position: relative;
    display: none;
    animation: slideDown 0.3s ease;
}

.tree-node.expanded > .tree-children {
    display: block;
}

/* Connection lines */
.tree-children::before {
    content: '';
    position: absolute;
    left: -2px;
    top: 0;
    width: 2px;
    height: 100%;
    background: linear-gradient(to bottom, #1e3a8a 0%, #e2e8f0 100%);
}

.tree-children .tree-item::before {
    content: '';
    position: absolute;
    left: -1rem;
    top: 50%;
    width: 0.75rem;
    height: 1px;
    background-color: #e2e8f0;
}

/* Level-specific styling */
.tree-node[data-level="0"] {
    margin-bottom: 0.5rem;
}

.tree-node[data-level="0"] > .tree-item {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    font-weight: 600;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tree-node[data-level="0"] > .tree-item:hover {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tree-node[data-level="1"] > .tree-item {
    background: rgba(59, 130, 246, 0.03);
    border-left: 3px solid #3b82f6;
    margin: 0.25rem 0;
}

.tree-node[data-level="1"] > .tree-item:hover {
    background: rgba(59, 130, 246, 0.08);
}

.tree-node[data-level="2"] > .tree-item {
    background: rgba(16, 185, 129, 0.03);
    border-left: 3px solid #10b981;
}

.tree-node[data-level="2"] > .tree-item:hover {
    background: rgba(16, 185, 129, 0.08);
}

.tree-node[data-level="3"] > .tree-item {
    background: rgba(245, 158, 11, 0.03);
    border-left: 3px solid #f59e0b;
}

.tree-node[data-level="4"] > .tree-item {
    background: rgba(239, 68, 68, 0.03);
    border-left: 3px solid #ef4444;
}

.tree-node[data-level="5"] > .tree-item {
    background: rgba(107, 114, 128, 0.03);
    border-left: 3px solid #6b7280;
}

/* Badges */
.badge {
    font-size: 0.65rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.badge-primary {
    background-color: #1e3a8a;
    color: white;
}

.badge-info {
    background-color: #0891b2;
    color: white;
}

.badge-success {
    background-color: #059669;
    color: white;
}

.badge-warning {
    background-color: #d97706;
    color: white;
}

.badge-danger {
    background-color: #dc2626;
    color: white;
}

.badge-secondary {
    background-color: #4b5563;
    color: white;
}

/* Empty states */
.tree-item em {
    color: #9ca3af;
    font-style: italic;
    font-size: 0.85rem;
}

/* Animations */
@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 1000px;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.tree-node {
    animation: fadeIn 0.3s ease;
}

/* Responsive design */
@media (max-width: 768px) {
    .structure-tree {
        padding: 0.5rem;
        max-height: 400px;
    }
    
    .tree-item {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
    
    .tree-children {
        margin-left: 1.5rem;
        padding-left: 0.75rem;
    }
    
    .tree-actions {
        opacity: 1; /* Always visible on mobile */
    }
    
    .tree-actions .btn {
        padding: 0.125rem 0.25rem;
        font-size: 0.625rem;
    }
    
    .tree-icon {
        width: 28px;
        height: 28px;
        margin-right: 0.5rem;
    }
    
    .tree-icon i {
        font-size: 1rem;
    }
    
    .badge {
        font-size: 0.55rem;
        padding: 0.125rem 0.375rem;
    }
}

@media (max-width: 576px) {
    .tree-label {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .tree-node[data-level="0"] > .tree-item {
        padding: 0.75rem;
    }
    
    .tree-children {
        margin-left: 1rem;
        padding-left: 0.5rem;
    }
}

/* Print styles */
@media print {
    .tree-actions,
    .btn {
        display: none !important;
    }
    
    .tree-node {
        break-inside: avoid;
        animation: none;
    }
    
    .tree-children {
        display: block !important;
    }
    
    .tree-toggle {
        display: none;
    }
    
    .tree-item {
        border: 1px solid #ccc !important;
        margin-bottom: 0.25rem;
        background: white !important;
    }
}

/* Focus and accessibility */
.tree-toggle:focus,
.tree-item:focus {
    outline: 2px solid #1e3a8a;
    outline-offset: 2px;
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .tree-item {
        border: 2px solid #000;
    }
    
    .tree-children {
        border-left-color: #000;
    }
    
    .badge {
        border: 1px solid #000;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .tree-toggle i,
    .tree-children,
    .tree-item,
    .tree-node {
        transition: none;
        animation: none;
    }
}
.badge {
    font-size: 0.625rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
}

.badge-primary {
    background-color: #3b82f6;
    color: white;
}

.badge-info {
    background-color: #06b6d4;
    color: white;
}

.badge-success {
    background-color: #10b981;
    color: white;
}

.badge-warning {
    background-color: #f59e0b;
    color: white;
}

.badge-danger {
    background-color: #ef4444;
    color: white;
}

.badge-secondary {
    background-color: #6b7280;
    color: white;
}

/* Form styles */
.form-floating > .form-control,
.form-floating > .form-select {
    height: calc(3.5rem + 2px);
    line-height: 1.25;
}

.form-floating > label {
    padding: 1rem 0.75rem;
}

.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label,
.form-floating > .form-select ~ label {
    opacity: 0.65;
    transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
}

/* Modal enhancements */
.modal-content {
    border: none;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
    border-bottom: 1px solid #e5e7eb;
    border-radius: 0.75rem 0.75rem 0 0;
    background-color: #f9fafb;
}

.modal-footer {
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 0.75rem 0.75rem;
    background-color: #f9fafb;
}

/* Table enhancements */
.table-hover tbody tr:hover {
    background-color: #f8fafc;
}

.table th {
    font-weight: 600;
    color: #374151;
    background-color: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
}

.table td {
    vertical-align: middle;
}

/* Action buttons */
.btn-action {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Search box */
.search-box {
    position: relative;
}

.search-box .form-control {
    padding-left: 2.5rem;
}

.search-box .search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    pointer-events: none;
}

/* Filter section */
.filter-section {
    background-color: #f8fafc;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.filter-section .form-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

/* Status indicators */
.status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-dot.active {
    background-color: #10b981;
}

.status-dot.inactive {
    background-color: #6b7280;
}

/* Loading states */
.loading {
    position: relative;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #e5e7eb;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive design */
@media (max-width: 768px) {
    .structure-tree {
        padding: 0.5rem;
        max-height: 400px;
    }
    
    .tree-item {
        padding: 0.375rem;
        font-size: 0.8125rem;
    }
    
    .tree-children {
        margin-left: 1rem;
        padding-left: 0.375rem;
    }
    
    .tree-actions {
        opacity: 1;
    }
    
    .tree-actions .btn {
        padding: 0.125rem 0.25rem;
        font-size: 0.625rem;
    }
    
    .badge {
        font-size: 0.5rem;
        padding: 0.125rem 0.25rem;
    }
}

@media (max-width: 576px) {
    .tree-label {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .tree-icon {
        margin-right: 0.5rem;
    }
    
    .tree-toggle {
        margin-right: 0.25rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .table-responsive {
        font-size: 0.75rem;
    }
}

/* Print styles */
@media print {
    .tree-actions,
    .btn,
    .dropdown {
        display: none !important;
    }
    
    .tree-node {
        break-inside: avoid;
    }
    
    .tree-children {
        display: block !important;
    }
    
    .tree-toggle {
        display: none;
    }
    
    .tree-item {
        border: 1px solid #ccc;
        margin-bottom: 0.25rem;
    }
    
    .badge {
        border: 1px solid #ccc;
        background-color: white !important;
        color: black !important;
    }
}

/* Animation for tree expand/collapse */
.tree-children {
    overflow: hidden;
    transition: all 0.3s ease;
    max-height: 0;
}

.tree-node.expanded > .tree-children {
    max-height: 2000px;
}

/* Drag and drop (for future use) */
.tree-item.dragging {
    opacity: 0.5;
}

.tree-item.drop-target {
    background-color: #dbeafe;
    border: 2px dashed #3b82f6;
}

/* Highlight search results */
.tree-item.search-match {
    background-color: #fef3c7;
    border: 1px solid #f59e0b;
}

.tree-item.search-match .tree-label {
    font-weight: 600;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h5 {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.empty-state p {
    margin-bottom: 1.5rem;
}

/* Accessibility improvements */
.tree-toggle:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

.btn:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

.form-control:focus,
.form-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .tree-item {
        border: 1px solid #000;
    }
    
    .tree-children {
        border-left-color: #000;
    }
    
    .badge {
        border: 1px solid #000;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .tree-toggle i,
    .tree-children,
    .btn,
    .tree-item {
        transition: none;
    }
    
    .loading::after {
        animation: none;
    }
}