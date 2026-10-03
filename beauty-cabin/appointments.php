<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_role('CUSTOMER');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $appointmentId = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$appointmentId) {
        flash('danger', 'Invalid appointment.');
        redirect('appointments.php');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT a.id, a.status, a.appointment_date, a.start_time, c.id AS customer_id FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE a.id = ? AND c.user_id = ? FOR UPDATE");
        $stmt->execute([$appointmentId, (int)$user['id']]);
        $appointment = $stmt->fetch();
        if (!$appointment || !in_array($appointment['status'], ['PENDING', 'CONFIRMED'], true) || ($appointment['appointment_date'] . ' ' . $appointment['start_time']) <= date('Y-m-d H:i:s')) {
            throw new DomainException('This appointment is no longer eligible for cancellation.');
        }
        $pdo->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ?")->execute([$appointmentId]);
        $pdo->prepare('INSERT INTO appointment_status_history (appointment_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)')->execute([$appointmentId, $appointment['status'], 'CANCELLED', (int)$user['id']]);
        $pdo->commit();
        flash('success', 'Appointment successfully cancelled.');
    } catch (DomainException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('warning', $ex->getMessage());
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Appointment cancellation failed: ' . $ex->getMessage());
        flash('danger', 'The appointment could not be cancelled. Please try again.');
    }
    redirect('appointments.php');
}

$stmt = $pdo->prepare('
    SELECT a.*, s.name AS service_name, s.price, w.name AS worker_name
    FROM appointments a
    JOIN customers c ON c.id = a.customer_id
    JOIN services s ON s.id = a.service_id
    LEFT JOIN workers w ON w.id = a.worker_id
    WHERE c.user_id = ?
    ORDER BY a.appointment_date DESC, a.start_time DESC');
$stmt->execute([(int)$user['id']]);
$appointments = $stmt->fetchAll();
$pageTitle = 'My Appointments';
require __DIR__ . '/includes/header.php';
?>
<section class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><p class="eyebrow">YOUR VISITS</p><h1 class="section-title mb-0">Appointment history</h1></div><a href="<?= e(url('booking.php')) ?>" class="btn btn-gold">New booking</a></div>
    <?php if (!$appointments): ?><div class="empty-state"><h2 class="h4">No appointments yet</h2><p>Your bookings will show here.</p><a href="<?= e(url('booking.php')) ?>" class="btn btn-rose">Book your first visit</a></div>
    <?php else: ?>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0">
        <thead><tr><th>Appointment</th><th>Service</th><th>Date &amp; time</th><th>Professional</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($appointments as $appointment): ?>
            <tr>
                <td><?= e($appointment['appointment_number']) ?></td>
                <td><?= e($appointment['service_name']) ?></td>
                <td><?= e(format_date($appointment['appointment_date'])) ?><br><small><?= e(format_time($appointment['start_time'])) ?> - <?= e(format_time($appointment['end_time'])) ?></small></td>
                <td><?= e($appointment['worker_name'] ?? 'To be assigned') ?></td>
                <td>₹<?= e(number_format((float)$appointment['price'], 2)) ?></td>
                <td><?= status_badge($appointment['status']) ?></td>
                <td><?php if (in_array($appointment['status'], ['PENDING', 'CONFIRMED'], true) && ($appointment['appointment_date'] . ' ' . $appointment['start_time']) > date('Y-m-d H:i:s')): ?>
                    <form method="post" data-confirm="Cancel this appointment?"><?= csrf_token() ?><input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>"><button class="btn btn-sm btn-outline-danger">Cancel</button></form>
                    <?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
