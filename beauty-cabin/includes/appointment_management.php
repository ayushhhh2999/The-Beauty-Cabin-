<?php
require_once __DIR__ . '/functions.php';
$actor = require_role(['OWNER', 'MANAGER']);
$returnPath = $actor['role'] === 'OWNER' ? 'owner/appointments.php' : 'manager/appointments.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$appointmentId) {
        flash('danger', 'Invalid appointment.');
        redirect($returnPath);
    }

    if ($action === 'status') {
        $newStatus = strtoupper(trim($_POST['new_status'] ?? ''));
        $transitions = [
            'PENDING' => ['CONFIRMED', 'CANCELLED', 'NO_SHOW'],
            'CONFIRMED' => ['CANCELLED', 'COMPLETED', 'NO_SHOW'],
        ];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = ? FOR UPDATE');
            $stmt->execute([$appointmentId]);
            $oldStatus = $stmt->fetchColumn();
            if (!$oldStatus || !in_array($newStatus, $transitions[$oldStatus] ?? [], true)) {
                throw new DomainException('That appointment status change is not allowed.');
            }
            $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$newStatus, $appointmentId]);
            $pdo->prepare('INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)')->execute([$appointmentId, $oldStatus, $newStatus, (int)$actor['id']]);
            $pdo->commit();
            flash('success', 'Appointment status updated.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('warning', $ex instanceof DomainException ? $ex->getMessage() : 'Appointment status could not be updated.');
        }
        redirect($returnPath);
    }

    if ($action === 'assign') {
        $workerId = filter_var($_POST['worker_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$workerId) {
            flash('warning', 'Choose a qualified worker.');
            redirect($returnPath);
        }
        $lockName = null;
        $locked = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT service_id, appointment_date, start_time, end_time, status FROM appointments WHERE id = ? FOR UPDATE');
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch();
            if (!$appointment || in_array($appointment['status'], ['CANCELLED', 'NO_SHOW'], true)) throw new DomainException('This appointment cannot be assigned.');
            $lockName = 'beautycabin-' . str_replace('-', '', $appointment['appointment_date']);
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
            $lock->execute([$lockName]);
            $locked = (int)$lock->fetchColumn() === 1;
            if (!$locked) throw new RuntimeException('Assignment is busy. Try again.');

            $qualified = $pdo->prepare("SELECT COUNT(*) FROM workers w JOIN users u ON u.id = w.user_id JOIN worker_services ws ON ws.worker_id = w.id WHERE w.id = ? AND w.status = 'ACTIVE' AND u.status = 'ACTIVE' AND ws.service_id = ?");
            $qualified->execute([$workerId, (int)$appointment['service_id']]);
            if ((int)$qualified->fetchColumn() !== 1) throw new DomainException('This worker is not active or qualified for the selected service.');

            $conflict = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE worker_id = ? AND appointment_date = ? AND id <> ? AND status NOT IN ('CANCELLED', 'NO_SHOW') AND start_time < ? AND end_time > ?");
            $conflict->execute([$workerId, $appointment['appointment_date'], $appointmentId, $appointment['end_time'], $appointment['start_time']]);
            if ((int)$conflict->fetchColumn() > 0) throw new DomainException('That worker has an overlapping appointment.');
            $pdo->prepare('UPDATE appointments SET worker_id = ? WHERE id = ?')->execute([$workerId, $appointmentId]);
            $pdo->commit();
            flash('success', 'Worker assigned to the appointment.');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('warning', $ex instanceof DomainException ? $ex->getMessage() : 'Worker could not be assigned.');
        } finally {
            if ($locked && $lockName !== null) {
                $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([$lockName]);
            }
        }
        redirect($returnPath);
    }

    if ($action === 'delete' && $actor['role'] === 'OWNER') {
        $pdo->prepare('DELETE FROM appointments WHERE id = ?')->execute([$appointmentId]);
        flash('success', 'Appointment deleted.');
        redirect($returnPath);
    }
    flash('danger', 'That action is not allowed.');
    redirect($returnPath);
}

