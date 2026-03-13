<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Register Patient';
$activePage = 'patients';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $dob         = $_POST['date_of_birth'] ?? '';
    $gender      = $_POST['gender'] ?? '';
    $phone       = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $blood_group = $_POST['blood_group'] ?? 'Unknown';
    $national_id = trim($_POST['national_id'] ?? '');
    $emerg_name  = trim($_POST['emergency_contact'] ?? '');
    $emerg_phone = trim($_POST['emergency_phone'] ?? '');
    $allergies   = trim($_POST['allergies'] ?? '');
    $chronic     = trim($_POST['chronic_conditions'] ?? '');

    if (!$first_name) $errors[] = 'First name is required.';
    if (!$last_name)  $errors[] = 'Last name is required.';
    if (!$gender)     $errors[] = 'Gender is required.';
    if (!$phone)      $errors[] = 'Phone number is required.';

    if (!$errors) {
        $stmt = $db->prepare("INSERT INTO patients
            (first_name, last_name, date_of_birth, gender, phone, email,
             address, blood_group, national_id, emergency_contact,
             emergency_phone, allergies, chronic_conditions, registered_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $first_name, $last_name, $dob ?: null, $gender,
            $phone, $email, $address, $blood_group, $national_id,
            $emerg_name, $emerg_phone, $allergies, $chronic,
            $_SESSION['user_id']
        ]);
        $newId = $db->lastInsertId();
        logActivity('Registered patient', 'patients', $newId, "$first_name $last_name");
        setFlash('success', "Patient {$first_name} {$last_name} registered successfully. ID: " . patientCode($newId));
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Register New Patient</h1>
        <p>Fill in the patient details below</p>
    </div>
    <div class="page-actions">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Patients
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
<div class="row g-4">

    <!-- Personal Info -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-person-vcard"></i> Personal Information
                </span>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            First Name <span class="req">*</span>
                        </label>
                        <input type="text" name="first_name" class="form-control"
                            value="<?= clean($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Last Name <span class="req">*</span>
                        </label>
                        <input type="text" name="last_name" class="form-control"
                            value="<?= clean($_POST['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control"
                            value="<?= clean($_POST['date_of_birth'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Gender <span class="req">*</span>
                        </label>
                        <select name="gender" class="form-control form-select" required>
                            <option value="">-- Select --</option>
                            <option value="Male" <?= ($_POST['gender']??'')==='Male'?'selected':'' ?>>Male</option>
                            <option value="Female" <?= ($_POST['gender']??'')==='Female'?'selected':'' ?>>Female</option>
                            <option value="Other" <?= ($_POST['gender']??'')==='Other'?'selected':'' ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Phone Number <span class="req">*</span>
                        </label>
                        <input type="tel" name="phone" class="form-control"
                            value="<?= clean($_POST['phone'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= clean($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">National ID / Passport</label>
                        <input type="text" name="national_id" class="form-control"
                            value="<?= clean($_POST['national_id'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Blood Group</label>
                        <select name="blood_group" class="form-control form-select">
                            <?php foreach (['Unknown','A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                            <option value="<?= $bg ?>"
                                <?= ($_POST['blood_group']??'Unknown')===$bg?'selected':'' ?>>
                                <?= $bg ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Physical Address</label>
                    <textarea name="address" class="form-control" rows="2"><?= clean($_POST['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Info -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-telephone-plus"></i> Emergency Contact
                </span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Contact Name</label>
                    <input type="text" name="emergency_contact" class="form-control"
                        value="<?= clean($_POST['emergency_contact'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Contact Phone</label>
                    <input type="tel" name="emergency_phone" class="form-control"
                        value="<?= clean($_POST['emergency_phone'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-heart-pulse"></i> Medical Notes
                </span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Known Allergies</label>
                    <textarea name="allergies" class="form-control" rows="2"
                        placeholder="e.g. Penicillin, Aspirin"><?= clean($_POST['allergies'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Chronic Conditions</label>
                    <textarea name="chronic_conditions" class="form-control" rows="2"
                        placeholder="e.g. Hypertension, Diabetes"><?= clean($_POST['chronic_conditions'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Register Patient
        </button>
        <a href="list.php" class="btn btn-outline ms-2">Cancel</a>
    </div>
</div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>