<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Record Payment';
$activePage = 'billing';
$errors = [];

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("
    SELECT b.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.phone AS patient_phone
    FROM bills b
    JOIN patients p ON b.patient_id = p.patient_id
    WHERE b.bill_id = ?
");
$stmt->execute([$id]);
$bill = $stmt->fetch();
if (!$bill) { header('Location: list.php'); exit; }

if ($bill['payment_status'] === 'paid') {
    setFlash('warning', 'This bill has already been fully paid.');
    header('Location: view.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount         = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes          = trim($_POST['notes'] ?? '');

    if ($amount <= 0) {
        $errors[] = 'Payment amount must be greater than zero.';
    }
    if ($amount > $bill['balance']) {
        $errors[] = 'Payment amount cannot exceed the balance of '
            . money($bill['balance']) . '.';
    }

    if (!$errors) {
        $newPaid    = $bill['amount_paid'] + $amount;
        $newBalance = $bill['total_amount'] - $newPaid;
        $newStatus  = $newBalance <= 0 ? 'paid' : 'partial';

        $stmt = $db->prepare("UPDATE bills SET
            amount_paid     = ?,
            balance         = ?,
            payment_status  = ?,
            payment_method  = ?,
            notes           = CONCAT(IFNULL(notes,''), ?)
            WHERE bill_id   = ?");
        $stmt->execute([
            $newPaid,
            max(0, $newBalance),
            $newStatus,
            $payment_method,
            $notes ? "\n" . date('d/m/Y H:i') . ": $notes" : '',
            $id
        ]);

        logActivity('Recorded payment', 'billing', $id,
            "Amount: $amount, Method: $payment_method");
        setFlash('success',
            'Payment of ' . money($amount) . ' recorded successfully. '
            . ($newStatus === 'paid'
                ? 'Bill is now fully paid! ✅'
                : 'Remaining balance: ' . money($newBalance)));
        header('Location: view.php?id=' . $id);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Record Payment</h1>
        <p><?= billCode($bill['bill_id']) ?> —
            <?= clean($bill['patient_name']) ?></p>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Invoice
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

<div class="row g-4" style="max-width:800px;">
<div class="col-lg-7">
    <form method="POST">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="bi bi-cash-coin"></i> Payment Details
                </span>
            </div>
            <div class="card-body">

                <!-- Balance Info -->
                <div style="background:var(--bg);border-radius:10px;
                    padding:16px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;
                        margin-bottom:8px;font-size:14px;">
                        <span style="color:var(--text-muted);">
                            Total Bill Amount
                        </span>
                        <strong>
                            <?= money($bill['total_amount']) ?>
                        </strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;
                        margin-bottom:8px;font-size:14px;">
                        <span style="color:var(--text-muted);">
                            Amount Already Paid
                        </span>
                        <strong style="color:var(--success);">
                            <?= money($bill['amount_paid']) ?>
                        </strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;
                        font-size:16px;font-weight:700;
                        color:var(--danger);
                        border-top:2px solid var(--border);
                        padding-top:10px;margin-top:4px;">
                        <span>Outstanding Balance</span>
                        <span><?= money($bill['balance']) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Payment Amount (KSh)
                        <span class="req">*</span>
                    </label>
                    <input type="number" name="amount"
                        class="form-control"
                        value="<?= clean($_POST['amount']
                            ?? $bill['balance']) ?>"
                        min="0.01"
                        max="<?= $bill['balance'] ?>"
                        step="0.01"
                        placeholder="Enter amount"
                        required autofocus
                        oninput="updateRemaining(this.value)">
                    <div class="form-hint">
                        Maximum payable:
                        <?= money($bill['balance']) ?>
                    </div>
                </div>

                <!-- Remaining Preview -->
                <div id="remainingBox"
                     style="background:#f0fff4;border:1px solid #c6f6d5;
                        border-radius:8px;padding:12px 16px;
                        margin-bottom:16px;display:none;">
                    <div style="font-size:13px;color:var(--success);
                        font-weight:600;">
                        <i class="bi bi-check-circle"></i>
                        Remaining balance after payment:
                        <span id="remainingAmt"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method"
                            class="form-control form-select">
                        <?php foreach (['cash','mpesa','card',
                            'insurance','other'] as $m): ?>
                        <option value="<?= $m ?>"
                            <?= (($_POST['payment_method']
                                ??'cash')===$m)?'selected':'' ?>>
                            <?= ucfirst($m) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control"
                        rows="2"
                        placeholder="e.g. M-Pesa ref: XXXX..."
                        ><?= clean($_POST['notes'] ?? '') ?>
                    </textarea>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i>
                    Confirm Payment
                </button>
                <a href="view.php?id=<?= $id ?>"
                   class="btn btn-outline ms-2">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Summary -->
<div class="col-lg-5">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-receipt"></i> Bill Summary
            </span>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:12px;
                margin-bottom:16px;padding-bottom:16px;
                border-bottom:1px solid var(--border);">
                <div style="width:42px;height:42px;
                    background:var(--accent-light);
                    color:var(--accent);border-radius:50%;
                    display:flex;align-items:center;
                    justify-content:center;font-size:18px;
                    font-weight:700;">
                    <?= strtoupper(substr(
                        $bill['patient_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight:700;">
                        <?= clean($bill['patient_name']) ?>
                    </div>
                    <div style="font-size:12px;
                        color:var(--text-muted);">
                        <?= clean($bill['patient_phone']) ?>
                    </div>
                </div>
            </div>
            <?php
            $summary = [
                'Bill ID'     => billCode($bill['bill_id']),
                'Bill Date'   => fdate($bill['bill_date']),
                'Total Amount'=> money($bill['total_amount']),
                'Discount'    => money($bill['discount']),
                'Paid So Far' => money($bill['amount_paid']),
                'Balance'     => money($bill['balance']),
                'Status'      => ucfirst($bill['payment_status']),
            ];
            foreach ($summary as $label => $val): ?>
            <div style="display:flex;justify-content:space-between;
                padding:8px 0;font-size:13.5px;
                border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted);
                    font-size:12px;font-weight:600;">
                    <?= $label ?>
                </span>
                <span style="font-weight:500;"><?= $val ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<script>
function updateRemaining(amount) {
    const balance = <?= $bill['balance'] ?>;
    const paid    = parseFloat(amount) || 0;
    const remaining = balance - paid;
    const box = document.getElementById('remainingBox');
    const amt = document.getElementById('remainingAmt');
    if (paid > 0 && paid <= balance) {
        box.style.display = 'block';
        amt.textContent = 'KSh ' + Math.max(0,
            remaining).toLocaleString('en-KE',
            {minimumFractionDigits:2});
        if (remaining <= 0) {
            box.style.background = '#f0fff4';
            box.style.borderColor = '#c6f6d5';
            amt.textContent = '0.00 — Bill will be FULLY PAID ✅';
        } else {
            box.style.background = '#fffaf0';
            box.style.borderColor = '#fbd38d';
        }
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>