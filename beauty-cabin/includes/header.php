<?php
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'The Beauty Cabin';
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | The Beauty Cabin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bc-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= e(url($currentUser ? role_home($currentUser['role']) : 'index.php')) ?>">The Beauty Cabin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
            <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php')) ?>">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= e(url('services.php')) ?>">Services</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= e(url('contact.php')) ?>">Contact</a></li>
            <?php if ($currentUser): ?>
                <li class="nav-item"><a class="nav-link" href="<?= e(url(role_home($currentUser['role']))) ?>">Dashboard</a></li>
                <?php if ($currentUser['role'] === 'CUSTOMER'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('appointments.php')) ?>">Appointments</a></li><?php endif; ?>
                <?php if ($currentUser['role'] === 'WORKER'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('worker/appointments.php')) ?>">My schedule</a></li><?php endif; ?>
                <?php if ($currentUser['role'] === 'MANAGER'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('manager/appointments.php')) ?>">Appointments</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('manager/schedules.php')) ?>">Schedules</a></li><?php endif; ?>
                <?php if ($currentUser['role'] === 'OWNER'): ?><li class="nav-item"><a class="nav-link" href="<?= e(url('owner/appointments.php')) ?>">Appointments</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('owner/schedules.php')) ?>">Schedules</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('owner/manager.php')) ?>">Manager</a></li><?php endif; ?>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('account.php')) ?>">Account security</a></li>
                <li class="nav-item"><form method="post" action="<?= e(url('logout.php')) ?>" class="m-0"><?= csrf_token() ?><button class="nav-link border-0 bg-transparent">Sign out (<?= e($currentUser['name']) ?>)</button></form></li>
            <?php else: ?>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>">Sign in</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('register.php')) ?>">Register</a></li>
            <?php endif; ?>
            <li class="nav-item ms-lg-2"><a class="btn btn-gold btn-sm" href="<?= e(url('booking.php')) ?>">Book appointment</a></li>
            </ul>
        </div>
    </div>
</nav>
<main>
<?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>
