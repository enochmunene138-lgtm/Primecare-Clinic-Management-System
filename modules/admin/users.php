<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireRole('admin');

$db = getDB();
$pageTitle  = 'Admin — Users & Services';
$activePage = 'admin';
$errors = [];

// Handle user status toggle
if (isset($_GET['toggle'])) {
    $uid  = (int)$_GET['toggle'];
    $stmt = $db->prepare("UPDATE users SET status =
        IF(status='active','inactive','active')
        WHERE user_id = ?");
    $stmt->execute([$uid]);
    setFlash('success', 'User status updated.');
    header('Location: users.php');
    exit;
}

// Handle service delete
if (isset($_GET['del_service'])) {
    $sid = (int)$_GET['del_service'];
    $db->prepare("UPDATE services SET status='inactive'
        WHERE service_id=?")->execute([$sid]);
    setFlash('success', 'Service deactivated.');
    header('Location: users.php#services');
    exit;
}

// Add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $role      = $_POST['role']           ?? '';
    $phone     = trim($_POST['phone']     ?? '');
    $gender    = $_POST['gender']         ?? '';

    if (!$full_name) $errors[] = 'Full name is required.';
    if (!$username)  $errors[] = 'Username is required.';
    if (!$email)     $errors[] = 'Email is required.';
    if (!$password)  $errors[] = 'Password is required.';
    if (!$role)      $errors[] = 'Role is required.';

    // Check duplicate username/email
    if (!$errors) {
        $dup = $db->prepare("SELECT COUNT(*) FROM users
            WHERE username=? OR email=?");
        $dup->execute([$username, $email]);
        if ($dup->fetchColumn() > 0) {
            $errors[] = 'Username or email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users
            (full_name, username, email, password,
             role, phone, gender)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $full_name, $username, $email,
            $hash, $role, $phone, $gender
        ]);
        $newUserId = $db->lastInsertId();

        // If doctor, add to doctors table
        if ($role === 'doctor') {
            $spec = trim($_POST['specialization'] ?? '');
            $qual = trim($_POST['qualification']  ?? '');
            $fee  = (float)($_POST['fee']         ?? 500);
            $dStmt = $db->prepare("INSERT INTO doctors
                (user_id, full_name, specialization,
                 qualification, phone, email,
                 gender, consultation_fee)
                VALUES (?,?,?,?,?,?,?,?)");
            $dStmt->execute([
                $newUserId, $full_name, $spec,
                $qual, $phone, $email, $gender, $fee
            ]);
        }

        logActivity('Created user', 'admin',
            $newUserId, "Username: $username, Role: $role");
        setFlash('success',
            "User $full_name created successfully.");
        header('Location: users.php');
        exit;
    }
}

// Add new service
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_service'])) {
    $sname = trim($_POST['service_name'] ?? '');
    $scat  = trim($_POST['category']     ?? '');
    $sprice = (float)($_POST['price']    ?? 0);

    if ($sname && $sprice > 0) {
        $db->prepare("INSERT INTO services
            (service_name, category, price)
            VALUES (?,?,?)")
            ->execute([$sname, $scat, $sprice]);
        setFlash('success', "Service '$sname' added.");
        header('Location: users.php#services');
        exit;
    } else {
        $errors[] = 'Service name and price are required.';
    }
}

// Load data
$users = $db->query("
    SELECT u.*,
           d.specialization, d.consultation_fee
    FROM users u
    LEFT JOIN doctors d ON u.user_id = d.user_id
    ORDER BY u.created_at DESC
")->fetchAll();

$services = $db->query("
    SELECT * FROM services
    WHERE status='active'
    ORDER BY category, service_name
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Admin Panel</h1>
        <p>Manage system users and clinic services</p>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert-banner alert-error">
    <i class="bi bi-exclamation-triangle"></i>
    <div>
        <?php foreach ($errors as $e) echo clean($e) . ' '; ?>
    </div>
    <button onclick="this.closest('.alert-banner').remove()">
        <i class="bi bi-x"></i>
    </button>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="pill-tabs">
    <button class="pill-tab active"
            onclick="showAdminTab('users', this)">
        <i class="bi bi-people"></i> Users
        (<?= count($users) ?>)
    </button>
    <button class="pill-tab"
            onclick="showAdminTab('adduser', this)">
        <i class="bi bi-person-plus"></i> Add User
    </button>
    <button class="pill-tab" id="tab-btn-services"
            onclick="showAdminTab('services', this)">
        <i class="bi bi-grid"></i> Services
        (<?= count($services) ?>)
    </button>
    <button class="pill-tab"
            onclick="showAdminTab('addservice', this)">
        <i class="bi bi-plus-circle"></i> Add Service
    </button>
</div>

<!-- USERS LIST -->
<div id="admin-users">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-people"></i> System Users
            </span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Specialization</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display:flex;
                                align-items:center;gap:10px;">
                                <div style="width:34px;height:34px;
                                    background:var(--accent-light);
                                    color:var(--accent);
                                    border-radius:50%;display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    font-weight:700;font-size:14px;">
                                    <?= strtoupper(substr(
                                        $u['full_name'],0,1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;">
                                        <?= clean($u['full_name']) ?>
                                    </div>
                                    <div style="font-size:11.5px;
                                        color:var(--text-muted);">
                                        <?= clean($u['email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code style="background:var(--bg);
                                padding:2px 8px;border-radius:4px;
                                font-size:12px;">
                                <?= clean($u['username']) ?>
                            </code>
                        </td>
                        <td>
                            <?php
                            $roleColors = [
                                'admin'        => '#6b46c1',
                                'doctor'       => '#2b6cb0',
                                'receptionist' => '#2f855a',
                                'nurse'        => '#b83280',
                            ];
                            $rc = $roleColors[$u['role']] ?? '#718096';
                            ?>
                            <span style="background:<?= $rc ?>22;
                                color:<?= $rc ?>;padding:3px 10px;
                                border-radius:20px;font-size:12px;
                                font-weight:700;
                                text-transform:capitalize;">
                                <?= clean($u['role']) ?>
                            </span>
                        </td>
                        <td style="font-size:13px;
                            color:var(--text-muted);">
                            <?= clean($u['specialization'] ?? '—') ?>
                        </td>
                        <td><?= clean($u['phone'] ?? '—') ?></td>
                        <td>
                            <span class="badge-status
                                <?= $u['status'] ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td style="font-size:12.5px;
                            color:var(--text-muted);">
                            <?= fdate($u['created_at']) ?>
                        </td>
                        <td>
                            <?php if ($u['user_id']
                                != $_SESSION['user_id']): ?>
                            <a href="users.php?toggle=<?= $u['user_id'] ?>"
                               class="btn btn-sm
                                   <?= $u['status']==='active'
                                       ?'btn-danger':'btn-success' ?>"
                               onclick="return confirm(
                                   'Toggle user status?')">
                                <i class="bi bi-<?= $u['status']
                                    ==='active'?'pause':'play' ?>
                                    -circle"></i>
                                <?= $u['status']==='active'
                                    ?'Deactivate':'Activate' ?>
                            </a>
                            <?php else: ?>
                            <span style="font-size:12px;
                                color:var(--text-muted);">
                                (You)
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD USER -->
<div id="admin-adduser" style="display:none;">
    <div class="card" style="max-width:700px;">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-person-plus"></i> Add New User
            </span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="add_user" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Full Name <span class="req">*</span>
                        </label>
                        <input type="text" name="full_name"
                            class="form-control"
                            value="<?= clean(
                                $_POST['full_name'] ?? '') ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Username <span class="req">*</span>
                        </label>
                        <input type="text" name="username"
                            class="form-control"
                            value="<?= clean(
                                $_POST['username'] ?? '') ?>"
                            required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Email <span class="req">*</span>
                        </label>
                        <input type="email" name="email"
                            class="form-control"
                            value="<?= clean(
                                $_POST['email'] ?? '') ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Password <span class="req">*</span>
                        </label>
                        <input type="password" name="password"
                            class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Role <span class="req">*</span>
                        </label>
                        <select name="role"
                                class="form-control form-select"
                                onchange="toggleDoctorFields(this)"
                                required>
                            <option value="">-- Select Role --</option>
                            <?php foreach (['admin','doctor',
                                'receptionist','nurse'] as $r): ?>
                            <option value="<?= $r ?>"
                                <?= (($_POST['role']??'')===$r)
                                    ?'selected':'' ?>>
                                <?= ucfirst($r) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender"
                                class="form-control form-select">
                            <option value="">-- Select --</option>
                            <?php foreach (['Male','Female','Other']
                                as $g): ?>
                            <option value="<?= $g ?>"
                                <?= (($_POST['gender']??'')===$g)
                                    ?'selected':'' ?>>
                                <?= $g ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone"
                        class="form-control"
                        value="<?= clean($_POST['phone'] ?? '') ?>">
                </div>

                <!-- Doctor Fields -->
                <div id="doctorFields" style="display:none;">
                    <hr style="margin:16px 0;
                        border-color:var(--border);">
                    <div style="font-size:13px;font-weight:700;
                        color:var(--primary);margin-bottom:12px;">
                        <i class="bi bi-person-badge"></i>
                        Doctor Information
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Specialization
                            </label>
                            <input type="text" name="specialization"
                                class="form-control"
                                placeholder="e.g. General Medicine"
                                value="<?= clean(
                                    $_POST['specialization'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Consultation Fee (KSh)
                            </label>
                            <input type="number" name="fee"
                                class="form-control"
                                value="<?= clean(
                                    $_POST['fee'] ?? '500') ?>"
                                min="0" step="50">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification"
                            class="form-control"
                            placeholder="e.g. MBChB, MMed"
                            value="<?= clean(
                                $_POST['qualification'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i> Create User
                </button>
            </form>
        </div>
    </div>
</div>

<!-- SERVICES LIST -->
<div id="admin-services" style="display:none;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-grid"></i>
                Active Services (<?= count($services) ?>)
            </span>
            <button class="btn btn-sm btn-accent"
                onclick="showAdminTab('addservice',
                    document.querySelector(
                        '.pill-tab:last-child'))">
                <i class="bi bi-plus"></i> Add Service
            </button>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($services as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong>
                                <?= clean($s['service_name']) ?>
                            </strong>
                        </td>
                        <td>
                            <span style="background:var(--bg);
                                padding:3px 10px;border-radius:20px;
                                font-size:12px;font-weight:600;
                                color:var(--text-muted);">
                                <?= clean($s['category'] ?? '—') ?>
                            </span>
                        </td>
                        <td style="font-weight:700;
                            color:var(--primary);">
                            <?= money($s['price']) ?>
                        </td>
                        <td>
                            <a href="users.php?del_service=<?= $s['service_id'] ?>"
                               class="btn btn-sm btn-danger"
                               onclick="return confirm(
                                   'Deactivate this service?')">
                                <i class="bi bi-trash"></i>
                                Remove
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD SERVICE -->
<div id="admin-addservice" style="display:none;">
    <div class="card" style="max-width:500px;">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-plus-circle"></i> Add New Service
            </span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="add_service" value="1">
                <div class="form-group">
                    <label class="form-label">
                        Service Name <span class="req">*</span>
                    </label>
                    <input type="text" name="service_name"
                        class="form-control"
                        placeholder="e.g. Blood Test CBC"
                        required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category"
                            class="form-control form-select">
                        <?php foreach (['Consultation',
                            'Laboratory','Radiology','Procedure',
                            'Maternity','Preventive','Other']
                            as $cat): ?>
                        <option value="<?= $cat ?>">
                            <?= $cat ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        Price (KSh) <span class="req">*</span>
                    </label>
                    <input type="number" name="price"
                        class="form-control"
                        placeholder="e.g. 500"
                        min="0" step="50" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i>
                    Add Service
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function showAdminTab(name, btn) {
    ['users','adduser','services','addservice'].forEach(t => {
        document.getElementById('admin-' + t).style.display = 'none';
    });
    document.querySelectorAll('.pill-tab').forEach(b =>
        b.classList.remove('active'));
    document.getElementById('admin-' + name).style.display = 'block';
    btn.classList.add('active');
}

function toggleDoctorFields(select) {
    const fields = document.getElementById('doctorFields');
    fields.style.display =
        select.value === 'doctor' ? 'block' : 'none';
}

// Auto open services tab if hash is #services
if (window.location.hash === '#services') {
    showAdminTab('services',
        document.getElementById('tab-btn-services'));
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>