<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Create Bill';
$activePage = 'billing';
$errors = [];

$prePatient = (int)($_GET['patient_id'] ?? 0);
$preVisit   = (int)($_GET['visit_id'] ?? 0);

$patients = $db->query("
    SELECT patient_id, first_name, last_name, phone
    FROM patients WHERE status='active'
    ORDER BY first_name, last_name
")->fetchAll();

$services = $db->query("
    SELECT service_id, service_name, category, price
    FROM services WHERE status='active'
    ORDER BY category, service_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id    = (int)($_POST['patient_id'] ?? 0);
    $visit_id      = (int)($_POST['visit_id'] ?? 0);
    $bill_date     = $_POST['bill_date'] ?? date('Y-m-d');
    $discount      = (float)($_POST['discount'] ?? 0);
    $amount_paid   = (float)($_POST['amount_paid'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes         = trim($_POST['notes'] ?? '');
    $subtotal      = (float)($_POST['subtotal'] ?? 0);
    $total_amount  = (float)($_POST['total_amount'] ?? 0);

    $descs   = $_POST['desc']  ?? [];
    $qtys    = $_POST['qty']   ?? [];
    $prices  = $_POST['price'] ?? [];
    $svc_ids = $_POST['svc_id'] ?? [];

    if (!$patient_id) $errors[] = 'Please select a patient.';
    if (empty($descs)) $errors[] = 'Please add at least one item.';

    // Validate items
    $items = [];
    foreach ($descs as $i => $desc) {
        $desc  = trim($desc);
        $qty   = (int)($qtys[$i] ?? 1);
        $price = (float)($prices[$i] ?? 0);
        if (!$desc || $price <= 0) continue;
        $items[] = [
            'svc_id' => (int)($svc_ids[$i] ?? 0),
            'desc'   => $desc,
            'qty'    => $qty,
            'price'  => $price,
            'total'  => $qty * $price,
        ];
    }
    if (empty($items)) $errors[] = 'Please add at least one valid item.';

    if (!$errors) {
        // Recalculate totals server-side
        $subtotal = array_sum(array_column($items, 'total'));
        $total_amount = max(0, $subtotal - $discount);
        $balance = max(0, $total_amount - $amount_paid);
        $pay_status = 'pending';
        if ($amount_paid >= $total_amount) $pay_status = 'paid';
        elseif ($amount_paid > 0)          $pay_status = 'partial';

        $stmt = $db->prepare("INSERT INTO bills
            (visit_id, patient_id, bill_date, subtotal, discount,
             total_amount, amount_paid, balance, payment_method,
             payment_status, notes, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $visit_id ?: null, $patient_id, $bill_date,
            $subtotal, $discount, $total_amount,
            $amount_paid, $balance, $payment_method,
            $pay_status, $notes, $_SESSION['user_id']
        ]);
        $billId = $db->lastInsertId();

        // Save bill items
        foreach ($items as $item) {
            $iStmt = $db->prepare("INSERT INTO bill_items
                (bill_id, service_id, description, quantity,
                 unit_price, total_price)
                VALUES (?,?,?,?,?,?)");
            $iStmt->execute([
                $billId,
                $item['svc_id'] ?: null,
                $item['desc'],
                $item['qty'],
                $item['price'],
                $item['total'],
            ]);
        }

        logActivity('Created bill', 'billing', $billId,
            "Patient ID: $patient_id, Total: $total_amount");
        setFlash('success', 'Bill ' . billCode($billId)
            . ' created successfully.');
        header('Location: view.php?id=' . $billId);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Create Bill</h1>
        <p>Generate a new patient invoice</p>
    </div>
    <div class="page-actions">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back to Billing
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
<input type="hidden" name="subtotal" id="subtotal">
<input type="hidden" name="total_amount" id="total_amount">
<input type="hidden" name="visit_id"
    value="<?= $preVisit ?>">

<div class="row g-4">
<div class="col-lg-8">

    <!-- Bill Details -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-receipt"></i> Bill Details
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
                    <label class="form-label">Bill Date</label>
                    <input type="date" name="bill_date"
                        class="form-control"
                        value="<?= clean($_POST['bill_date']
                            ?? date('Y-m-d')) ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Items -->
    <div class="card mb-4">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-list-check"></i> Bill Items
            </span>
            <button type="button" class="btn btn-sm btn-accent"
                onclick="addItemRow(<?= htmlspecialchars(
                    json_encode($services), ENT_QUOTES) ?>)">
                <i class="bi bi-plus"></i> Add Item
            </button>
        </div>
        <div class="card-body">
            <!-- Header -->
            <div style="display:grid;
                grid-template-columns:2fr 2fr 70px 120px 100px 40px;
                gap:8px;margin-bottom:8px;">
                <?php foreach (['Service','Description',
                    'Qty','Unit Price','Total',''] as $h): ?>
                <div style="font-size:11px;font-weight:700;
                    color:var(--text-muted);
                    text-transform:uppercase;">
                    <?= $h ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="billItems"></div>
            <button type="button"
                    class="btn btn-sm btn-outline mt-2"
                    onclick="addItemRow(<?= htmlspecialchars(
                        json_encode($services), ENT_QUOTES) ?>)">
                <i class="bi bi-plus-circle"></i> Add Row
            </button>
        </div>
    </div>

    <!-- Totals -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-calculator"></i> Payment Summary
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">
                            Payment Method
                        </label>
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
                        <label class="form-label">Notes</label>
                        <textarea name="notes"
                            class="form-control" rows="2"
                            placeholder="Any billing notes..."
                            ><?= clean($_POST['notes'] ?? '') ?>
                        </textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="background:var(--bg);border-radius:10px;
                        padding:16px;">
                        <div style="display:flex;
                            justify-content:space-between;
                            padding:8px 0;font-size:14px;">
                            <span style="color:var(--text-muted);">
                                Subtotal
                            </span>
                            <span id="subtotalDisplay"
                                  style="font-weight:600;">
                                KSh 0.00
                            </span>
                        </div>
                        <div style="display:flex;
                            justify-content:space-between;
                            padding:8px 0;font-size:14px;
                            align-items:center;">
                            <span style="color:var(--text-muted);">
                                Discount (KSh)
                            </span>
                            <input type="number" name="discount"
                                id="discount"
                                class="form-control"
                                style="width:120px;text-align:right;"
                                value="<?= clean($_POST['discount']
                                    ?? '0') ?>"
                                min="0" step="0.01"
                                onchange="calcBillTotal()">
                        </div>
                        <div style="display:flex;
                            justify-content:space-between;
                            padding:12px 0;font-size:16px;
                            font-weight:700;color:var(--primary);
                            border-top:2px solid var(--border);
                            margin-top:4px;">
                            <span>Total</span>
                            <span id="totalDisplay">KSh 0.00</span>
                        </div>
                        <div style="margin-top:12px;">
                            <label class="form-label">
                                Amount Paid (KSh)
                            </label>
                            <input type="number"
                                name="amount_paid"
                                class="form-control"
                                value="<?= clean($_POST['amount_paid']
                                    ?? '0') ?>"
                                min="0" step="0.01"
                                placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i>
                    Create Bill & Generate Invoice
                </button>
                <a href="list.php" class="btn btn-outline ms-2">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Side Panel -->
