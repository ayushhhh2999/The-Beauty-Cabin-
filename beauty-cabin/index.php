<?php
require_once __DIR__ . '/includes/functions.php';

$popular = $pdo->query("SELECT * FROM services WHERE status = 'ACTIVE' ORDER BY id LIMIT 3")->fetchAll();
$salonName = setting($pdo, 'name', 'The Beauty Cabin');
$salonAddress = setting($pdo, 'address', 'Salon address to be configured');
$salonPhone = setting($pdo, 'phone', 'Salon phone to be configured');
$salonEmail = setting($pdo, 'email', 'Salon email to be configured');
$hours = working_hours($pdo);
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container">
        <h1>The Beauty Cabin</h1>
        <p class="lead mb-4">Where beauty meets relaxation. Book your appointment in minutes.</p>
        <a href="<?= e(url('booking.php')) ?>" class="btn btn-gold btn-lg">Book Appointment</a>
    </div>
</section>

<section class="container py-5">
    <div class="row align-items-center g-4">
        <div class="col-md-6">
            <h2 class="section-title">About Our Salon</h2>
            <p>The Beauty Cabin is a friendly neighbourhood salon offering hair, skin and nail care by trained professionals. We use quality products and put hygiene and comfort first.</p>
            <p class="mb-0">Choose a service, pick a time that suits you, and we will take care of the rest.</p>
        </div>
        <div class="col-md-6 salon-image" role="img" aria-label="A calm, sunlit beauty salon interior"></div>
    </div>
</section>

<section class="container pb-4">
    <h2 class="section-title text-center mb-4">Popular Services</h2>
    <div class="row g-4">
        <?php foreach ($popular as $s): ?>
            <div class="col-md-4">
                <div class="card service-card">
                    <div class="service-icon">
                        <span aria-hidden="true">✦</span>
                    </div>
                    <div class="card-body">
                        <h5><?= e($s['name']) ?></h5>
                        <p class="text-muted small"><?= e($s['description']) ?></p>
                        <div class="d-flex justify-content-between">
                            <span class="price">₹<?= e(number_format($s['price'])) ?></span>
                            <span class="text-muted"><?= (int)$s['duration_minutes'] ?> min</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
        <a href="<?= e(url('services.php')) ?>" class="btn btn-rose">View All Services</a>
    </div>
</section>
<section class="contact-band py-5">
    <div class="container contact-grid">
        <div><p class="eyebrow">PLAN YOUR VISIT</p><h2 class="section-title"><?= e($salonName) ?></h2><address><?= e($salonAddress) ?></address><p class="mb-1"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $salonPhone)) ?>"><?= e($salonPhone) ?></a></p><p><a href="mailto:<?= e($salonEmail) ?>"><?= e($salonEmail) ?></a></p><a class="btn btn-outline-dark" href="<?= e(url('contact.php')) ?>">Contact the salon</a></div>
        <div><h3 class="h5">Opening hours</h3><dl class="hours-list mb-0"><?php foreach ($hours as $hour): ?><div><dt><?= e($weekdays[(int)$hour['weekday']]) ?></dt><dd><?= (bool)$hour['is_closed'] ? 'Closed' : e(format_time($hour['opens_at']) . ' – ' . format_time($hour['closes_at'])) ?></dd></div><?php endforeach; ?></dl></div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
