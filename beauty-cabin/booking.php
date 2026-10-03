<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_role('CUSTOMER');
$stmt = $pdo->prepare('SELECT id, name, email, mobile, address FROM customers WHERE user_id = ?');
$stmt->execute([(int)$user['id']]);
$customer = $stmt->fetch();
if (!$customer) {
    http_response_code(404);
    exit('Customer profile was not found.');
}
$services = $pdo->query("SELECT id, name, price, duration_minutes FROM services WHERE status = 'ACTIVE' ORDER BY name")->fetchAll();
$selectedService = (int)($_GET['service'] ?? 0);
$pageTitle = 'Book an appointment';
require __DIR__ . '/includes/header.php';
?>
<section class="container py-5">
    <div class="booking-heading mb-4"><p class="eyebrow">A LITTLE TIME FOR YOU</p><h1 class="section-title">Book your visit</h1><p class="text-muted mb-0">Choose a service and time. We’ll match you with a qualified available professional.</p></div>
    <?php if (!$services): ?>
        <div class="alert alert-info">No services are available for booking right now.</div>
    <?php else: ?>
    <div class="booking-layout">
        <form id="booking-form" class="booking-panel" data-create-url="<?= e(url('api/appointments/create.php')) ?>" data-earliest-url="<?= e(url('api/availability/earliest.php')) ?>" data-check-url="<?= e(url('api/availability/check.php')) ?>">
            <?= csrf_token() ?>
            <h2 class="h4 mb-4">Appointment details</h2>
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="service_id">Service</label>
                    <select id="service_id" name="service_id" class="form-select" required>
                        <option value="">Select a service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int)$service['id'] ?>" <?= $selectedService === (int)$service['id'] ? 'selected' : '' ?>><?= e($service['name']) ?> · ₹<?= e(number_format((float)$service['price'], 2)) ?> · <?= (int)$service['duration_minutes'] ?> min</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="appointment_date">Date</label>
                    <input id="appointment_date" type="date" name="appointment_date" class="form-control" min="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="appointment_time">Start time</label>
                    <input id="appointment_time" type="time" name="appointment_time" class="form-control" step="1800" required></div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" id="find-earliest" class="btn btn-outline-dark">Find earliest available</button>
                <button type="button" id="check-availability" class="btn btn-outline-secondary">Check availability</button>
            </div>
            <div id="booking-message" class="mt-3" aria-live="polite"></div>
            <div id="earliest-result" class="earliest-result mt-3" hidden></div>
            <hr class="my-4">
            <h2 class="h5 mb-3">Your details</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="customer_name">Name</label><input id="customer_name" class="form-control" value="<?= e($customer['name']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label" for="customer_mobile">Mobile</label><input id="customer_mobile" class="form-control" value="<?= e($customer['mobile']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label" for="customer_email">Email</label><input id="customer_email" class="form-control" value="<?= e($customer['email']) ?>" readonly></div>
                <div class="col-md-6"><label class="form-label" for="customer_address">Address</label><input id="customer_address" class="form-control" value="<?= e($customer['address']) ?>" readonly></div>
            </div>
            <button type="submit" class="btn btn-rose w-100 mt-4">Confirm appointment</button>
        </form>
        <aside class="booking-aside"><p class="eyebrow">THE BEAUTY CABIN</p><h2 class="h3">Your time, your way.</h2><p>Every appointment is matched to an active team member qualified for your service. Your booking details and assigned professional will appear in your dashboard.</p><a href="<?= e(url('contact.php')) ?>">View salon hours and contact details</a></aside>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
