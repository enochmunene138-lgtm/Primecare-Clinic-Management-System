<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'New Consultation';
$activePage = 'diagnosis';
$errors = [];

// Pre-fill from appointment or patient
$prePatient     = (int)($_GET['patient_id'] ?? 0);
$preDoctor      = (int)($_GET['doctor_id'] ?? 0);
$preAppointment = (int)($_GET['appointment_id'] ?? 0);

$patients = $db->query("
    SELECT patient_id, first_name, last_name, phone
    FROM patients WHERE status='active'
    ORDER BY first_name, last_name
")->fetchAll();

$doctors = $db->query("
    SELECT doctor_id, full_name, specialization
    FROM doctors WHERE status='active'
    ORDER BY full_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id     = (int)($_POST['patient_id'] ?? 0);
    $doctor_id      = (int)($_POST['doctor_id'] ?? 0);
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $visit_date     = $_POST['visit_date'] ?? date('Y-m-d');
    $visit_time     = $_POST['visit_time'] ?? date('H:i');
    $complaint      = trim($_POST['chief_complaint'] ?? '');
    $diagnosis      = trim($_POST['diagnosis'] ?? '');
    $treatment      = trim($_POST['treatment_notes'] ?? '');
    $bp             = trim($_POST['blood_pressure'] ?? '');
    $temp           = trim($_POST['temperature'] ?? '');
    $pulse          = trim($_POST['pulse'] ?? '');
    $weight         = trim($_POST['weight'] ?? '');
    $height         = trim($_POST['height'] ?? '');
    $follow_up      = $_POST['follow_up_date'] ?? '';

    if (!$patient_id) $errors[] = 'Please select a patient.';
    if (!$doctor_id)  $errors[] = 'Please select a doctor.';
    if (!$complaint)  $errors[] = 'Chief complaint is required.';

    if (!$errors) {
        $stmt = $db->prepare("INSERT INTO visits
            (appointment_id, patient_id, doctor_id, visit_date,
             visit_time, chief_complaint, diagnosis, treatment_notes,
             blood_pressure, temperature, pulse, weight, height,
             follow_up_date)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $appointment_id ?: null,
            $patient_id, $doctor_id, $visit_date, $visit_time,
            $complaint, $diagnosis, $treatment,
            $bp, $temp, $pulse, $weight, $height,
            $follow_up ?: null
        ]);
        $visitId = $db->lastInsertId();

        // Save prescriptions
        $meds    = $_POST['med_name']  ?? [];
        $doses   = $_POST['med_dose']  ?? [];
        $freqs   = $_POST['med_freq']  ?? [];
        $durs    = $_POST['med_dur']   ?? [];
        $routes  = $_POST['med_route'] ?? [];
        $instrs  = $_POST['med_instr'] ?? [];

        foreach ($meds as $i => $med) {
            $med = trim($med);
            if (!$med) continue;
            $rx = $db->prepare("INSERT INTO prescriptions
                (visit_id, patient_id, doctor_id, medication_name,
                 dosage, frequency, duration, route, instructions)
                VALUES (?,?,?,?,?,?,?,?,?)");
            $rx->execute([
                $visitId, $patient_id, $doctor_id,
                $med,
                trim($doses[$i]  ?? ''),
                trim($freqs[$i]  ?? ''),
                trim($durs[$i]   ?? ''),
                trim($routes[$i] ?? 'Oral'),
                trim($instrs[$i] ?? ''),
            ]);
        }

        // Mark appointment completed
        if ($appointment_id) {
            $db->prepare("UPDATE appointments SET status='completed'
                WHERE appointment_id=?")->execute([$appointment_id]);
        }

        logActivity('Recorded consultation', 'diagnosis',
            $visitId, "Patient ID: $patient_id");
        setFlash('success', 'Consultation recorded successfully.');
        header('Location: view.php?id=' . $visitId);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>New Consultation</h1>
        <p>Record patient visit, vitals, diagnosis and prescriptions</p>
    </div>
    <div class="page-actions">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back
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

<form method="POST">
<input type="hidden" name="appointment_id"
    value="<?= $preAppointment ?>">

<div class="row g-4">
<div class="col-lg-8">

    <!-- Patient & Doctor -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-people"></i> Patient & Doctor
            </span>
        </div>
        <div class="card-body">
            <div class="form-row">
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
                                ($_POST['patient_id']??0)==
                                $p['patient_id'])?'selected':'' ?>>
                            <?= clean($p['first_name'].' '.
                                $p['last_name']) ?>
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
                            class="form-control form-select" required>
                        <option value="">-- Select Doctor --</option>
                        <?php foreach ($doctors as $d): ?>
                        <option value="<?= $d['doctor_id'] ?>"
                            <?= ($preDoctor==$d['doctor_id']||
                                ($_POST['doctor_id']??0)==
                                $d['doctor_id'])?'selected':'' ?>>
                            <?= clean($d['full_name']) ?> —
                            <?= clean($d['specialization']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Visit Date</label>
                    <input type="date" name="visit_date"
                        class="form-control"
                        value="<?= clean($_POST['visit_date']
                            ?? date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Visit Time</label>
                    <input type="time" name="visit_time"
                        class="form-control"
                        value="<?= clean($_POST['visit_time']
                            ?? date('H:i')) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Vitals -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-heart-pulse"></i> Vital Signs
            </span>
        </div>
        <div class="card-body">
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">Blood Pressure</label>
                    <input type="text" name="blood_pressure"
                        class="form-control" placeholder="e.g. 120/80"
                        value="<?= clean($_POST['blood_pressure']
                            ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Temperature (°C)</label>
                    <input type="text" name="temperature"
                        class="form-control" placeholder="e.g. 36.5"
                        value="<?= clean($_POST['temperature']
                            ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Pulse (bpm)</label>
                    <input type="text" name="pulse"
                        class="form-control" placeholder="e.g. 72"
                        value="<?= clean($_POST['pulse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Weight (kg)</label>
                    <input type="text" name="weight"
                        class="form-control" placeholder="e.g. 70"
                        value="<?= clean($_POST['weight'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Height (cm)</label>
                    <input type="text" name="height"
                        class="form-control" placeholder="e.g. 170"
                        value="<?= clean($_POST['height'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Follow Up Date</label>
                    <input type="date" name="follow_up_date"
                        class="form-control"
                        value="<?= clean($_POST['follow_up_date']
                            ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Clinical Notes -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-clipboard-pulse"></i> Clinical Notes
            </span>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">
                    Chief Complaint <span class="req">*</span>
                </label>
                <textarea name="chief_complaint"
                    class="form-control" rows="2"
                    placeholder="Patient's main complaint..."
                    required><?= clean($_POST['chief_complaint']
                        ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Diagnosis</label>
                <textarea name="diagnosis"
                    class="form-control" rows="2"
                    placeholder="Doctor's diagnosis..."
                    ><?= clean($_POST['diagnosis'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Treatment Notes</label>
                <textarea name="treatment_notes"
                    class="form-control" rows="3"
                    placeholder="Treatment plan and notes..."
                    ><?= clean($_POST['treatment_notes']
                        ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Prescriptions -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-capsule"></i> Prescriptions
            </span>
            <button type="button" class="btn btn-sm btn-accent"
                    onclick="addRxRow()">
                <i class="bi bi-plus"></i> Add Medicine
            </button>
        </div>
        <div class="card-body">
            <div id="rxRows">
                <div style="display:grid;
                    grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;
                    gap:8px;margin-bottom:8px;">
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;">
                        Medicine
                    </div>
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;">
                        Dose
                    </div>
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;">
                        Frequency
                    </div>
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;">
                        Duration
                    </div>
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;">
                        Route
                    </div>
                    <div style="font-size:11px;font-weight:700;
                        color:var(--text-muted);text-transform:uppercase;">
                        Instructions
                    </div>
                    <div></div>
                </div>
            </div>
            <button type="button"
                    class="btn btn-sm btn-outline mt-2"
                    onclick="addRxRow()">
                <i class="bi bi-plus-circle"></i>
                Add Prescription Row
            </button>
        </div>
    </div>

</div>

<!-- Side Panel -->
<div class="col-lg-4">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-info-circle"></i> Quick Guide
            </span>
        </div>
        <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <?php
                $tips = [
                    ['bi-heart-pulse','Vitals First',
                     'Record vital signs before diagnosis'],
                    ['bi-capsule','Prescriptions',
                     'Click Add Medicine to add prescription rows'],
                    ['bi-calendar-event','Follow Up',
                     'Set follow-up date if patient needs to return'],
                    ['bi-receipt','Billing',
                     'After saving you can generate a bill from the consultation view'],
                ];
                foreach ($tips as [$icon,$title,$desc]): ?>
                <div style="display:flex;gap:12px;
                    align-items:flex-start;">
                    <i class="bi <?= $icon ?>"
                       style="color:var(--primary);font-size:18px;
                              flex-shrink:0;margin-top:2px;"></i>
                    <div>
                        <div style="font-weight:600;font-size:13px;">
                            <?= $title ?>
                        </div>
                        <div style="font-size:12px;
                            color:var(--text-muted);margin-top:2px;">
                            <?= $desc ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="col-12">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle"></i> Save Consultation
    </button>
    <a href="list.php" class="btn btn-outline ms-2">Cancel</a>
</div>
</div>
</form>

<script>
function addRxRow() {
    const container = document.getElementById('rxRows');
    const routes = ['Oral','Injection','Topical',
        'Inhalation','IV','IM','Other'];
    const row = document.createElement('div');
    row.style.cssText = `display:grid;
        grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;
        gap:8px;margin-bottom:8px;`;
    row.innerHTML = `
        <input type="text" name="med_name[]"
            class="form-control" placeholder="Medicine name" required>
        <input type="text" name="med_dose[]"
            class="form-control" placeholder="e.g. 500mg">
        <input type="text" name="med_freq[]"
            class="form-control" placeholder="e.g. 3x daily">
        <input type="text" name="med_dur[]"
            class="form-control" placeholder="e.g. 7 days">
        <select name="med_route[]" class="form-control form-select">
            ${routes.map(r =>
                `<option value="${r}">${r}</option>`).join('')}
        </select>
        <input type="text" name="med_instr[]"
            class="form-control" placeholder="Instructions">
        <button type="button"
            class="btn btn-sm btn-danger btn-icon"
            onclick="this.closest('div').remove()">
            <i class="bi bi-trash"></i>
        </button>
    `;
    container.appendChild(row);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>