$filters = [
    'date' => trim($_GET['date'] ?? ''),
    'worker' => (int)($_GET['worker'] ?? 0),
    'service' => (int)($_GET['service'] ?? 0),
    'status' => strtoupper(trim($_GET['status'] ?? '')),
];
$where = [];
$params = [];
if ($filters['date'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date'])) { $where[] = 'a.appointment_date = ?'; $params[] = $filters['date']; }
if ($filters['worker'] > 0) { $where[] = 'a.worker_id = ?'; $params[] = $filters['worker']; }
if ($filters['service'] > 0) { $where[] = 'a.service_id = ?'; $params[] = $filters['service']; }
if (in_array($filters['status'], ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW'], true)) { $where[] = 'a.status = ?'; $params[] = $filters['status']; }
$sql = 'SELECT a.*, c.name AS customer, s.name AS service, s.price, w.name AS worker FROM appointments a JOIN customers c ON c.id = a.customer_id JOIN services s ON s.id = a.service_id LEFT JOIN workers w ON w.id = a.worker_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();
$services = $pdo->query('SELECT id, name FROM services ORDER BY name')->fetchAll();
$workers = $pdo->query("SELECT w.id, w.name, ws.service_id FROM workers w JOIN users u ON u.id = w.user_id AND u.status = 'ACTIVE' JOIN worker_services ws ON ws.worker_id = w.id WHERE w.status = 'ACTIVE' ORDER BY w.name")->fetchAll();
$workersByService = [];
foreach ($workers as $worker) $workersByService[(int)$worker['service_id']][] = $worker;
$pageTitle = 'Appointments';
require __DIR__ . '/header.php';
?>
<section class="container py-5"><p class="eyebrow">SCHEDULE &amp; BOOKINGS</p><h1 class="section-title mb-4">Appointments</h1>
    <form method="get" class="filter-bar mb-4"><div><label class="form-label" for="filter-date">Date</label><input id="filter-date" type="date" name="date" class="form-control" value="<?= e($filters['date']) ?>"></div><div><label class="form-label" for="filter-worker">Worker</label><select id="filter-worker" name="worker" class="form-select"><option value="0">All workers</option><?php foreach ($workers as $worker): ?><option value="<?= (int)$worker['id'] ?>" <?= $filters['worker'] === (int)$worker['id'] ? 'selected' : '' ?>><?= e($worker['name']) ?></option><?php endforeach; ?></select></div><div><label class="form-label" for="filter-service">Service</label><select id="filter-service" name="service" class="form-select"><option value="0">All services</option><?php foreach ($services as $service): ?><option value="<?= (int)$service['id'] ?>" <?= $filters['service'] === (int)$service['id'] ? 'selected' : '' ?>><?= e($service['name']) ?></option><?php endforeach; ?></select></div><div><label class="form-label" for="filter-status">Status</label><select id="filter-status" name="status" class="form-select"><option value="">All statuses</option><?php foreach (['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW'] as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select></div><button class="btn btn-dark align-self-end">Filter</button><a class="btn btn-outline-secondary align-self-end" href="<?= e(url($returnPath)) ?>">Clear</a></form>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Date &amp; time</th><th>Worker</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php if (!$appointments): ?><tr><td colspan="7" class="text-center text-muted py-4">No appointments match these filters.</td></tr><?php endif; ?>
    <?php foreach ($appointments as $appointment): ?><tr><td><?= e($appointment['appointment_number']) ?></td><td><?= e($appointment['customer']) ?><br><small><a href="tel:<?= e($appointment['customer_mobile']) ?>"><?= e($appointment['customer_mobile']) ?></a></small></td><td><?= e($appointment['service']) ?><br><small>₹<?= e(number_format((float)$appointment['price'], 2)) ?></small></td><td><?= e(format_date($appointment['appointment_date'])) ?><br><?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></td><td><?= e($appointment['worker'] ?? 'Unassigned') ?></td><td><?= status_badge($appointment['status']) ?></td><td class="action-cell">
        <form method="post" class="mb-2"><?= csrf_token() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>"><select class="form-select form-select-sm mb-1" name="worker_id" required><option value="">Assign qualified worker</option><?php foreach ($workersByService[(int)$appointment['service_id']] ?? [] as $qualified): ?><option value="<?= (int)$qualified['id'] ?>" <?= (int)$appointment['worker_id'] === (int)$qualified['id'] ? 'selected' : '' ?>><?= e($qualified['name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-dark">Assign / reassign</button></form>
        <?php if (in_array($appointment['status'], ['PENDING', 'CONFIRMED'], true)): ?><form method="post" class="d-flex gap-1"><?= csrf_token() ?><input type="hidden" name="action" value="status"><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>"><select name="new_status" class="form-select form-select-sm"><option value="">Change status</option><?php foreach (($appointment['status'] === 'PENDING' ? ['CONFIRMED', 'CANCELLED', 'NO_SHOW'] : ['CANCELLED', 'COMPLETED', 'NO_SHOW']) as $status): ?><option value="<?= e($status) ?>"><?= e(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-rose">Save</button></form><?php endif; ?>
        <?php if ($actor['role'] === 'OWNER'): ?><form method="post" class="mt-1" data-confirm="Delete this appointment and its status history?"><?= csrf_token() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?>
    </td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
