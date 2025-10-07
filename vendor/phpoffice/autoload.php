<?php
/**
 * PhpSpreadsheet Autoloader với PSR và Composer
 */

// PSR SimpleCache Interface
if (!interface_exists('Psr\SimpleCache\CacheInterface')) {
    eval('
    namespace Psr\SimpleCache;
    interface CacheInterface {
        public function get($key, $default = null);
        public function set($key, $value, $ttl = null);
        public function delete($key);
        public function clear();
        public function getMultiple($keys, $default = null);
        public function setMultiple($values, $ttl = null);
        public function deleteMultiple($keys);
        public function has($key);
    }
    ');
}

// Composer Pcre Preg Class
if (!class_exists('Composer\Pcre\Preg')) {
    require_once __DIR__ . '/../composer/Pcre/Preg.php';
}

// Autoload PhpSpreadsheet
spl_autoload_register(function($class) {
    // PhpSpreadsheet classes
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet\\') === 0) {
        $classPath = str_replace('PhpOffice\\PhpSpreadsheet\\', '', $class);
        $classPath = str_replace('\\', '/', $classPath);
        $filePath = __DIR__ . '/PhpSpreadsheet/' . $classPath . '.php';
        
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }
    }
    
    // Composer classes
    if (strpos($class, 'Composer\\') === 0) {
        $classPath = str_replace('Composer\\', '', $class);
        $classPath = str_replace('\\', '/', $classPath);
        $filePath = __DIR__ . '/../composer/' . $classPath . '.php';
        
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }
    }
    
    return false;
});