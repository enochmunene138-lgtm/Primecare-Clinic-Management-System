<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    logActivity('Logout', 'auth', $_SESSION['user_id'], 'User logged out');
    session_destroy();
}
header('Location: ' . BASE_URL . '/index.php?msg=You+have+been+logged+out.');
exit;