<div class="col-lg-4">
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <i class="bi bi-grid"></i> Quick Add Services
            </span>
        </div>
        <div class="card-body">
            <p style="font-size:12.5px;color:var(--text-muted);
                margin-bottom:12px;">
                Click any service below to quickly add it to the bill:
            </p>
            <?php
            $categories = [];
            foreach ($services as $s) {
                $categories[$s['category']][] = $s;
            }
            foreach ($categories as $cat => $svcs): ?>
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;
                    color:var(--text-muted);text-transform:uppercase;
                    letter-spacing:.6px;margin-bottom:6px;">
                    <?= clean($cat) ?>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($svcs as $s): ?>
                    <button type="button"
                        style="padding:5px 10px;background:var(--bg);
                            border:1px solid var(--border);
                            border-radius:6px;font-size:12px;
                            cursor:pointer;transition:all .2s;"
                        onclick="quickAdd(<?= $s['service_id'] ?>,
                            '<?= addslashes($s['service_name']) ?>',
                            <?= $s['price'] ?>)"
                        onmouseover="this.style.background='var(--accent-light)'"
                        onmouseout="this.style.background='var(--bg)'">
                        <?= clean($s['service_name']) ?>
                        <span style="color:var(--accent);
                            font-weight:600;">
                            <?= money($s['price']) ?>
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</div>
</form>

