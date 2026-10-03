<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$totalCustomers    = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalServices     = (int)$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
$totalAppointments = (int)$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
$pendingCount      = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();

$recent = $pdo->query('
    SELECT a.appointment_date, a.appointment_time, a.status, u.name AS customer, s.name AS service
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id
    ORDER BY a.created_at DESC, a.id DESC LIMIT 5')->fetchAll();

$cards = [
    ['Total Customers', $totalCustomers, 'bg-primary'],
    ['Total Services', $totalServices, 'bg-success'],
    ['Total Appointments', $totalAppointments, 'bg-info'],
    ['Pending Appointments', $pendingCount, 'bg-warning'],
];

$pageTitle = 'Dashboard';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="section-title mb-4">Dashboard</h1>
    <div class="row g-3 mb-4">
        <?php foreach ($cards as [$label, $count, $bg]): ?>
            <div class="col-6 col-lg-3">
                <div class="card stat-card <?= $bg ?>"><div class="card-body">
                    <div class="small"><?= e($label) ?></div>
                    <div class="display-6 fw-bold"><?= $count ?></div>
                </div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <h5>Latest Bookings</h5>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table mb-0 align-middle">
            <thead class="table-light"><tr><th>Customer</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?><tr><td colspan="5" class="text-muted text-center">No appointments yet.</td></tr><?php endif; ?>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= e($r['customer']) ?></td>
                    <td><?= e($r['service']) ?></td>
                    <td><?= e(format_date($r['appointment_date'])) ?></td>
                    <td><?= e(format_time($r['appointment_time'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
