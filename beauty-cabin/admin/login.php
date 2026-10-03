<?php
require_once __DIR__ . '/../includes/functions.php';
redirect('login.php');
require_once __DIR__ . '/../config/database.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin/index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, password FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        redirect('admin/index.php');
    }
    $error = 'Invalid admin email or password.';
}

$pageTitle = 'Admin Login';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container">
    <div class="form-card">
        <h2 class="section-title mb-3">Admin Login</h2>
        <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required></div>
            <div class="mb-3"><label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required></div>
            <button class="btn btn-rose w-100">Login</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
