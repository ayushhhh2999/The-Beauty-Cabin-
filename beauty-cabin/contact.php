<?php
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['name']    = trim($_POST['name'] ?? '');
    $old['email']   = trim($_POST['email'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if (strlen($old['name']) < 2) {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($old['message']) < 10 || strlen($old['message']) > 1000) {
        $errors[] = 'Message must be 10 to 1000 characters.';
    }

    if (!$errors) {
        // XAMPP has no mail server by default, so the message is not sent anywhere.
        // To email it, configure mail() or PHPMailer here.
        flash('success', 'Thank you, ' . $old['name'] . '! We will get back to you soon.');
        redirect('contact.php');
    }
}

$pageTitle = 'Contact';
$salonName = setting($pdo, 'name', 'The Beauty Cabin');
$address = setting($pdo, 'address', 'Salon address to be configured');
$phone = setting($pdo, 'phone', 'Salon phone to be configured');
$email = setting($pdo, 'email', 'Salon email to be configured');
$hours = working_hours($pdo);
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <h1 class="section-title text-center mb-4">Contact Us</h1>
    <div class="row g-4">
        <div class="col-md-5">
            <div class="bg-white p-4 rounded shadow-sm h-100">
                <h5><?= e($salonName) ?></h5>
                <p class="mb-2"><strong>Address:</strong><br><?= e($address) ?></p>
                <p class="mb-2"><strong>Phone:</strong> <?= e($phone) ?></p>
                <p class="mb-2"><strong>Email:</strong> <?= e($email) ?></p>
                <p class="mb-1"><strong>Opening Hours:</strong></p>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($hours as $hour): ?>
                        <li><?= e(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][(int)$hour['weekday']]) ?>:
                            <?= (bool)$hour['is_closed'] ? 'Closed' : e(format_time($hour['opens_at']) . ' - ' . format_time($hour['closes_at'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="col-md-7">
            <div class="bg-white p-4 rounded shadow-sm">
                <h5 class="mb-3">Send us a message</h5>
                <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post" novalidate>
                    <?= csrf_token() ?>
                    <div class="mb-3"><label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required></div>
                    <div class="mb-3"><label class="form-label">Message</label>
                        <textarea name="message" rows="4" class="form-control" required><?= e($old['message']) ?></textarea></div>
                    <button class="btn btn-rose">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
