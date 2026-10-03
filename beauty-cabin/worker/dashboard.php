<?php
require_once __DIR__ . '/../includes/functions.php';
$workerUser = require_role('WORKER');
$workerId = (int)($workerUser['profile_id'] ?? 0);
if (!$workerId) {
    $stmt = $pdo->prepare('SELECT id FROM workers WHERE user_id = ?');
    $stmt->execute([(int)$workerUser['id']]);
    $workerId = (int)$stmt->fetchColumn();
}
if (!$workerId) {
    http_response_code(403);
    exit('Worker profile not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    if ($appointmentId) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, status, appointment_date, end_time FROM appointments WHERE id = ? AND worker_id = ? FOR UPDATE');
            $stmt->execute([$appointmentId, $workerId]);
            $appointment = $stmt->fetch();
            if (!$appointment || $appointment['status'] !== 'CONFIRMED' || ($appointment['appointment_date'] . ' ' . $appointment['end_time']) > date('Y-m-d H:i:s')) {
                throw new DomainException('Only your confirmed appointments that have ended can be marked completed.');
            }
            $pdo->prepare("UPDATE appointments SET status = 'COMPLETED' WHERE id = ?")->execute([$appointmentId]);
            $pdo->prepare('INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)')->execute([$appointmentId, 'CONFIRMED', 'COMPLETED', (int)$workerUser['id']]);
            $pdo->commit();
            flash('success', 'Appointment marked as completed.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('warning', $ex instanceof DomainException ? $ex->getMessage() : 'Could not update the appointment.');
        }
    }
    redirect('worker/dashboard.php');
}

$statsQuery = $pdo->prepare("SELECT SUM(appointment_date = CURRENT_DATE) AS today_count, SUM(appointment_date >= CURRENT_DATE AND status IN ('PENDING', 'CONFIRMED')) AS upcoming, SUM(status = 'COMPLETED') AS completed FROM appointments WHERE worker_id = ?");
$statsQuery->execute([$workerId]);
$stats = $statsQuery->fetch();
$appointmentsQuery = $pdo->prepare("SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id = a.service_id WHERE a.worker_id = ? AND a.appointment_date >= CURRENT_DATE AND a.status IN ('PENDING', 'CONFIRMED') ORDER BY a.appointment_date, a.start_time");
$appointmentsQuery->execute([$workerId]);
$appointments = $appointmentsQuery->fetchAll();
$completedQuery = $pdo->prepare("SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON s.id = a.service_id WHERE a.worker_id = ? AND a.status = 'COMPLETED' ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 10");
$completedQuery->execute([$workerId]);
$completed = $completedQuery->fetchAll();
$pageTitle = 'Worker dashboard';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-5">
    <div class="dashboard-heading"><div><p class="eyebrow">TEAM DASHBOARD</p><h1 class="section-title">Hello, <?= e($workerUser['name']) ?></h1><p class="text-muted">Your assigned appointments and schedule.</p></div></div>
    <div class="stats-grid mb-5"><div class="stat-tile"><span>Today</span><strong><?= (int)$stats['today_count'] ?></strong></div><div class="stat-tile"><span>Upcoming</span><strong><?= (int)($stats['upcoming'] ?? 0) ?></strong></div><div class="stat-tile"><span>Completed</span><strong><?= (int)($stats['completed'] ?? 0) ?></strong></div></div>
    <div class="section-heading"><h2 class="h4">Upcoming appointments</h2></div>
    <?php if (!$appointments): ?><div class="empty-state"><p class="mb-0">No upcoming appointments assigned to you.</p></div><?php else: ?>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Appointment</th><th>Customer</th><th>Mobile</th><th>Service</th><th>Date &amp; time</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($appointments as $appointment): ?><tr><td><?= e($appointment['appointment_number']) ?></td><td><?= e($appointment['customer_name']) ?></td><td><a href="tel:<?= e($appointment['customer_mobile']) ?>"><?= e($appointment['customer_mobile']) ?></a></td><td><?= e($appointment['service_name']) ?></td><td><?= e(format_date($appointment['appointment_date'])) ?><br><?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></td><td><?= status_badge($appointment['status']) ?></td><td><?php if ($appointment['status'] === 'CONFIRMED' && ($appointment['appointment_date'] . ' ' . $appointment['end_time']) <= date('Y-m-d H:i:s')): ?><form method="post"><?= csrf_token() ?><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>"><button class="btn btn-sm btn-rose">Mark completed</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <div class="section-heading mt-5"><h2 class="h4">Recently completed</h2></div>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Appointment</th><th>Customer</th><th>Service</th><th>Date</th><th>Status</th></tr></thead><tbody><?php foreach ($completed as $appointment): ?><tr><td><?= e($appointment['appointment_number']) ?></td><td><?= e($appointment['customer_name']) ?></td><td><?= e($appointment['service_name']) ?></td><td><?= e(format_date($appointment['appointment_date'])) ?></td><td><?= status_badge($appointment['status']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
