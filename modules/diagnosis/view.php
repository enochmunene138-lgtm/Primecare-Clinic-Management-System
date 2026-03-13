<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Consultation Details';
$activePage = 'diagnosis';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("
    SELECT v.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.phone AS patient_phone,
           p.date_of_birth, p.gender AS patient_gender,
           p.blood_group, p.patient_id,
           d.full_name AS doctor_name,
           d.specialization, d.doctor_id
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    JOIN doctors d ON v.doctor_id = d.doctor_id
    WHERE v.visit_id = ?
");
$stmt->execute([$id]);
$visit = $stmt->fetch();
if (!$visit) { header('Location: list.php'); exit; }

// Prescriptions for this visit
$rxStmt = $db->prepare("
    SELECT * FROM prescriptions
    WHERE visit_id = ?
    ORDER BY prescription_id
");
$rxStmt->execute([$id]);
$prescriptions = $rxStmt->fetchAll();

// Check if bill exists
$billStmt = $db->prepare("
    SELECT bill_id FROM bills WHERE visit_id = ?
");
$billStmt->execute([$id]);
$existingBill = $billStmt->fetch();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Consultation Details</h1>
        <p><?= clean($visit['patient_name']) ?> —
            <?= fdate($visit['visit_date']) ?></p>
    </div>
    <div class="page-actions no-print">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <?php if ($existingBill): ?>
        <a href="../billing/view.php?id=<?= $existingBill['bill_id'] ?>"
           class="btn btn-accent">
            <i class="bi bi-receipt"></i> View Bill
        </a>
        <?php else: ?>
        <a href="../billing/add.php?visit_id=<?= $id ?>&patient_id=<?= $visit['patient_id'] ?>"
           class="btn btn-accent">
            <i class="bi bi-receipt"></i> Generate Bill
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print
        </button>
    </div>
</div>

<!-- Profile Header -->
<div class="profile-header" style="margin-bottom:24px;">
    <div class="profile-avatar">
        <?= strtoupper(substr($visit['patient_name'], 0, 1)) ?>
    </div>
    <div class="profile-info">
        <h2><?= clean($visit['patient_name']) ?></h2>
        <div class="profile-meta">
            <span><i class="bi bi-calendar3"></i>
                <?= calcAge($visit['date_of_birth']) ?> old
            </span>
            <span><i class="bi bi-gender-ambiguous"></i>
                <?= clean($visit['patient_gender']) ?>
            </span>
            <span><i class="bi bi-telephone"></i>
                <?= clean($visit['patient_phone']) ?>
            </span>
            <span><i class="bi bi-droplet-fill"></i>
                <?= clean($visit['blood_group']) ?>
            </span>
        </div>
    </div>
    <div style="text-align:right;margin-left:auto;">
        <div style="font-size:13px;opacity:.7;">Attending Doctor</div>
        <div style="font-size:16px;font-weight:700;">
            <?= clean($visit['doctor_name']) ?>
        </div>
        <div style="font-size:12px;opacity:.6;">
            <?= clean($visit['specialization']) ?>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Vitals -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-heart-pulse"></i> Vital Signs
                </span>
                <span style="font-size:12px;color:var(--text-muted);">
                    <?= fdate($visit['visit_date']) ?>
                    <?= date('H:i', strtotime($visit['visit_time'])) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="vitals-grid">
                    <?php
                    $vitals = [
                        ['Blood Pressure', $visit['blood_pressure'],
                         'bi-activity', 'mmHg'],
                        ['Temperature',    $visit['temperature'],
                         'bi-thermometer-half', '°C'],
                        ['Pulse',          $visit['pulse'],
                         'bi-heart-pulse', 'bpm'],
                        ['Weight',         $visit['weight'],
                         'bi-speedometer', 'kg'],
                        ['Height',         $visit['height'],
                         'bi-rulers', 'cm'],
                    ];
                    foreach ($vitals as [$label,$val,$icon,$unit]): ?>
                    <div class="vital-box">
                        <i class="bi <?= $icon ?>"
                           style="color:var(--primary);
                                  font-size:20px;"></i>
                        <div class="vital-val">
                            <?= $val
                                ? clean($val) . ' '
                                  . '<small style="font-size:12px;
                                    font-weight:400;">'
                                  . $unit . '</small>'
                                : '—' ?>
                        </div>
                        <div class="vital-label"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Clinical Notes -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-clipboard-pulse"></i>
                    Clinical Notes
                </span>
            </div>
            <div class="card-body">
                <?php
                $notes = [
                    ['Chief Complaint',  $visit['chief_complaint']],
                    ['Diagnosis',        $visit['diagnosis']],
                    ['Treatment Notes',  $visit['treatment_notes']],
                ];
                foreach ($notes as [$label, $val]): ?>
                <div style="margin-bottom:20px;">
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;
                        letter-spacing:.6px;margin-bottom:6px;">
                        <?= $label ?>
                    </div>
                    <p style="font-size:14px;line-height:1.7;
                        color:<?= $val?'var(--text)':'var(--text-muted)' ?>">
                        <?= $val ? clean($val) : '—' ?>
                    </p>
                </div>
                <?php endforeach; ?>

                <?php if ($visit['follow_up_date']): ?>
                <div style="background:#fffaf0;border:1px solid #fbd38d;
                    border-radius:8px;padding:12px 16px;
                    display:flex;align-items:center;gap:10px;">
                    <i class="bi bi-calendar-event"
                       style="color:var(--warning);font-size:18px;">
                    </i>
                    <div>
                        <div style="font-weight:700;font-size:13px;
                            color:var(--warning);">Follow-up Required</div>
                        <div style="font-size:13px;">
                            <?= fdate($visit['follow_up_date']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Prescriptions -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-capsule"></i>
                    Prescriptions (<?= count($prescriptions) ?>)
                </span>
            </div>
            <?php if ($prescriptions): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Route</th>
                            <th>Instructions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($prescriptions as $i => $rx): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <strong>
                                    <?= clean($rx['medication_name']) ?>
                                </strong>
                            </td>
                            <td><?= clean($rx['dosage'] ?? '—') ?></td>
                            <td><?= clean($rx['frequency'] ?? '—') ?></td>
                            <td><?= clean($rx['duration'] ?? '—') ?></td>
                            <td>
                                <span style="background:var(--accent-light);
                                    color:var(--accent);padding:2px 8px;
                                    border-radius:20px;font-size:11.5px;
                                    font-weight:600;">
                                    <?= clean($rx['route'] ?? 'Oral') ?>
                                </span>
                            </td>
                            <td style="font-size:12.5px;
                                color:var(--text-muted);">
                                <?= clean($rx['instructions'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:32px;">
                <i class="bi bi-capsule"></i>
                <p>No prescriptions recorded for this visit.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-info-circle"></i> Visit Summary
                </span>
            </div>
            <div class="card-body">
                <?php
                $summary = [
                    'Visit Date'  => fdate($visit['visit_date']),
                    'Visit Time'  => date('h:i A',
                        strtotime($visit['visit_time'])),
                    'Doctor'      => clean($visit['doctor_name']),
                    'Specialization' => clean($visit['specialization']),
                    'Prescriptions'  => count($prescriptions)
                        . ' medication(s)',
                    'Follow Up'   => $visit['follow_up_date']
                        ? fdate($visit['follow_up_date'])
                        : 'Not required',
                ];
                foreach ($summary as $label => $val): ?>
                <div style="display:flex;justify-content:space-between;
                    padding:8px 0;border-bottom:1px solid var(--border);
                    font-size:13.5px;">
                    <span style="color:var(--text-muted);
                        font-weight:600;font-size:12px;">
                        <?= $label ?>
                    </span>
                    <span style="font-weight:500;"><?= $val ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-lightning"></i> Quick Actions
                </span>
            </div>
            <div class="card-body"
                 style="display:flex;flex-direction:column;gap:10px;">
                <a href="../patients/view.php?id=<?= $visit['patient_id'] ?>"
                   class="btn btn-outline" style="width:100%;">
                    <i class="bi bi-person"></i> View Patient Profile
                </a>
                <?php if ($existingBill): ?>
                <a href="../billing/view.php?id=<?= $existingBill['bill_id'] ?>"
                   class="btn btn-accent" style="width:100%;">
                    <i class="bi bi-receipt"></i> View Invoice
                </a>
                <?php else: ?>
                <a href="../billing/add.php?visit_id=<?= $id ?>&patient_id=<?= $visit['patient_id'] ?>"
                   class="btn btn-accent" style="width:100%;">
                    <i class="bi bi-receipt"></i> Generate Bill
                </a>
                <?php endif; ?>
                <button onclick="window.print()"
                    class="btn btn-primary no-print"
                    style="width:100%;">
                    <i class="bi bi-printer"></i> Print Consultation
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>