<script>
const allServices = <?= json_encode($services) ?>;

function quickAdd(svcId, name, price) {
    const container = document.getElementById('billItems');
    const row = document.createElement('div');
    row.style.cssText = `display:grid;
        grid-template-columns:2fr 2fr 70px 120px 100px 40px;
        gap:8px;margin-bottom:8px;align-items:center;`;
    row.innerHTML = `
        <input type="hidden" name="svc_id[]" value="${svcId}">
        <div style="font-size:13px;font-weight:600;
            color:var(--primary);">${name}</div>
        <input type="text" name="desc[]"
            class="form-control" value="${name}">
        <input type="number" name="qty[]"
            class="form-control item-qty"
            value="1" min="1"
            onchange="calcBillTotal()">
        <input type="number" name="price[]"
            class="form-control item-price"
            value="${price}" step="0.01"
            onchange="calcBillTotal()">
        <span class="item-total"
            style="font-weight:700;color:var(--primary);">
            KSh ${parseFloat(price).toFixed(2)}
        </span>
        <button type="button"
            class="btn btn-sm btn-danger btn-icon"
            onclick="this.closest('div').remove();calcBillTotal()">
            <i class="bi bi-trash"></i>
        </button>
    `;
    container.appendChild(row);
    calcBillTotal();
}

function addItemRow(services) {
    const container = document.getElementById('billItems');
    const opts = services.map(s =>
        `<option value="${s.service_id}"
            data-price="${s.price}"
            data-name="${s.service_name}">
            ${s.service_name} — KSh ${s.price}
        </option>`).join('');
    const row = document.createElement('div');
    row.style.cssText = `display:grid;
        grid-template-columns:2fr 2fr 70px 120px 100px 40px;
        gap:8px;margin-bottom:8px;align-items:center;`;
    row.innerHTML = `
        <select name="svc_id[]"
                class="form-control form-select"
                onchange="fillPrice(this)">
            <option value="">-- Service --</option>
            ${opts}
        </select>
        <input type="text" name="desc[]"
            class="form-control" placeholder="Description">
        <input type="number" name="qty[]"
            class="form-control item-qty"
            value="1" min="1" onchange="calcBillTotal()">
        <input type="number" name="price[]"
            class="form-control item-price"
            placeholder="0.00" step="0.01"
            onchange="calcBillTotal()">
        <span class="item-total"
            style="font-weight:700;color:var(--primary);">
            KSh 0.00
        </span>
        <button type="button"
            class="btn btn-sm btn-danger btn-icon"
            onclick="this.closest('div').remove();calcBillTotal()">
            <i class="bi bi-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function fillPrice(select) {
    const opt   = select.options[select.selectedIndex];
    const price = opt?.dataset?.price || 0;
    const name  = opt?.dataset?.name  || '';
    const row   = select.closest('div');
    row.querySelector('.item-price').value = price;
    row.querySelector('[name="desc[]"]').value = name;
    calcBillTotal();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>