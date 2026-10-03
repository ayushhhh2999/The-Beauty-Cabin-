<?php
require_once __DIR__ . '/functions.php';
$adminUser = require_role(['OWNER', 'MANAGER']);
$legacyPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$destination = match ($legacyPage) {
    'services.php' => 'services.php',
    'customers.php' => 'customers.php',
    'appointments.php' => 'appointments.php',
    default => 'dashboard.php',
};
redirect(strtolower($adminUser['role']) . '/' . $destination);
