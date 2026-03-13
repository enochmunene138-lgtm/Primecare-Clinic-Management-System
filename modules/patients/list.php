<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Patients';
$activePage = 'patients';

$search = trim($_GET['search'] ?? '');
$gender = $_GET['gender'] ?? '';

$sql = "SELECT p.*, u.full_name AS registered_by_name
        FROM patients p
        LEFT JOIN users u ON p.registered_by = u.user_id
        WHERE p.status='active'";
$params = [];
if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($gender) {
    $sql .= " AND p.gender = ?";
    $params[] = $gender;
}
$sql .= " ORDER BY p.registration_date DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$totalCount = $db->query("SELECT COUNT(*) FROM patients WHERE status='active'")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Patients</h1>
        <p><?= number_format($totalCount) ?> registered patients in the system</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Register Patient
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search"
                    placeholder="Search name, phone..."
                    value="<?= clean($search) ?>">
            </div>
            <select name="gender" class="form-control form-select" style="width:140px;">
                <option value="">All Genders</option>
                <option value="Male" <?= $gender==='Male'?'selected':'' ?>>Male</option>
                <option value="Female" <?= $gender==='Female'?'selected':'' ?>>Female</option>
                <option value="Other" <?= $gender==='Other'?'selected':'' ?>>Other</option>
            </select>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <a href="list.php" class="btn btn-outline">
                <i class="bi bi-x"></i> Clear
            </a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="bi bi-people"></i>
            Patient Records (<?= count($patients) ?>)
        </span>
    </div>
    <div class="table-responsive">
        <?php if ($patients): ?>
        <table class="data-table" id="patientsTable">
            <thead>
                <tr>
                    <th>Patient ID</th>
                    <th>Full Name</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Phone</th>
                    <th>Blood Group</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $p): ?>
                <tr>
                    <td>
                        <span style="font-weight:700;color:var(--primary);font-family:monospace;">
                            <?= patientCode($p['patient_id']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:34px;height:34px;background:var(--accent-light);color:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;">
                                <?= strtoupper(substr($p['first_name'],0,1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;">
                                    <?= clean($p['first_name'] . ' ' . $p['last_name']) ?>
                                </div>
                                <div style="font-size:11.5px;color:var(--text-muted);">
                                    <?= clean($p['email'] ?: '—') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-status <?= strtolower($p['gender']) ?>">
                            <?= $p['gender'] ?>
                        </span>
                    </td>
                    <td><?= calcAge($p['date_of_birth']) ?></td>
                    <td><?= clean($p['phone']) ?></td>
                    <td>
                        <span style="background:var(--accent-light);color:var(--accent);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                            <?= clean($p['blood_group']) ?>
                        </span>
                    </td>
                    <td style="font-size:12.5px;color:var(--text-muted);">
                        <?= fdate($p['registration_date']) ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="view.php?id=<?= $p['patient_id'] ?>"
                               class="btn btn-sm btn-outline btn-icon" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="edit.php?id=<?= $p['patient_id'] ?>"
                               class="btn btn-sm btn-outline btn-icon" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="../appointments/add.php?patient_id=<?= $p['patient_id'] ?>"
                               class="btn btn-sm btn-accent btn-icon" title="Book Appointment">
                                <i class="bi bi-calendar-plus"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-person-x"></i>
            <p>No patients found.
                <a href="add.php" style="color:var(--primary);">
                    Register the first patient
                </a>.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>