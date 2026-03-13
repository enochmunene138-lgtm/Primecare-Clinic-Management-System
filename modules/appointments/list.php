<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Appointments';
$activePage = 'appointments';

$date   = $_GET['date'] ?? '';
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT a.*,
        CONCAT(p.first_name,' ',p.last_name) AS patient_name,
        p.phone AS patient_phone,
        d.full_name AS doctor_name,
        d.specialization
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE 1=1";
$params = [];

if ($date) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $date;
}
if ($status) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}
if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ?
              OR d.full_name LIKE ?)";
    $params = array_merge($params,
        ["%$search%", "%$search%", "%$search%"]);
}
$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Stats
$totalToday    = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetchColumn();
$totalPending  = $db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
$totalDone     = $db->query("SELECT COUNT(*) FROM appointments WHERE status='completed'")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Appointments</h1>
        <p>Manage and track all clinic appointments</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-calendar-plus"></i> Book Appointment
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-calendar-day"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalToday ?></div>
            <div class="stat-label">Today's Appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalPending ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalDone ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET"
              style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search"
                    placeholder="Search patient or doctor..."
                    value="<?= clean($search) ?>">
            </div>
            <input type="date" name="date" class="form-control"
                   style="width:160px;" value="<?= clean($date) ?>">
            <select name="status" class="form-control form-select"
                    style="width:150px;">
                <option value="">All Statuses</option>
                <?php foreach (['pending','confirmed','completed','cancelled','no-show'] as $s): ?>
                <option value="<?= $s ?>"
                    <?= $status===$s?'selected':'' ?>>
                    <?= ucfirst($s) ?>
                </option>
                <?php endforeach; ?>
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
            <i class="bi bi-calendar-check"></i>
            Appointments (<?= count($appointments) ?>)
        </span>
    </div>
    <div class="table-responsive">
        <?php if ($appointments): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td>
                        <strong><?= fdate($a['appointment_date']) ?></strong>
                        <?php if ($a['appointment_date'] === date('Y-m-d')): ?>
                        <span style="background:#f0fff4;color:var(--success);
                            padding:2px 8px;border-radius:20px;font-size:10px;
                            font-weight:700;margin-left:4px;">TODAY</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>
                            <?= date('H:i', strtotime($a['appointment_time'])) ?>
                        </strong>
                    </td>
                    <td>
                        <div style="font-weight:600;">
                            <?= clean($a['patient_name']) ?>
                        </div>
                        <div style="font-size:11.5px;color:var(--text-muted);">
                            <?= clean($a['patient_phone']) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;">
                            <?= clean($a['doctor_name']) ?>
                        </div>
                        <div style="font-size:11.5px;color:var(--text-muted);">
                            <?= clean($a['specialization']) ?>
                        </div>
                    </td>
                    <td style="max-width:180px;">
                        <?= clean(substr($a['reason'] ?? '—', 0, 50)) ?>
                    </td>
                    <td>
                        <span class="badge-status <?= $a['status'] ?>">
                            <?= ucfirst($a['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="view.php?id=<?= $a['appointment_id'] ?>"
                               class="btn btn-sm btn-outline btn-icon"
                               title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (in_array($a['status'],
                                ['pending','confirmed'])): ?>
                            <a href="update_status.php?id=<?= $a['appointment_id'] ?>&status=completed"
                               class="btn btn-sm btn-success btn-icon"
                               title="Mark Complete"
                               onclick="return confirm('Mark as completed?')">
                                <i class="bi bi-check-lg"></i>
                            </a>
                            <a href="update_status.php?id=<?= $a['appointment_id'] ?>&status=cancelled"
                               class="btn btn-sm btn-danger btn-icon"
                               title="Cancel"
                               onclick="return confirm('Cancel this appointment?')">
                                <i class="bi bi-x-lg"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>No appointments found.
                <a href="add.php" style="color:var(--primary);">
                    Book the first appointment
                </a>.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>