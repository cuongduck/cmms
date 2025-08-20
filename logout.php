<?php
session_start();

require_once 'config/config.php';
require_once 'config/auth.php';

// Logout user
$auth->logout();

// Redirect to login page
redirect('/login.php');
?>