<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDB();
$pageTitle  = 'Doctors';
$activePage = 'doctors';

$doctors = $db->query("
    SELECT d.*,
           COUNT(DISTINCT a.appointment_id) AS total_appointments,
           COUNT(DISTINCT v.visit_id) AS total_visits
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    LEFT JOIN visits v ON d.doctor_id = v.doctor_id
    WHERE d.status = 'active'
    GROUP BY d.doctor_id
    ORDER BY d.full_name
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Doctors</h1>
        <p><?= count($doctors) ?> active doctors on the team</p>
    </div>
</div>

<?php if ($doctors): ?>
<div style="display:grid;grid-template-columns:
    repeat(auto-fill,minmax(300px,1fr));gap:20px;">
    <?php foreach ($doctors as $d): ?>
    <div class="card" style="transition:transform .2s,box-shadow .2s;"
         onmouseover="this.style.transform='translateY(-3px)';
             this.style.boxShadow='var(--shadow-hover)'"
         onmouseout="this.style.transform='none';
             this.style.boxShadow='var(--shadow)'">
        <div class="card-body">

            <!-- Doctor Header -->
            <div style="display:flex;align-items:center;
                gap:14px;margin-bottom:16px;">
                <div style="width:56px;height:56px;
                    background:linear-gradient(135deg,
                        var(--primary),var(--accent));
                    border-radius:14px;display:flex;
                    align-items:center;justify-content:center;
                    font-size:22px;font-weight:700;color:#fff;
                    flex-shrink:0;">
                    <?= strtoupper(substr($d['full_name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:15px;">
                        <?= clean($d['full_name']) ?>
                    </div>
                    <div style="font-size:12.5px;
                        color:var(--accent);font-weight:600;">
                        <?= clean($d['specialization']) ?>
                    </div>
                    <div style="font-size:12px;
                        color:var(--text-muted);margin-top:2px;">
                        <?= clean($d['qualification'] ?? '') ?>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;
                gap:8px;margin-bottom:16px;">
                <div style="text-align:center;background:var(--bg);
                    border-radius:8px;padding:10px 6px;">
                    <div style="font-size:18px;font-weight:700;
                        color:var(--primary);">
                        <?= $d['total_appointments'] ?>
                    </div>
                    <div style="font-size:10px;
                        color:var(--text-muted);
                        text-transform:uppercase;
                        letter-spacing:.5px;margin-top:2px;">
                        Appointments
                    </div>
                </div>
                <div style="text-align:center;background:var(--bg);
                    border-radius:8px;padding:10px 6px;">
                    <div style="font-size:18px;font-weight:700;
                        color:var(--accent);">
                        <?= $d['total_visits'] ?>
                    </div>
                    <div style="font-size:10px;
                        color:var(--text-muted);
                        text-transform:uppercase;
                        letter-spacing:.5px;margin-top:2px;">
                        Consultations
                    </div>
                </div>
                <div style="text-align:center;background:var(--bg);
                    border-radius:8px;padding:10px 6px;">
                    <div style="font-size:14px;font-weight:700;
                        color:var(--success);">
                        <?= money($d['consultation_fee']) ?>
                    </div>
                    <div style="font-size:10px;
                        color:var(--text-muted);
                        text-transform:uppercase;
                        letter-spacing:.5px;margin-top:2px;">
                        Fee
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div style="font-size:13px;color:var(--text-muted);
                margin-bottom:16px;line-height:1.8;">
                <?php if ($d['phone']): ?>
                <div>
                    <i class="bi bi-telephone"
                       style="margin-right:6px;"></i>
                    <?= clean($d['phone']) ?>
                </div>
                <?php endif; ?>
                <?php if ($d['email']): ?>
                <div>
                    <i class="bi bi-envelope"
                       style="margin-right:6px;"></i>
                    <?= clean($d['email']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;">
                <a href="../appointments/add.php?doctor_id=<?= $d['doctor_id'] ?>"
                   class="btn btn-sm btn-primary"
                   style="flex:1;justify-content:center;">
                    <i class="bi bi-calendar-plus"></i>
                    Book Appointment
                </a>
                <a href="../appointments/list.php?doctor_id=<?= $d['doctor_id'] ?>"
                   class="btn btn-sm btn-outline btn-icon"
                   title="View Schedule">
                    <i class="bi bi-calendar-week"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="bi bi-person-badge"></i>
    <p>No doctors found in the system.</p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>