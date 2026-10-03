<?php
require_once __DIR__ . '/functions.php';
$actor = require_role(['OWNER', 'MANAGER']);
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM customers) AS customers,
    (SELECT COUNT(*) FROM workers WHERE status = 'ACTIVE') AS workers,
    (SELECT COUNT(*) FROM appointments) AS appointments,
    (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE) AS today,
    (SELECT COUNT(*) FROM appointments WHERE status = 'PENDING') AS pending,
    (SELECT COUNT(*) FROM appointments WHERE status = 'CONFIRMED') AS confirmed,
    (SELECT COUNT(*) FROM appointments WHERE status = 'COMPLETED') AS completed")->fetch();
$todayQuery = $pdo->query("SELECT a.id, a.appointment_number, a.appointment_date, a.start_time, a.end_time, a.status, c.name AS customer_name, s.name AS service_name, w.name AS worker_name FROM appointments a JOIN customers c ON c.id = a.customer_id JOIN services s ON s.id = a.service_id LEFT JOIN workers w ON w.id = a.worker_id WHERE a.appointment_date = CURRENT_DATE ORDER BY a.start_time LIMIT 30");
$todayAppointments = $todayQuery->fetchAll();
$cards = [
    ['Customers', (int)$stats['customers']],
    ['Active workers', (int)$stats['workers']],
    ['Appointments', (int)$stats['appointments']],
    ["Today's appointments", (int)$stats['today']],
    ['Pending', (int)$stats['pending']],
    ['Confirmed', (int)$stats['confirmed']],
    ['Completed', (int)$stats['completed']],
];
$pageTitle = ucfirst(strtolower($actor['role'])) . ' dashboard';
require __DIR__ . '/header.php';
?>
<section class="container py-5">
    <div class="dashboard-heading"><div><p class="eyebrow"><?= e($actor['role']) ?> OVERVIEW</p><h1 class="section-title">Good day, <?= e($actor['name']) ?></h1><p class="text-muted">The salon at a glance for <?= e(date('l, j F Y')) ?>.</p></div></div>
    <div class="stats-grid mb-5"><?php foreach ($cards as [$label, $count]): ?><div class="stat-tile"><span><?= e($label) ?></span><strong><?= $count ?></strong></div><?php endforeach; ?></div>
    <div class="section-heading"><h2 class="h4">Today’s appointments</h2><a href="<?= e(url($actor['role'] === 'OWNER' ? 'owner/appointments.php' : 'manager/appointments.php')) ?>">Manage appointments</a></div>
    <?php if (!$todayAppointments): ?><div class="empty-state"><p class="mb-0">There are no appointments scheduled for today.</p></div><?php else: ?>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Appointment</th><th>Customer</th><th>Service</th><th>Time</th><th>Worker</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($todayAppointments as $appointment): ?><tr><td><?= e($appointment['appointment_number']) ?></td><td><?= e($appointment['customer_name']) ?></td><td><?= e($appointment['service_name']) ?></td><td><?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></td><td><?= e($appointment['worker_name'] ?? 'Unassigned') ?></td><td><?= status_badge($appointment['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <div class="quick-links mt-4">
        <a href="<?= e(url($actor['role'] === 'OWNER' ? 'owner/workers.php' : 'manager/workers.php')) ?>">Manage workers</a>
        <a href="<?= e(url($actor['role'] === 'OWNER' ? 'owner/customers.php' : 'manager/customers.php')) ?>">View customers</a>
        <a href="<?= e(url($actor['role'] === 'OWNER' ? 'owner/services.php' : 'manager/services.php')) ?>">Manage services</a>
        <?php if ($actor['role'] === 'OWNER'): ?><a href="<?= e(url('owner/manager.php')) ?>">Manager account</a><a href="<?= e(url('owner/settings.php')) ?>">Salon settings</a><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
