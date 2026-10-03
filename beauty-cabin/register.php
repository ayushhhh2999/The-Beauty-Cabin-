<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) {
    redirect(role_home(current_user()['role']));
}

$errors = [];
$old = ['name' => '', 'username' => '', 'email' => '', 'mobile' => '', 'address' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $old['email'] = strtolower($old['email']);

    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 100) $errors[] = 'Name must be 2 to 100 characters.';
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $old['username'])) $errors[] = 'Username must be 3 to 50 letters, numbers, dots, dashes, or underscores.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 254) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[0-9+() -]{7,20}$/', $old['mobile'])) $errors[] = 'Enter a valid mobile number.';
    if ($old['address'] === '' || mb_strlen($old['address']) > 500) $errors[] = 'Address is required (maximum 500 characters).';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'CUSTOMER')");
            $stmt->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO customers (user_id, name, email, mobile, address) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $old['name'], $old['email'], $old['mobile'], $old['address']]);
            $pdo->commit();
            flash('success', 'Your account is ready. Please sign in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Customer registration failed: ' . $ex->getMessage());
            $errors[] = 'That username or email is already registered.';
        }
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/includes/header.php';
?>
<div class="container"><div class="form-card wide">
    <p class="eyebrow">YOUR SALON ACCOUNT</p><h1 class="section-title mb-3">Create an account</h1>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_token() ?>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Full name</label><input id="name" name="name" class="form-control" maxlength="100" value="<?= e($old['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="username">Username</label><input id="username" name="username" class="form-control" autocomplete="username" value="<?= e($old['username']) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="email">Email</label><input id="email" type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="mobile">Mobile</label><input id="mobile" name="mobile" class="form-control" value="<?= e($old['mobile']) ?>" required></div>
            <div class="col-12"><label class="form-label" for="address">Address</label><textarea id="address" name="address" class="form-control" maxlength="500" required><?= e($old['address']) ?></textarea></div>
            <div class="col-md-6"><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required></div>
            <div class="col-md-6"><label class="form-label" for="confirm_password">Confirm password</label><input id="confirm_password" type="password" name="confirm_password" class="form-control" autocomplete="new-password" required></div>
        </div>
        <button class="btn btn-rose w-100 mt-4">Create account</button>
    </form>
    <p class="text-center mt-3 mb-0">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
