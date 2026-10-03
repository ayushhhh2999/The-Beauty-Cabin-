<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$customers = $pdo->query('
    SELECT u.id, u.name, u.email, u.phone, u.created_at, COUNT(a.id) AS bookings
    FROM users u
    LEFT JOIN appointments a ON a.user_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC')->fetchAll();

$pageTitle = 'Customers';
$isAdmin = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <h1 class="section-title mb-4">Customers</h1>
    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Name</th><th>Email</th><th>Mobile</th><th>Bookings</th><th>Registered</th></tr></thead>
            <tbody>
            <?php if (!$customers): ?><tr><td colspan="6" class="text-center text-muted">No customers yet.</td></tr><?php endif; ?>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><?= e($c['phone']) ?></td>
                    <td><?= (int)$c['bookings'] ?></td>
                    <td><?= e(format_date($c['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
