<?php
require_once __DIR__ . '/functions.php';
$actor = require_role(['OWNER', 'MANAGER']);
$returnPath = $actor['role'] === 'OWNER' ? 'owner/workers.php' : 'manager/workers.php';
$errors = [];
$editing = null;
$activeServices = $pdo->query("SELECT id, name FROM services WHERE status = 'ACTIVE' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $workerId = filter_var($_POST['worker_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    if ($action === 'delete' && $workerId > 0) {
        $stmt = $pdo->prepare('SELECT user_id FROM workers WHERE id = ?');
        $stmt->execute([$workerId]);
        $userId = $stmt->fetchColumn();
        if ($userId) {
            $pdo->prepare('DELETE FROM users WHERE id = ? AND role = \'WORKER\'')->execute([(int)$userId]);
            flash('success', 'Worker removed. Existing appointments remain in history without an assigned worker.');
        }
        redirect($returnPath);
    }

    if ($action === 'save') {
        $worker = [
            'id' => $workerId,
            'name' => trim($_POST['name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => strtolower(trim($_POST['email'] ?? '')),
            'mobile' => trim($_POST['mobile'] ?? ''),
            'aadhaar_number' => preg_replace('/\D/', '', $_POST['aadhaar_number'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'joining_date' => trim($_POST['joining_date'] ?? ''),
            'status' => ($_POST['status'] ?? '') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE',
        ];
        $password = $_POST['password'] ?? '';
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $_POST['service_ids'] ?? []))));
        if (mb_strlen($worker['name']) < 2 || mb_strlen($worker['name']) > 100) $errors[] = 'Enter a worker name (2 to 100 characters).';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $worker['username'])) $errors[] = 'Enter a valid username.';
        if (!filter_var($worker['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!preg_match('/^[0-9+() -]{7,20}$/', $worker['mobile'])) $errors[] = 'Enter a valid mobile number.';
        if ((!$workerId || $worker['aadhaar_number'] !== '') && !preg_match('/^[0-9]{12}$/', $worker['aadhaar_number'])) $errors[] = 'Aadhaar number must contain 12 digits.';
        if ($worker['address'] === '' || mb_strlen($worker['address']) > 500) $errors[] = 'Address is required (maximum 500 characters).';
        $joiningDate = DateTimeImmutable::createFromFormat('!Y-m-d', $worker['joining_date']);
        if (!$joiningDate || $joiningDate->format('Y-m-d') !== $worker['joining_date'] || $worker['joining_date'] > date('Y-m-d')) $errors[] = 'Enter a valid joining date.';
        if (!$workerId && strlen($password) < 8) $errors[] = 'A password of at least 8 characters is required.';
        if ($workerId && $password !== '' && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (!$serviceIds) $errors[] = 'Select at least one service for this worker.';
        if ($serviceIds) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $check = $pdo->prepare("SELECT COUNT(*) FROM services WHERE status = 'ACTIVE' AND id IN ($placeholders)");
            $check->execute($serviceIds);
            if ((int)$check->fetchColumn() !== count($serviceIds)) $errors[] = 'One or more selected services are unavailable.';
        }

        if (!$errors) {
            $lockName = 'beautycabin-worker-code';
            $locked = false;
            try {
                $pdo->beginTransaction();
                if (!$workerId) {
                    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
                    $lock->execute([$lockName]);
                    $locked = (int)$lock->fetchColumn() === 1;
                    if (!$locked) throw new RuntimeException('Worker creation is busy. Try again.');
                    $nextCode = (int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(worker_code, 2) AS UNSIGNED)), 0) + 1 FROM workers")->fetchColumn();
                    $workerCode = 'W' . str_pad((string)$nextCode, 3, '0', STR_PAD_LEFT);
                    $userInsert = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'WORKER', ?)");
                    $userInsert->execute([$worker['username'], $worker['email'], password_hash($password, PASSWORD_DEFAULT), $worker['status']]);
                    $userId = (int)$pdo->lastInsertId();
                    $insert = $pdo->prepare('INSERT INTO workers (user_id, worker_code, name, email, mobile, aadhaar_number, address, joining_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $insert->execute([$userId, $workerCode, $worker['name'], $worker['email'], $worker['mobile'], $worker['aadhaar_number'], $worker['address'], $worker['joining_date'], $worker['status']]);
                    $workerId = (int)$pdo->lastInsertId();
                } else {
                    $stmt = $pdo->prepare('SELECT user_id, aadhaar_number FROM workers WHERE id = ? FOR UPDATE');
                    $stmt->execute([$workerId]);
                    $existingWorker = $stmt->fetch();
                    if (!$existingWorker) throw new DomainException('Worker no longer exists.');
                    $userId = (int)$existingWorker['user_id'];
                    $userUpdate = $pdo->prepare("UPDATE users SET username = ?, email = ?, status = ?" . ($password !== '' ? ', password = ?' : '') . ' WHERE id = ? AND role = \'WORKER\'');
                    $values = [$worker['username'], $worker['email'], $worker['status']];
                    if ($password !== '') $values[] = password_hash($password, PASSWORD_DEFAULT);
                    $values[] = (int)$userId;
                    $userUpdate->execute($values);
                    $aadhaarToSave = $worker['aadhaar_number'] !== '' ? $worker['aadhaar_number'] : $existingWorker['aadhaar_number'];
                    $update = $pdo->prepare('UPDATE workers SET name = ?, email = ?, mobile = ?, aadhaar_number = ?, address = ?, joining_date = ?, status = ? WHERE id = ?');
                    $update->execute([$worker['name'], $worker['email'], $worker['mobile'], $aadhaarToSave, $worker['address'], $worker['joining_date'], $worker['status'], $workerId]);
                    $pdo->prepare('DELETE FROM worker_services WHERE worker_id = ?')->execute([$workerId]);
                }
                $link = $pdo->prepare('INSERT INTO worker_services (worker_id, service_id) VALUES (?, ?)');
                foreach ($serviceIds as $serviceId) $link->execute([$workerId, $serviceId]);
                $pdo->commit();
                flash('success', $worker['id'] ? 'Worker details updated.' : 'Worker created with code ' . $workerCode . '.');
                redirect($returnPath);
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Worker save failed: ' . $ex->getMessage());
                $errors[] = $ex instanceof DomainException ? $ex->getMessage() : 'Worker could not be saved. Check that the username, email, and Aadhaar number are unique.';
                $editing = $worker + ['service_ids' => $serviceIds];
            } finally {
                if ($locked) {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                }
            }
        } else {
            $editing = $worker + ['service_ids' => $serviceIds];
        }
    }
}

