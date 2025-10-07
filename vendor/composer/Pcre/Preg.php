<?php
namespace Composer\Pcre;

class Preg {
    public static function match($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
        return preg_match($pattern, $subject, $matches, $flags, $offset);
    }
    
    public static function matchAll($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
        return preg_match_all($pattern, $subject, $matches, $flags, $offset);
    }
    
    public static function replace($pattern, $replacement, $subject, $limit = -1, &$count = null) {
        return preg_replace($pattern, $replacement, $subject, $limit, $count);
    }
    
    public static function replaceCallback($pattern, $callback, $subject, $limit = -1, &$count = null, $flags = 0) {
        return preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);
    }
    
    public static function split($pattern, $subject, $limit = -1, $flags = 0) {
        return preg_split($pattern, $subject, $limit, $flags);
    }
    
    // THÊM METHOD NÀY
    public static function isMatch($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
        return (bool) preg_match($pattern, $subject, $matches, $flags, $offset);
    }
    
    // THÊM THÊM CÁC METHOD PHỤ TRỢ
    public static function grep($pattern, array $array, $flags = 0) {
        return preg_grep($pattern, $array, $flags);
    }
    
    public static function matchWithOffsets($pattern, $subject, &$matches = null, $flags = 0, $offset = 0) {
        return preg_match($pattern, $subject, $matches, $flags | PREG_OFFSET_CAPTURE, $offset);
    }
    
    public static function replaceCallbackArray($pattern, $subject, $limit = -1, &$count = null, $flags = 0) {
        return preg_replace_callback_array($pattern, $subject, $limit, $count, $flags);
    }
}