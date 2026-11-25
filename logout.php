<?php
/**
 * Secure logout (any user).
 */
require_once __DIR__ . '/config/config.php';

Auth::logout();
header('Location: ' . APP_URL . 'public/login.php');
exit;
