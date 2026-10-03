<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['OWNER', 'MANAGER']);
$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$stmt = $pdo->query("SELECT w.id, w.worker_code, w.name, w.status, GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS service_names FROM workers w LEFT JOIN worker_services ws ON ws.worker_id = w.id LEFT JOIN services s ON s.id = ws.service_id GROUP BY w.id ORDER BY w.worker_code");
$workerRows = $stmt->fetchAll();
$workers = [];
foreach ($workerRows as $row) {
    $id = (int)$row['id'];
    $workers[$id]['worker'] = $row;
    $workers[$id]['appointments'] = [];
}
$appointmentsQuery = $pdo->prepare("SELECT a.*, w.id AS worker_ref, s.name AS service_name FROM appointments a JOIN workers w ON w.id = a.worker_id JOIN services s ON s.id = a.service_id WHERE a.appointment_date = ? AND a.status NOT IN ('CANCELLED', 'NO_SHOW') ORDER BY w.worker_code, a.start_time");
$appointmentsQuery->execute([$date]);
foreach ($appointmentsQuery->fetchAll() as $appointment) {
    $workers[(int)$appointment['worker_ref']]['appointments'][] = $appointment;
}
$pageTitle = 'Worker schedules';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-5"><p class="eyebrow">TEAM PLANNING</p><h1 class="section-title mb-4">Worker schedules</h1>
    <form method="get" class="filter-bar mb-4"><div><label class="form-label" for="schedule-date">Date</label><input id="schedule-date" type="date" name="date" class="form-control" value="<?= e($date) ?>"></div><button class="btn btn-dark align-self-end">View schedule</button></form>
    <div class="schedule-grid">
        <?php foreach ($workers as $group): $worker = $group['worker']; ?>
        <article class="schedule-panel"><div class="section-heading"><div><h2 class="h5 mb-1"><?= e($worker['worker_code']) ?> · <?= e($worker['name']) ?></h2><small><?= e($worker['service_names'] ?? '') ?></small></div><?= status_badge($worker['status']) ?></div>
            <?php if (empty($group['appointments'])): ?><p class="mb-0 text-muted">No bookings on <?= e(format_date($date)) ?>.</p><?php else: ?><ul class="schedule-list"><?php foreach ($group['appointments'] as $appointment): ?><li><strong><?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></strong><span><?= e($appointment['customer_name']) ?> · <?= e($appointment['service_name']) ?></span><?= status_badge($appointment['status']) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
