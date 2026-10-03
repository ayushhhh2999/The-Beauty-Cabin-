<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$statuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id > 0 && in_array($status, $statuses, true)) {
        $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', "Appointment marked as $status.");
    }
    $back = 'admin/appointments.php';
    if (in_array($_POST['filter'] ?? '', $statuses, true)) {
        $back .= '?status=' . urlencode($_POST['filter']);
    }
    redirect($back);
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$sql = '
    SELECT a.id, a.appointment_date, a.appointment_time, a.status,
           u.name AS customer, u.phone, s.name AS service
    FROM appointments a
    JOIN users u ON u.id = a.user_id
    JOIN services s ON s.id = a.service_id';
$params = [];
if ($filter !== '') {
    $sql .= ' WHERE a.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY a.appointment_date DESC, a.appointment_time DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Appointments';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h1 class="section-title mb-0">Appointments</h1>
        <div class="btn-group btn-group-sm">
            <a href="?" class="btn btn-outline-secondary <?= $filter === '' ? 'active' : '' ?>">All</a>
            <?php foreach ($statuses as $st): ?>
                <a href="?status=<?= e($st) ?>" class="btn btn-outline-secondary <?= $filter === $st ? 'active' : '' ?>"><?= e($st) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Customer</th><th>Mobile</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if (!$appointments): ?><tr><td colspan="7" class="text-center text-muted">No appointments found.</td></tr><?php endif; ?>
            <?php foreach ($appointments as $a): ?>
                <tr>
                    <td><?= e($a['customer']) ?></td>
                    <td><?= e($a['phone']) ?></td>
                    <td><?= e($a['service']) ?></td>
                    <td><?= e(format_date($a['appointment_date'])) ?></td>
                    <td><?= e(format_time($a['appointment_time'])) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php foreach (['Confirmed' => ['success', 'Confirm'], 'Cancelled' => ['danger', 'Cancel'], 'Completed' => ['secondary', 'Complete']] as $newStatus => [$color, $label]):
                            if ($a['status'] === $newStatus) continue; ?>
                            <form method="post" class="d-inline" <?= $newStatus === 'Cancelled' ? 'data-confirm="Cancel this appointment?"' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                <input type="hidden" name="status" value="<?= e($newStatus) ?>">
                                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                <button class="btn btn-sm btn-outline-<?= $color ?>"><?= e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
