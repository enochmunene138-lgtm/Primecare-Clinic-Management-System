<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Consultations';
$activePage = 'diagnosis';

$search = trim($_GET['search'] ?? '');
$doctor = (int)($_GET['doctor_id'] ?? 0);
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$sql = "SELECT v.*,
        CONCAT(p.first_name,' ',p.last_name) AS patient_name,
        p.phone AS patient_phone,
        d.full_name AS doctor_name,
        d.specialization
        FROM visits v
        JOIN patients p ON v.patient_id = p.patient_id
        JOIN doctors d ON v.doctor_id = d.doctor_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ?
              OR v.diagnosis LIKE ? OR v.chief_complaint LIKE ?)";
    $params = array_merge($params,
        ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($doctor) {
    $sql .= " AND v.doctor_id = ?";
    $params[] = $doctor;
}
if ($from) {
    $sql .= " AND v.visit_date >= ?";
    $params[] = $from;
}
if ($to) {
    $sql .= " AND v.visit_date <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY v.visit_date DESC, v.visit_time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

$doctors = $db->query("SELECT doctor_id, full_name FROM doctors
    WHERE status='active' ORDER BY full_name")->fetchAll();

$totalVisits = $db->query("SELECT COUNT(*) FROM visits")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Consultations</h1>
        <p><?= number_format($totalVisits) ?> total consultation records</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-clipboard-plus"></i> New Consultation
        </a>
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
                    placeholder="Search patient, diagnosis..."
                    value="<?= clean($search) ?>">
            </div>
            <select name="doctor_id"
                    class="form-control form-select" style="width:180px;">
                <option value="">All Doctors</option>
                <?php foreach ($doctors as $d): ?>
                <option value="<?= $d['doctor_id'] ?>"
                    <?= $doctor===$d['doctor_id']?'selected':'' ?>>
                    <?= clean($d['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" class="form-control"
                   style="width:150px;" value="<?= clean($from) ?>"
                   placeholder="From">
            <input type="date" name="to" class="form-control"
                   style="width:150px;" value="<?= clean($to) ?>"
                   placeholder="To">
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
            <i class="bi bi-clipboard-pulse"></i>
            Consultation Records (<?= count($visits) ?>)
        </span>
    </div>
    <div class="table-responsive">
        <?php if ($visits): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Chief Complaint</th>
                    <th>Diagnosis</th>
                    <th>Follow Up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($visits as $v): ?>
                <tr>
                    <td>
                        <strong><?= fdate($v['visit_date']) ?></strong>
                        <div style="font-size:11.5px;color:var(--text-muted);">
                            <?= date('H:i',
                                strtotime($v['visit_time'])) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;">
                            <?= clean($v['patient_name']) ?>
                        </div>
                        <div style="font-size:11.5px;
                            color:var(--text-muted);">
                            <?= clean($v['patient_phone']) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;">
                            <?= clean($v['doctor_name']) ?>
                        </div>
                        <div style="font-size:11.5px;
                            color:var(--text-muted);">
                            <?= clean($v['specialization']) ?>
                        </div>
                    </td>
                    <td style="max-width:160px;">
                        <?= clean(substr(
                            $v['chief_complaint'] ?? '—', 0, 50)) ?>
                    </td>
                    <td style="max-width:160px;">
                        <?= clean(substr(
                            $v['diagnosis'] ?? '—', 0, 50)) ?>
                    </td>
                    <td>
                        <?php if ($v['follow_up_date']): ?>
                        <span style="color:var(--warning);
                            font-weight:600;font-size:13px;">
                            <i class="bi bi-calendar-event"></i>
                            <?= fdate($v['follow_up_date']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="view.php?id=<?= $v['visit_id'] ?>"
                               class="btn btn-sm btn-outline btn-icon"
                               title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="../billing/add.php?visit_id=<?= $v['visit_id'] ?>&patient_id=<?= $v['patient_id'] ?>"
                               class="btn btn-sm btn-accent btn-icon"
                               title="Generate Bill">
                                <i class="bi bi-receipt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-clipboard-x"></i>
            <p>No consultation records found.
                <a href="add.php" style="color:var(--primary);">
                    Record first consultation
                </a>.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>