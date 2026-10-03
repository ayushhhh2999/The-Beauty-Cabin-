<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        exit('Invalid or expired form submission. Refresh the page and try again.');
    }
}

function current_user(): ?array
{
    if (!isset($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
        return null;
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT role, status FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['auth']['id']]);
    $account = $stmt->fetch();
    if (!$account || $account['status'] !== 'ACTIVE' || $account['role'] !== $_SESSION['auth']['role']) {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        return null;
    }
    return $_SESSION['auth'];
}

function require_role(string|array $allowedRoles): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }

    $allowedRoles = (array)$allowedRoles;
    if (!in_array($user['role'] ?? '', $allowedRoles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<section class="container py-5"><h1>Access denied</h1><p>You are not authorized to access this page.</p></section>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}

function format_date(string $date): string
{
    return date('d M Y', strtotime($date));
}

function format_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

function status_badge(string $status): string
{
    $normalized = strtoupper($status);
    $classes = [
        'PENDING' => 'warning text-dark',
        'CONFIRMED' => 'success',
        'CANCELLED' => 'danger',
        'COMPLETED' => 'primary',
        'NO_SHOW' => 'secondary',
    ];
    return '<span class="badge bg-' . ($classes[$normalized] ?? 'secondary') . '">' . e(str_replace('_', ' ', ucfirst(strtolower($normalized)))) . '</span>';
}

function setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM salon_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

function working_hours(PDO $pdo): array
{
    return $pdo->query('SELECT weekday, opens_at, closes_at, is_closed FROM working_hours ORDER BY weekday')->fetchAll();
}

function role_home(string $role): string
{
    return match ($role) {
        'OWNER' => 'owner/dashboard.php',
        'MANAGER' => 'manager/dashboard.php',
        'WORKER' => 'worker/dashboard.php',
        default => 'customer/dashboard.php',
    };
}