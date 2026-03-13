<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Book Appointment';
$activePage = 'appointments';
$errors = [];

// Pre-fill patient if coming from patient profile
$prePatient = (int)($_GET['patient_id'] ?? 0);

// Get all active patients
$patients = $db->query("
    SELECT patient_id, first_name, last_name, phone
    FROM patients WHERE status='active'
    ORDER BY first_name, last_name
")->fetchAll();

// Get all active doctors
$doctors = $db->query("
    SELECT doctor_id, full_name, specialization, consultation_fee
    FROM doctors WHERE status='active'
    ORDER BY full_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $doctor_id  = (int)($_POST['doctor_id'] ?? 0);
    $appt_date  = $_POST['appointment_date'] ?? '';
    $appt_time  = $_POST['appointment_time'] ?? '';
    $reason     = trim($_POST['reason'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if (!$patient_id)  $errors[] = 'Please select a patient.';
    if (!$doctor_id)   $errors[] = 'Please select a doctor.';
    if (!$appt_date)   $errors[] = 'Appointment date is required.';
    if (!$appt_time)   $errors[] = 'Appointment time is required.';
    if ($appt_date && $appt_date < date('Y-m-d'))
        $errors[] = 'Appointment date cannot be in the past.';

    // Conflict check
    if (!$errors) {
        $conflict = $db->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE doctor_id=? AND appointment_date=?
            AND appointment_time=?
            AND status NOT IN ('cancelled','no-show')
        ");
        $conflict->execute([$doctor_id, $appt_date, $appt_time]);
        if ($conflict->fetchColumn() > 0) {
            $errors[] = 'This doctor already has an appointment at
                         that time. Please choose a different time.';
        }
    }

    if (!$errors) {
        $stmt = $db->prepare("INSERT INTO appointments
            (patient_id, doctor_id, appointment_date, appointment_time,
             reason, notes, status, booked_by)
            VALUES (?,?,?,?,?,?,'confirmed',?)");
        $stmt->execute([
            $patient_id, $doctor_id, $appt_date, $appt_time,
            $reason, $notes, $_SESSION['user_id']
        ]);
        $newId = $db->lastInsertId();
        logActivity('Booked appointment', 'appointments', $newId,
            "Patient ID: $patient_id, Doctor ID: $doctor_id");
        setFlash('success', 'Appointment booked successfully.');
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Book Appointment</h1>
        <p>Schedule a new patient appointment</p>
    </div>
    <div class="page-actions">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Appointments
        </a>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert-banner alert-error">
    <i class="bi bi-exclamation-triangle"></i>
    <div><?php foreach ($errors as $e) echo clean($e) . ' '; ?></div>
    <button onclick="this.closest('.alert-banner').remove()">
        <i class="bi bi-x"></i>
    </button>
</div>
<?php endif; ?>

<div class="row g-4">
<div class="col-lg-8">
<form method="POST">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-calendar-plus"></i> Appointment Details
            </span>
        </div>
        <div class="card-body">

            <div class="form-group">
                <label class="form-label">
                    Patient <span class="req">*</span>
                </label>
                <select name="patient_id"
                        class="form-control form-select" required>
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $p): ?>
                    <option value="<?= $p['patient_id'] ?>"
                        <?= ($prePatient==$p['patient_id']||
                            ($_POST['patient_id']??0)==$p['patient_id'])
                            ?'selected':'' ?>>
                        <?= clean($p['first_name'].' '.$p['last_name']) ?>
                        — <?= clean($p['phone']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Doctor <span class="req">*</span>
                </label>
                <select name="doctor_id"
                        class="form-control form-select" required
                        onchange="showFee(this)">
                    <option value="">-- Select Doctor --</option>
                    <?php foreach ($doctors as $d): ?>
                    <option value="<?= $d['doctor_id'] ?>"
                        data-fee="<?= $d['consultation_fee'] ?>"
                        <?= (($_POST['doctor_id']??0)==$d['doctor_id'])
                            ?'selected':'' ?>>
                        <?= clean($d['full_name']) ?>
                        — <?= clean($d['specialization']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="feeDisplay" style="margin-top:6px;font-size:13px;
                    color:var(--accent);font-weight:600;display:none;">
                    <i class="bi bi-cash-coin"></i>
                    Consultation Fee: <span id="feeAmt"></span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        Date <span class="req">*</span>
                    </label>
                    <input type="date" name="appointment_date"
                        class="form-control"
                        min="<?= date('Y-m-d') ?>"
                        value="<?= clean($_POST['appointment_date']
                            ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        Time <span class="req">*</span>
                    </label>
                    <select name="appointment_time"
                            class="form-control form-select" required>
                        <option value="">-- Select Time --</option>
                        <?php
                        $times = [];
                        $start = strtotime('08:00');
                        $end   = strtotime('17:00');
                        for ($t = $start; $t <= $end; $t += 1800) {
                            $times[] = date('H:i', $t);
                        }
                        foreach ($times as $time):
                            $sel = (($_POST['appointment_time']
                                ?? '') === $time) ? 'selected' : '';
                        ?>
                        <option value="<?= $time ?>" <?= $sel ?>>
                            <?= date('h:i A', strtotime($time)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Reason for Visit</label>
                <textarea name="reason" class="form-control" rows="3"
                    placeholder="Describe the reason for this appointment..."
                    ><?= clean($_POST['reason'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="2"
                    placeholder="Any additional notes..."
                    ><?= clean($_POST['notes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-calendar-check"></i> Book Appointment
            </button>
            <a href="list.php" class="btn btn-outline ms-2">Cancel</a>
        </div>
    </div>
</form>
</div>

<div class="col-lg-4">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-info-circle"></i> Booking Guide
            </span>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php
                $tips = [
                    ['bi-clock','Working Hours',
                     '8:00 AM – 5:00 PM, Monday to Saturday'],
                    ['bi-exclamation-triangle','Conflict Check',
                     'System auto-checks for doctor schedule conflicts'],
                    ['bi-telephone','Patient Contact',
                     'Confirm appointment with patient via phone'],
                    ['bi-calendar-check','Status',
                     'New bookings are set to Confirmed automatically'],
                ];
                foreach ($tips as [$icon, $title, $desc]): ?>
                <div style="display:flex;gap:12px;align-items:flex-start;">
                    <i class="bi <?= $icon ?>"
                       style="color:var(--primary);font-size:18px;
                              flex-shrink:0;margin-top:2px;"></i>
                    <div>
                        <div style="font-weight:600;font-size:13px;">
                            <?= $title ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);
                                    margin-top:2px;">
                            <?= $desc ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function showFee(select) {
    const opt = select.options[select.selectedIndex];
    const fee = opt?.dataset?.fee;
    const display = document.getElementById('feeDisplay');
    const amt     = document.getElementById('feeAmt');
    if (fee && fee > 0) {
        amt.textContent = 'KSh ' + parseFloat(fee).toLocaleString();
        display.style.display = 'block';
    } else {
        display.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>