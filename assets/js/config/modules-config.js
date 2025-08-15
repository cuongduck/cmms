/**
 * JavaScript Modules Configuration
 * File: assets/js/config/modules-config.js
 * Cấu hình và quản lý tất cả các module JavaScript trong hệ thống
 */

window.CMMSModules = {
    // Module mapping configuration
    config: {
        organization: {
            index: '/assets/js/modules/organization.js'
        },
        equipment: {
            index: '/assets/js/modules/equipment.js',
            add: '/assets/js/modules/equipment-add.js',
            edit: '/assets/js/modules/equipment-edit.js'
        },
        maintenance: {
            index: '/assets/js/modules/maintenance.js',
            add: '/assets/js/modules/maintenance-add.js',
            edit: '/assets/js/modules/maintenance-edit.js',
            history: '/assets/js/modules/maintenance-history.js'
        },
        bom: {
            index: '/assets/js/modules/boms.js',
            add: '/assets/js/modules/bom-add.js',
            edit: '/assets/js/modules/bom-edit.js'
        },
        tasks: {
            index: '/assets/js/modules/tasks.js',
            add: '/assets/js/modules/tasks-add.js',
            edit: '/assets/js/modules/tasks-edit.js',
            requests: '/assets/js/modules/tasks-requests.js'
        },
        inventory: {
            index: '/assets/js/modules/inventory.js',
            add: '/assets/js/modules/inventory-add.js',
            edit: '/assets/js/modules/inventory-edit.js'
        },
        calibration: {
            index: '/assets/js/modules/calibration.js',
            add: '/assets/js/modules/calibration-add.js',
            edit: '/assets/js/modules/calibration-edit.js'
        },
        users: {
            index: '/assets/js/modules/users.js',
            add: '/assets/js/modules/users-add.js',
            edit: '/assets/js/modules/users-edit.js'
        },
        qr_scanner: {
            index: '/assets/js/modules/qr-scanner.js'
        },
        reports: {
            index: '/assets/js/modules/reports.js'
        }
    },
    
    // Loaded modules cache
    loaded: new Set(),
    
    // Load script function
    loadScript: function(src) {
        return new Promise((resolve, reject) => {
            // Check if already loaded
            if (this.loaded.has(src)) {
                resolve();
                return;
            }
            
            // Check if script element already exists
            if (document.querySelector(`script[src="${src}"]`)) {
                this.loaded.add(src);
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = src;
            script.onload = () => {
                this.loaded.add(src);
                console.log(`Module loaded: ${src}`);
                resolve();
            };
            script.onerror = () => {
                console.warn(`Failed to load module: ${src}`);
                reject(new Error(`Failed to load script: ${src}`));
            };
            document.head.appendChild(script);
        });
    },
    
    // Auto-detect and load module based on current page
    autoLoad: function() {
        const currentPath = window.location.pathname;
        const pathParts = currentPath.split('/');
        
        // Extract module name and page type
        let moduleName = '';
        let pageType = 'index';
        
        // Find module name from path
        const moduleIndex = pathParts.findIndex(part => part === 'modules');
        if (moduleIndex !== -1 && pathParts[moduleIndex + 1]) {
            moduleName = pathParts[moduleIndex + 1].replace('-', '_');
            
            // Extract page type from filename
            const fileName = pathParts[pathParts.length - 1];
            if (fileName && fileName !== '') {
                pageType = fileName.split('.')[0];
                
                // Handle special cases
                if (pageType === 'index' || pageType === '') {
                    pageType = 'index';
                }
            }
        }
        
        // Load appropriate module
        if (moduleName && this.config[moduleName]) {
            const moduleConfig = this.config[moduleName];
            const scriptPath = moduleConfig[pageType] || moduleConfig.index;
            
            if (scriptPath) {
                console.log(`Auto-loading module: ${moduleName}/${pageType} -> ${scriptPath}`);
                this.loadScript(scriptPath).catch(error => {
                    console.error('Auto-load failed:', error);
                });
            } else {
                console.warn(`No script found for module: ${moduleName}/${pageType}`);
            }
        }
    },
    
    // Manually load a specific module
    load: function(moduleName, pageType = 'index') {
        if (this.config[moduleName] && this.config[moduleName][pageType]) {
            const scriptPath = this.config[moduleName][pageType];
            return this.loadScript(scriptPath);
        } else {
            console.warn(`Module not found: ${moduleName}/${pageType}`);
            return Promise.reject(new Error(`Module not found: ${moduleName}/${pageType}`));
        }
    },
    
    // Preload multiple modules
    preload: function(modules) {
        const promises = modules.map(({ module, page = 'index' }) => {
            return this.load(module, page);
        });
        
        return Promise.all(promises);
    },
    
    // Get loaded modules list
    getLoaded: function() {
        return Array.from(this.loaded);
    },
    
    // Check if module is loaded
    isLoaded: function(src) {
        return this.loaded.has(src);
    },
    
    // Reload a module (useful for development)
    reload: function(moduleName, pageType = 'index') {
        if (this.config[moduleName] && this.config[moduleName][pageType]) {
            const scriptPath = this.config[moduleName][pageType];
            
            // Remove from cache
            this.loaded.delete(scriptPath);
            
            // Remove existing script element
            const existingScript = document.querySelector(`script[src="${scriptPath}"]`);
            if (existingScript) {
                existingScript.remove();
            }
            
            // Reload
            return this.loadScript(scriptPath);
        } else {
            console.warn(`Module not found for reload: ${moduleName}/${pageType}`);
            return Promise.reject(new Error(`Module not found: ${moduleName}/${pageType}`));
        }
    }
};

// Auto-load when DOM is ready
$(document).ready(function() {
    // Auto-load modules based on current page
    CMMSModules.autoLoad();
});

// Make it globally accessible
window.loadScript = CMMSModules.loadScript.bind(CMMSModules);