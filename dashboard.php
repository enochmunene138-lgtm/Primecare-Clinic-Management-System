<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// Stats
$stats = [];
$stats['total_patients']     = $db->query("SELECT COUNT(*) FROM patients WHERE status='active'")->fetchColumn();
$stats['today_appointments'] = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetchColumn();
$stats['pending_bills']      = $db->query("SELECT COUNT(*) FROM bills WHERE payment_status='pending'")->fetchColumn();
$stats['total_doctors']      = $db->query("SELECT COUNT(*) FROM doctors WHERE status='active'")->fetchColumn();
$stats['monthly_revenue']    = $db->query("SELECT COALESCE(SUM(amount_paid),0) FROM bills WHERE MONTH(bill_date)=MONTH(CURDATE()) AND YEAR(bill_date)=YEAR(CURDATE())")->fetchColumn();
$stats['completed_today']    = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='completed'")->fetchColumn();

// Today's appointments
$todayAppts = $db->query("
    SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           d.full_name AS doctor_name, d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id=p.patient_id
    JOIN doctors d ON a.doctor_id=d.doctor_id
    WHERE a.appointment_date=CURDATE()
    ORDER BY a.appointment_time ASC LIMIT 8
")->fetchAll();

// Recent patients
$recentPatients = $db->query("
    SELECT * FROM patients ORDER BY registration_date DESC LIMIT 6
")->fetchAll();

// Monthly appointments chart
$chartData = $db->query("
    SELECT DATE_FORMAT(appointment_date,'%b %Y') AS month, COUNT(*) AS cnt
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(appointment_date), MONTH(appointment_date)
    ORDER BY appointment_date
")->fetchAll();

// Revenue last 6 months
$revenueData = $db->query("
    SELECT DATE_FORMAT(bill_date,'%b %Y') AS month, COALESCE(SUM(amount_paid),0) AS revenue
    FROM bills
    WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(bill_date), MONTH(bill_date)
    ORDER BY bill_date
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= clean($user['name']) ?>. Here's what's happening today.</p>
    </div>
    <div class="page-actions">
        <a href="modules/patients/add.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> New Patient
        </a>
        <a href="modules/appointments/add.php" class="btn btn-accent">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stat-grid">
    <a href="modules/patients/list.php" class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['total_patients']) ?></div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-sub">Registered patients</div>
        </div>
    </a>
    <a href="modules/appointments/list.php" class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-calendar-check-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['today_appointments']) ?></div>
            <div class="stat-label">Today's Appointments</div>
            <div class="stat-sub"><?= $stats['completed_today'] ?> completed</div>
        </div>
    </a>
    <a href="modules/doctors/list.php" class="stat-card">
        <div class="stat-icon green"><i class="bi bi-person-badge-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['total_doctors']) ?></div>
            <div class="stat-label">Active Doctors</div>
            <div class="stat-sub">On the team</div>
        </div>
    </a>
    <a href="modules/billing/list.php" class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-receipt-cutoff"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($stats['pending_bills']) ?></div>
            <div class="stat-label">Pending Bills</div>
            <div class="stat-sub">Awaiting payment</div>
        </div>
    </a>
    <a href="modules/reports/index.php" class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-cash-stack"></i></div>
        <div class="stat-info">
            <div class="stat-value">KSh <?= number_format($stats['monthly_revenue']) ?></div>
            <div class="stat-label">Monthly Revenue</div>
            <div class="stat-sub"><?= date('F Y') ?></div>
        </div>
    </a>
</div>

<!-- Charts -->
<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-bar-chart-line"></i>
                    Appointments (Last 6 Months)
                </span>
            </div>
            <div class="card-body">
                <canvas id="appointmentsChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-cash-coin"></i>
                    Revenue (Last 6 Months)
                </span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Today's Appointments + Recent Patients -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-clock-history"></i> Today's Schedule
                </span>
                <a href="modules/appointments/list.php" class="btn btn-sm btn-outline">
                    View All
                </a>
            </div>
            <div class="table-responsive">
                <?php if ($todayAppts): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($todayAppts as $appt): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= date('H:i', strtotime($appt['appointment_time'])) ?>
                                </strong>
                            </td>
                            <td><?= clean($appt['patient_name']) ?></td>
                            <td>
                                <small class="text-muted">
                                    <?= clean($appt['doctor_name']) ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge-status <?= $appt['status'] ?>">
                                    <?= ucfirst($appt['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="modules/appointments/view.php?id=<?= $appt['appointment_id'] ?>"
                                   class="btn btn-sm btn-outline btn-icon">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <p>No appointments scheduled for today.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-person-lines-fill"></i> Recent Patients
                </span>
                <a href="modules/patients/list.php" class="btn btn-sm btn-outline">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                <?php foreach ($recentPatients as $p): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);">
                    <div style="width:38px;height:38px;background:var(--accent-light);color:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0;">
                        <?= strtoupper(substr($p['first_name'], 0, 1)) ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13.5px;">
                            <?= clean($p['first_name'] . ' ' . $p['last_name']) ?>
                        </div>
                        <div style="font-size:11.5px;color:var(--text-muted);">
                            <?= patientCode($p['patient_id']) ?> ·
                            <?= $p['gender'] ?> ·
                            <?= calcAge($p['date_of_birth']) ?>
                        </div>
                    </div>
                    <a href="modules/patients/view.php?id=<?= $p['patient_id'] ?>"
                       class="btn btn-sm btn-outline btn-icon">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Appointments Chart
new Chart(document.getElementById('appointmentsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chartData, 'month')) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column($chartData, 'cnt')) ?>,
            backgroundColor: 'rgba(26,92,138,.75)',
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($revenueData, 'month')) ?>,
        datasets: [{
            label: 'Revenue (KSh)',
            data: <?= json_encode(array_column($revenueData, 'revenue')) ?>,
            borderColor: '#00b4a6',
            backgroundColor: 'rgba(0,180,166,.1)',
            fill: true,
            tension: .4,
            pointBackgroundColor: '#00b4a6',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>