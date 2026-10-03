<?php
require_once __DIR__ . '/functions.php';
$actor = require_role(['OWNER', 'MANAGER']);
$returnPath = $actor['role'] === 'OWNER' ? 'owner/services.php' : 'manager/services.php';
$errors = [];
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['service_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = $_POST['price'] ?? '';
        $duration = filter_var($_POST['duration_minutes'] ?? null, FILTER_VALIDATE_INT);
        $status = ($_POST['status'] ?? '') === 'INACTIVE' ? 'INACTIVE' : 'ACTIVE';
        if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Service name is required (maximum 100 characters).';
        if (!is_numeric($price) || (float)$price < 0 || (float)$price > 1000000) $errors[] = 'Enter a valid service price.';
        if (!$duration || $duration > 600) $errors[] = 'Duration must be between 1 and 600 minutes.';
        if (mb_strlen($description) > 5000) $errors[] = 'Description is too long.';
        if (!$errors) {
            try {
                if ($id) {
                    $stmt = $pdo->prepare('UPDATE services SET name = ?, description = ?, price = ?, duration_minutes = ?, status = ? WHERE id = ?');
                    $stmt->execute([$name, $description ?: null, $price, $duration, $status, $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO services (name, description, price, duration_minutes, status) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $description ?: null, $price, $duration, $status]);
                }
                flash('success', $id ? 'Service updated.' : 'Service added.');
                redirect($returnPath);
            } catch (PDOException $ex) {
                error_log('Service save failed: ' . $ex->getMessage());
                $errors[] = 'That service name is already in use or could not be saved.';
                $editing = compact('id', 'name', 'description', 'price', 'duration', 'status');
            }
        } else {
            $editing = compact('id', 'name', 'description', 'price', 'duration', 'status');
        }
    } elseif ($action === 'toggle' && $id > 0) {
        $pdo->prepare("UPDATE services SET status = IF(status = 'ACTIVE', 'INACTIVE', 'ACTIVE') WHERE id = ?")->execute([$id]);
        flash('success', 'Service status updated.');
        redirect($returnPath);
    } elseif ($action === 'delete' && $id > 0) {
        $check = $pdo->prepare('SELECT (SELECT COUNT(*) FROM appointments WHERE service_id = ?) + (SELECT COUNT(*) FROM worker_services WHERE service_id = ?)');
        $check->execute([$id, $id]);
        if ((int)$check->fetchColumn() > 0) {
            flash('warning', 'This service has linked records. Deactivate it instead of deleting it.');
        } else {
            $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
            flash('success', 'Service deleted.');
        }
        redirect($returnPath);
    }
}

if ($editing === null && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, description, price, duration_minutes AS duration, status FROM services WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}
$services = $pdo->query('SELECT * FROM services ORDER BY name')->fetchAll();
$form = $editing ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'duration' => '', 'status' => 'ACTIVE'];
$pageTitle = 'Manage services';
require __DIR__ . '/header.php';
?>
<section class="container py-5">
    <div class="dashboard-heading"><div><p class="eyebrow">SALON CATALOG</p><h1 class="section-title">Services</h1></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <div class="management-layout">
        <form method="post" class="management-form">
            <?= csrf_token() ?><input type="hidden" name="action" value="save"><input type="hidden" name="service_id" value="<?= (int)$form['id'] ?>">
            <h2 class="h5 mb-3"><?= $form['id'] ? 'Edit service' : 'Add a service' ?></h2>
            <label class="form-label" for="service-name">Name</label><input id="service-name" class="form-control mb-3" name="name" maxlength="100" value="<?= e($form['name']) ?>" required>
            <label class="form-label" for="service-description">Description</label><textarea id="service-description" class="form-control mb-3" name="description" rows="4" maxlength="5000"><?= e($form['description']) ?></textarea>
            <div class="row g-3"><div class="col-6"><label class="form-label" for="service-price">Price (₹)</label><input id="service-price" type="number" min="0" max="1000000" step="0.01" class="form-control" name="price" value="<?= e($form['price']) ?>" required></div><div class="col-6"><label class="form-label" for="service-duration">Minutes</label><input id="service-duration" type="number" min="1" max="600" class="form-control" name="duration_minutes" value="<?= e($form['duration']) ?>" required></div></div>
            <label class="form-label mt-3" for="service-status">Status</label><select id="service-status" name="status" class="form-select mb-3"><option value="ACTIVE" <?= $form['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= $form['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option></select>
            <button class="btn btn-rose">Save service</button> <?php if ($form['id']): ?><a class="btn btn-outline-secondary" href="<?= e(url($returnPath)) ?>">Cancel</a><?php endif; ?>
        </form>
        <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Service</th><th>Price</th><th>Duration</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ($services as $service): ?>
                <tr><td><strong><?= e($service['name']) ?></strong><br><small><?= e($service['description']) ?></small></td><td>₹<?= e(number_format((float)$service['price'], 2)) ?></td><td><?= (int)$service['duration_minutes'] ?> min</td><td><?= status_badge($service['status']) ?></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-dark" href="?edit=<?= (int)$service['id'] ?>">Edit</a>
                    <form method="post" class="d-inline"><?= csrf_token() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $service['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button></form>
                    <form method="post" class="d-inline" data-confirm="Delete this unreferenced service?"><?= csrf_token() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                </td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
