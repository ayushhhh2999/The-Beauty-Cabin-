<?php
require_once __DIR__ . '/functions.php';
$owner = require_role('OWNER');
$errors = [];
$manager = $pdo->query('SELECT m.*, u.username, u.email AS login_email FROM manager m JOIN users u ON u.id = m.user_id LIMIT 1')->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete' && $manager) {
        $pdo->prepare('DELETE FROM users WHERE id = ? AND role = \'MANAGER\'')->execute([(int)$manager['user_id']]);
        flash('success', 'Manager account deleted.');
        redirect('owner/manager.php');
    }
    if ($action === 'save') {
        $id = (int)($_POST['manager_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $mobile = trim($_POST['mobile'] ?? '');
        $aadhaar = preg_replace('/\D/', '', $_POST['aadhaar_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Enter a manager name (2 to 100 characters).';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) $errors[] = 'Enter a valid username.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (!preg_match('/^[0-9+() -]{7,20}$/', $mobile)) $errors[] = 'Enter a valid mobile number.';
        if ((!$id || $aadhaar !== '') && !preg_match('/^[0-9]{12}$/', $aadhaar)) $errors[] = 'Aadhaar number must contain 12 digits.';
        if ($address === '' || mb_strlen($address) > 500) $errors[] = 'Address is required (maximum 500 characters).';
        if (!$id && !$manager && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($id && $password !== '' && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($id && (!$manager || $id !== (int)$manager['id'])) $errors[] = 'The manager account could not be found.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($id && $manager) {
                    $userSql = 'UPDATE users SET username = ?, email = ?' . ($password !== '' ? ', password = ?' : '') . ' WHERE id = ? AND role = \'MANAGER\'';
                    $values = [$username, $email];
                    if ($password !== '') $values[] = password_hash($password, PASSWORD_DEFAULT);
                    $values[] = (int)$manager['user_id'];
                    $pdo->prepare($userSql)->execute($values);
                    $aadhaarToSave = $aadhaar !== '' ? $aadhaar : $manager['aadhaar_number'];
                    $pdo->prepare('UPDATE manager SET name = ?, email = ?, mobile = ?, aadhaar_number = ?, address = ? WHERE id = ?')->execute([$name, $email, $mobile, $aadhaarToSave, $address, $id]);
                } else {
                    if ($manager) throw new DomainException('A manager account already exists.');
                    $insert = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'MANAGER')");
                    $insert->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $userId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO manager (user_id, name, email, mobile, aadhaar_number, address) VALUES (?, ?, ?, ?, ?, ?)')->execute([$userId, $name, $email, $mobile, $aadhaar, $address]);
                }
                $pdo->commit();
                flash('success', $id ? 'Manager details updated.' : 'Manager account created.');
                redirect('owner/manager.php');
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Manager save failed: ' . $ex->getMessage());
                $errors[] = $ex instanceof DomainException ? $ex->getMessage() : 'Manager could not be saved. Check that the username and email are unique.';
            }
        }
        $form = compact('name', 'username', 'email', 'mobile', 'aadhaar', 'address');
        $form['id'] = $id;
    }
}

$form = $form ?? ($manager ? [
    'id' => (int)$manager['id'],
    'name' => $manager['name'],
    'username' => $manager['username'],
    'email' => $manager['login_email'],
    'mobile' => $manager['mobile'],
    'aadhaar' => '',
    'address' => $manager['address'],
] : ['id' => 0, 'name' => '', 'username' => '', 'email' => '', 'mobile' => '', 'aadhaar' => '', 'address' => '']);
$pageTitle = 'Manager account';
require __DIR__ . '/header.php';
?>
<section class="container py-5"><p class="eyebrow">ACCESS CONTROL</p><h1 class="section-title">Manager account</h1>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <?php if ($manager): ?><p class="text-muted">Exactly one manager account is allowed. Edit or remove the current account below.</p><?php endif; ?>
    <form method="post" class="management-form manager-form">
        <?= csrf_token() ?><input type="hidden" name="action" value="save"><input type="hidden" name="manager_id" value="<?= (int)$form['id'] ?>">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($form['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Username</label><input name="username" class="form-control" value="<?= e($form['username']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= e($form['email']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Mobile</label><input name="mobile" class="form-control" value="<?= e($form['mobile']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Aadhaar number <?= $form['id'] && $manager ? '(leave blank to keep XXXX-XXXX-' . e(substr($manager['aadhaar_number'], -4)) . ')' : '' ?></label><input name="aadhaar_number" class="form-control" inputmode="numeric" value="<?= e($form['aadhaar']) ?>" maxlength="14" <?= $form['id'] ? '' : 'required' ?>></div>
            <div class="col-md-6"><label class="form-label">Password <?= $form['id'] ? '(leave blank to keep current)' : '' ?></label><input type="password" name="password" class="form-control" minlength="8" <?= $form['id'] ? '' : 'required' ?>></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" maxlength="500" required><?= e($form['address']) ?></textarea></div>
        </div>
        <button class="btn btn-rose mt-3"><?= $form['id'] ? 'Save manager' : 'Create manager' ?></button>
    </form>
    <?php if ($manager): ?><form method="post" class="mt-3" data-confirm="Delete the manager account?"><?= csrf_token() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger">Delete manager</button></form>
        <div class="data-surface mt-4 p-3"><strong>Identity check:</strong> <?= e($manager['name']) ?> · <?= e($manager['username']) ?> · Aadhaar <?= e('XXXX-XXXX-' . substr($manager['aadhaar_number'], -4)) ?></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/footer.php'; ?>
