<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_role(['OWNER', 'MANAGER', 'WORKER', 'CUSTOMER']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([(int)$user['id']]);
    $passwordHash = $stmt->fetchColumn();
    if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) $errors[] = 'Current password is incorrect.';
    if (strlen($newPassword) < 12 || strlen($newPassword) > 4096) $errors[] = 'New password must be at least 12 characters.';
    if ($newPassword !== $confirmPassword) $errors[] = 'New passwords do not match.';
    if (!$errors) {
        $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
        session_regenerate_id(true);
        flash('success', 'Password updated successfully.');
        redirect('account.php');
    }
}

$pageTitle = 'Account security';
require __DIR__ . '/includes/header.php';
?>
<section class="container py-5"><div class="form-card">
    <p class="eyebrow">ACCOUNT SECURITY</p><h1 class="section-title mb-3">Change password</h1>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post">
        <?= csrf_token() ?>
        <div class="mb-3"><label class="form-label" for="current-password">Current password</label><input id="current-password" type="password" name="current_password" class="form-control" autocomplete="current-password" required></div>
        <div class="mb-3"><label class="form-label" for="new-password">New password</label><input id="new-password" type="password" name="new_password" class="form-control" minlength="12" autocomplete="new-password" required></div>
        <div class="mb-3"><label class="form-label" for="confirm-password">Confirm new password</label><input id="confirm-password" type="password" name="confirm_password" class="form-control" minlength="12" autocomplete="new-password" required></div>
        <button class="btn btn-rose w-100">Update password</button>
    </form>
</div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
