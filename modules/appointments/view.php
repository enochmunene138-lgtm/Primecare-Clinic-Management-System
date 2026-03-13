<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Appointment Details';
$activePage = 'appointments';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("
    SELECT a.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.phone AS patient_phone,
           p.date_of_birth,
           p.gender AS patient_gender,
           p.patient_id,
           d.full_name AS doctor_name,
           d.specialization,
           d.consultation_fee,
           d.doctor_id,
           CONCAT(u.full_name) AS booked_by_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    LEFT JOIN users u ON a.booked_by = u.user_id
    WHERE a.appointment_id = ?
");
$stmt->execute([$id]);
$appt = $stmt->fetch();
if (!$appt) { header('Location: list.php'); exit; }

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Appointment Details</h1>
        <p>Appointment #<?= $id ?> —
            <?= fdate($appt['appointment_date']) ?></p>
    </div>
    <div class="page-actions">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <?php if (in_array($appt['status'],
            ['pending','confirmed'])): ?>
        <a href="update_status.php?id=<?= $id ?>&status=completed"
           class="btn btn-success"
           onclick="return confirm('Mark as completed?')">
            <i class="bi bi-check-circle"></i> Mark Complete
        </a>
        <a href="update_status.php?id=<?= $id ?>&status=cancelled"
           class="btn btn-danger"
           onclick="return confirm('Cancel this appointment?')">
            <i class="bi bi-x-circle"></i> Cancel
        </a>
        <?php endif; ?>
        <?php if ($appt['status'] === 'completed'): ?>
        <a href="../diagnosis/add.php?appointment_id=<?= $id ?>&patient_id=<?= $appt['patient_id'] ?>&doctor_id=<?= $appt['doctor_id'] ?>"
           class="btn btn-primary">
            <i class="bi bi-clipboard-plus"></i> Record Consultation
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">

    <!-- Appointment Info -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-calendar-event"></i>
                    Appointment Information
                </span>
                <span class="badge-status <?= $appt['status'] ?>">
                    <?= ucfirst($appt['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $info = [
                        'Date'       => fdate($appt['appointment_date']),
                        'Time'       => date('h:i A',
                            strtotime($appt['appointment_time'])),
                        'Status'     => ucfirst($appt['status']),
                        'Booked By'  => clean($appt['booked_by_name']
                            ?? '—'),
                        'Booked On'  => fdate($appt['created_at'],
                            'd M Y H:i'),
                    ];
                    foreach ($info as $label => $value): ?>
                    <div class="col-sm-6">
                        <div style="padding:12px;background:var(--bg);
                            border-radius:8px;">
                            <div style="font-size:11px;font-weight:700;
                                color:var(--text-muted);text-transform:
                                uppercase;letter-spacing:.6px;
                                margin-bottom:4px;">
                                <?= $label ?>
                            </div>
                            <div style="font-size:14px;font-weight:600;">
                                <?= $value ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($appt['reason']): ?>
                <div style="margin-top:16px;">
                    <div style="font-size:12px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;
                        letter-spacing:.6px;margin-bottom:6px;">
                        Reason for Visit
                    </div>
                    <p style="font-size:13.5px;line-height:1.6;">
                        <?= clean($appt['reason']) ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($appt['notes']): ?>
                <div style="margin-top:12px;">
                    <div style="font-size:12px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;
                        letter-spacing:.6px;margin-bottom:6px;">
                        Notes
                    </div>
                    <p style="font-size:13.5px;line-height:1.6;">
                        <?= clean($appt['notes']) ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Timeline -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-arrow-repeat"></i> Update Status
                </span>
            </div>
            <div class="card-body">
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php
                    $statuses = [
                        'pending'   => ['btn-warning', 'hourglass'],
                        'confirmed' => ['btn-primary', 'calendar-check'],
                        'completed' => ['btn-success', 'check-circle'],
                        'cancelled' => ['btn-danger',  'x-circle'],
                        'no-show'   => ['btn-outline',  'person-x'],
                    ];
                    foreach ($statuses as $s => [$cls, $icon]): ?>
                    <a href="update_status.php?id=<?= $id ?>&status=<?= $s ?>"
                       class="btn btn-sm <?= $cls ?>
                           <?= $appt['status']===$s?' disabled':'' ?>"
                       onclick="return confirm('Change status to
                           <?= ucfirst($s) ?>?')">
                        <i class="bi bi-<?= $icon ?>"></i>
                        <?= ucfirst($s) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient & Doctor -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-person"></i> Patient
                </span>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:center;
                    gap:12px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;
                        background:var(--accent-light);
                        color:var(--accent);border-radius:50%;
                        display:flex;align-items:center;
                        justify-content:center;font-size:20px;
                        font-weight:700;">
                        <?= strtoupper(substr(
                            $appt['patient_name'],0,1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:15px;">
                            <?= clean($appt['patient_name']) ?>
                        </div>
                        <div style="font-size:12px;
                            color:var(--text-muted);">
                            <?= clean($appt['patient_phone']) ?>
                        </div>
                    </div>
                </div>
                <div style="font-size:13px;color:var(--text-muted);
                    margin-bottom:12px;">
                    <?= calcAge($appt['date_of_birth']) ?> ·
                    <?= clean($appt['patient_gender']) ?>
                </div>
                <a href="../patients/view.php?id=<?= $appt['patient_id'] ?>"
                   class="btn btn-sm btn-outline" style="width:100%;">
                    <i class="bi bi-eye"></i> View Patient Profile
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-person-badge"></i> Doctor
                </span>
            </div>
            <div class="card-body">
                <div style="display:flex;align-items:center;
                    gap:12px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;
                        background:#ebf8ff;color:var(--info);
                        border-radius:50%;display:flex;
                        align-items:center;justify-content:center;
                        font-size:20px;font-weight:700;">
                        <?= strtoupper(substr(
                            $appt['doctor_name'],0,1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:15px;">
                            <?= clean($appt['doctor_name']) ?>
                        </div>
                        <div style="font-size:12px;
                            color:var(--text-muted);">
                            <?= clean($appt['specialization']) ?>
                        </div>
                    </div>
                </div>
                <div style="background:var(--bg);border-radius:8px;
                    padding:10px 14px;font-size:13px;">
                    <span style="color:var(--text-muted);">
                        Consultation Fee:
                    </span>
                    <strong style="color:var(--accent);">
                        <?= money($appt['consultation_fee']) ?>
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>