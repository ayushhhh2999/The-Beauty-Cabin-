<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('WORKER');
$workerId = (int)($user['profile_id'] ?? 0);
if (!$workerId) {
    $stmt = $pdo->prepare('SELECT id FROM workers WHERE user_id = ?');
    $stmt->execute([(int)$user['id']]);
    $workerId = (int)$stmt->fetchColumn();
}
$stmt = $pdo->prepare('SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id = a.service_id WHERE a.worker_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 300');
$stmt->execute([$workerId]);
$appointments = $stmt->fetchAll();
$pageTitle = 'My schedule';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-5"><p class="eyebrow">YOUR SCHEDULE</p><h1 class="section-title mb-4">Assigned appointments</h1>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Appointment</th><th>Customer</th><th>Mobile</th><th>Service</th><th>Date &amp; time</th><th>Status</th></tr></thead><tbody>
        <?php if (!$appointments): ?><tr><td colspan="6" class="text-center text-muted py-4">No appointments have been assigned to you.</td></tr><?php endif; ?>
        <?php foreach ($appointments as $appointment): ?><tr><td><?= e($appointment['appointment_number']) ?></td><td><?= e($appointment['customer_name']) ?></td><td><a href="tel:<?= e($appointment['customer_mobile']) ?>"><?= e($appointment['customer_mobile']) ?></a></td><td><?= e($appointment['service_name']) ?></td><td><?= e(format_date($appointment['appointment_date'])) ?><br><?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></td><td><?= status_badge($appointment['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
