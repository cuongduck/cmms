<?php
/**
 * Updated header.php section for equipment view
 * Add this to your includes/header.php file
 */

// Include equipment helpers
require_once BASE_PATH . '/config/equipment_helpers.php';

// CSS includes - add to head section of header.php
if (isset($moduleCSS)) {
    if (is_array($moduleCSS)) {
        foreach ($moduleCSS as $css) {
            echo '<link rel="stylesheet" href="' . APP_URL . '/assets/css/' . $css . '.css?v=' . time() . '">' . PHP_EOL;
        }
    } else {
        echo '<link rel="stylesheet" href="' . APP_URL . '/assets/css/' . $moduleCSS . '.css?v=' . time() . '">' . PHP_EOL;
    }
}

// Equipment specific CSS
if ($currentModule === 'equipment') {
    echo '<link rel="stylesheet" href="' . APP_URL . '/assets/css/equipment.css?v=' . time() . '">' . PHP_EOL;
    
    // Add equipment view specific CSS if on view page
    if (basename($_SERVER['PHP_SELF']) === 'view.php') {
        echo '<link rel="stylesheet" href="' . APP_URL . '/assets/css/equipment-view.css?v=' . time() . '">' . PHP_EOL;
    }
}
?>

<!-- Add before closing </head> tag -->
<style>
/* Critical CSS for equipment module */
:root {
    --equipment-primary: #2563eb;
    --equipment-secondary: #64748b;
    --equipment-success: #10b981;
    --equipment-warning: #f59e0b;
    --equipment-danger: #ef4444;
    --equipment-info: #06b6d4;
    --equipment-light: #f8fafc;
    --equipment-dark: #1f2937;
}

/* Loading spinner for initial page load */
.page-loading {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.page-loading .spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--equipment-primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Hide content until loaded */
.equipment-view-container {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.equipment-view-container.loaded {
    opacity: 1;
}
</style>

<?php
// JavaScript includes - add before closing </body> tag in footer.php
$footerJS = '
<!-- Equipment Module Scripts -->
<script>
// Show loading spinner
document.addEventListener("DOMContentLoaded", function() {
    const loading = document.createElement("div");
    loading.className = "page-loading";
    loading.innerHTML = "<div class=\'spinner\'></div>";
    document.body.appendChild(loading);
    
    // Hide loading after page is ready
    window.addEventListener("load", function() {
        setTimeout(function() {
            loading.style.opacity = "0";
            setTimeout(function() {
                loading.remove();
                const container = document.querySelector(".equipment-view-container");
                if (container) {
                    container.classList.add("loaded");
                }
            }, 300);
        }, 500);
    });
});
</script>';

// Add core equipment JavaScript
if ($currentModule === 'equipment') {
    $footerJS .= '
<script src="' . APP_URL . '/assets/js/equipment-core.js?v=' . time() . '"></script>';
    
    // Add equipment view specific JavaScript if on view page
    if (basename($_SERVER['PHP_SELF']) === 'view.php') {
        $footerJS .= '
<script src="' . APP_URL . '/assets/js/equipment-view.js?v=' . time() . '"></script>';
    }
    
    // Add equipment add specific JavaScript if on add page
    if (basename($_SERVER['PHP_SELF']) === 'add.php') {
        $footerJS .= '
<script src="' . APP_URL . '/assets/js/equipment-add.js?v=' . time() . '"></script>';
    }
}

// Store footer JS for inclusion in footer.php
if (!isset($GLOBALS['footerJS'])) {
    $GLOBALS['footerJS'] = '';
}
$GLOBALS['footerJS'] .= $footerJS;
?>