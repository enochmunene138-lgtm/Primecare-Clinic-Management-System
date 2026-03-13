<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Reports & Analytics';
$activePage = 'reports';

// Date range filter
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Summary Stats
$newPatients   = $db->prepare("SELECT COUNT(*) FROM patients WHERE DATE(registration_date) BETWEEN ? AND ?");
$newPatients->execute([$from, $to]);
$newPatients = $newPatients->fetchColumn();

$totalAppts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ?");
$totalAppts->execute([$from, $to]);
$totalAppts = $totalAppts->fetchColumn();

$totalVisits = $db->prepare("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ?");
$totalVisits->execute([$from, $to]);
$totalVisits = $totalVisits->fetchColumn();

$totalRevenue = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM bills WHERE bill_date BETWEEN ? AND ?");
$totalRevenue->execute([$from, $to]);
$totalRevenue = $totalRevenue->fetchColumn();

$outstanding = $db->prepare("SELECT COALESCE(SUM(balance),0) FROM bills WHERE bill_date BETWEEN ? AND ? AND payment_status != 'paid'");
$outstanding->execute([$from, $to]);
$outstanding = $outstanding->fetchColumn();

// Daily appointments chart
$dailyAppts = $db->prepare("
    SELECT appointment_date AS day,
           COUNT(*) AS cnt
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY appointment_date
    ORDER BY appointment_date
");
$dailyAppts->execute([$from, $to]);
$dailyAppts = $dailyAppts->fetchAll();

// Appointment status breakdown
$statusBreak = $db->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM appointments
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY status
");
$statusBreak->execute([$from, $to]);
$statusBreak = $statusBreak->fetchAll();

// Daily revenue chart
$dailyRevenue = $db->prepare("
    SELECT bill_date AS day,
           COALESCE(SUM(amount_paid),0) AS revenue
    FROM bills
    WHERE bill_date BETWEEN ? AND ?
    GROUP BY bill_date
    ORDER BY bill_date
");
$dailyRevenue->execute([$from, $to]);
$dailyRevenue = $dailyRevenue->fetchAll();

// Top doctors by consultations
$topDoctors = $db->prepare("
    SELECT d.full_name, d.specialization,
           COUNT(v.visit_id) AS visits,
           COALESCE(SUM(b.amount_paid),0) AS revenue
    FROM doctors d
    LEFT JOIN visits v ON d.doctor_id = v.doctor_id
        AND v.visit_date BETWEEN ? AND ?
    LEFT JOIN bills b ON v.visit_id = b.visit_id
    GROUP BY d.doctor_id
    ORDER BY visits DESC
    LIMIT 5
");
$topDoctors->execute([$from, $to]);
$topDoctors = $topDoctors->fetchAll();

// Gender breakdown
$genderBreak = $db->query("
    SELECT gender, COUNT(*) AS cnt
    FROM patients
    WHERE status='active'
    GROUP BY gender
")->fetchAll();

// Blood group breakdown
$bloodGroups = $db->query("
    SELECT blood_group, COUNT(*) AS cnt
    FROM patients
    WHERE status='active'
    GROUP BY blood_group
    ORDER BY cnt DESC
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Reports & Analytics</h1>
        <p>Clinic performance overview and statistics</p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-outline">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET"
              style="display:flex;gap:12px;align-items:center;
                     flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;">
                <label style="font-size:13px;font-weight:600;
                    white-space:nowrap;">Date Range:</label>
                <input type="date" name="from"
                       class="form-control" style="width:160px;"
                       value="<?= clean($from) ?>">
                <span style="color:var(--text-muted);">to</span>
                <input type="date" name="to"
                       class="form-control" style="width:160px;"
                       value="<?= clean($to) ?>">
            </div>
            <!-- Quick ranges -->
            <?php
            $ranges = [
                'Today'      => [date('Y-m-d'), date('Y-m-d')],
                'This Week'  => [date('Y-m-d',strtotime('monday this week')), date('Y-m-d')],
                'This Month' => [date('Y-m-01'), date('Y-m-d')],
                'Last Month' => [date('Y-m-01',strtotime('first day of last month')), date('Y-m-t',strtotime('last day of last month'))],
                'This Year'  => [date('Y-01-01'), date('Y-m-d')],
            ];
            foreach ($ranges as $label => [$rf, $rt]): ?>
            <a href="?from=<?= $rf ?>&to=<?= $rt ?>"
               class="btn btn-sm <?= ($from===$rf&&$to===$rt)
                   ?'btn-primary':'btn-outline' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel"></i> Apply
            </button>
        </form>
    </div>
</div>

<!-- Summary Stats -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-person-plus"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                <?= number_format($newPatients) ?>
            </div>
            <div class="stat-label">New Patients</div>
            <div class="stat-sub">Registered in period</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">
            <i class="bi bi-calendar-check"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                <?= number_format($totalAppts) ?>
            </div>
            <div class="stat-label">Appointments</div>
            <div class="stat-sub">In selected period</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="bi bi-clipboard-pulse"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                <?= number_format($totalVisits) ?>
            </div>
            <div class="stat-label">Consultations</div>
            <div class="stat-sub">Completed visits</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-cash-stack"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                KSh <?= number_format($totalRevenue) ?>
            </div>
            <div class="stat-label">Revenue Collected</div>
            <div class="stat-sub">In selected period</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                KSh <?= number_format($outstanding) ?>
            </div>
            <div class="stat-label">Outstanding</div>
            <div class="stat-sub">Unpaid balances</div>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-bar-chart"></i>
                    Daily Appointments
                </span>
            </div>
            <div class="card-body">
                <canvas id="dailyApptsChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-pie-chart"></i>
                    Appointment Status
                </span>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-graph-up"></i>
                    Daily Revenue
                </span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-people"></i>
                    Patient Demographics
                </span>
            </div>
            <div class="card-body">
                <div style="display:flex;gap:16px;">
                    <div style="flex:1;">
                        <canvas id="genderChart" height="160"></canvas>
                    </div>
                    <div style="flex:1;">
                        <canvas id="bloodChart" height="160"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Top Doctors Table -->
<div class="card mb-4">
    <div class="card-header">
        <span class="card-title">
            <i class="bi bi-trophy"></i>
            Top Doctors by Consultations
        </span>
    </div>
    <div class="table-responsive">
        <?php if ($topDoctors): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Consultations</th>
                    <th>Revenue Generated</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $maxVisits = max(array_column($topDoctors,'visits')) ?: 1;
            foreach ($topDoctors as $i => $doc):
            ?>
                <tr>
                    <td>
                        <?php if ($i === 0): ?>
                        <span style="font-size:18px;">🥇</span>
                        <?php elseif ($i === 1): ?>
                        <span style="font-size:18px;">🥈</span>
                        <?php elseif ($i === 2): ?>
                        <span style="font-size:18px;">🥉</span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);
                            font-weight:700;"><?= $i+1 ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;
                            gap:10px;">
                            <div style="width:34px;height:34px;
                                background:linear-gradient(135deg,
                                    var(--primary),var(--accent));
                                border-radius:50%;display:flex;
                                align-items:center;
                                justify-content:center;
                                font-size:13px;font-weight:700;
                                color:#fff;">
                                <?= strtoupper(substr(
                                    $doc['full_name'],0,1)) ?>
                            </div>
                            <strong>
                                <?= clean($doc['full_name']) ?>
                            </strong>
                        </div>
                    </td>
                    <td style="color:var(--text-muted);">
                        <?= clean($doc['specialization']) ?>
                    </td>
                    <td>
                        <strong style="font-size:16px;">
                            <?= $doc['visits'] ?>
                        </strong>
                    </td>
                    <td style="color:var(--success);font-weight:600;">
                        <?= money($doc['revenue']) ?>
                    </td>
                    <td style="min-width:150px;">
                        <div style="background:var(--border);
                            border-radius:20px;height:8px;
                            overflow:hidden;">
                            <div style="height:100%;
                                background:linear-gradient(90deg,
                                    var(--primary),var(--accent));
                                border-radius:20px;
                                width:<?= $maxVisits > 0
                                    ? round($doc['visits']/$maxVisits*100)
                                    : 0 ?>%;">
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-bar-chart"></i>
            <p>No consultation data for the selected period.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Daily Appointments
new Chart(document.getElementById('dailyApptsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) =>
            date('d M', strtotime($r['day'])),
            $dailyAppts)) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column(
                $dailyAppts, 'cnt')) ?>,
            backgroundColor: 'rgba(26,92,138,.75)',
            borderRadius: 5,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true,
            ticks: { stepSize: 1 } } }
    }
});

