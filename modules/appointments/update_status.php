<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();

$id     = (int)($_GET['id'] ?? 0);
$status = $_GET['status'] ?? '';

$allowed = ['pending','confirmed','completed','cancelled','no-show'];

if (!$id || !in_array($status, $allowed)) {
    header('Location: list.php');
    exit;
}

// Check appointment exists
$stmt = $db->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
$stmt->execute([$id]);
$appt = $stmt->fetch();

if (!$appt) {
    setFlash('error', 'Appointment not found.');
    header('Location: list.php');
    exit;
}

// Update status
$stmt = $db->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
$stmt->execute([$status, $id]);

logActivity('Updated appointment status', 'appointments', $id, "Status changed to: $status");
setFlash('success', 'Appointment status updated to ' . ucfirst($status) . '.');

// If completed redirect to record consultation
if ($status === 'completed') {
    header('Location: view.php?id=' . $id);
} else {
    header('Location: view.php?id=' . $id);
}
exit;