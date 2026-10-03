<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit('Method not allowed.');
}
csrf_check();
$_SESSION = [];
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect('index.php');