// Status Doughnut
const statusLabels = <?= json_encode(array_map(fn($r) =>
    ucfirst($r['status']), $statusBreak)) ?>;
const statusData   = <?= json_encode(array_column(
    $statusBreak, 'cnt')) ?>;
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusData,
            backgroundColor: [
                '#d69e2e','#2b6cb0','#2f855a',
                '#e53e3e','#6b46c1'
            ],
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom',
                labels: { font: { size: 11 } } }
        }
    }
});

// Daily Revenue
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) =>
            date('d M', strtotime($r['day'])),
            $dailyRevenue)) ?>,
        datasets: [{
            label: 'Revenue (KSh)',
            data: <?= json_encode(array_column(
                $dailyRevenue, 'revenue')) ?>,
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

// Gender Chart
new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column(
            $genderBreak, 'gender')) ?>,
        datasets: [{
            data: <?= json_encode(array_column(
                $genderBreak, 'cnt')) ?>,
            backgroundColor: ['#2b6cb0','#b83280','#6b46c1'],
            borderWidth: 2, borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom',
                labels: { font: { size: 10 } } },
            title: { display: true, text: 'Gender',
                font: { size: 12 } }
        }
    }
});

// Blood Group Chart
new Chart(document.getElementById('bloodChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column(
            $bloodGroups, 'blood_group')) ?>,
        datasets: [{
            label: 'Patients',
            data: <?= json_encode(array_column(
                $bloodGroups, 'cnt')) ?>,
            backgroundColor: 'rgba(0,180,166,.7)',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            title: { display: true, text: 'Blood Groups',
                font: { size: 12 } }
        },
        scales: { y: { beginAtZero: true,
            ticks: { stepSize: 1 } } }
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>