<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('CUSTOMER');
$profileQuery = $pdo->prepare('SELECT * FROM customers WHERE user_id = ?');
$profileQuery->execute([(int)$user['id']]);
$profile = $profileQuery->fetch();
if (!$profile) {
    http_response_code(404);
    exit('Customer profile was not found.');
}
$statsQuery = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status IN ('PENDING', 'CONFIRMED') AND CONCAT(appointment_date, ' ', start_time) >= NOW()) AS upcoming FROM appointments WHERE customer_id = ?");
$statsQuery->execute([(int)$profile['id']]);
$stats = $statsQuery->fetch();
$recentQuery = $pdo->prepare('SELECT a.*, s.name AS service_name, s.price, w.name AS worker_name FROM appointments a JOIN services s ON s.id = a.service_id LEFT JOIN workers w ON w.id = a.worker_id WHERE a.customer_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 5');
$recentQuery->execute([(int)$profile['id']]);
$recent = $recentQuery->fetchAll();
$pageTitle = 'Customer dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-5">
    <div class="dashboard-heading"><div><p class="eyebrow">CUSTOMER SPACE</p><h1 class="section-title">Welcome, <?= e($profile['name']) ?></h1><p class="text-muted">Your next little reset starts here.</p></div><a href="<?= e(url('booking.php')) ?>" class="btn btn-rose">Book an appointment</a></div>
    <div class="row g-3 mb-4"><div class="col-sm-6 col-lg-3"><div class="stat-tile"><span>Upcoming</span><strong><?= (int)($stats['upcoming'] ?? 0) ?></strong></div></div><div class="col-sm-6 col-lg-3"><div class="stat-tile"><span>All visits</span><strong><?= (int)$stats['total'] ?></strong></div></div></div>
    <div class="section-heading"><h2 class="h4">Recent appointments</h2><a href="<?= e(url('appointments.php')) ?>">View history</a></div>
    <?php if (!$recent): ?><div class="empty-state"><p class="mb-0">Your appointment history will appear here.</p></div><?php else: ?>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Appointment</th><th>Service</th><th>Date</th><th>Time</th><th>Professional</th><th>Price</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($recent as $appointment): ?><tr><td><?= e($appointment['appointment_number']) ?></td><td><?= e($appointment['service_name']) ?></td><td><?= e(format_date($appointment['appointment_date'])) ?></td><td><?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></td><td><?= e($appointment['worker_name'] ?? 'To be assigned') ?></td><td>₹<?= e(number_format((float)$appointment['price'], 2)) ?></td><td><?= status_badge($appointment['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>