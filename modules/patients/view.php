<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Patient Profile';
$activePage = 'patients';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { header('Location: list.php'); exit; }

// Appointments
$appointments = $db->prepare("
    SELECT a.*, d.full_name AS doctor_name, d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointments->execute([$id]);
$appointments = $appointments->fetchAll();

// Visits
$visits = $db->prepare("
    SELECT v.*, d.full_name AS doctor_name
    FROM visits v
    JOIN doctors d ON v.doctor_id = d.doctor_id
    WHERE v.patient_id = ?
    ORDER BY v.visit_date DESC
");
$visits->execute([$id]);
$visits = $visits->fetchAll();

// Bills
$bills = $db->prepare("
    SELECT * FROM bills WHERE patient_id = ?
    ORDER BY bill_date DESC
");
$bills->execute([$id]);
$bills = $bills->fetchAll();

// Prescriptions
$prescriptions = $db->prepare("
    SELECT pr.*, d.full_name AS doctor_name
    FROM prescriptions pr
    JOIN doctors d ON pr.doctor_id = d.doctor_id
    WHERE pr.patient_id = ?
    ORDER BY pr.created_at DESC
");
$prescriptions->execute([$id]);
$prescriptions = $prescriptions->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar">
        <?= strtoupper(substr($patient['first_name'], 0, 1)) ?>
    </div>
    <div class="profile-info">
        <h2><?= clean($patient['first_name'] . ' ' . $patient['last_name']) ?></h2>
        <div class="profile-meta">
            <span><i class="bi bi-person-badge"></i>
                <?= patientCode($patient['patient_id']) ?>
            </span>
            <span><i class="bi bi-calendar3"></i>
                <?= calcAge($patient['date_of_birth']) ?> old
            </span>
            <span><i class="bi bi-gender-ambiguous"></i>
                <?= clean($patient['gender']) ?>
            </span>
            <span><i class="bi bi-telephone"></i>
                <?= clean($patient['phone']) ?>
            </span>
            <span><i class="bi bi-droplet-fill"></i>
                <?= clean($patient['blood_group']) ?>
            </span>
        </div>
    </div>
    <div class="page-actions ms-auto">
        <a href="edit.php?id=<?= $id ?>" class="btn btn-accent">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="../appointments/add.php?patient_id=<?= $id ?>"
           class="btn btn-primary">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </a>
        <a href="../diagnosis/add.php?patient_id=<?= $id ?>"
           class="btn btn-outline" style="border-color:rgba(255,255,255,.3);color:#fff;">
            <i class="bi bi-clipboard-plus"></i> New Consultation
        </a>
    </div>
</div>

<!-- Tabs -->
<div class="pill-tabs">
    <button class="pill-tab active" onclick="showTab('info', this)">
        <i class="bi bi-person"></i> Info
    </button>
    <button class="pill-tab" onclick="showTab('appointments', this)">
        <i class="bi bi-calendar-check"></i>
        Appointments (<?= count($appointments) ?>)
    </button>
    <button class="pill-tab" onclick="showTab('visits', this)">
        <i class="bi bi-clipboard-pulse"></i>
        Consultations (<?= count($visits) ?>)
    </button>
    <button class="pill-tab" onclick="showTab('prescriptions', this)">
        <i class="bi bi-capsule"></i>
        Prescriptions (<?= count($prescriptions) ?>)
    </button>
    <button class="pill-tab" onclick="showTab('bills', this)">
        <i class="bi bi-receipt"></i>
        Bills (<?= count($bills) ?>)
    </button>
</div>

<!-- INFO TAB -->
<div id="tab-info">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <i class="bi bi-person-lines-fill"></i> Personal Details
                    </span>
                </div>
                <div class="card-body">
                    <?php
                    $details = [
                        'Full Name'    => clean($patient['first_name'].' '.$patient['last_name']),
                        'Date of Birth'=> fdate($patient['date_of_birth']) . ' (' . calcAge($patient['date_of_birth']) . ')',
                        'Gender'       => clean($patient['gender']),
                        'Phone'        => clean($patient['phone']),
                        'Email'        => clean($patient['email'] ?: '—'),
                        'Address'      => clean($patient['address'] ?: '—'),
                        'National ID'  => clean($patient['national_id'] ?: '—'),
                        'Blood Group'  => clean($patient['blood_group']),
                        'Registered'   => fdate($patient['registration_date']),
                    ];
                    foreach ($details as $label => $value): ?>
                    <div style="display:flex;padding:8px 0;border-bottom:1px solid var(--border);">
                        <span style="width:140px;font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;flex-shrink:0;">
                            <?= $label ?>
                        </span>
                        <span style="font-size:13.5px;"><?= $value ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <span class="card-title">
                        <i class="bi bi-telephone-plus"></i> Emergency Contact
                    </span>
                </div>
                <div class="card-body">
                    <p style="font-weight:600;">
                        <?= clean($patient['emergency_contact'] ?: '—') ?>
                    </p>
                    <p style="color:var(--text-muted);font-size:13px;">
                        <?= clean($patient['emergency_phone'] ?: '') ?>
                    </p>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        <i class="bi bi-heart-pulse"></i> Medical Notes
                    </span>
                </div>
                <div class="card-body">
                    <p style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                        Allergies
                    </p>
                    <p style="margin-bottom:16px;">
                        <?= clean($patient['allergies'] ?: 'None recorded') ?>
                    </p>
                    <p style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">
                        Chronic Conditions
                    </p>
                    <p><?= clean($patient['chronic_conditions'] ?: 'None recorded') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- APPOINTMENTS TAB -->
<div id="tab-appointments" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-calendar-check"></i> Appointment History
            </span>
            <a href="../appointments/add.php?patient_id=<?= $id ?>"
               class="btn btn-sm btn-primary">
                <i class="bi bi-plus"></i> Book New
            </a>
        </div>
        <div class="table-responsive">
            <?php if ($appointments): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Doctor</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?= fdate($a['appointment_date']) ?></td>
                        <td><?= date('H:i', strtotime($a['appointment_time'])) ?></td>
                        <td><?= clean($a['doctor_name']) ?></td>
                        <td><?= clean(substr($a['reason'] ?? '—', 0, 40)) ?></td>
                        <td>
                            <span class="badge-status <?= $a['status'] ?>">
                                <?= ucfirst($a['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="../appointments/view.php?id=<?= $a['appointment_id'] ?>"
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
                <p>No appointments found for this patient.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- VISITS TAB -->
<div id="tab-visits" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-clipboard-pulse"></i> Consultation History
            </span>
            <a href="../diagnosis/add.php?patient_id=<?= $id ?>"
               class="btn btn-sm btn-primary">
                <i class="bi bi-plus"></i> New Consultation
            </a>
        </div>
        <div class="table-responsive">
            <?php if ($visits): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Complaint</th>
                        <th>Diagnosis</th>
                        <th>Follow Up</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($visits as $v): ?>
                    <tr>
                        <td><?= fdate($v['visit_date']) ?></td>
                        <td><?= clean($v['doctor_name']) ?></td>
                        <td><?= clean(substr($v['chief_complaint'] ?? '—', 0, 40)) ?></td>
                        <td><?= clean(substr($v['diagnosis'] ?? '—', 0, 40)) ?></td>
                        <td>
                            <?= $v['follow_up_date']
                                ? fdate($v['follow_up_date'])
                                : '<span style="color:var(--text-muted)">—</span>' ?>
                        </td>
                        <td>
                            <a href="../diagnosis/view.php?id=<?= $v['visit_id'] ?>"
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
                <i class="bi bi-clipboard-x"></i>
                <p>No consultations found for this patient.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- PRESCRIPTIONS TAB -->
<div id="tab-prescriptions" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-capsule"></i> Prescription History
            </span>
        </div>
        <div class="table-responsive">
            <?php if ($prescriptions): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Doctor</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($prescriptions as $rx): ?>
                    <tr>
                        <td><?= fdate($rx['created_at']) ?></td>
                        <td><strong><?= clean($rx['medication_name']) ?></strong></td>
                        <td><?= clean($rx['dosage'] ?? '—') ?></td>
                        <td><?= clean($rx['frequency'] ?? '—') ?></td>
                        <td><?= clean($rx['duration'] ?? '—') ?></td>
                        <td><?= clean($rx['doctor_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-capsule"></i>
                <p>No prescriptions found for this patient.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- BILLS TAB -->
<div id="tab-bills" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-receipt"></i> Billing History
            </span>
            <a href="../billing/add.php?patient_id=<?= $id ?>"
               class="btn btn-sm btn-primary">
                <i class="bi bi-plus"></i> Create Bill
            </a>
        </div>
        <div class="table-responsive">
            <?php if ($bills): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill ID</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bills as $b): ?>
                    <tr>
                        <td>
                            <span style="font-family:monospace;font-weight:700;color:var(--primary);">
                                <?= billCode($b['bill_id']) ?>
                            </span>
                        </td>
                        <td><?= fdate($b['bill_date']) ?></td>
                        <td><?= money($b['total_amount']) ?></td>
                        <td><?= money($b['amount_paid']) ?></td>
                        <td><?= money($b['balance']) ?></td>
                        <td>
                            <span class="badge-status <?= $b['payment_status'] ?>">
                                <?= ucfirst($b['payment_status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="../billing/view.php?id=<?= $b['bill_id'] ?>"
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
                <i class="bi bi-receipt"></i>
                <p>No bills found for this patient.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTab(name, btn) {
    document.querySelectorAll('[id^="tab-"]').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.pill-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).style.display = 'block';
    btn.classList.add('active');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>