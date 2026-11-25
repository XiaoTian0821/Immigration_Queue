<?php
/**
 * Root entry: route logged-in users to their role dashboard,
 * everyone else to the login page.
 */
require_once __DIR__ . '/config/config.php';

$me = Auth::currentUser();
if ($me === null) {
    header('Location: ' . APP_URL . 'public/login.php');
    exit;
}
if ($me['role'] === 'admin') {
    header('Location: ' . APP_URL . 'admin/dashboard.php');
} else {
    header('Location: ' . APP_URL . 'officer/dashboard.php');
}
