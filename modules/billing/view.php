<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Invoice';
$activePage = 'billing';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("
    SELECT b.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.phone AS patient_phone,
           p.address AS patient_address,
           p.email AS patient_email,
           p.date_of_birth, p.gender,
           u.full_name AS created_by_name
    FROM bills b
    JOIN patients p ON b.patient_id = p.patient_id
    LEFT JOIN users u ON b.created_by = u.user_id
    WHERE b.bill_id = ?
");
$stmt->execute([$id]);
$bill = $stmt->fetch();
if (!$bill) { header('Location: list.php'); exit; }

// Bill items
$items = $db->prepare("
    SELECT bi.*, s.service_name, s.category
    FROM bill_items bi
    LEFT JOIN services s ON bi.service_id = s.service_id
    WHERE bi.bill_id = ?
    ORDER BY bi.item_id
");
$items->execute([$id]);
$items = $items->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
    <div class="page-title">
        <h1>Invoice</h1>
        <p><?= billCode($bill['bill_id']) ?> —
            <?= clean($bill['patient_name']) ?></p>
    </div>
    <div class="page-actions">
        <a href="list.php" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back
        </a>
        <?php if ($bill['payment_status'] !== 'paid'): ?>
        <a href="pay.php?id=<?= $id ?>" class="btn btn-success">
            <i class="bi bi-cash-coin"></i> Record Payment
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer"></i> Print Invoice
        </button>
    </div>
</div>

<!-- Invoice Card -->
<div class="card" style="max-width:860px;margin:0 auto;">
    <div class="card-body" style="padding:40px;">

        <!-- Invoice Header -->
        <div class="invoice-header">
            <div>
                <div class="invoice-logo">
                    <i class="bi bi-hospital-fill"
                       style="margin-right:8px;"></i>
                    PrimeCare
                </div>
                <div style="font-size:13px;color:var(--text-muted);
                    margin-top:4px;">
                    Clinic Management System
                </div>
                <div style="font-size:12.5px;color:var(--text-muted);
                    margin-top:8px;line-height:1.6;">
                    <i class="bi bi-geo-alt"></i>
                    Nairobi, Kenya<br>
                    <i class="bi bi-telephone"></i>
                    +254 700 000 000<br>
                    <i class="bi bi-envelope"></i>
                    info@primecare.co.ke
                </div>
            </div>
            <div class="invoice-meta">
                <div style="font-size:28px;font-weight:800;
                    color:var(--primary);letter-spacing:-1px;">
                    INVOICE
                </div>
                <div style="font-family:monospace;font-size:15px;
                    font-weight:700;color:var(--accent);
                    margin-top:6px;">
                    <?= billCode($bill['bill_id']) ?>
                </div>
                <div style="margin-top:12px;line-height:1.8;
                    font-size:13px;">
                    <div>
                        <strong>Date:</strong>
                        <?= fdate($bill['bill_date']) ?>
                    </div>
                    <div>
                        <strong>Status:</strong>
                        <span class="badge-status
                            <?= $bill['payment_status'] ?>">
                            <?= ucfirst($bill['payment_status']) ?>
                        </span>
                    </div>
                    <div>
                        <strong>Method:</strong>
                        <?= ucfirst($bill['payment_method']) ?>
                    </div>
                </div>
            </div>
        </div>

        <hr class="invoice-divider">

        <!-- Bill To -->
        <div style="margin-bottom:32px;">
            <div style="font-size:11px;font-weight:700;
                color:var(--text-muted);text-transform:uppercase;
                letter-spacing:.8px;margin-bottom:8px;">
                Bill To
            </div>
            <div style="font-size:16px;font-weight:700;">
                <?= clean($bill['patient_name']) ?>
            </div>
            <div style="font-size:13px;color:var(--text-muted);
                margin-top:4px;line-height:1.7;">
                <?= clean($bill['patient_phone']) ?><br>
                <?php if ($bill['patient_email']): ?>
                <?= clean($bill['patient_email']) ?><br>
                <?php endif; ?>
                <?php if ($bill['patient_address']): ?>
                <?= clean($bill['patient_address']) ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <table class="data-table" style="margin-bottom:24px;">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Description</th>
                    <th>Category</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong>
                            <?= clean($item['description']) ?>
                        </strong>
                    </td>
                    <td style="font-size:12px;
                        color:var(--text-muted);">
                        <?= clean($item['category'] ?? '—') ?>
                    </td>
                    <td style="text-align:center;">
                        <?= $item['quantity'] ?>
                    </td>
                    <td style="text-align:right;">
                        <?= money($item['unit_price']) ?>
                    </td>
                    <td style="text-align:right;font-weight:600;">
                        <?= money($item['total_price']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="invoice-totals">
            <div class="invoice-total-row">
                <span>Subtotal</span>
                <span><?= money($bill['subtotal']) ?></span>
            </div>
            <?php if ($bill['discount'] > 0): ?>
            <div class="invoice-total-row"
                 style="color:var(--success);">
                <span>Discount</span>
                <span>− <?= money($bill['discount']) ?></span>
            </div>
            <?php endif; ?>
            <div class="invoice-total-row grand">
                <span>Total Amount</span>
                <span><?= money($bill['total_amount']) ?></span>
            </div>
            <div class="invoice-total-row"
                 style="color:var(--success);">
                <span>Amount Paid</span>
                <span><?= money($bill['amount_paid']) ?></span>
            </div>
            <div class="invoice-total-row"
                 style="color:<?= $bill['balance'] > 0
                    ? 'var(--danger)' : 'var(--success)' ?>;
                    font-weight:700;">
                <span>Balance Due</span>
                <span><?= money($bill['balance']) ?></span>
            </div>
        </div>

        <!-- Payment Stamp -->
        <?php if ($bill['payment_status'] === 'paid'): ?>
        <div style="position:absolute;right:60px;bottom:120px;
            border:4px solid var(--success);border-radius:8px;
            padding:8px 20px;color:var(--success);font-size:28px;
            font-weight:800;text-transform:uppercase;
            opacity:.25;transform:rotate(-15deg);
            pointer-events:none;">
            PAID
        </div>
        <?php endif; ?>

        <hr class="invoice-divider" style="margin-top:32px;">

        <!-- Footer -->
        <div style="display:flex;justify-content:space-between;
            align-items:center;font-size:12px;
            color:var(--text-muted);">
            <div>
                Generated by:
                <?= clean($bill['created_by_name'] ?? 'System') ?><br>
                Printed: <?= date('d M Y H:i') ?>
            </div>
            <div style="text-align:right;">
                <?php if ($bill['notes']): ?>
                <em><?= clean($bill['notes']) ?></em><br>
                <?php endif; ?>
                Thank you for choosing PrimeCare Clinic!
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>