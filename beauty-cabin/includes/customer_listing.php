<?php
require_once __DIR__ . '/functions.php';
$actor = require_role(['OWNER', 'MANAGER']);
$customers = $pdo->query('SELECT c.id, c.name, c.email, c.mobile, c.address, c.created_at, u.username, COALESCE(a.appointment_count, 0) AS appointment_count FROM customers c JOIN users u ON u.id = c.user_id LEFT JOIN (SELECT customer_id, COUNT(*) AS appointment_count FROM appointments GROUP BY customer_id) a ON a.customer_id = c.id ORDER BY c.created_at DESC')->fetchAll();
$pageTitle = 'Customers';
require __DIR__ . '/header.php';
?>
<section class="container py-5"><p class="eyebrow">CUSTOMER DIRECTORY</p><h1 class="section-title mb-4">Customers</h1>
    <div class="table-responsive data-surface"><table class="table align-middle mb-0"><thead><tr><th>Customer</th><th>Mobile</th><th>Address</th><th>Appointments</th><th>Joined</th></tr></thead><tbody>
        <?php if (!$customers): ?><tr><td colspan="5" class="text-center text-muted py-4">No customers are registered yet.</td></tr><?php endif; ?>
        <?php foreach ($customers as $customer): ?><tr><td><strong><?= e($customer['name']) ?></strong><br><small><?= e($customer['username']) ?> · <?= e($customer['email']) ?></small></td><td><a href="tel:<?= e($customer['mobile']) ?>"><?= e($customer['mobile']) ?></a></td><td><?= e($customer['address']) ?></td><td><?= (int)$customer['appointment_count'] ?></td><td><?= e(format_date($customer['created_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
