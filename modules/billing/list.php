<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Billing';
$activePage = 'billing';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$sql = "SELECT b.*,
        CONCAT(p.first_name,' ',p.last_name) AS patient_name,
        p.phone AS patient_phone
        FROM bills b
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ?
              OR p.phone LIKE ?)";
    $params = array_merge($params,
        ["%$search%","%$search%","%$search%"]);
}
if ($status) {
    $sql .= " AND b.payment_status = ?";
    $params[] = $status;
}
if ($from) {
    $sql .= " AND b.bill_date >= ?";
    $params[] = $from;
}
if ($to) {
    $sql .= " AND b.bill_date <= ?";
    $params[] = $to;
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll();

// Summary stats
$totalBills   = $db->query("SELECT COUNT(*) FROM bills")->fetchColumn();
$totalRev     = $db->query("SELECT COALESCE(SUM(amount_paid),0) FROM bills")->fetchColumn();
$totalPending = $db->query("SELECT COALESCE(SUM(balance),0) FROM bills WHERE payment_status != 'paid'")->fetchColumn();
$paidCount    = $db->query("SELECT COUNT(*) FROM bills WHERE payment_status='paid'")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Billing & Payments</h1>
        <p>Manage patient invoices and payment records</p>
    </div>
    <div class="page-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="bi bi-receipt"></i> Create Bill
        </a>
    </div>
</div>

<!-- Stats -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="bi bi-receipt-cutoff"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($totalBills) ?></div>
            <div class="stat-label">Total Bills</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="bi bi-cash-stack"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                KSh <?= number_format($totalRev) ?>
            </div>
            <div class="stat-label">Total Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value">
                KSh <?= number_format($totalPending) ?>
            </div>
            <div class="stat-label">Outstanding Balance</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= number_format($paidCount) ?></div>
            <div class="stat-label">Fully Paid Bills</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET"
              style="display:flex;gap:12px;align-items:center;
                     flex-wrap:wrap;">
            <div class="search-bar">
                <i class="bi bi-search"></i>
                <input type="text" name="search"
                    placeholder="Search patient name or phone..."
                    value="<?= clean($search) ?>">
            </div>
            <select name="status"
                    class="form-control form-select"
                    style="width:150px;">
                <option value="">All Statuses</option>
                <?php foreach (['pending','partial','paid'] as $s): ?>
                <option value="<?= $s ?>"
                    <?= $status===$s?'selected':'' ?>>
                    <?= ucfirst($s) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from"
                   class="form-control" style="width:150px;"
                   value="<?= clean($from) ?>">
            <input type="date" name="to"
                   class="form-control" style="width:150px;"
                   value="<?= clean($to) ?>">
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
            <i class="bi bi-receipt"></i>
            Bills (<?= count($bills) ?>)
        </span>
    </div>
    <div class="table-responsive">
        <?php if ($bills): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Bill ID</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bills as $b): ?>
                <tr>
                    <td>
                        <span style="font-family:monospace;
                            font-weight:700;color:var(--primary);">
                            <?= billCode($b['bill_id']) ?>
                        </span>
                    </td>
                    <td><?= fdate($b['bill_date']) ?></td>
                    <td>
                        <div style="font-weight:600;">
                            <?= clean($b['patient_name']) ?>
                        </div>
                        <div style="font-size:11.5px;
                            color:var(--text-muted);">
                            <?= clean($b['patient_phone']) ?>
                        </div>
                    </td>
                    <td style="font-weight:600;">
                        <?= money($b['total_amount']) ?>
                    </td>
                    <td style="color:var(--success);font-weight:600;">
                        <?= money($b['amount_paid']) ?>
                    </td>
                    <td style="color:<?= $b['balance']>0
                        ?'var(--danger)':'var(--success)' ?>;
                        font-weight:600;">
                        <?= money($b['balance']) ?>
                    </td>
                    <td>
                        <span style="text-transform:capitalize;">
                            <?= clean($b['payment_method']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-status
                            <?= $b['payment_status'] ?>">
                            <?= ucfirst($b['payment_status']) ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="view.php?id=<?= $b['bill_id'] ?>"
                               class="btn btn-sm btn-outline btn-icon"
                               title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($b['payment_status'] !== 'paid'): ?>
                            <a href="pay.php?id=<?= $b['bill_id'] ?>"
                               class="btn btn-sm btn-success btn-icon"
                               title="Record Payment">
                                <i class="bi bi-cash-coin"></i>
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
            <i class="bi bi-receipt"></i>
            <p>No bills found.
                <a href="add.php" style="color:var(--primary);">
                    Create the first bill
                </a>.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>