if ($editing === null && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT w.*, u.username FROM workers w JOIN users u ON u.id = w.user_id WHERE w.id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
    if ($editing) {
        $editing['aadhaar_display'] = 'XXXX-XXXX-' . substr($editing['aadhaar_number'], -4);
        $editing['aadhaar_number'] = '';
        $stmt = $pdo->prepare('SELECT service_id FROM worker_services WHERE worker_id = ?');
        $stmt->execute([(int)$editing['id']]);
        $editing['service_ids'] = array_map('intval', array_column($stmt->fetchAll(), 'service_id'));
    }
}
$workers = $pdo->query('SELECT w.*, u.username, (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR \' · \') FROM worker_services ws JOIN services s ON s.id = ws.service_id WHERE ws.worker_id = w.id) AS service_names, (SELECT COUNT(*) FROM appointments a WHERE a.worker_id = w.id) AS appointment_count FROM workers w JOIN users u ON u.id = w.user_id ORDER BY w.worker_code')->fetchAll();
$form = $editing ?? ['id' => 0, 'name' => '', 'username' => '', 'email' => '', 'mobile' => '', 'aadhaar_number' => '', 'address' => '', 'joining_date' => date('Y-m-d'), 'status' => 'ACTIVE', 'service_ids' => []];
$maskAadhaar = static fn(string $value): string => 'XXXX-XXXX-' . substr(preg_replace('/\D/', '', $value), -4);
$pageTitle = 'Manage workers';
require __DIR__ . '/header.php';
?>
<section class="container py-5">
    <div class="dashboard-heading"><div><p class="eyebrow">TEAM MANAGEMENT</p><h1 class="section-title">Workers</h1></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <div class="management-layout">
        <form method="post" class="management-form">
            <?= csrf_token() ?><input type="hidden" name="action" value="save"><input type="hidden" name="worker_id" value="<?= (int)$form['id'] ?>">
            <h2 class="h5 mb-3"><?= $form['id'] ? 'Edit worker' : 'Add worker' ?></h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="100" value="<?= e($form['name']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= e($form['username']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($form['email']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= e($form['mobile']) ?>" required></div>
                <div class="col-md-6"><label class="form-label">Aadhaar number <?= $form['id'] ? '(leave blank to keep ' . e($form['aadhaar_display'] ?? 'existing number') . ')' : '' ?></label><input name="aadhaar_number" class="form-control" inputmode="numeric" maxlength="14" value="<?= e($form['aadhaar_number']) ?>" <?= $form['id'] ? '' : 'required' ?>></div>
                <div class="col-md-6"><label class="form-label">Joining date</label><input type="date" name="joining_date" class="form-control" max="<?= e(date('Y-m-d')) ?>" value="<?= e($form['joining_date']) ?>" required></div>
                <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" maxlength="500" required><?= e($form['address']) ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Password <?= $form['id'] ? '(leave blank to keep current)' : '' ?></label><input type="password" name="password" class="form-control" minlength="8" <?= $form['id'] ? '' : 'required' ?>></div>
                <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="ACTIVE" <?= $form['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= $form['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option></select></div>
                <div class="col-12"><fieldset><legend class="form-label">Qualified services</legend><?php foreach ($activeServices as $service): ?><label class="service-check"><input type="checkbox" name="service_ids[]" value="<?= (int)$service['id'] ?>" <?= in_array((int)$service['id'], $form['service_ids'] ?? [], true) ? 'checked' : '' ?>> <?= e($service['name']) ?></label><?php endforeach; ?></fieldset></div>
            </div>
            <button class="btn btn-rose mt-3">Save worker</button> <?php if ($form['id']): ?><a class="btn btn-outline-secondary mt-3" href="<?= e(url($returnPath)) ?>">Cancel</a><?php endif; ?>
        </form>
        <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Worker</th><th>Services</th><th>Mobile</th><th>Aadhaar</th><th>Status</th><th>Appointments</th><th></th></tr></thead><tbody>
            <?php foreach ($workers as $worker): ?><tr><td><strong><?= e($worker['worker_code']) ?> · <?= e($worker['name']) ?></strong><br><small><?= e($worker['username']) ?> · <?= e($worker['email']) ?></small></td><td><?= e($worker['service_names'] ?? '') ?></td><td><?= e($worker['mobile']) ?></td><td><?= e($maskAadhaar($worker['aadhaar_number'])) ?></td><td><?= status_badge($worker['status']) ?></td><td><?= (int)$worker['appointment_count'] ?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-dark" href="?edit=<?= (int)$worker['id'] ?>">Edit</a><form method="post" class="d-inline" data-confirm="Remove this worker account?"><?= csrf_token() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="worker_id" value="<?= (int)$worker['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
