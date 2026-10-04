<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$errors = [];
$editing = null;

// ---------- Handle POST actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'save') {
        $name     = trim($_POST['name'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $price    = $_POST['price'] ?? '';
        $duration = $_POST['duration'] ?? '';
        $image    = trim($_POST['image'] ?? '');
        $status   = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

        if ($name === '' || strlen($name) > 100) {
            $errors[] = 'Service name is required (max 100 characters).';
        }
        if (!is_numeric($price) || $price < 0 || $price > 1000000) {
            $errors[] = 'Enter a valid price.';
        }
        if (!ctype_digit((string)$duration) || (int)$duration < 5 || (int)$duration > 600) {
            $errors[] = 'Duration must be 5 to 600 minutes.';
        }
        if ($image !== '' && !preg_match('/^(https?:\/\/[^\s]+|[A-Za-z0-9_\-]+\.(jpg|jpeg|png|webp|gif)(\?.*)?)$/i', $image)) {
            $errors[] = 'Image must be an image URL or a file name like facial.jpg (placed in assets/images).';
        }

        if (!$errors) {
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE services SET name=?, description=?, price=?, duration=?, image=?, status=? WHERE id=?');
                $stmt->execute([$name, $desc, $price, (int)$duration, $image ?: null, $status, $id]);
                flash('success', 'Service updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO services (name, description, price, duration, image, status) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$name, $desc, $price, (int)$duration, $image ?: null, $status]);
                flash('success', 'Service added.');
            }
            redirect('admin/services.php');
        }
        // Keep the form filled in when validation fails
        $editing = ['id' => $id, 'name' => $name, 'description' => $desc, 'price' => $price,
                    'duration' => $duration, 'image' => $image, 'status' => $status];

    } elseif ($action === 'toggle' && $id > 0) {
        $pdo->prepare("UPDATE services SET status = IF(status = 'active', 'inactive', 'active') WHERE id = ?")->execute([$id]);
        flash('success', 'Service status changed.');
        redirect('admin/services.php');

    } elseif ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE service_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            flash('warning', 'This service has appointments, so it cannot be deleted. Deactivate it instead.');
        } else {
            $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
            flash('success', 'Service deleted.');
        }
        redirect('admin/services.php');
    }
}

// ---------- Load service for editing ----------
if ($editing === null && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

$services = $pdo->query('SELECT * FROM services ORDER BY id')->fetchAll();
$f = $editing ?? ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'duration' => '', 'image' => '', 'status' => 'active'];

$pageTitle = 'Manage Services';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="section-title mb-4">Services</h1>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="bg-white p-4 rounded shadow-sm">
                <h5><?= $f['id'] ? 'Edit Service' : 'Add Service' ?></h5>
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    <div class="mb-2"><label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($f['name']) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control"><?= e($f['description']) ?></textarea></div>
                    <div class="row">
                        <div class="col-6 mb-2"><label class="form-label">Price (₹)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e($f['price']) ?>" required></div>
                        <div class="col-6 mb-2"><label class="form-label">Minutes</label>
                            <input type="number" min="5" name="duration" class="form-control" value="<?= e($f['duration']) ?>" required></div>
                    </div>
                    <div class="mb-2"><label class="form-label">Image file (optional)</label>
                        <input type="text" name="image" class="form-control" placeholder="facial.jpg" value="<?= e($f['image']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select></div>
                    <button class="btn btn-rose">Save</button>
                    <?php if ($f['id']): ?><a href="<?= BASE_URL ?>/admin/services.php" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Name</th><th>Price</th><th>Min</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($services as $s): ?>
                        <tr>
                            <td><?= e($s['name']) ?></td>
                            <td>₹<?= e(number_format($s['price'])) ?></td>
                            <td><?= e($s['duration']) ?></td>
                            <td><span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                            <td class="text-end text-nowrap">
                                <a href="?edit=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <form method="post" class="d-inline" data-confirm="Delete this service permanently?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
