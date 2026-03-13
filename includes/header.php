<?php
$flash = getFlash();
$user  = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($pageTitle ?? 'Dashboard') ?> — PrimeCare CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/main.css" rel="stylesheet">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-hospital"></i></div>
        <div class="brand-text">
            <span class="brand-name">PrimeCare</span>
            <span class="brand-sub">Clinic Management</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-label">Main</span>
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-item <?= ($activePage==='dashboard'?'active':'') ?>">
                <i class="bi bi-grid-1x2"></i><span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">Clinical</span>
            <a href="<?= BASE_URL ?>/modules/patients/list.php" class="nav-item <?= ($activePage==='patients'?'active':'') ?>">
                <i class="bi bi-people"></i><span>Patients</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/appointments/list.php" class="nav-item <?= ($activePage==='appointments'?'active':'') ?>">
                <i class="bi bi-calendar-check"></i><span>Appointments</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/diagnosis/list.php" class="nav-item <?= ($activePage==='diagnosis'?'active':'') ?>">
                <i class="bi bi-clipboard-pulse"></i><span>Consultations</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">Finance</span>
            <a href="<?= BASE_URL ?>/modules/billing/list.php" class="nav-item <?= ($activePage==='billing'?'active':'') ?>">
                <i class="bi bi-receipt"></i><span>Billing</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">Management</span>
            <a href="<?= BASE_URL ?>/modules/doctors/list.php" class="nav-item <?= ($activePage==='doctors'?'active':'') ?>">
                <i class="bi bi-person-badge"></i><span>Doctors</span>
            </a>
            <a href="<?= BASE_URL ?>/modules/reports/index.php" class="nav-item <?= ($activePage==='reports'?'active':'') ?>">
                <i class="bi bi-bar-chart-line"></i><span>Reports</span>
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= BASE_URL ?>/modules/admin/users.php" class="nav-item <?= ($activePage==='admin'?'active':'') ?>">
                <i class="bi bi-gear"></i><span>Admin</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= clean($user['name']) ?></span>
                <span class="user-role"><?= ucfirst($user['role']) ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content" id="mainContent">

    <!-- TOP BAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-breadcrumb">
                <span class="breadcrumb-clinic">PrimeCare</span>
                <i class="bi bi-chevron-right"></i>
                <span class="breadcrumb-page"><?= clean($pageTitle ?? 'Dashboard') ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-date">
                <i class="bi bi-calendar3"></i>
                <?= date('D, d M Y') ?>
            </div>
            <div class="topbar-time">
                <i class="bi bi-clock"></i>
                <span id="clockTime"><?= date('H:i') ?></span>
            </div>
        </div>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($flash): ?>
    <div class="alert-banner alert-<?= $flash['type'] ?>" id="flashAlert">
        <i class="bi bi-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?>"></i>
        <?= clean($flash['message']) ?>
        <button onclick="document.getElementById('flashAlert').remove()">
            <i class="bi bi-x"></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- PAGE CONTENT STARTS -->
     <div class="page-body">