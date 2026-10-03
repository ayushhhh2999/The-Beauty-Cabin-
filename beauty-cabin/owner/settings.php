<?php
require_once __DIR__ . '/../includes/functions.php';
require_role('OWNER');
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $settings = [
        'name' => trim($_POST['name'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
    ];
    if ($settings['name'] === '' || mb_strlen($settings['name']) > 150) $errors[] = 'Salon name is required (maximum 150 characters).';
    if ($settings['address'] === '' || mb_strlen($settings['address']) > 1000) $errors[] = 'Address is required (maximum 1000 characters).';
    if (!preg_match('/^[0-9+() -]{7,30}$/', $settings['phone'])) $errors[] = 'Enter a valid phone number.';
    if (!filter_var($settings['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid contact email.';
    $hours = [];
    foreach ($days as $weekday => $day) {
        $closed = isset($_POST['closed'][$weekday]);
        $opens = trim($_POST['opens_at'][$weekday] ?? '');
        $closes = trim($_POST['closes_at'][$weekday] ?? '');
        if (!$closed && (!preg_match('/^(?:[01]\d|2[0-3]):(?:00|30)$/', $opens) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $closes) || $closes <= $opens)) {
            $errors[] = $day . ' must have valid opening and closing times.';
        }
        $hours[$weekday] = ['closed' => $closed, 'opens' => $opens, 'closes' => $closes];
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $settingInsert = $pdo->prepare('INSERT INTO salon_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($settings as $key => $value) $settingInsert->execute([$key, $value]);
            $hourInsert = $pdo->prepare('INSERT INTO working_hours (weekday, opens_at, closes_at, is_closed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_closed = VALUES(is_closed)');
            foreach ($hours as $weekday => $hour) {
                $hourInsert->execute([$weekday, $hour['closed'] ? null : $hour['opens'] . ':00', $hour['closed'] ? null : $hour['closes'] . ':00', $hour['closed'] ? 1 : 0]);
            }
            $pdo->commit();
            flash('success', 'Salon settings and opening hours saved.');
            redirect('owner/settings.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Salon settings save failed: ' . $ex->getMessage());
            $errors[] = 'Settings could not be saved.';
        }
    }
}

$storedHours = working_hours($pdo);
$hourByDay = [];
foreach ($storedHours as $hour) $hourByDay[(int)$hour['weekday']] = $hour;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($days as $weekday => $_day) {
        $hourByDay[$weekday] = [
            'opens_at' => $_POST['opens_at'][$weekday] ?? null,
            'closes_at' => $_POST['closes_at'][$weekday] ?? null,
            'is_closed' => isset($_POST['closed'][$weekday]),
        ];
    }
} else {
    $settings = [
        'name' => setting($pdo, 'name', 'The Beauty Cabin'),
        'address' => setting($pdo, 'address', ''),
        'phone' => setting($pdo, 'phone', ''),
        'email' => setting($pdo, 'email', ''),
    ];
}
$pageTitle = 'Salon settings';
require __DIR__ . '/../includes/header.php';
?>
<section class="container py-5"><p class="eyebrow">SALON CONFIGURATION</p><h1 class="section-title mb-4">Salon settings</h1>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <form method="post" class="management-form manager-form">
        <?= csrf_token() ?>
        <div class="row g-3"><div class="col-md-6"><label class="form-label">Salon name</label><input name="name" class="form-control" maxlength="150" value="<?= e($settings['name']) ?>" required></div><div class="col-md-6"><label class="form-label">Contact email</label><input type="email" name="email" class="form-control" value="<?= e($settings['email']) ?>" required></div><div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($settings['phone']) ?>" required></div><div class="col-md-6"><label class="form-label">Address</label><input name="address" class="form-control" maxlength="1000" value="<?= e($settings['address']) ?>" required></div></div>
        <h2 class="h5 mt-4">Working hours</h2>
        <div class="hours-grid">
            <?php foreach ($days as $weekday => $day): $hour = $hourByDay[$weekday] ?? ['opens_at' => null, 'closes_at' => null, 'is_closed' => true]; ?>
            <div class="hours-row"><strong><?= e($day) ?></strong><label class="hours-time">Opens<input type="time" name="opens_at[<?= $weekday ?>]" value="<?= e($hour['opens_at'] ? substr($hour['opens_at'], 0, 5) : '') ?>"></label><label class="hours-time">Closes<input type="time" name="closes_at[<?= $weekday ?>]" value="<?= e($hour['closes_at'] ? substr($hour['closes_at'], 0, 5) : '') ?>"></label><label class="form-check"><input type="checkbox" name="closed[<?= $weekday ?>]" class="form-check-input" <?= (bool)$hour['is_closed'] ? 'checked' : '' ?>> Closed</label></div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-rose mt-4">Save settings</button>
    </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
