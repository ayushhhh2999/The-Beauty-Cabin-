<?php
require_once __DIR__ . '/includes/functions.php';

$services = $pdo->query("SELECT * FROM services WHERE status = 'ACTIVE' ORDER BY name")->fetchAll();

$pageTitle = 'Services';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <h1 class="section-title text-center mb-4">Our Services</h1>
    <?php if (!$services): ?>
        <p class="text-center text-muted">No services available right now.</p>
    <?php endif; ?>
    <div class="row g-4">
        <?php foreach ($services as $s): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card service-card">
                    <?php $serviceImage = !empty($s['image']) ? e($s['image']) : 'https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?auto=format&fit=crop&w=900&q=80'; ?>
                    <img src="<?= $serviceImage ?>" class="service-thumb" alt="<?= e($s['name']) ?>" loading="lazy">
                    <div class="card-body d-flex flex-column">
                        <h5><?= e($s['name']) ?></h5>
                        <p class="text-muted small"><?= e($s['description']) ?></p>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="price">₹<?= e(number_format($s['price'])) ?></span>
                            <span class="text-muted"><?= (int)$s['duration_minutes'] ?> minutes</span>
                        </div>
                        <a href="<?= e(url('booking.php?service=' . (int)$s['id'])) ?>" class="btn btn-rose mt-auto">Book Now</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
