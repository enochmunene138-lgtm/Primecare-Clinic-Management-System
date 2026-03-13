<?php
// ============================================================
// PrimeCare — Auth & Session Helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', '/primecare');
define('CLINIC_NAME', 'PrimeCare Clinic');

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php?msg=Please+login+to+continue');
        exit;
    }
}

function requireRole(...$roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . BASE_URL . '/dashboard.php?msg=Access+denied');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return [
        'id'       => $_SESSION['user_id']   ?? null,
        'name'     => $_SESSION['user_name'] ?? '',
        'role'     => $_SESSION['role']      ?? '',
        'username' => $_SESSION['username']  ?? '',
    ];
}

function logActivity($action, $module = '', $record_id = null, $details = '') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, module, record_id, details, ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action, $module, $record_id, $details,
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function clean($val) {
    return htmlspecialchars(trim($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($amount) {
    return 'KSh ' . number_format($amount, 2);
}

function fdate($date, $format = 'd M Y') {
    if (!$date) return '—';
    return date($format, strtotime($date));
}

function calcAge($dob) {
    if (!$dob) return '—';
    return (new DateTime($dob))->diff(new DateTime())->y . ' yrs';
}

function patientCode($id) {
    return 'PC-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

function billCode($id) {
    return 'BILL-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}