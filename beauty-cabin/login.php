<?php
require_once __DIR__ . '/includes/functions.php';

if ($user = current_user()) {
    redirect(role_home($user['role']));
}

$error = '';
$identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.email, u.password, u.role, u.status,
               COALESCE(c.name, m.name, w.name, u.username) AS display_name,
               COALESCE(c.id, m.id, w.id) AS profile_id
        FROM users u
        LEFT JOIN customers c ON c.user_id = u.id
        LEFT JOIN manager m ON m.user_id = u.id
        LEFT JOIN workers w ON w.user_id = u.id
        WHERE u.username = ? OR u.email = ?
        LIMIT 1');
    $stmt->execute([$identity, strtolower($identity)]);
    $account = $stmt->fetch();

    if ($account && $account['status'] === 'ACTIVE' && password_verify($password, $account['password'])) {
        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'id' => (int)$account['id'],
            'profile_id' => $account['profile_id'] === null ? null : (int)$account['profile_id'],
            'role' => $account['role'],
            'username' => $account['username'],
            'name' => $account['display_name'],
        ];
        redirect(role_home($account['role']));
    }
    $error = 'Invalid username/email or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="container"><div class="form-card">
    <p class="eyebrow">THE BEAUTY CABIN</p>
    <h1 class="section-title mb-3">Sign in</h1>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_token() ?>
        <div class="mb-3"><label class="form-label" for="identity">Username or email</label>
            <input id="identity" name="identity" class="form-control" autocomplete="username" value="<?= e($identity) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required></div>
        <button class="btn btn-rose w-100">Sign in</button>
    </form>
    <p class="text-center mt-3 mb-0">New to the salon? <a href="<?= e(url('register.php')) ?>">Create a customer account</a></p>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
